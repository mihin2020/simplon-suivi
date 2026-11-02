<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Identity documents, CVs, contracts and receipts used to be written on the public disk, where anyone who knew the
 * address could open them. They are now served through protected routes: this moves the existing files to the private
 * disk under the same relative path, so nothing in the database changes. Safe to run again.
 */
class MoveSensitiveFilesToPrivateDisk extends Command
{
    /** Folders of the public disk that hold private documents (learner photos and logos stay public). */
    private const DIRECTORIES = [
        'learners/cnib',
        'learners/cv',
        'trainers/cvs',
        'contracts',
        'expenses',
        'insertion-records',
    ];

    protected $signature = 'files:make-private {--dry-run : List what would be moved without moving anything}';

    protected $description = 'Move identity documents, CVs, contracts and receipts from the public disk to the private disk';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        $failed = 0;

        foreach (self::DIRECTORIES as $directory) {
            foreach ($public->allFiles($directory) as $path) {
                if ($this->option('dry-run')) {
                    $this->line("would move {$path}");
                    $moved++;

                    continue;
                }

                // The copy is checked before the public one is removed, so an interrupted run never loses a file.
                $stream = $public->readStream($path);

                $isCopied = $stream !== null && $private->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                if (! $isCopied || $private->size($path) !== $public->size($path)) {
                    $this->error("failed: {$path}");
                    $failed++;

                    continue;
                }

                $public->delete($path);
                $moved++;
            }
        }

        $this->info(($this->option('dry-run') ? 'To move: ' : 'Moved: ').$moved.($failed ? " ({$failed} failed)" : ''));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
