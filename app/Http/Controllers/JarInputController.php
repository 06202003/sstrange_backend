<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\MessagesController;
use App\Models\JarInput;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log; 
use Carbon\Carbon;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\File;

class JarInputController extends Controller
{
    public function getAll(Request $request){

        $data = JarInput::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseController::getResponse($data, 200, 'Success');
    }

    public function getData($guid){
        /// GET DATA
        $data = JarInput::where('guid', '=', $guid)
            ->first();

        if (!isset($data)) {
            return ResponseController::getResponse(null, 400, "Data not found");
        }

        return ResponseController::getResponse($data, 200, 'Success');
    }

    public function getAllDataTable(){

        $this->deleteExpiredData();

        $data = JarInput::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        $dataTable = DataTables::of($data)
            ->addIndexColumn()
            ->make(true);

        return $dataTable;
    }

    public function download($filename){
        $fileRelativePath = "uploads/" . $filename . "/" . $filename . ".zip";
        $filePath = storage_path("app/public/" . $fileRelativePath);

        if (Storage::disk('public')->exists($fileRelativePath)) {
            return response()->download($filePath);
        } else {
            return response()->json(['message' => 'File not found.'], 404);
        }
    }
    

    public function insertData(Request $request){
        $validator = Validator::make($request->all(), [
            'zip_file_path' => 'nullable|file|mimes:zip|max:2048',
            'dir_file_path' => 'nullable|string|max:255',
            'submission_type' => 'nullable|string|max:255',
            'submission_language' => 'required|string|max:255',
            'explanation_language' => 'required|string|max:255',
            'sim_threshold' => 'nullable|integer',
            'dissim_threshold' => 'nullable|integer',
            'maximum_reported_submission_pairs' => 'nullable|integer',
            'minimum_matching_length' => 'nullable|integer',
            'template_directory_path' => 'nullable|string|max:255',
            'common_content' => 'nullable|string|max:255',
            'ai_generated_sample' => 'nullable|string|max:255',
            'similarity_measurement' => 'nullable|string|max:255',
            'resource_path' => 'nullable|string|max:255',
            'number_of_clusters' => 'nullable|integer',
            'number_of_stages' => 'nullable|integer',
            'user_id' => 'required|string',
            'expired' => 'nullable|date',
        ]);



        if ($validator->fails()) {
            return response()->json([
                'error' => 'Ada Kesalahan atau Form Input yang Belum Terisi',
                'details' => $validator->errors()
            ], 422);
        }
        

        try {           
            if ($request->hasFile('zip_file_path') && empty($request->input('dir_file_path'))) {
                $file = $request->file('zip_file_path');
                $originalFileName = $file->getClientOriginalName();
                $fileNameOnly = pathinfo($originalFileName, PATHINFO_FILENAME); 
                $zipFileDirectory = storage_path('app/public/uploads/' . $fileNameOnly); 
                $zipFilePath = $zipFileDirectory . DIRECTORY_SEPARATOR . $originalFileName;
            
                // Buat direktori tujuan jika belum ada
                if (!File::exists($zipFileDirectory)) {
                    File::makeDirectory($zipFileDirectory, 0755, true);
                }
            
                // Pindahkan file ZIP ke direktori tujuan
                $file->move($zipFileDirectory, $originalFileName);
                Log::info('Zip file saved to: ' . $zipFilePath);
            
                // Ekstrak file ZIP tanpa membuat subfolder tambahan
                $zip = new \ZipArchive;
                if ($zip->open($zipFilePath) === TRUE) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $zipEntry = $zip->getNameIndex($i);
            
                        // Dapatkan hanya nama file tanpa path
                        $entryName = basename($zipEntry);
            
                        // Tentukan path untuk file yang akan diekstrak
                        $extractPath = $zipFileDirectory . DIRECTORY_SEPARATOR . $entryName;
            
                        // Jika file bukan direktori, ekstrak file tersebut
                        if (substr($zipEntry, -1) !== '/') {
                            copy("zip://" . $zipFilePath . "#" . $zipEntry, $extractPath);
                            Log::info('Extracted: ' . $extractPath);
                        }
                    }
                    $zip->close();
                    Log::info('Zip file extracted to: ' . $zipFileDirectory);
       
                    // Hapus file ZIP setelah ekstraksi selesai
                    File::delete($zipFilePath);
                    Log::info('Zip file deleted: ' . $zipFilePath);
                } else {
                    Log::error('Failed to extract zip file.');
                    return response()->json(['error' => 'Failed to extract zip file'], 500);
                }
            
                // Proses AI jika ada
                if (!empty($request->input('ai_generated_sample'))) {
                    // Input AI directory path
                    $inputAiPath = $request->input('ai_generated_sample');
            
                    // Create the corresponding directory in public storage
                    $lastAIDirectory = basename($inputAiPath);
                    $publicAIPath = storage_path('app/public/uploads/' . $lastAIDirectory);
            
                    // Copy all contents from input directory to public storage directory
                    if (File::copyDirectory($inputAiPath, $publicAIPath)) {
                        Log::info('Directory copied to: ' . $publicAIPath);
                    } else {
                        Log::error('Failed to copy directory to: ' . $publicAIPath);
                        return response()->json(['error' => 'Failed to copy directory'], 500);
                    }
                } else {
                    $publicAIPath = "none";
                }
            
                // Set expiration date
                $expirationDate = now()->addDays(14);
            
                // Create database record
                $data = JarInput::create([
                    'zip_file_path' => $zipFileDirectory, // Gunakan direktori hasil ekstraksi
                    'filename' => $fileNameOnly,
                    'submission_type' => $request->input('submission_type'),
                    'submission_language' => $request->input('submission_language'),
                    'explanation_language' => $request->input('explanation_language'),
                    'sim_threshold' => $request->input('sim_threshold'),
                    'dissim_threshold' => $request->input('dissim_threshold'),
                    'maximum_reported_submission_pairs' => $request->input('maximum_reported_submission_pairs'),
                    'minimum_matching_length' => $request->input('minimum_matching_length'),
                    'template_directory_path' => $request->input('template_directory_path'),
                    'common_content' => $request->input('common_content'),
                    'ai_generated_sample' => $publicAIPath,
                    'similarity_measurement' => $request->input('similarity_measurement'),
                    'resource_path' => storage_path('app/public/results'),
                    'number_of_clusters' => $request->input('number_of_clusters'),
                    'number_of_stages' => $request->input('number_of_stages'),
                    'user_id' => $request->input('user_id'),
                    'expired' => $expirationDate,
                ]);
            
                // Set working directory and run JAR
                $templateDirectory = storage_path('app/public/sstrange');
                $javaPath = 'C:\Program Files\Java\jdk-22\bin\java.exe';
                $sstrangeJarPath = $templateDirectory . '/sstrange.jar';
                $command = sprintf(
                    '"%s" -jar "%s" "%s" "%s" "%s" "%s" %d %d %d "%s" %s "%s" "%s" %d "%s" %d %d',
                    $javaPath,
                    $sstrangeJarPath,
                    $data->zip_file_path, // Path ke folder hasil ekstraksi
                    $data->submission_type,
                    $data->submission_language,
                    $data->explanation_language,
                    $data->sim_threshold,
                    $data->minimum_matching_length,
                    $data->maximum_reported_submission_pairs,
                    $data->template_directory_path ?? "none",
                    $data->common_content ?? "true",
                    $data->similarity_measurement,
                    $data->resource_path,
                    $data->dissim_threshold,
                    $data->ai_generated_sample,
                    $data->number_of_clusters,
                    $data->number_of_stages
                );

                Log::info('Executing command: ' . $command);

                chdir($templateDirectory);
                $output = shell_exec($command);

                if ($output === null) {
                    Log::error('JAR file execution failed.');
                    return response()->json(['error' => 'JAR file execution failed'], 500);
                }

                Log::info('JAR execution output: ' . $output);
            
                // Tentukan output path yang bisa diakses frontend
                $outputPath = 'storage/uploads/[out] ' . $fileNameOnly . '/index.html';
            
                // Update database record dengan result path
                $data->update(['result' => $outputPath]);
            
                return response()->json(['data' => $data, 'message' => 'Success'], 200);
            }          
            elseif (!empty($request->input('dir_file_path')) && !$request->hasFile('zip_file_path')) {
                // Input directory path
                $inputDirPath = $request->input('dir_file_path');
        
                // Create the corresponding directory in public storage
                $lastDirectory = basename($inputDirPath);
                $publicDirPath = storage_path('app/public/uploads/' . $lastDirectory);
        
                // Copy all contents from input directory to public storage directory
                if (File::copyDirectory($inputDirPath, $publicDirPath)) {
                    Log::info('Directory copied to: ' . $publicDirPath);
                } else {
                    Log::error('Failed to copy directory to: ' . $publicDirPath);
                    return response()->json(['error' => 'Failed to copy directory'], 500);
                }

                if (!empty($request->input('ai_generated_sample'))) {
                    // Input AI directory path
                    $inputAiPath = $request->input('ai_generated_sample');
            
                    // Create the corresponding directory in public storage
                    $lastAIDirectory = basename($inputAiPath);
                    $publicAIPath = storage_path('app/public/uploads/' . $lastAIDirectory);
            
                    // Copy all contents from input directory to public storage directory
                    if (File::copyDirectory($inputAiPath, $publicAIPath)) {
                        Log::info('Directory copied to: ' . $publicAIPath);
                    } else {
                        Log::error('Failed to copy directory to: ' . $publicAIPath);
                        return response()->json(['error' => 'Failed to copy directory'], 500);
                    }
                } else {
                    $publicAIPath = "none";
                }

                $expirationDate = now()->addDays(14);
        
                // Create database record
                $data = JarInput::create([
                    'dir_file_path' => $publicDirPath,
                    'filename' => $lastDirectory,
                    'submission_type' => $request->input('submission_type'),
                    'submission_language' => $request->input('submission_language'),
                    'explanation_language' => $request->input('explanation_language'),
                    'sim_threshold' => $request->input('sim_threshold'),
                    'dissim_threshold' => $request->input('dissim_threshold'),
                    'maximum_reported_submission_pairs' => $request->input('maximum_reported_submission_pairs'),
                    'minimum_matching_length' => $request->input('minimum_matching_length'),
                    'template_directory_path' => $request->input('template_directory_path'),
                    'common_content' => $request->input('common_content'),
                    'ai_generated_sample' => $publicAIPath,
                    'similarity_measurement' => $request->input('similarity_measurement'),
                    'resource_path' => storage_path('app/public/results'),
                    'number_of_clusters' => $request->input('number_of_clusters'),
                    'number_of_stages' => $request->input('number_of_stages'),
                    'user_id' => $request->input('user_id'),
                    'expired' => $expirationDate,
                ]);
        
                // Set the working directory to the location of the JAR file and templates
                $templateDirectory = storage_path('app/public/sstrange');
        
                // Build the command
                $javaPath = 'C:\Program Files\Java\jdk-22\bin\java.exe';
                $sstrangeJarPath = $templateDirectory . '/sstrange.jar';
                $command = sprintf(
                    '"%s" -jar "%s" "%s" "%s" "%s" "%s" %d %d %d "%s" %s "%s" "%s" %d "%s" %d %d',
                    $javaPath,
                    $sstrangeJarPath,
                    $publicDirPath, // Pass the copied directory path
                    $data->submission_type,
                    $data->submission_language,
                    $data->explanation_language,
                    $data->sim_threshold,
                    $data->minimum_matching_length,
                    $data->maximum_reported_submission_pairs,
                    $data->template_directory_path ?? "none",
                    $data->common_content,
                    $data->similarity_measurement,
                    $data->resource_path,
                    $data->dissim_threshold,
                    $data->ai_generated_sample,
                    $data->number_of_clusters,
                    $data->number_of_stages
                );
        
                Log::info('Executing command: ' . $command);
        
                // Run the command using shell_exec with the working directory set
                chdir($templateDirectory);
                $output = shell_exec($command);
        
                if ($output === null) {
                    Log::error('JAR file execution failed.');
                    return response()->json(['error' => 'JAR file execution failed'], 500);
                }
        
                Log::info('JAR execution output: ' . $output);
        
                // Determine the output path accessible from the frontend
                $outputPathResult = 'storage/uploads/[out] ' . $lastDirectory . '/index.html';
        
                // Update the database record with the result path
                $data->update(['result' => $outputPathResult]);
        
                return response()->json(['data' => $data, 'message' => 'Success'], 200);
            }
        }
        catch (\Exception $e) {
            Log::error('Exception occurred: ' . $e->getMessage());
            return response()->json(['error' => 'Exception: ' . $e->getMessage()], 500);
        } 
    }

    public function deleteData($guid)
    {
        // GET DATA
        $data = JarInput::where('guid', '=', $guid)->first();
    
        if (!isset($data)) {
            return response()->json(['error' => 'Data not found'], 400);
        }
    
        // Delete the zip files if they exist
        if ($data->zip_file_path && File::exists($data->zip_file_path)) {
            File::deleteDirectory($data->zip_file_path);
        }
    
        if ($data->ai_generated_sample && File::exists($data->ai_generated_sample)) {
            File::delete($data->ai_generated_sample);
        }
    
        if ($data->template_directory_path && File::exists($data->template_directory_path)) {
            File::delete($data->template_directory_path);
        }
    
        // Additional logic to delete the result directory if it exists
        if ($data->result && File::exists($data->result)) {
            $resultDirectory = dirname($data->result);
            if (File::exists($resultDirectory)) {
                File::deleteDirectory($resultDirectory);
            }
        }
    
    
        // Finally, delete the record from the database
        $data->delete();
    
        return response()->json(['message' => 'Success'], 200);
    }
    
    public function updateData(Request $request)
    {
        $data = JarInput::where('guid', '=', $request['guid'])->first();

        if (!isset($data)) {
            return ResponseController::getResponse(null, 400, "Data not found");
        }

        /// UPDATE DATA
        $data->generated_code = $request['generated_code'];
        $data->save();

        return ResponseController::getResponse($data, 200, 'Success');

    }


    public function deleteExpiredData()
    {
        $expiredData = JarInput::where('expired', '<=', now())->get();

        foreach ($expiredData as $data) {
            // Hapus direktori hasil jika sudah expired
            $fileNameOnly = pathinfo($data->zip_file_path, PATHINFO_FILENAME);
            Storage::disk('public')->deleteDirectory("uploads/[out]/$fileNameOnly");
            Log::info("Expired file deleted: $fileNameOnly");

            if ($data->result && File::exists($data->result)) {
                $resultDirectory = dirname($data->result);
                if (File::exists($resultDirectory)) {
                    File::deleteDirectory($resultDirectory);
                }
            }    

            // Hapus data dari database
            $data->delete();
            Log::info("Expired data deleted from database");
        }

        return response()->json(['message' => 'Expired data deleted successfully.'], 200);
    }


}
