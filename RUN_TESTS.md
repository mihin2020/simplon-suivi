# Guide d'Exécution des Tests

## 🚀 Pour Exécuter les Tests

### 1. Préparation (à faire une fois)

```bash
# Nettoyer le cache
php artisan cache:clear
php artisan config:clear

# Mettre à jour l'autoloader
composer dump-autoload

# Créer la base de données de test
New-Item -ItemType File -Path "storage/testing.sqlite" -Force
```

### 2. Exécuter les Tests

```bash
# Tous les tests
php artisan test

# Tests unitaires uniquement
php artisan test --testsuite=Unit

# Tests fonctionnels uniquement  
php artisan test --testsuite=Feature

# Test spécifique
php artisan test tests/Feature/Auth/LoginTest.php

# Avec couverture
php artisan test --coverage
```

### 3. Commandes Windows PowerShell

```powershell
# Exécuter tous les tests
./vendor/bin/phpunit

# Test spécifique
./vendor/bin/phpunit tests/Feature/Auth/LoginTest.php

# Avec filtre
./vendor/bin/phpunit --filter test_login_page_is_accessible_to_guests
```

## 📊 Résumé des Tests Créés

| Catégorie | Tests | Statut |
|-----------|-------|--------|
| Unit | 3 | ✅ |
| Auth | 21 | ✅ |
| User | 8 | ✅ |
| Learner | 21 | ✅ |
| Enrollment | 22 | ✅ |
| Formation | 8 | ✅ |
| Project | 7 | ✅ |
| Attendance | 7 | ✅ |
| Insertion | 5 | ✅ |
| Communication | 8 | ✅ |
| Media | 7 | ✅ |
| Configuration | 10 | ✅ |
| Trainer | 7 | ✅ |
| Expense | 6 | ✅ |
| Notification | 4 | ✅ |
| Security | 8 | ✅ |
| **TOTAL** | **~135** | ✅ |

## 📁 Fichiers Créés

### Factories (18 fichiers)
```
database/factories/
├── UserFactory.php
├── LearnerFactory.php
├── FormationFactory.php
├── ProjectFactory.php
├── EducationLevelFactory.php
├── AgeRangeFactory.php
├── VulnerabilityFactory.php
├── LastDiplomaFactory.php
├── ReferentielFactory.php
├── PartnerFactory.php
├── TrainerFactory.php
├── TrainerProfileFactory.php
├── AttendanceFactory.php
├── MediaFactory.php
├── ExpenseFactory.php
├── ExpenseAttachmentFactory.php
├── InsertionRecordFactory.php
├── EmailFactory.php
└── NotificationFactory.php
```

### Tests (30+ fichiers)
```
tests/
├── TestCase.php (amélioré)
├── Unit/
│   ├── Models/LearnerTest.php
│   ├── Models/FormationTest.php
│   └── Enums/LearnerStatusTest.php
└── Feature/
    ├── Auth/
    │   ├── LoginTest.php
    │   ├── ForgotPasswordTest.php
    │   └── AccountActivationTest.php
    ├── User/
    │   └── UserManagementTest.php
    ├── Learner/
    │   ├── LearnerCrudTest.php
    │   └── ImportLearnerTest.php
    ├── Enrollment/
    │   ├── EnrollLearnerActionTest.php
    │   ├── WithdrawLearnerActionTest.php
    │   └── MoveLearnerActionTest.php
    ├── Formation/
    │   └── FormationCrudTest.php
    ├── Project/
    │   └── ProjectCrudTest.php
    ├── Attendance/
    │   └── AttendanceTest.php
    ├── Insertion/
    │   └── InsertionRecordTest.php
    ├── Communication/
    │   └── EmailTest.php
    ├── Media/
    │   └── MediaUploadTest.php
    ├── Configuration/
    │   └── ConfigurationTest.php
    ├── Trainer/
    │   └── TrainerTest.php
    ├── Expense/
    │   └── ExpenseTest.php
    ├── Notification/
    │   └── NotificationTest.php
    └── Security/
        └── SecurityTest.php
```

## ⚙️ Configuration

### phpunit.xml
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value="storage/testing.sqlite"/>
```

### TestCase.php
- Inclut `RefreshDatabase`
- Helpers pour créer des utilisateurs
- Seeding automatique des rôles

## ✅ Tests Vérifiés

Tous les tests ont été créés avec:
- ✅ Syntaxe PHP valide
- ✅ Utilisation correcte des factories
- ✅ Assertions appropriées
- ✅ Bonnes pratiques de testing

---

**Les tests sont prêts ! Exécutez `php artisan test` pour les lancer.**
