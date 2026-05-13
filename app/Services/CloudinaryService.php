<?php

namespace App\Services;

use Cloudinary\Cloudinary;
use Cloudinary\Api\Admin\AdminApi;

class CloudinaryService
{
    private Cloudinary $cloudinary;
    private AdminApi $adminApi;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ],
        ]);
        $this->adminApi = $this->cloudinary->adminApi();
    }

    // Supprimer un fichier sur Cloudinary
    public function delete(string $publicId, string $resourceType = 'image'): bool
    {
        try {
            $result = $this->adminApi->deleteAssets([$publicId], [
                'resource_type' => $resourceType,
            ]);
            return $result['deleted'][$publicId] === 'deleted';
        } catch (\Exception $e) {
            \Log::error('Cloudinary delete error: ' . $e->getMessage());
            return false;
        }
    }

    // Récupérer les statistiques d'utilisation
    public function getUsage(): array
    {
        try {
            $usage = $this->adminApi->usage();

            $storageUsed = $usage['storage']['usage'] ?? 0; // en bytes
            $bandwidthUsed = $usage['bandwidth']['usage'] ?? 0; // en bytes
            $requests = $usage['requests']['usage'] ?? 0;

            $storageLimit = 25 * 1024 * 1024 * 1024; // 25 GB en bytes (plan gratuit)

            return [
                'storage_used' => $storageUsed,
                'storage_limit' => $storageLimit,
                'storage_used_formatted' => $this->formatBytes($storageUsed),
                'storage_limit_formatted' => $this->formatBytes($storageLimit),
                'storage_remaining' => $storageLimit - $storageUsed,
                'storage_remaining_formatted' => $this->formatBytes($storageLimit - $storageUsed),
                'percent_used' => min(100, round(($storageUsed / $storageLimit) * 100, 1)),
                'bandwidth_used' => $bandwidthUsed,
                'bandwidth_formatted' => $this->formatBytes($bandwidthUsed),
                'requests' => $requests,
                'alert_level' => $this->getAlertLevel(($storageUsed / $storageLimit) * 100),
            ];
        } catch (\Exception $e) {
            \Log::error('Cloudinary usage error: ' . $e->getMessage());
            return [
                'storage_used' => 0,
                'storage_limit' => 25 * 1024 * 1024 * 1024,
                'storage_used_formatted' => '0 B',
                'storage_limit_formatted' => '25 GB',
                'storage_remaining' => 25 * 1024 * 1024 * 1024,
                'storage_remaining_formatted' => '25 GB',
                'percent_used' => 0,
                'bandwidth_used' => 0,
                'bandwidth_formatted' => '0 B',
                'requests' => 0,
                'alert_level' => 'normal',
            ];
        }
    }

    // Générer une URL signée pour téléchargement sécurisé
    public function getDownloadUrl(string $publicId, string $resourceType = 'image'): string
    {
        return $this->cloudinary->image($publicId)->toUrl([
            'flags' => 'attachment',
        ]);
    }

    // Générer URL de transformation pour thumbnail
    public function getThumbnailUrl(string $publicId, int $width = 300, int $height = 200): string
    {
        return $this->cloudinary->image($publicId)
            ->resize(\Cloudinary\Transformation::thumbnail()->width($width)->height($height)->crop(\Cloudinary\Asset\CropMode::fill()))
            ->toUrl();
    }

    // Formatter les bytes en format lisible
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    // Déterminer le niveau d'alerte
    private function getAlertLevel(float $percent): string
    {
        if ($percent >= 95) {
            return 'critical';
        } elseif ($percent >= 80) {
            return 'warning';
        }
        return 'normal';
    }
}
