<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\Learner\EnrollLearnerController;
use App\Http\Controllers\Learner\MoveLearnerController;
use App\Http\Controllers\Learner\WithdrawLearnerController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TrainerController;
use Illuminate\Support\Facades\Route;

// Auth routes (invités uniquement)
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [LoginController::class, 'create'])->name('login');
    Route::post('/connexion', [LoginController::class, 'store']);
});

Route::post('/deconnexion', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// Routes protégées
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Projects
    Route::resource('projects', ProjectController::class);

    // Formations (nested shallow : create/index sous le projet, show/edit/update/destroy à plat)
    Route::resource('projects.formations', FormationController::class)->shallow();

    // Learners
    Route::resource('learners', LearnerController::class);

    // Enrollment / withdrawal / move (actions sur la relation formation ↔ apprenant)
    Route::post('formations/{formation}/learners', EnrollLearnerController::class)
        ->name('formations.learners.enroll');
    Route::delete('formations/{formation}/learners/{learner}', WithdrawLearnerController::class)
        ->name('formations.learners.withdraw');
    Route::get('learners/{learner}/move', [MoveLearnerController::class, 'create'])
        ->name('learners.move.create');
    Route::post('learners/{learner}/move', [MoveLearnerController::class, 'store'])
        ->name('learners.move');

    // Trainers
    Route::resource('trainers', TrainerController::class);

    // Attendances (par formation)
    Route::get('formations/{formation}/attendances', [AttendanceController::class, 'index'])
        ->name('attendances.index');
    Route::post('formations/{formation}/attendances', [AttendanceController::class, 'store'])
        ->name('attendances.store');
});
