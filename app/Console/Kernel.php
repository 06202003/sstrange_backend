<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\File; 
use Illuminate\Support\Facades\Log;
use Carbon\Carbon; 

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(function () {
            // Panggil metode untuk menghapus data yang kadaluarsa
            $controller = new \App\Http\Controllers\JarInputController();
            $controller->deleteExpiredData();

            // Tambahkan logika untuk menghapus file ZIP lama
            $zipFiles = File::glob(storage_path('app/public/download_result_*.zip')); // Cari semua file ZIP dengan pola
            foreach ($zipFiles as $file) {
                // Hapus file jika sudah lebih dari 1 menit
                if (Carbon::now()->diffInMinutes(Carbon::createFromTimestamp(File::lastModified($file))) > 1) {
                    File::delete($file);
                    Log::info("File ZIP dihapus: {$file}");
                }
            }
        })->everyMinute();
    }
    

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
    
        require base_path('routes/console.php');
    }
    
}
