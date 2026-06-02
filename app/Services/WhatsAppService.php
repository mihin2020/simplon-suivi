<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\WhatsAppMessage;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;

/**
 * Service WhatsApp unifié supportant Twilio et Meta Cloud API
 */
class WhatsAppService
{
    protected ?Client $twilioClient = null;
    protected string $twilioFrom = '';
    protected ?MetaWhatsAppService $metaService = null;
    protected string $provider = 'twilio'; // 'twilio' ou 'meta'
    public ?string $currentRecipientName = null;
    public ?string $currentFormationName = null;
    public ?string $currentProjectName   = null;

    public function __construct()
    {
        // Détection automatique du provider
        $this->provider = AppSetting::get('whatsapp_provider', 'twilio');

        // Initialisation Twilio
        $sid   = AppSetting::get('twilio_sid')   ?: config('services.twilio.sid');
        $token = AppSetting::get('twilio_token') ?: config('services.twilio.token');
        $from = AppSetting::get('twilio_whatsapp_from') ?: config('services.twilio.whatsapp_from', '');
        if ($from && !str_starts_with($from, 'whatsapp:')) {
            $from = 'whatsapp:' . $from;
        }
        $this->twilioFrom = $from;

        if ($sid && $token) {
            try {
                $this->twilioClient = new Client($sid, $token);
            } catch (\Exception $e) {
                Log::error('WhatsApp: impossible d\'initialiser Twilio', ['error' => $e->getMessage()]);
            }
        }

        // Initialisation Meta
        $this->metaService = new MetaWhatsAppService();
    }

    /**
     * Récupère la configuration des deux providers
     */
    public static function getConfig(): array
    {
        $twilioConfigured = !empty(AppSetting::get('twilio_sid'))
            && !empty(AppSetting::get('twilio_token'))
            && !empty(AppSetting::get('twilio_whatsapp_from'));

        $metaConfigured = !empty(AppSetting::get('whatsapp_meta_token'))
            && !empty(AppSetting::get('whatsapp_meta_phone_id'));

        $provider = AppSetting::get('whatsapp_provider', 'twilio');

        return [
            'provider' => $provider,
            'twilio' => [
                'sid' => AppSetting::get('twilio_sid', ''),
                'from' => AppSetting::get('twilio_whatsapp_from', ''),
                'configured' => $twilioConfigured,
            ],
            'meta' => [
                'token' => AppSetting::get('whatsapp_meta_token', '') ? '********' . substr(AppSetting::get('whatsapp_meta_token', ''), -4) : '',
                'phone_number_id' => AppSetting::get('whatsapp_meta_phone_id', ''),
                'business_account_id' => AppSetting::get('whatsapp_meta_business_id', ''),
                'configured' => $metaConfigured,
            ],
            'configured' => $provider === 'twilio' ? $twilioConfigured : $metaConfigured,
            'active_provider' => $provider,
        ];
    }

    /**
     * Envoie un message WhatsApp à un numéro
     */
    public function send(string $to, string $message): array
    {
        // Utiliser le provider configuré
        if ($this->provider === 'meta' && $this->metaService->isConfigured()) {
            return $this->metaService->send($to, $message);
        }

        // Fallback sur Twilio
        if (!$this->twilioClient) {
            return [
                'success' => false,
                'error' => 'Aucun service WhatsApp configuré. Configurez Twilio ou Meta Cloud API.'
            ];
        }

        // Normaliser le numéro (ajouter + si manquant)
        $to = $this->normalizePhone($to);

        try {
            $result = $this->twilioClient->messages->create(
                "whatsapp:$to",
                [
                    'from' => $this->twilioFrom,
                    'body' => $message
                ]
            );

            Log::info('WhatsApp Twilio envoyé', [
                'to' => $to,
                'sid' => $result->sid,
                'status' => $result->status
            ]);

            WhatsAppMessage::create([
                'phone'          => $to,
                'recipient_name' => $this->currentRecipientName ?? null,
                'message'        => $message,
                'provider'       => 'twilio',
                'status'         => 'sent',
                'external_id'    => $result->sid,
                'formation_name' => $this->currentFormationName ?? null,
                'project_name'   => $this->currentProjectName ?? null,
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

            WhatsAppMessage::create([
                'phone'          => $to,
                'recipient_name' => $this->currentRecipientName ?? null,
                'message'        => $message,
                'provider'       => 'twilio',
                'status'         => 'failed',
                'error'          => $e->getMessage(),
                'formation_name' => $this->currentFormationName ?? null,
                'project_name'   => $this->currentProjectName ?? null,
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

            $this->currentRecipientName = $name ?: null;
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
     * Vérifie si un service WhatsApp est configuré
     */
    public function isConfigured(): bool
    {
        if ($this->provider === 'meta') {
            return $this->metaService->isConfigured();
        }
        return $this->twilioClient !== null && !empty($this->twilioFrom);
    }

    /**
     * Retourne le provider actif
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Change le provider actif
     */
    public function setProvider(string $provider): void
    {
        $this->provider = $provider;
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
