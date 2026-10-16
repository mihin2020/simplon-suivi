<?php

namespace App\Services\Contracts;

use App\Enums\ContractEmailStatus;
use App\Models\Contract;
use App\Services\EmailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Grouped actions on a selection of contracts: one ZIP of their PDFs, or
 * emailing them as attachments.
 */
class ContractBulkService
{
    private const TEMP_DIRECTORY = 'contracts-bulk-temp';

    public function __construct(
        private readonly ContractPdfService $pdf,
        private readonly ContractSubjectDirectory $subjects,
        private readonly EmailService $emails,
    ) {}

    /**
     * Build a ZIP of the PDFs and return its absolute path (to be deleted after sending).
     *
     * @param  Collection<int, Contract>  $contracts
     */
    public function zip(Collection $contracts): string
    {
        $path = $this->tempPath('zip');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossible de préparer l\'archive.');
        }

        foreach ($contracts as $contract) {
            $zip->addFromString($this->filename($contract), $this->pdf->output($contract));
        }

        $zip->close();

        return $path;
    }

    /**
     * Send the contracts by email. In "each" mode every contract goes to its
     * own recipient; contracts whose recipient has no email are skipped and
     * reported. In "single" mode, one email carries every contract.
     *
     * @param  Collection<int, Contract>  $contracts
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @return array{sent: int, skipped: list<string>}
     */
    public function email(Collection $contracts, string $mode, array $to, array $cc, string $subject, string $message, string $userId): array
    {
        $bodyHtml = nl2br(e($message));
        $ccList = array_map(fn (string $email) => ['email' => $email], $cc);
        $files = [];

        try {
            if ($mode === 'single') {
                $attachments = $contracts->map(function (Contract $contract) use (&$files) {
                    return $this->attachment($contract, $files);
                })->all();

                $this->emails->sendEmail(array_map(fn (string $email) => ['email' => $email], $to), $subject, $bodyHtml, $attachments, null, $userId, $ccList ?: null);
                $contracts->each(fn (Contract $contract) => $this->markEmailed($contract, implode(', ', $to)));

                return ['sent' => 1, 'skipped' => []];
            }

            $sent = 0;
            $skipped = [];

            foreach ($contracts as $contract) {
                $address = $this->subjects->contractEmail($contract);

                if ($address === null) {
                    $skipped[] = $contract->reference;

                    continue;
                }

                $this->emails->sendEmail(
                    [['email' => $address, 'name' => $this->subjects->name($contract->subject)]],
                    $subject,
                    $bodyHtml,
                    [$this->attachment($contract, $files)],
                    null,
                    $userId,
                    $ccList ?: null,
                );
                $this->markEmailed($contract, $address);
                $sent++;
            }

            return ['sent' => $sent, 'skipped' => $skipped];
        } finally {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }

    private function markEmailed(Contract $contract, string $to): void
    {
        $contract->forceFill([
            'emailed_at' => now(),
            'emailed_to' => mb_substr($to, 0, 1000),
            'email_count' => $contract->email_count + 1,
            'email_status' => ContractEmailStatus::Sent,
        ])->saveQuietly();
    }

    /**
     * @param  list<string>  $files  temporary files to clean up afterwards
     * @return array{path: string, name: string}
     */
    private function attachment(Contract $contract, array &$files): array
    {
        $path = $this->tempPath('pdf');
        file_put_contents($path, $this->pdf->output($contract));
        $files[] = $path;

        return ['path' => $path, 'name' => $this->filename($contract)];
    }

    private function filename(Contract $contract): string
    {
        $name = $this->subjects->name($contract->subject);

        return str($contract->reference.($name !== '' ? '_'.$name : ''))->slug('_').'.pdf';
    }

    private function tempPath(string $extension): string
    {
        Storage::disk('local')->makeDirectory(self::TEMP_DIRECTORY);

        return Storage::disk('local')->path(self::TEMP_DIRECTORY.'/'.Str::uuid().'.'.$extension);
    }
}
