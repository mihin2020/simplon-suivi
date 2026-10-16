<?php

namespace App\Http\Controllers;

use App\Enums\ContractTarget;
use App\Http\Requests\Contract\DraftContractTemplateRequest;
use App\Services\Contracts\ContractDraftGenerator;
use App\Services\Contracts\ContractSubjectDirectory;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractDraftController extends Controller
{
    public function __invoke(
        DraftContractTemplateRequest $request,
        ContractDraftGenerator $generator,
        ContractVariableResolver $resolver,
        ContractSubjectDirectory $subjects,
    ): JsonResponse|StreamedResponse {
        // The AI round-trip for a full contract can exceed PHP's default 30 s.
        set_time_limit(150);

        $data = $request->validated();
        $target = ContractTarget::from($data['target']);

        // What the application already knows about the recipient, so the AI
        // writes the real identity instead of leaving blanks.
        $subject = $subjects->find($target, $data['subject_id'] ?? null);
        $facts = $subject ? $resolver->facts($target, $subject) : [];

        if ($data['action'] === 'questions') {
            try {
                return response()->json(['questions' => $generator->questions($target, $data['prompt'], $facts)]);
            } catch (RuntimeException $e) {
                return response()->json(['message' => "L'IA n'a pas pu répondre : ".$e->getMessage()], 422);
            }
        }

        // Drafting takes tens of seconds: the answer is streamed as one JSON
        // object per line - progress events, then the result (or an error).
        return response()->stream(function () use ($data, $target, $facts, $generator) {
            $send = function (array $event) {
                echo json_encode($event, JSON_UNESCAPED_UNICODE)."\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            $lastPercent = -1;
            $onProgress = function (int $percent) use ($send, &$lastPercent) {
                if ($percent > $lastPercent) {
                    $lastPercent = $percent;
                    $send(['type' => 'progress', 'percent' => $percent]);
                }
            };

            try {
                $result = match ($data['action']) {
                    'draft' => $generator->draft($target, $data['prompt'], $facts, $data['answers'] ?? [], $onProgress),
                    'reformat' => $generator->reformat($target, $data['current_html'], $facts, $onProgress),
                    default => $generator->revise($target, $data['current_html'], $data['instruction'], $facts, $onProgress),
                };

                $send(['type' => 'done', ...$result]);
            } catch (RuntimeException $e) {
                $send(['type' => 'error', 'message' => "L'IA n'a pas pu répondre : ".$e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
