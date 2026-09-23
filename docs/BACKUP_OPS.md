# Ops — Sauvegardes

## Local

1. Vérifier `BACKUP_MYSQLDUMP_PATH` dans `.env` (Laragon / XAMPP) — ou laisser la détection auto.
2. Page : `/configuration/backups` (progression live en %)
3. Optionnel CLI : `php artisan backup:run --type=manual`
4. Pour tester le scheduler : `php artisan schedule:work`

## Production (VPS)

Cron obligatoire :

```cron
* * * * * cd /var/www/simplon-suivi && php artisan schedule:run >> /dev/null 2>&1
```

## Cloudflare R2 (copie externe)

Voir `docs/BACKUP_SPEC.md` §7. Variables : `AWS_*`, `AWS_ENDPOINT`, `BACKUP_REMOTE_ENABLED=true`, puis activer dans l’UI.

## Espace disque

Les backups locaux nécessitent de l’espace libre sur le serveur (`storage/app/backups`). En cas d’échec « No space left », libérer du disque ou s’appuyer sur R2 + rétention basse.
