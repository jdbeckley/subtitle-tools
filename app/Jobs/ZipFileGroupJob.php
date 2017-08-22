<?php

namespace App\Jobs;

use App\Facades\FileName;
use App\Facades\TempFile;
use App\Models\FileGroup;
use App\Models\StoredFile;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use ZipArchive;

class ZipFileGroupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    protected $fileGroup;

    public function __construct(FileGroup $fileGroup)
    {
        $this->fileGroup = $fileGroup;

        $fileGroup->update(['archive_requested_at' => Carbon::now()]);
    }

    public function handle()
    {
        $newZip = new ZipArchive();

        $zipTempFilePath = TempFile::makeFilePath('zip');

        if($newZip->open($zipTempFilePath, ZipArchive::CREATE) !== true) {
            return $this->abort('messages.zip-job.create_failed');
        }

        $fileJobs = $this->fileGroup->fileJobs->filter(function($fileJob) {
            return $fileJob->output_stored_file_id !== null;
        })->all();

        $alreadyAddedNames = [];

        foreach($fileJobs as $fileJob) {
            $nameInZip = $fileJob->originalNameWithNewExtension;

            while(in_array(strtolower($nameInZip), $alreadyAddedNames)) {
                $nameInZip = FileName::appendName($nameInZip, '-st');
            }

            $alreadyAddedNames[] = strtolower($nameInZip);

            $newZip->addFile($fileJob->outputStoredFile->filePath, $nameInZip);
        }

        if($newZip->numFiles === 0) {
            return $this->abort('messages.zip-job.no_files_added');
        }

        $newZip->setArchiveComment('Edited at https://subtitletools.com');

        if($newZip->close() !== true) {
            return $this->abort('messages.zip-job.close_failed');
        }

        $storedFile = StoredFile::getOrCreate($zipTempFilePath);

        $this->fileGroup->update([
            'archive_stored_file_id' => $storedFile->id,
            'archive_finished_at' => Carbon::now(),
        ]);

        unlink($zipTempFilePath);

        return $this->fileGroup;
    }

    public function abort($errorMessage)
    {
        $this->fileGroup->update([
            'archive_error' => $errorMessage,
            'archive_finished_at' => Carbon::now(),
        ]);

        return $this->fileGroup;
    }

    public function failed(Exception $exception)
    {
        $this->abort('messages.zip-job.unknown_error');
    }
}