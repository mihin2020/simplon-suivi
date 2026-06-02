# Tests Suite - Simplon Suivi

## Structure des Tests

### Tests Unitaires (`tests/Unit/`)
- `Models/LearnerTest.php` - Tests du modèle Learner
- `Models/FormationTest.php` - Tests du modèle Formation
- `Enums/LearnerStatusTest.php` - Tests des enums

### Tests Fonctionnels (`tests/Feature/`)

#### Authentification (`Auth/`)
- `LoginTest.php` - 7 tests (login, logout, CSRF, redirections)
- `ForgotPasswordTest.php` - 7 tests (reset password, token validation)
- `AccountActivationTest.php` - 7 tests (activation flow)

#### Utilisateurs (`User/`)
- `UserManagementTest.php` - 8 tests (CRUD, invitations, permissions)

#### Apprenants (`Learner/`)
- `LearnerCrudTest.php` - 15 tests (CRUD, recherche, filtres)
- `ImportLearnerTest.php` - 6 tests (import Excel, templates)

#### Inscriptions (`Enrollment/`)
- `EnrollLearnerActionTest.php` - 9 tests (inscriptions, capacité, validations)
- `WithdrawLearnerActionTest.php` - 7 tests (retraits, statuts)
- `MoveLearnerActionTest.php` - 6 tests (déplacements, transactions)

#### Formations (`Formation/`)
- `FormationCrudTest.php` - 8 tests (CRUD, dates, capacité)

#### Projets (`Project/`)
- `ProjectCrudTest.php` - 7 tests (CRUD, partenaires, statuts)

#### Présences (`Attendance/`)
- `AttendanceTest.php` - 7 tests (enregistrement, PDF, récap)

#### Insertion (`Insertion/`)
- `InsertionRecordTest.php` - 5 tests (stage, emploi, suivi)

#### Communication (`Communication/`)
- `EmailTest.php` - 8 tests (emails, réponses, transferts)

#### Médiathèque (`Media/`)
- `MediaUploadTest.php` - 7 tests (upload Cloudinary, batch, suppression)

#### Configuration (`Configuration/`)
- `ConfigurationTest.php` - 10 tests (CRUD référentiels, WhatsApp)

#### Formateurs (`Trainer/`)
- `TrainerTest.php` - 7 tests (CRUD, assignations, invitations)

#### Dépenses (`Expense/`)
- `ExpenseTest.php` - 6 tests (CRUD, pièces jointes)

#### Notifications (`Notification/`)
- `NotificationTest.php` - 4 tests (marquage lu/non lu)

#### Sécurité (`Security/`)
- `SecurityTest.php` - 8 tests (XSS, SQL injection, authentification)

## Factories (`database/factories/`)
- 18 factories pour tous les modèles principaux

## Commandes d'exécution

```bash
# Tous les tests
php artisan test

# Avec couverture
php artisan test --coverage

# Tests spécifiques
php artisan test tests/Feature/Auth
php artisan test tests/Feature/Learner

# Un fichier spécifique
php artisan test tests/Feature/Auth/LoginTest.php

# En parallèle
php artisan test --parallel
```

## Configuration

Les tests utilisent :
- **SQLite in-memory** pour la base de données (rapide, isolé)
- **RefreshDatabase** pour réinitialiser la BDD à chaque test
- **Mock** pour les services externes (Cloudinary, Email)

## Nombre Total de Tests

| Type | Nombre |
|------|--------|
| Tests Unitaires | ~15 |
| Tests Fonctionnels | ~120 |
| **Total** | **~135** |
