<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppWWebService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.wwebjs.url', 'http://127.0.0.1:3001'), '/');
    }

    public function getStatus(): array
    {
        try {
            // Connexion locale rapide : timeout court pour un polling réactif
            $response = Http::connectTimeout(1)->timeout(3)->get("{$this->baseUrl}/status");
            return $response->json() ?? ['connected' => false, 'qr' => null];
        } catch (\Exception) {
            return ['connected' => false, 'qr' => null, 'unavailable' => true];
        }
    }

    public function sendBulk(array $recipients, string $message, ?array $attachment = null): array
    {
        $payload = ['recipients' => $recipients, 'message' => $message];
        if ($attachment) {
            $payload['attachment'] = $attachment;
        }

        try {
            // connectTimeout court : si le microservice est arrêté, on échoue vite
            // (l'UI optimiste a déjà affiché le message, on réconcilie immédiatement).
            $response = Http::connectTimeout(2)->timeout(300)->post("{$this->baseUrl}/send-bulk", $payload);
            return $response->json() ?? ['success' => 0, 'failed' => count($recipients), 'errors' => []];
        } catch (\Exception $e) {
            return [
                'success' => 0,
                'failed'  => count($recipients),
                'errors'  => [['error' => $e->getMessage()]],
            ];
        }
    }

    public function getIncoming(int $since = 0): array
    {
        try {
            // Timeout élevé : les pièces jointes en base64 peuvent rendre la réponse volumineuse
            $response = Http::timeout(60)->get("{$this->baseUrl}/messages/incoming", ['since' => $since]);
            return $response->json() ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    public function getRevokedMessages(string $since = ''): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/messages/revoked", ['since' => $since]);
            return $response->json() ?? [];
        } catch (\Exception) {
            return [];
        }
    }

    public function revokeMessage(string $waId): bool
    {
        try {
            $response = Http::timeout(10)->post("{$this->baseUrl}/revoke-message", ['wa_id' => $waId]);
            return ($response->json()['success'] ?? false) === true;
        } catch (\Exception) {
            return false;
        }
    }

    public function debug(): array
    {
        try {
            $response = Http::timeout(5)->get("{$this->baseUrl}/debug");
            return $response->json() ?? ['error' => 'Réponse vide'];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function logout(): void
    {
        try {
            Http::connectTimeout(1)->timeout(5)->post("{$this->baseUrl}/logout");
        } catch (\Exception) {
        }
    }
}
