<?php

namespace App\Http\Requests\Contract;

use Illuminate\Validation\Rule;

class SendContractsEmailRequest extends BulkContractRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // each: every contract goes to its own recipient; single: one email with all the contracts.
            'mode' => ['required', Rule::in(['each', 'single'])],
            'to' => ['required_if:mode,single', 'nullable', 'array', 'max:50'],
            'to.*' => ['email:rfc', 'max:255'],
            'cc' => ['nullable', 'array', 'max:20'],
            'cc.*' => ['email:rfc', 'max:255'],
            // Contract id => address, as completed in the dialog for nominative sending.
            'recipients' => ['nullable', 'array'],
            'recipients.*' => ['nullable', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'to.required_if' => 'Indiquez au moins un destinataire.',
            'to.*.email' => 'Une adresse email n\'est pas valide.',
            'cc.*.email' => 'Une adresse en copie n\'est pas valide.',
            'recipients.*.email' => 'Une adresse de destinataire n\'est pas valide.',
            'subject.required' => 'Indiquez l\'objet de l\'email.',
            'message.required' => 'Écrivez un message.',
        ];
    }
}
