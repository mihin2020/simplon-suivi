<?php

namespace App\Actions\Mail;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class StageEmailAttachments
{
    /**
     * Stores uploaded files in a temporary folder until the e-mail is sent (the sending job deletes them).
     *
     * @param  array<int, UploadedFile>  $files
     * @return list<array{path: string, name: string}>
     */
    public function execute(array $files): array
    {
        $staged = [];

        foreach ($files as $file) {
            $stored = $file->store('temp-email-attachments');

            $staged[] = [
                'path' => Storage::disk('local')->path($stored),
                'name' => $file->getClientOriginalName(),
            ];
        }

        return $staged;
    }
}
