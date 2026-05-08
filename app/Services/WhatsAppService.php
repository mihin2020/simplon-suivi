<?php

namespace App\Services;

use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected ?Client $client = null;
    protected string $from;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.token');
        $this->from = config('services.twilio.whatsapp_from', '');

        if ($sid && $token) {
            $this->client = new Client($sid, $token);
        }
    }

    /**
     * Envoie un message WhatsApp à un numéro
     */
    public function send(string $to, string $message): array
    {
        if (!$this->client) {
            return [
                'success' => false,
                'error' => 'Twilio non configuré. Vérifiez TWILIO_SID et TWILIO_AUTH_TOKEN dans .env'
            ];
        }

        // Normaliser le numéro (ajouter + si manquant)
        $to = $this->normalizePhone($to);

        try {
            $result = $this->client->messages->create(
                "whatsapp:$to",
                [
                    'from' => $this->from,
                    'body' => $message
                ]
            );

            Log::info('WhatsApp envoyé', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status
            ]);

            return [
                'success' => true,
                'sid' => $result->sid,
                'status' => $result->status
            ];
        } catch (\Exception $e) {
            Log::error('Erreur WhatsApp Twilio', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Envoie des messages en masse
     */
    public function sendBulk(array $recipients, string $message): array
    {
        $results = [
            'total' => count($recipients),
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($recipients as $recipient) {
            $phone = is_array($recipient) ? ($recipient['phone'] ?? '') : $recipient;
            $name = is_array($recipient) ? ($recipient['name'] ?? '') : '';

            if (empty($phone)) {
                $results['failed']++;
                $results['errors'][] = ['phone' => $phone, 'error' => 'Numéro vide'];
                continue;
            }

            // Personnaliser le message si {nom} présent
            $personalizedMessage = str_replace('{nom}', $name, $message);

            $result = $this->send($phone, $personalizedMessage);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'phone' => $phone,
                    'name' => $name,
                    'error' => $result['error']
                ];
            }

            // Petit délai pour éviter le rate limiting
            usleep(100000); // 100ms
        }

        return $results;
    }

    /**
     * Vérifie si Twilio est configuré
     */
    public function isConfigured(): bool
    {
        return $this->client !== null && !empty($this->from);
    }

    /**
     * Normalise un numéro de téléphone
     */
    protected function normalizePhone(string $phone): string
    {
        // Enlever espaces, tirets, parenthèses
        $phone = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        // Ajouter + si manquant
        if (!str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
