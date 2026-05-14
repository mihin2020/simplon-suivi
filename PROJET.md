# 📚 Simplon Suivi - Documentation Projet

Application de gestion et suivi des formations Simplon.co

---

## 🎯 Vue d'ensemble

Simplon Suivi est une application web complète permettant de gérer :
- Les formations et projets
- Les apprenants et leur parcours
- Les formateurs et leur assignation
- La médiathèque avec stockage cloud
- Les présences et évaluations

---

## 🚀 Fonctionnalités Implémentées

### 1. 👥 Gestion des Apprenants (Learners)

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| CRUD complet | Création, lecture, mise à jour, suppression | ✅ |
| Import Excel | Import massif via fichier Excel (.xlsx) | ✅ |
| Export modèle | Téléchargement du modèle d'import | ✅ |
| Champs enrichis | Adresse, localisation, profil, domaine d'études | ✅ |
| Statut d'insertion | Suivi de l'insertion professionnelle | ✅ |
| Contact urgence | Informations de contact d'urgence | ✅ |

**Champs apprenant :**
- Identité (nom, prénom, genre, date naissance)
- Coordonnées (téléphone, email, adresse, localisation)
- Informations complémentaires (profil, domaine d'études)
- Documents (carte ID)
- Contact d'urgence

### 2. 👨‍🏫 Gestion des Formateurs (Trainers)

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| CRUD formateurs | Gestion complète des profils | ✅ |
| Spécialités | Profils métier par formateur | ✅ |
| Assignation | Affectation aux formations | ✅ |

### 3. 🎓 Gestion des Formations

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| CRUD formations | Création et gestion des formations | ✅ |
| Apprenants liés | Association apprenants ↔ formation | ✅ |
| Formateurs liés | Association formateurs ↔ formation | ✅ |
| Médiathèque | Gestion des médias par formation | ✅ |

### 4. 📸 Médiathèque (MediaLibrary) - NOUVEAU !

Système complet de gestion de médias avec stockage Cloudinary.

#### Fonctionnalités :

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| **Upload Cloudinary** | Upload direct vers le cloud | ✅ |
| **Widget Cloudinary** | Interface de upload drag & drop | ✅ |
| **Visionneuse** | Lightbox pour photos/vidéos | ✅ |
| **Albums/Dossiers** | Organisation par dossiers | ✅ |
| **Sélection multiple** | Actions sur plusieurs médias | ✅ |
| **Téléchargement** | Download individuel ou par album | ✅ |
| **Édition** | Modifier titre, album, etc. | ✅ |
| **Suppression** | Suppression avec confirmation | ✅ |
| **Jauge stockage** | Affichage de l'espace utilisé | ✅ |

#### Architecture Médiathèque :

```
┌─────────────────────────────────────────┐
│           Interface Utilisateur          │
│  (Vue.js + Inertia.js)                   │
├─────────────────────────────────────────┤
│              Vue Dossiers               │
│  📁 Album 1 (5 médias)                  │
│  📁 Album 2 (3 médias)                  │
│  📁 Sans album (2 médias)               │
└─────────────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│           API Backend (Laravel)          │
│  - MediaController                       │
│  - CloudinaryService                     │
└─────────────────────────────────────────┘
                   ↓
┌─────────────────────────────────────────┐
│              Stockage                    │
│  BDD (métadonnées) ← → Cloudinary      │
│  (MySQL/SQLite)       (fichiers)         │
└─────────────────────────────────────────┘
```

#### Tables concernées :
- `medias` : Stockage des métadonnées (URL, taille, album, etc.)

#### Limitations :
- Max 10 MB par fichier (plan gratuit Cloudinary)
- 25 GB de stockage total

### 5. 🤖 Assistant IA (Chatbot)

Système de chatbot intelligent intégré à l'interface, accessible depuis le header.

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| **Widget flottant** | Bouton `smart_toy` dans le header, panel chat animé | ✅ |
| **Multi-provider** | Claude, OpenAI, DeepSeek, Grok, Gemini, Custom (OpenAI-compatible) | ✅ |
| **Full context dump** | Toutes les données BDD chargées dans le prompt à chaque requête | ✅ |
| **Cache intelligent** | Cache 60s du contexte, invalidé automatiquement à chaque modification BDD | ✅ |
| **Adaptation volume** | Mode liste individuelle (≤500), résumé par formation (≤2000), agrégats (>2000) | ✅ |
| **Rendu Markdown** | Tables, titres, listes, liens cliquables rendus en HTML dans le chat | ✅ |
| **Clé API chiffrée** | Stockée avec `Crypt::encryptString()`, jamais exposée | ✅ |
| **Rate limiting** | 10 messages/minute par utilisateur | ✅ |
| **Configuration** | Sélecteur provider + clé API + modèle + URL personnalisée dans /configuration | ✅ |

#### Données accessibles par l'IA
L'assistant a accès en lecture seule à toutes les entités :
- Projets, Formations, Apprenants (genre, email, insertion, formation)
- Formateurs, Partenaires, Référentiels (blocs + compétences)
- Utilisateurs de la plateforme, Présences (résumé)
- Suivi insertion/emploi/stage avec entreprises et dates

#### Observer d'invalidation cache
`AiCacheObserver` enregistré sur : Learner · Formation · Project · Trainer · InsertionRecord · Partner · Referentiel · CompetenceBlock · Competence · EducationLevel · TrainerProfile · User · Attendance

#### Architecture

```
Header (bouton smart_toy)
        ↓
AiChatbot.vue (widget flottant)
        ↓ POST /chatbot/message (throttle: 10/min)
AiChatController → set_time_limit(120)
        ↓
AiChatService::chat()
        ↓
Cache::remember(60s) → loadAllData() → dump BDD complet (~5,600 tokens)
        ↓
Provider API (Claude / OpenAI / DeepSeek / Grok / Gemini / Custom)
        ↓
Réponse Markdown → rendu HTML dans le widget
```

#### Configuration requise (.env / page Configuration)
```
# Via page /configuration (stocké chiffré en BDD)
ai_provider = claude | openai | deepseek | grok | gemini | custom
ai_api_key  = sk-ant-... (chiffré)
ai_model    = optionnel (ex: gpt-4o, claude-opus-4-5)
ai_base_url = optionnel, pour providers custom compatibles OpenAI
```

---

### 6. ⚙️ Configuration

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| Profils formateurs | CRUD des spécialités | ✅ |
| Niveaux d'études | Gestion des niveaux scolaires | ✅ |
| Paramètres système | Configuration globale | ✅ |
| **Clé API IA** | Sélecteur provider + clé chiffrée + modèle + URL custom | ✅ |

### 6. 🔐 Authentification & Autorisations

| Fonctionnalité | Description | Statut |
|----------------|-------------|--------|
| Authentification | Login sécurisé | ✅ |
| Rôles (Spatie) | Gestion des permissions | ✅ |
| Middleware | Protection des routes | ✅ |

---

## 🛠️ Stack Technique

### Backend
- **Framework** : Laravel 11.x
- **Langage** : PHP 8.3+
- **Base de données** : SQLite (dev) / MySQL (prod)
- **ORM** : Eloquent
- **Authentification** : Laravel Breeze + Spatie Permissions

### Frontend
- **Framework** : Vue.js 3 (Composition API)
- **Routing** : Inertia.js
- **Styling** : Tailwind CSS (partiel)
- **UI** : Material Icons
- **TypeScript** : Support TS configuré

### Services Externes
- **Cloudinary** : Stockage et transformation de médias
- **Maatwebsite Excel** : Import/Export Excel

### Packages Clés
```json
{
  "laravel/framework": "^11.0",
  "inertiajs/inertia-laravel": "^1.0",
  "spatie/laravel-permission": "^6.0",
  "maatwebsite/excel": "^3.1",
  "cloudinary/cloudinary_php": "^2.0",
  "zipstream-php/zipstream": "^3.0"
}
```

---

## 📁 Structure du Projet

```
simplon-suivi/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── LearnerController.php      # CRUD apprenants
│   │   │   ├── TrainerController.php        # CRUD formateurs
│   │   │   ├── FormationController.php      # CRUD formations
│   │   │   ├── MediaController.php          # Médiathèque ⭐
│   │   │   ├── EducationLevelController.php # Niveaux d'études
│   │   │   └── ConfigurationController.php    # Configuration
│   │   └── Requests/                        # Validation FormRequest
│   ├── Models/
│   │   ├── Learner.php                      # Modèle apprenant
│   │   ├── Trainer.php                      # Modèle formateur
│   │   ├── Formation.php                    # Modèle formation
│   │   ├── Media.php                        # Modèle média ⭐
│   │   └── EducationLevel.php               # Modèle niveau d'études
│   └── Services/
│       └── CloudinaryService.php            # Service Cloudinary ⭐
├── config/
│   └── cloudinary.php                       # Config Cloudinary
├── database/
│   └── migrations/
│       ├── 2026_05_13_010000_create_medias_table.php  # Table médias
│       └── ...
├── resources/
│   └── js/
│       └── Pages/
│           ├── Learners/                    # Pages apprenants
│           │   ├── Index.vue
│           │   ├── Create.vue
│           │   ├── Edit.vue
│           │   ├── Show.vue
│           │   └── Import.vue
│           ├── Formations/
│           │   ├── Index.vue
│           │   ├── Show.vue
│           │   └── MediaLibrary.vue           # Médiathèque ⭐
│           └── Configuration/Index.vue        # Configuration
├── routes/
│   └── web.php                              # Routes
└── .env                                     # Variables d'environnement
```

---

## 🔧 Configuration Requise

### Variables d'environnement (.env)

```env
# Cloudinary
CLOUDINARY_CLOUD_NAME=da0iiibwe
CLOUDINARY_API_KEY=votre_api_key
CLOUDINARY_API_SECRET=votre_api_secret
CLOUDINARY_UPLOAD_PRESET=simplon_medias

# Database
DB_CONNECTION=sqlite
# ou
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simplon_suivi
DB_USERNAME=root
DB_PASSWORD=
```

### Installation

```bash
# 1. Cloner le projet
git clone https://github.com/mihin2020/simplon-suivi.git
cd simplon-suivi

# 2. Installer les dépendances
composer install
npm install

# 3. Configurer l'environnement
cp .env.example .env
php artisan key:generate

# 4. Migrer la base de données
php artisan migrate

# 5. Créer un utilisateur admin
php artisan tinker
>>> \App\Models\User::create(['first_name' => 'Admin', 'last_name' => 'Simplon', 'email' => 'admin@simplon.co', 'password' => bcrypt('password'), 'role' => 'admin']);

# 6. Compiler les assets
npm run build

# 7. Lancer le serveur
php artisan serve
```

---

## 🎯 Utilisation de la Médiathèque

### Accès
1. Aller sur une formation → Cliquer "Médiathèque"

### Upload
1. Cliquer "Ajouter des médias"
2. Sélectionner ou glisser-déposer des fichiers
3. Limite : 10 MB par fichier
4. Les fichiers sont uploadés vers Cloudinary

### Organisation
- **Vue Dossiers** : Affiche les albums comme des dossiers
- **Double-clic** sur un dossier pour l'ouvrir
- **Créer un album** : Sélectionner des médias → "Ajouter à l'album"

### Visualisation
- **Cliquer** sur une image/vidéo pour l'ouvrir en grand
- **Flèches** (← →) pour naviguer
- **Escape** pour fermer

### Téléchargement
- **Individuel** : Bouton "Télécharger" sur chaque média
- **Par album** : À venir

---

## 📝 API Endpoints (Médiathèque)

```
GET    /formations/{formation}/medias          # Liste des médias
POST   /formations/{formation}/medias          # Upload un média
PUT    /formations/{formation}/medias/{media}  # Modifier un média
DELETE /formations/{formation}/medias/{media}  # Supprimer un média
POST   /formations/{formation}/medias/batch-update  # Mise à jour multiple
GET    /formations/{formation}/medias/{media}/download  # Télécharger
GET    /formations/{formation}/medias/album/{album}/download  # ZIP album
```

---

## 🔒 Sécurité

- **CSRF Protection** : Tous les formulaires protégés
- **Authentification** : Middleware `auth` sur toutes les routes protégées
- **Autorisations** : Policies Laravel pour chaque modèle
- **Validation** : FormRequest pour toutes les entrées utilisateur
- **XSS Protection** : Échappement automatique Blade/Vue

---

## 🐛 Dépannage

### Erreur 419 (Page Expired)
Solution : Vider le cache
```bash
php artisan cache:clear
php artisan view:clear
```

### Upload ne fonctionne pas
1. Vérifier le token CSRF dans `resources/views/app.blade.php`
2. Vérifier les credentials Cloudinary dans `.env`
3. Vérifier la console navigateur (F12)

### Jauge à 0%
Normal si < 0.01% de 25 GB utilisé. Le calcul se fait depuis la BDD locale.

---

## 🚀 Roadmap

### Prochaines fonctionnalités
- [ ] Upload multi-fichiers simultanés
- [ ] Tags sur les médias
- [ ] Recherche full-text dans les médias
- [ ] Statistiques détaillées d'utilisation
- [ ] Sauvegarde automatique des présences
- [ ] Module d'évaluation des apprenants

---

## 👨‍💻 Auteur

**Hugues Aimé MIHIN**
- GitHub : [@mihin2020](https://github.com/mihin2020)

---

## 📄 License

Ce projet est sous licence [MIT](https://opensource.org/licenses/MIT).

---

<p align="center">Made with ❤️ for Simplon.co</p>
