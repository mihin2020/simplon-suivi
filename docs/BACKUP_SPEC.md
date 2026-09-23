# Backup applicatif — Spécification

> Document de référence.  
> Contexte : développement **local** (Windows), déploiement cible **VPS Linux**.  
> Public : Super Admin / Admin autorisé.

---

## 1. Objectif

Permettre à un administrateur de :

1. **Lancer un backup manuellement** depuis l’interface web.
2. **Programmer des backups automatiques** (fréquence configurable).
3. **Consulter l’historique**, **télécharger** et **supprimer** les archives.
4. **Notifier par e-mail** (sans pièce jointe) un destinataire configurable.
5. **Copier hors serveur** vers **Cloudflare R2** (S3-compatible, gratuit 10 Go) pour survie en cas de panne machine.
6. Pouvoir **récupérer** l’application (base + fichiers) via CLI / téléchargement.

Un backup complet = **base de données MySQL** + **fichiers métier** (`storage/app`).

---

## 2. Stockage

| Copie | Emplacement | Rôle |
|---|---|---|
| Locale | `storage/app/backups/` (hors `public/`) | Rapide, téléchargeable depuis l’UI |
| Distante | Cloudflare R2 (disque `backup_remote`) | Récupération si le serveur est perdu |
| E-mail | Notification uniquement | Pas de ZIP en pièce jointe (limites SMTP) |

---

## 3. Périmètre inclus / exclus

### Inclus

| Élément | Contenu |
|---|---|
| Dump SQL | Base `DB_DATABASE` (ou fichier SQLite en local/tests) |
| Fichiers | `storage/app` (hors `backups/` et `backup-tmp/`) |
| Métadonnées | `manifest.json` + ligne table `backups` |

### Exclus

- `vendor/`, `node_modules/`, `.env`, logs, caches, code source (Git)

### Restauration

- **Phase 1** : hors UI (doc + SSH / Artisan).
- **Phase 2 (optionnelle)** : restauration guidée UI.

---

## 4. UI

Page : **Configuration → Sauvegardes** (`/configuration/backups`).

Permissions : `backup.view`, `backup.run`, `backup.manage`.

---

## 5. Technique

- CLI scheduler / manuel : `php artisan backup:run {--type=manual|auto}`
- UI (processus détaché + polling %) : `php artisan backup:process {uuid}` via `BackupProcessLauncher`
- Scheduler : `routes/console.php` selon `BackupSettings` (`interval_days` + `run_at`)
- Config `.env` : `BACKUP_MYSQLDUMP_PATH`, `BACKUP_*`, `AWS_*` + `AWS_ENDPOINT` pour R2
- Dépendance : `league/flysystem-aws-s3-v3`

### Archive

```
backup-YYYY-mm-dd-HHMMSS.zip
├── database.sql   (ou database.sqlite)
├── files/
└── manifest.json
```

### Cron VPS (obligatoire pour l’auto)

```cron
* * * * * cd /var/www/simplon-suivi && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Sécurité

- Permissions dédiées ; Super Admin bypass.
- Téléchargement authentifié uniquement (pas d’URL publique).
- Rate limit manuel : 1 / 5 min.
- Vérification espace disque avant run.
- Bucket R2 privé ; secrets uniquement dans `.env`.

---

## 7. Cloudflare R2 (setup rapide)

1. Créer un compte Cloudflare → R2 → Create bucket (privé).
2. Manage R2 API Tokens → Create API token (Object Read & Write).
3. Dans `.env` :

```env
BACKUP_REMOTE_ENABLED=true
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=simplon-suivi-backups
AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

4. Activer « Copier aussi vers Cloudflare R2 » dans l’UI.

---

## 8. Restauration (ops)

```bash
# MySQL
mysql -u USER -p DB_NAME < database.sql

# Fichiers
# décompresser files/ → storage/app/
php artisan storage:link
php artisan config:clear && php artisan cache:clear
```

Si le serveur est mort : télécharger le ZIP depuis le dashboard R2 puis restaurer ailleurs.

---

## 9. Hors scope actuel

- Google Drive / FTP
- Pièce jointe e-mail du ZIP
- Restauration one-click UI
- Chiffrement archive

---

*Implémenté selon le plan Backup admin v1.*
