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
use Smalot\PdfParser\Parser;

class TestingpdfController extends Controller
{
    public function showForm()
    {
        return view('upload-pdf');
    }

    // Menangani proses upload dan konversi
    public function handleUpload(Request $request)
    {
        // Validasi file yang diunggah
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:4096', // Maksimal 4MB
        ]);

        // Ambil file yang diunggah
        $file = $request->file('pdf');

        // Membaca isi file PDF tanpa menyimpannya
        $parser = new Parser();
        $pdf = $parser->parseContent(file_get_contents($file->getRealPath()));
        $text = $pdf->getText();

        // Proses teks untuk diubah menjadi JSON berdasarkan nomor soal
        // Asumsikan setiap soal diawali dengan nomor, misalnya "1.", "2.", dst.

        $questions = $this->extractQuestions($text);

        // Mengembalikan JSON sebagai respons
        return response()->json($questions);
    }

    // Fungsi untuk mengekstrak soal berdasarkan nomor
    private function extractQuestions($text)
    {
        // Gunakan regex untuk menemukan pola nomor soal
        // Misalnya, soal diawali dengan angka diikuti titik dan spasi: "1. ", "2. ", dll.

        // Split teks berdasarkan pola nomor soal
        $pattern = '/\n\s*\d+\.\s+/'; // Menemukan awal soal

        // Tambahkan pembatas agar explode bekerja
        $text = "\n" . $text;

        // Pecah teks menjadi array soal
        $parts = preg_split($pattern, $text);

        // Hapus elemen pertama jika kosong atau bukan soal
        if (isset($parts[0]) && trim($parts[0]) === '') {
            array_shift($parts);
        }

        // Kumpulkan soal dalam array asosiatif
        $questions = [];
        foreach ($parts as $index => $part) {
            $number = $index + 1;
            $questionText = trim($part);
            if ($questionText !== '') {
                $questions[] = [
                    'number' => $number,
                    'question' => $questionText,
                ];
            }
        }

        return $questions;
    }



}
