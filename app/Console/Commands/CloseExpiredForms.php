<?php

namespace App\Console\Commands;

use App\Actions\Forms\CloseForm;
use App\Enums\FormStatus;
use App\Models\Form;
use Illuminate\Console\Command;

class CloseExpiredForms extends Command
{
    protected $signature = 'forms:close-expired';

    protected $description = 'Close published forms whose closes_at deadline has passed';

    public function handle(CloseForm $closeForm): int
    {
        $forms = Form::query()
            ->where('status', FormStatus::Published)
            ->whereNotNull('settings->closes_at')
            ->get()
            ->filter(fn (Form $form) => $form->hasExpired());

        if ($forms->isEmpty()) {
            $this->info('Aucun formulaire expiré à fermer.');

            return self::SUCCESS;
        }

        foreach ($forms as $form) {
            $closeForm->execute($form);
            $this->line("Fermé : {$form->title} ({$form->id})");
        }

        $this->info($forms->count().' formulaire(s) fermé(s).');

        return self::SUCCESS;
    }
}
