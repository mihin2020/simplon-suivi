# Backup applicatif — Spécification

> Document de référence pour l’implémentation ultérieure.  
> Contexte : développement **local** (Windows), déploiement cible **VPS Linux**.  
> Public : Super Admin / Admin autorisé.

---

## 1. Objectif

Permettre à un administrateur de :

1. **Lancer un backup manuellement** depuis l’interface web.
2. **Programmer des backups automatiques** (fréquence configurable).
3. **Consulter l’historique**, **télécharger** et éventuellement **supprimer** les archives.
4. Pouvoir **récupérer** l’application en cas de problème (base + fichiers).

Un backup complet = **base de données MySQL** + **fichiers métier** (`storage/app`).

---

## 2. Périmètre inclus / exclus

### Inclus dans chaque backup

| Élément | Contenu |
|---|---|
| Dump SQL | Base `DB_DATABASE` (tables, données, structure) |
| Fichiers | `storage/app` (photos apprenants, pièces jointes formulaires, headers, etc.) |
| Métadonnées | Date, type (manuel / auto), taille, statut, utilisateur déclencheur (si manuel) |

### Exclus (volontairement)

- `vendor/`, `node_modules/`
- `.env` (secrets — à sauvegarder à part, hors appli publique)
- `storage/logs`, caches
- Code source (géré par Git)

### Restauration

- **Phase 1** : restauration **hors UI** (doc + commandes SSH / Artisan CLI).
- **Phase 2 (optionnelle)** : restauration guidée depuis l’UI (risquée — confirmation forte requise).

---

## 3. Fonctionnalités UI (admin)

Page proposée : **Configuration → Sauvegardes** (ou menu dédié « Backups »).

### 3.1 Actions manuelles

- Bouton **« Lancer un backup maintenant »**
- Indication de progression / succès / échec
- Liste paginée des backups :
  - date / heure
  - type (`manuel` / `automatique`)
  - taille
  - statut (`en_cours`, `réussi`, `échoué`)
  - déclenché par (nom admin ou « Scheduler »)
- Bouton **Télécharger** (fichier `.zip` contenant SQL + fichiers)
- Bouton **Supprimer** (avec confirmation)

### 3.2 Automatisation

Paramètres (stockés en base ou config) :

| Paramètre | Exemples |
|---|---|
| Activer l’auto | Oui / Non |
| Fréquence | Quotidien / Hebdomadaire / Mensuel |
| Heure d’exécution | ex. `02:00` |
| Rétention | Garder les N derniers (ex. 7, 14, 30) |
| Contenu | Toujours BDD + fichiers (défaut) |

À chaque run auto : créer le backup, puis **purger** les archives au-delà de la rétention.

---

## 4. Architecture technique proposée

### 4.1 Stack (conforme projet)

- Laravel 12 + Inertia / Vue 3
- Commande Artisan : `backup:run {--type=manual|auto}`
- Job en queue (recommandé) pour ne pas bloquer la requête HTTP
- Scheduler Laravel (`routes/console.php`) selon la fréquence
- Stockage des archives : `storage/app/backups/` (**hors** `public/`)
- Permissions manuelles (pas Spatie) :
  - `backup.view` — voir la liste / télécharger
  - `backup.run` — lancer un backup manuel
  - `backup.manage` — config fréquence + supprimer

### 4.2 Structure d’une archive

```
backup-2026-09-23-020000.zip
├── database.sql          # dump mysqldump
├── files/                # copie de storage/app (hors backups/)
│   ├── public/
│   ├── forms/
│   └── ...
└── manifest.json         # version app, date, type, checksums
```

### 4.3 Dépendance système

| Environnement | Prérequis |
|---|---|
| Local Windows | `mysqldump` disponible (XAMPP/Laragon/PATH) — chemin configurable via `.env` |
| VPS Linux | `mysqldump` + `zip` (ou ZipArchive PHP) — standard sur la plupart des images |

Variable `.env` proposée :

