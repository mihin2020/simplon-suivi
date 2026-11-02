<?php

namespace App\Http\Controllers;

use App\Actions\WhatsApp\QueueWhatsAppBroadcast;
use App\Actions\WhatsApp\ReplyToLearner;
use App\Enums\LearnerStatus;
use App\Exceptions\WhatsAppReplyRefused;
use App\Http\Requests\WhatsApp\ReplyToLearnerRequest;
use App\Http\Requests\WhatsApp\SendWhatsAppRequest;
use App\Models\Formation;
use App\Models\Learner;
use App\Models\WhatsAppMessage;
use App\Services\WhatsApp\BroadcastReport;
use App\Services\WhatsApp\ReplySynchronizer;
use App\Services\WhatsAppWWebService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WhatsAppController extends Controller
{
    public function index()
    {
        $formations = Formation::with([
            'project',
            'learners' => function ($query) {
                $query->whereNotNull('phone')
                    ->where('formation_learner.status', LearnerStatus::InProgress);
            },
        ])
            ->whereHas('learners', function ($query) {
                $query->whereNotNull('phone')
                    ->where('formation_learner.status', LearnerStatus::InProgress);
            })
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'project' => ['id' => $f->project->id, 'name' => $f->project->name],
                'learners' => $f->learners->map(fn ($l) => [
                    'id' => $l->id,
                    'first_name' => $l->first_name,
                    'last_name' => $l->last_name,
                    'phone' => $l->phone,
                ]),
            ]);

        // The service status takes about a second to answer: the page is shown at
        // once and asks for it right after (the status endpoint already exists).
        return Inertia::render('Communication/WhatsApp', [
            'formations' => $formations,
            'initialWaStatus' => null,
        ]);
    }

    public function status(WhatsAppWWebService $service): JsonResponse
    {
        $status = $service->getStatus();

        return response()->json([
            'connected' => $status['connected'] ?? false,
            'authenticating' => $status['authenticating'] ?? false,
            'qr' => $status['qr'] ?? null,
            'available' => ! isset($status['unavailable']),
        ]);
    }

    public function send(SendWhatsAppRequest $request, WhatsAppWWebService $service, QueueWhatsAppBroadcast $queue): RedirectResponse
    {
        // Checked now rather than in the background: nothing can go out anyway.
        if (! ($service->getStatus()['connected'] ?? false)) {
            return back()->with('error', "WhatsApp n'est pas connecté : aucun message envoyé. Reconnectez-vous puis réessayez.");
        }

        $count = $queue->execute($request->validated(), $request->file('attachments') ?? [], $request->user());

        return back()->with('success', "Envoi de {$count} message(s) WhatsApp en cours. Le suivi s'affiche dans l'historique.");
    }

    public function messages(): JsonResponse
    {
        $messages = WhatsAppMessage::with('learner:id,first_name,last_name')
            ->where('provider', 'wwebjs')
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get(['id', 'phone', 'recipient_name', 'message', 'direction', 'status', 'learner_id', 'formation_name', 'project_name', 'created_at']);

        return response()->json($messages);
    }

    public function syncReplies(ReplySynchronizer $synchronizer): JsonResponse
    {
        return response()->json($synchronizer->synchronize());
    }

    public function broadcasts(BroadcastReport $report): JsonResponse
    {
        return response()->json($report->summaries());
    }

    public function broadcastRecipients(string $broadcastId, BroadcastReport $report): JsonResponse
    {
        return response()->json($report->recipients($broadcastId));
    }

    public function thread(string $learnerId): JsonResponse
    {
        // withTrashed() pour afficher les messages supprimés avec indicateur visuel
        $messages = WhatsAppMessage::withTrashed()
            ->where('learner_id', $learnerId)
            ->where('provider', 'wwebjs')
            ->orderBy('created_at')
            ->get(['id', 'message', 'direction', 'status', 'error', 'broadcast_id', 'created_at',
                'deleted_at', 'attachment_path', 'attachment_mimetype', 'attachment_filename']);

        return response()->json($messages);
    }

    public function media(string $filename): StreamedResponse
    {
        $path = 'whatsapp_media/'.basename($filename);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    public function reply(ReplyToLearnerRequest $request, ReplyToLearner $action): JsonResponse
    {
        // Sending a large attachment through the WhatsApp service can take a while.
        set_time_limit(60);

        try {
            $result = $action->execute(
                Learner::findOrFail($request->validated('learner_id')),
                $request->validated('message'),
                $request->file('attachment'),
            );
        } catch (WhatsAppReplyRefused $refusal) {
            return response()->json(['success' => false, 'error' => $refusal->getMessage()], 422);
        }

        return response()->json($result);
    }

    public function markThreadRead(string $learnerId): JsonResponse
    {
        WhatsAppMessage::where('learner_id', $learnerId)
            ->where('direction', 'received')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function deleteBroadcast(string $broadcastId): JsonResponse
    {
        $count = WhatsAppMessage::where('broadcast_id', $broadcastId)->count();

        WhatsAppMessage::where('broadcast_id', $broadcastId)->delete();

        return response()->json(['deleted' => $count]);
    }

    public function deleteMessage(int $id, WhatsAppWWebService $service): JsonResponse
    {
        $msg = WhatsAppMessage::findOrFail($id);

        // Tenter la révocation côté WhatsApp si on a le wa_id
        if ($msg->external_id) {
            $service->revokeMessage($msg->external_id);
        }

        $msg->delete();

        return response()->json(['deleted' => true]);
    }

    public function diag(WhatsAppWWebService $service): JsonResponse
    {
        // 1. État du microservice
        $debug = $service->debug();

        // 2. Derniers messages envoyés
        $sentMessages = WhatsAppMessage::where('direction', 'sent')
            ->where('provider', 'wwebjs')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'phone', 'learner_id', 'status', 'created_at']);

        // 3. IDs apprenants à qui on a écrit
        $messagedLearnerIds = WhatsAppMessage::where('direction', 'sent')
            ->whereNotNull('learner_id')
            ->pluck('learner_id')
            ->unique()
            ->toArray();

        // 4. Phones des apprenants concernés
        $learnerPhones = Learner::whereIn('id', $messagedLearnerIds)
            ->pluck('phone', 'id');

        // 5. Derniers messages reçus en base
        $receivedInDb = WhatsAppMessage::where('direction', 'received')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'phone', 'learner_id', 'external_id', 'created_at']);

        // 6. Dernier message reçu synchronisé
        $lastReceivedAt = WhatsAppMessage::where('direction', 'received')
            ->orderByDesc('created_at')
            ->value('created_at');
        $lastTimestamp = $lastReceivedAt ? Carbon::parse($lastReceivedAt)->timestamp : 0;

        return response()->json([
            'node_buffer' => $debug,
            'last_sent' => $sentMessages,
            'messaged_learners' => $messagedLearnerIds,
            'learner_phones' => $learnerPhones,
            'received_in_db' => $receivedInDb,
            'last_synced_ts' => $lastTimestamp,
        ]);
    }

    public function logout(WhatsAppWWebService $service): JsonResponse
    {
        $service->logout();

        return response()->json(['success' => true]);
    }
}
