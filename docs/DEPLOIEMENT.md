# Mise en production sur un VPS (Ubuntu / Debian)

Le site web seul (Nginx + PHP-FPM) ne suffit pas : trois programmes doivent
tourner **en permanence** à côté, sinon une partie de l'application ne fait rien.

| Programme | Rôle | Sans lui |
|---|---|---|
| `php artisan queue:work` (worker) | Envois en arrière-plan : emails en masse, WhatsApp groupé, contrats par email, analyse des contrats, invitations | Les envois restent « en cours » indéfiniment |
| `php artisan schedule:run` (cron) | Tâches planifiées : sauvegardes auto, clôture des formulaires expirés | Pas de sauvegarde automatique, formulaires jamais clos |
| Service WhatsApp (Node, port 3001) | Connexion WhatsApp | Aucun message WhatsApp |

Chemin utilisé ci-dessous : `/var/www/simplon-suivi`, utilisateur `www-data`. Adapter si besoin.

---

## 1. Worker de la file d'attente (Supervisor)

Supervisor relance le worker s'il s'arrête et au redémarrage du serveur.

```bash
sudo apt install supervisor
sudo nano /etc/supervisor/conf.d/simplon-worker.conf
```

```ini
[program:simplon-worker]
command=php /var/www/simplon-suivi/artisan queue:work database --sleep=3 --timeout=1800 --max-time=3600
directory=/var/www/simplon-suivi
user=www-data
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
stopwaitsecs=1810
redirect_stderr=true
stdout_logfile=/var/www/simplon-suivi/storage/logs/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status        # doit afficher RUNNING
```

- `numprocs=2` : deux envois peuvent avancer en parallèle (un gros mailing ne bloque pas les invitations).
- `--timeout=1800` et `stopwaitsecs=1810` : un long envoi n'est pas coupé en cours de route.
- Dans `.env` : `QUEUE_CONNECTION=database` et `DB_QUEUE_RETRY_AFTER=1900` (doit rester **supérieur** à 1800,
  sinon un gros envoi peut être repris par le second worker et partir deux fois).

## 2. Tâches planifiées (cron)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/simplon-suivi && php artisan schedule:run >> /dev/null 2>&1
```

## 3. Service WhatsApp (Node)

Également sous Supervisor (ou pm2), pour qu'il redémarre seul :

```ini
[program:simplon-whatsapp]
command=npm start
directory=/var/www/whatsapp-service
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/www/simplon-suivi/storage/logs/whatsapp.log
```

`WWEBJS_URL=http://127.0.0.1:3001` dans `.env` (le port ne doit pas être ouvert sur internet).

## 4. À chaque mise à jour du code

```bash
cd /var/www/simplon-suivi
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart     # les workers rechargent le nouveau code
php artisan up
```

`queue:restart` est indispensable : sans lui, les workers continuent d'exécuter l'**ancien** code.
Ils terminent proprement l'envoi en cours avant de redémarrer.

### Première mise à jour depuis la version du 24/09/2026 (commit 45b33c9)

Compatible avec la base en ligne : 16 migrations, **uniquement des ajouts** (nouvelles tables
contrats/en-têtes, colonnes facultatives sur `trainers`, permissions du module Contrats ajoutées
puis libellées, sans toucher aux réglages existants). Aucune donnée existante n'est modifiée ni supprimée.

Accès au module Contrats : le Super Admin y a toujours accès. Pour un Administrateur, cocher les
permissions du groupe « Contrats » sur sa fiche utilisateur ; elles ne sont données à personne
automatiquement.

- **Ne pas lancer `db:seed` / `PermissionSeeder` en production** : il remet toutes les permissions
  au rôle Administrateur et effacerait les réglages faits en ligne. La migration
  `2026_10_15_100004_add_contract_permissions` suffit.
- `composer install` est obligatoire (nouvelle dépendance `smalot/pdfparser`, lecture des PDF importés).
- Le dossier `storage/fonts` doit être accessible en écriture (cache des polices du PDF).
- Le worker (§ 1) doit tourner : le module Contrats et les envois en masse en dépendent.

## 5. Réglages `.env` de production

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://…
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1900
SESSION_DRIVER=database
CACHE_STORE=database
```

Et côté serveur : OPcache activé, PHP-FPM `pm.max_children` ≈ 20 pour un VPS 2 vCPU / 4 Go,
`upload_max_filesize` / `post_max_size` ≥ 20M (pièces jointes WhatsApp jusqu'à 16 Mo).

## 6. Vérifier que tout tourne

```bash
sudo supervisorctl status                      # simplon-worker_00/01 et simplon-whatsapp : RUNNING
php artisan queue:monitor database:default     # nombre de tâches en attente
php artisan queue:failed                       # tâches en échec
tail -f storage/logs/worker.log
```

Si des envois restent « en cours » : le worker ne tourne pas (`supervisorctl status`), ou il exécute
un ancien code (`php artisan queue:restart`).
