<x-mail::message>
# Sauvegarde réussie

La sauvegarde **{{ $backup->type->label() }}** s'est terminée avec succès.

- **Date :** {{ $backup->finished_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}
@if($sizeMb)
- **Taille :** {{ $sizeMb }} Mo
@endif
- **Copie distante (R2) :** {{ $remoteSynced ? 'Synchronisée' : 'Non synchronisée / inactive' }}

L'archive est stockée sur le serveur. Cet e-mail est une **notification** (sans pièce jointe).

<x-mail::button :url="$backupsUrl">
Voir les sauvegardes
</x-mail::button>

Merci,<br>
{{ config('app.name') }}
</x-mail::message>
