<?php

namespace App\Http\Requests\Contract;

use App\Enums\ContractTarget;
use App\Models\Contract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contract::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // MIME types are validated on the real file content, not the extension.
            'model_file' => [
                'required',
                'file',
                'max:5120',
                'mimetypes:'.implode(',', [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip', // some clients send .docx as a plain zip
                    'application/pdf',
                    'text/plain',
                    'text/html',
                ]),
                'mimes:docx,pdf,txt,html,htm',
            ],
            'target' => ['required', Rule::enum(ContractTarget::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'model_file.mimetypes' => 'Le fichier doit être un document Word (.docx), un .pdf, un .txt ou un .html.',
            'model_file.mimes' => 'Le fichier doit être un document Word (.docx), un .pdf, un .txt ou un .html.',
            'model_file.max' => 'Le fichier ne doit pas dépasser 5 Mo.',
        ];
    }
}