```env
BACKUP_MYSQLDUMP_PATH=mysqldump
# Ex. Windows XAMPP :
# BACKUP_MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
BACKUP_DISK=local
BACKUP_RETENTION_COUNT=14
```

### 4.4 Modèle / table `backups` (UUID)

Colonnes suggérées :

- `id` (uuid)
- `type` (`manual` / `auto`)
- `status` (`pending` / `running` / `success` / `failed`)
- `disk_path` (chemin relatif de l’archive)
- `size_bytes` (nullable)
- `error_message` (nullable)
- `triggered_by` (nullable uuid → users)
- `started_at`, `finished_at`
- `created_at`, `updated_at`

---

## 5. Sécurité

- Accès **admin uniquement** (permissions ci-dessus).
- Archives **jamais** servies via URL publique directe → téléchargement authentifié (stream / signed route courte).
- Ne pas logger le contenu SQL ni les mots de passe.
- Rate limit sur le lancement manuel (ex. 1 backup / 5 min) pour éviter la saturation disque.
- Vérifier l’espace disque avant de démarrer (sinon échec propre avec message).

---

## 6. Scheduler — local vs VPS

### Local (dev)

```bash
php artisan schedule:work
```

Ou lancer `backup:run --type=auto` à la main pour tester.

### VPS (production)

Cron système (obligatoire) :

```cron
* * * * * cd /var/www/simplon-suivi && php artisan schedule:run >> /dev/null 2>&1
```

Le scheduler Laravel lit ensuite la config (quotidien / hebdo / mensuel) et déclenche `backup:run --type=auto`.

**Sans cron VPS, l’automatique ne tourne pas.**

---

## 7. Restauration (procédure opérationnelle)

### 7.1 Restaurer la base

```bash
# VPS / Linux
mysql -u USER -p DB_NAME < database.sql

# Windows (XAMPP)
mysql -u root suivi-laravel < database.sql
```

### 7.2 Restaurer les fichiers

1. Décompresser `files/` dans `storage/app/`
2. `php artisan storage:link`
3. Vérifier les droits (`www-data` sur VPS)

### 7.3 Après restauration

```bash
php artisan config:clear
php artisan cache:clear
php artisan migrate --force   # seulement si le dump est plus ancien que le code
```

---

## 8. UX / messages (FR)

- Succès manuel : « Backup lancé. Vous pourrez le télécharger une fois terminé. »
- Succès auto (logs / notif admin optionnelle) : « Backup automatique réussi. »
- Échec : « Le backup a échoué. Vérifiez que mysqldump est disponible et l’espace disque. »
- Config : « Les backups automatiques s’exécutent selon la fréquence définie (cron serveur requis en production). »

---

## 9. Plan d’implémentation (quand demandé)

1. Migration `backups` + permissions + seeder  
2. Service `BackupService` (dump + zip + rétention)  
3. Commande `backup:run` + Job  
4. Controller + pages Inertia (liste, config, téléchargement)  
5. Entrée menu admin + routes protégées  
6. Scheduler selon fréquence  
7. Tests feature (lancement manuel, validation permissions, rétention)  
8. Doc ops courte dans le README (cron VPS + chemin mysqldump)

---

## 10. Critères d’acceptation

- [ ] Un admin peut lancer un backup depuis l’UI  
- [ ] L’archive contient le dump SQL **et** les fichiers `storage/app`  
- [ ] L’admin peut télécharger et supprimer un backup  
- [ ] La fréquence auto est configurable et respectée si le scheduler tourne  
- [ ] La rétention purge les anciens backups  
- [ ] Un non-admin ne peut pas accéder à la page  
- [ ] Fonctionne en local (Windows + mysqldump) et est prêt pour VPS Linux  

---

## 11. Hors scope (v1)

- Backup vers S3 / Google Drive / FTP distant (peut être une v2)  
- Restauration one-click depuis l’UI  
- Backup différentiel / incrémental  
- Chiffrement de l’archive (possible en v2)

---

*Document créé pour cadrer l’implémentation. Ne pas coder tant que la demande explicite « implémente le backup » n’est pas faite.*
