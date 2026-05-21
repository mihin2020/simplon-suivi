<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service pour l'API WhatsApp Cloud de Meta (Facebook)
 * Documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaWhatsAppService
{
    protected string $apiVersion = 'v22.0';
    protected ?string $accessToken = null;
    protected ?string $phoneNumberId = null;
    protected ?string $businessAccountId = null;
    protected bool $configured = false;

    public function __construct()
    {
        $this->accessToken = AppSetting::get('whatsapp_meta_token')
            ?: config('services.whatsapp_meta.token');
        $this->phoneNumberId = AppSetting::get('whatsapp_meta_phone_id')
            ?: config('services.whatsapp_meta.phone_number_id');
        $this->businessAccountId = AppSetting::get('whatsapp_meta_business_id')
            ?: config('services.whatsapp_meta.business_account_id');

        $this->configured = !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    public static function getConfig(): array
    {
        $token = AppSetting::get('whatsapp_meta_token', '');
        $phoneId = AppSetting::get('whatsapp_meta_phone_id', '');
        $businessId = AppSetting::get('whatsapp_meta_business_id', '');

        return [
            'token' => $token ? substr($token, 0, 10) . '...' : '',
            'phone_number_id' => $phoneId,
            'business_account_id' => $businessId,
            'configured' => !empty($token) && !empty($phoneId),
            'provider' => 'meta',
        ];
    }

    /**
     * Envoie un message WhatsApp texte simple
     */
    public function send(string $to, string $message): array
    {
        if (!$this->configured) {
            return [
                'success' => false,
                'error' => 'WhatsApp Cloud API non configuré. Vérifiez WHATSAPP_META_TOKEN et WHATSAPP_META_PHONE_ID'
            ];
        }

        $to = $this->normalizePhone($to);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        try {
            $response = Http::withToken($this->accessToken)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $message
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('WhatsApp Meta envoyé', [
                    'to' => $to,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ]);

                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                    'status' => 'sent'
                ];
            }

            $error = $response->json('error.message', 'Erreur inconnue');
            Log::error('Erreur WhatsApp Meta', [
                'to' => $to,
                'error' => $error,
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'error' => $error
            ];

        } catch (\Exception $e) {
            Log::error('Exception WhatsApp Meta', [
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
     * Envoie un message avec template (pour les messages initiés par l'entreprise)
     */
    public function sendTemplate(string $to, string $templateName, array $language = [], array $components = []): array
    {
        if (!$this->configured) {
            return [
                'success' => false,
                'error' => 'WhatsApp Cloud API non configuré'
            ];
        }

        $to = $this->normalizePhone($to);

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => $language ?: ['code' => 'fr']
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken($this->accessToken)->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'message_id' => $data['messages'][0]['id'] ?? null,
                ];
            }

            return [
                'success' => false,
                'error' => $response->json('error.message', 'Erreur inconnue')
            ];

        } catch (\Exception $e) {
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

            // Délai pour éviter le rate limiting (80 messages/second max pour l'API Cloud)
            usleep(125000); // 125ms
        }

        return $results;
    }

    /**
     * Vérifie si l'API est configurée
     */
    public function isConfigured(): bool
    {
        return $this->configured;
    }

    /**
     * Récupère les templates disponibles
     */
    public function getTemplates(): array
    {
        if (!$this->configured || empty($this->businessAccountId)) {
            return [];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->businessAccountId}/message_templates";

        try {
            $response = Http::withToken($this->accessToken)->get($url);

            if ($response->successful()) {
                return $response->json('data', []);
            }

            return [];
        } catch (\Exception $e) {
            Log::error('Erreur récupération templates', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Vérifie le statut d'un numéro de téléphone
     */
    public function getPhoneNumberStatus(): array
    {
        if (!$this->configured) {
            return ['error' => 'Non configuré'];
        }

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";

        try {
            $response = Http::withToken($this->accessToken)
                ->get($url, ['fields' => 'id,number,display_phone_number,quality_rating,verified_name']);

            if ($response->successful()) {
                return $response->json();
            }

            return ['error' => $response->json('error.message', 'Erreur inconnue')];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Normalise un numéro de téléphone pour l'API WhatsApp
     */
    protected function normalizePhone(string $phone): string
    {
        // Enlever espaces, tirets, parenthèses
        $phone = preg_replace('/[\s\-\(\)\.]/', '', $phone);

        // WhatsApp Cloud API attend le format international sans le +
        // Ex: 22501234567 pour la Côte d'Ivoire
        $phone = ltrim($phone, '+');

        return $phone;
    }
}
