<x-mail::message>
# Échec de sauvegarde

La sauvegarde **{{ $backup->type->label() }}** a échoué.

- **Date :** {{ $backup->finished_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
- **Erreur :** {{ $backup->error_message ?: 'Erreur inconnue' }}

Vérifiez que `mysqldump` est disponible, l'espace disque, et la configuration R2 le cas échéant.

<x-mail::button :url="$backupsUrl">
Voir les sauvegardes
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
