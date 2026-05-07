<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FormationTrainerController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\Learner\EnrollLearnerController;
use App\Http\Controllers\Learner\ImportLearnerController;
use App\Http\Controllers\Learner\MoveLearnerController;
use App\Http\Controllers\Learner\WithdrawLearnerController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\PresenceRedirectController;
use App\Http\Controllers\TrainerProfileController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth routes (invités uniquement)
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store']);
});

// Activation de compte — accessible sans être connecté (lien email)
Route::get('/activation/{token}', [ActivationController::class, 'show'])->name('activation.show');
Route::post('/activation/{token}', [ActivationController::class, 'store'])->name('activation.store');

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// Routes protégées
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Users (gestion des comptes — Super Admin)
    Route::resource('users', UserController::class)->except(['show']);
    Route::patch('users/{user}/toggle-active', [UserController::class, 'toggleActive'])
        ->name('users.toggle-active');

    // Projects
    Route::resource('projects', ProjectController::class);

    // Partners (configuration)
    Route::resource('partners', PartnerController::class)->except(['show']);

    // Formations (nested shallow : create/index sous le projet, show/edit/update/destroy à plat)
    Route::resource('projects.formations', FormationController::class)->shallow();

    // Import Excel — must be declared BEFORE Route::resource('learners') to avoid
    // the {learner} wildcard swallowing /learners/import and /learners/import/template
    Route::get('learners/import', [ImportLearnerController::class, 'create'])->name('learners.import');
    Route::post('learners/import', [ImportLearnerController::class, 'store'])->name('learners.import.store');
    Route::get('learners/import/template', [ImportLearnerController::class, 'template'])->name('learners.import.template');

    // Learners
    Route::resource('learners', LearnerController::class);

    // Enrollment / withdrawal / move
    Route::get('formations/{formation}/learners/enroll', [EnrollLearnerController::class, 'create'])
        ->name('formations.learners.enroll.create');
    Route::post('formations/{formation}/learners', [EnrollLearnerController::class, 'store'])
        ->name('formations.learners.enroll');
    Route::get('formations/{formation}/learners/new', [EnrollLearnerController::class, 'createLearner'])
        ->name('formations.learners.new');
    Route::post('formations/{formation}/learners/new', [EnrollLearnerController::class, 'storeLearner'])
        ->name('formations.learners.store-new');
    Route::delete('formations/{formation}/learners/{learner}', WithdrawLearnerController::class)
        ->name('formations.learners.withdraw');
    Route::get('learners/{learner}/move', [MoveLearnerController::class, 'create'])
        ->name('learners.move.create');
    Route::post('learners/{learner}/move', [MoveLearnerController::class, 'store'])
        ->name('learners.move');

    // Référentiels (gestion globale)
    Route::resource('referentiels', ReferentielController::class)->except(['edit']);

    // Trainers
    Route::resource('trainers', TrainerController::class);
    Route::post('trainers/{trainer}/assign-formation', [TrainerController::class, 'assignFormation'])
        ->name('trainers.assign-formation');
    Route::delete('trainers/{trainer}/unassign-formation/{formation}', [TrainerController::class, 'unassignFormation'])
        ->name('trainers.unassign-formation');
    // Présences — redirection selon le rôle
    Route::get('presences', PresenceRedirectController::class)->name('presences');

    // Configuration hub
    Route::get('configuration', [ConfigurationController::class, 'index'])->name('configuration');

    Route::resource('trainer-profiles', TrainerProfileController::class)
        ->except(['create', 'edit', 'show']);

    // API pour récupérer les formations d'un projet (JSON)
    Route::get('api/projects/{project}/formations', [ProjectController::class, 'formationsJson'])
        ->name('api.projects.formations');

    // Formateurs d'une formation (assignation/désassignation)
    Route::post('formations/{formation}/trainers', [FormationTrainerController::class, 'store'])
        ->name('formations.trainers.store');
    Route::delete('formations/{formation}/trainers/{trainer}', [FormationTrainerController::class, 'destroy'])
        ->name('formations.trainers.destroy');

    // Attendances (par formation)
    Route::get('formations/{formation}/attendances', [AttendanceController::class, 'index'])
        ->name('attendances.index');
    Route::post('formations/{formation}/attendances', [AttendanceController::class, 'store'])
        ->name('attendances.store');
});
