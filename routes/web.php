<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FormationTrainerController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormationController;
use App\Http\Controllers\Learner\EnrollLearnerController;
use App\Http\Controllers\Learner\ImportLearnerController;
use App\Http\Controllers\Learner\MoveLearnerController;
use App\Http\Controllers\Learner\WithdrawLearnerController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\EducationLevelController;
use App\Http\Controllers\AgeRangeController;
use App\Http\Controllers\PresenceRedirectController;
use App\Http\Controllers\TrainerProfileController;
use App\Http\Controllers\ReferentielController;
use App\Http\Controllers\InsertionRecordController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Auth routes (invités uniquement)
Route::middleware("guest")->group(function () {
    Route::get("/connexion", [LoginController::class, "create"])->name("login");
    Route::post("/connexion", [LoginController::class, "store"]);
});

// Activation de compte — accessible sans être connecté (lien email)
Route::get("/activation/{token}", [ActivationController::class, "show"])->name(
    "activation.show",
);
Route::post("/activation/{token}", [
    ActivationController::class,
    "store",
])->name("activation.store");

// Mot de passe oublié
Route::get("/mot-de-passe-oublie", [
    ForgotPasswordController::class,
    "create",
])->name("password.request");
Route::post("/mot-de-passe-oublie", [
    ForgotPasswordController::class,
    "store",
])->name("password.email");

// Réinitialisation du mot de passe
Route::get("/reinitialisation/{token}", [
    ResetPasswordController::class,
    "show",
])->name("password.reset");
Route::post("/reinitialisation/{token}", [
    ResetPasswordController::class,
    "store",
])->name("password.update");

Route::post("/deconnexion", [LoginController::class, "destroy"])
    ->name("logout")
    ->middleware("auth");

// Routes protégées
Route::middleware("auth")->group(function () {
    Route::get("/", [DashboardController::class, "index"])->name("dashboard");

    // Profil utilisateur
    Route::get("/profil", [ProfileController::class, "show"])->name(
        "profile.show",
    );
    Route::put("/profil/informations", [
        ProfileController::class,
        "updateInfo",
    ])->name("profile.info");
    Route::put("/profil/mot-de-passe", [
        ProfileController::class,
        "updatePassword",
    ])->name("profile.password");

    // Users (gestion des comptes — Super Admin)
    Route::resource("users", UserController::class)->except(["show"]);
    Route::patch("users/{user}/toggle-active", [
        UserController::class,
        "toggleActive",
    ])->name("users.toggle-active");

    // Projects
    Route::resource("projects", ProjectController::class);

    // Partners (configuration)
    Route::resource("partners", PartnerController::class)->except(["show"]);

    // Formations (nested shallow : create/index sous le projet, show/edit/update/destroy à plat)
    Route::resource(
        "projects.formations",
        FormationController::class,
    )->shallow();

    // Import Excel — must be declared BEFORE Route::resource('learners') to avoid
    // the {learner} wildcard swallowing /learners/import and /learners/import/template
    Route::get("learners/import", [
        ImportLearnerController::class,
        "create",
    ])->name("learners.import");
    Route::post("learners/import", [
        ImportLearnerController::class,
        "store",
    ])->name("learners.import.store");
    Route::get("learners/import/template", [
        ImportLearnerController::class,
        "template",
    ])->name("learners.import.template");

    // Learners
    Route::resource("learners", LearnerController::class);

    // Insertion records (Stage et Emploi)
    Route::get("learners/{learner}/insertion", [
        InsertionRecordController::class,
        "index",
    ])->name("learners.insertion.index");
    Route::post("learners/{learner}/insertion", [
        InsertionRecordController::class,
        "store",
    ])->name("learners.insertion.store");
    Route::put("learners/{learner}/insertion/{record}", [
        InsertionRecordController::class,
        "update",
    ])->name("learners.insertion.update");
    Route::delete("learners/{learner}/insertion/{record}", [
        InsertionRecordController::class,
        "destroy",
    ])->name("learners.insertion.destroy");

    // Enrollment / withdrawal / move
    Route::get("formations/{formation}/learners/enroll", [
        EnrollLearnerController::class,
        "create",
    ])->name("formations.learners.enroll.create");
    Route::post("formations/{formation}/learners", [
        EnrollLearnerController::class,
        "store",
    ])->name("formations.learners.enroll");
    Route::get("formations/{formation}/learners/new", [
        EnrollLearnerController::class,
        "createLearner",
    ])->name("formations.learners.new");
    Route::post("formations/{formation}/learners/new", [
        EnrollLearnerController::class,
        "storeLearner",
    ])->name("formations.learners.store-new");
    Route::delete(
        "formations/{formation}/learners/{learner}",
        WithdrawLearnerController::class,
    )->name("formations.learners.withdraw");
    Route::post("formations/{formation}/learners/{learner}/abandon", [
        WithdrawLearnerController::class,
        "abandon",
    ])->name("formations.learners.abandon");
    Route::get("learners/{learner}/move", [
        MoveLearnerController::class,
        "create",
    ])->name("learners.move.create");
    Route::post("learners/{learner}/move", [
        MoveLearnerController::class,
        "store",
    ])->name("learners.move");

    // Référentiels (gestion globale)
    Route::resource("referentiels", ReferentielController::class)->except([
        "edit",
    ]);

    // Trainers
    Route::resource("trainers", TrainerController::class);
    Route::post("trainers/{trainer}/assign-formation", [
        TrainerController::class,
        "assignFormation",
    ])->name("trainers.assign-formation");
    Route::delete("trainers/{trainer}/unassign-formation/{formation}", [
        TrainerController::class,
        "unassignFormation",
    ])->name("trainers.unassign-formation");
    // Présences — redirection selon le rôle
    Route::get("presences", PresenceRedirectController::class)->name(
        "presences",
    );

    // Configuration hub
    Route::get("configuration", [
        ConfigurationController::class,
        "index",
    ])->name("configuration");

    Route::resource(
        "trainer-profiles",
        TrainerProfileController::class,
    )->except(["create", "edit", "show"]);

    Route::resource(
        "education-levels",
        EducationLevelController::class,
    )->except(["create", "edit", "show"]);

    Route::resource(
        "age-ranges",
        AgeRangeController::class,
    )->except(["create", "edit", "show"]);

    // Statistics
    Route::get("statistics", [StatisticsController::class, "index"])->name(
        "statistics.index",
    );
    Route::get("api/statistics/formation/{formation}/learners", [
        StatisticsController::class,
        "learners",
    ])->name("api.statistics.learners");

    // API pour récupérer les formations d'un projet (JSON)
    Route::get("api/projects/{project}/formations", [
        ProjectController::class,
        "formationsJson",
    ])->name("api.projects.formations");

    // API recherche apprenants pour emails
    Route::get("api/learners/search", [
        LearnerController::class,
        "search",
    ])->name("api.learners.search");

    // ───────────────────────────────────────────
    // MÉDIATHÈQUE (Cloudinary)
    // ───────────────────────────────────────────
    // Page principale médiathèque d'une formation
    Route::get("formations/{formation}/medias", [
        MediaController::class,
        "index",
    ])->name("formations.medias.index");

    // Upload d'un média (Cloudinary)
    Route::post("formations/{formation}/medias", [
        MediaController::class,
        "store",
    ])->name("formations.medias.store");

    // Mise à jour d'un média
    Route::put("formations/{formation}/medias/{media}", [
        MediaController::class,
        "update",
    ])->name("formations.medias.update");

    // Mise à jour en masse des médias (album)
    Route::post("formations/{formation}/medias/batch-update", [
        MediaController::class,
        "batchUpdate",
    ])->name("formations.medias.batch-update");

    // Suppression d'un média
    Route::delete("formations/{formation}/medias/{media}", [
        MediaController::class,
        "destroy",
    ])->name("formations.medias.destroy");

    // Téléchargement d'un média
    Route::get("formations/{formation}/medias/{media}/download", [
        MediaController::class,
        "download",
    ])->name("formations.medias.download");

    // Téléchargement ZIP d'un album
    Route::get("formations/{formation}/medias/album/download", [
        MediaController::class,
        "downloadAlbum",
    ])->name("formations.medias.album.download");

    // Liens externes de la médiathèque
    Route::post("formations/{formation}/links", [
        \App\Http\Controllers\FormationLinkController::class,
        "store",
    ])->name("formations.links.store");
    Route::put("formations/{formation}/links/{link}", [
        \App\Http\Controllers\FormationLinkController::class,
        "update",
    ])->name("formations.links.update");
    Route::delete("formations/{formation}/links/{link}", [
        \App\Http\Controllers\FormationLinkController::class,
        "destroy",
    ])->name("formations.links.destroy");

    // Stats Cloudinary (jauge)
    Route::get("api/cloudinary/stats", [
        MediaController::class,
        "cloudinaryStats",
    ])->name("api.cloudinary.stats");

    // Formateurs d'une formation (assignation/désassignation)
    Route::post("formations/{formation}/trainers", [
        FormationTrainerController::class,
        "store",
    ])->name("formations.trainers.store");
    Route::delete("formations/{formation}/trainers/{trainer}", [
        FormationTrainerController::class,
        "destroy",
    ])->name("formations.trainers.destroy");

    // Attendances (par formation)
    Route::get("formations/{formation}/attendances", [
        AttendanceController::class,
        "index",
    ])->name("attendances.index");
    Route::post("formations/{formation}/attendances", [
        AttendanceController::class,
        "store",
    ])->name("attendances.store");
    Route::post("formations/{formation}/attendances/single", [
        AttendanceController::class,
        "storeSingle",
    ])->name("attendances.store-single");
    Route::get("formations/{formation}/attendances/recap", [
        AttendanceController::class,
        "recap",
    ])->name("attendances.recap");
    Route::get("formations/{formation}/attendances/pdf", [
        AttendanceController::class,
        "pdf",
    ])->name("attendances.pdf");
    Route::get("formations/{formation}/attendances/pdf-recap", [
        AttendanceController::class,
        "pdfRecap",
    ])->name("attendances.pdf-recap");

    // Communication - Emails & WhatsApp
    Route::prefix("communication")->group(function () {
        Route::get("/emails", [EmailController::class, "index"])->name(
            "emails.index",
        );
        Route::get("/emails/sent", [EmailController::class, "sent"])->name(
            "emails.sent",
        );
        Route::get("/emails/compose", [
            EmailController::class,
            "compose",
        ])->name("emails.compose");
        Route::post("/emails", [EmailController::class, "store"])->name(
            "emails.store",
        );
        Route::get("/emails/sent/{email}", [
            EmailController::class,
            "showSent",
        ])->name("emails.sent.show");
        Route::get("/emails/thread/{threadId}", [
            EmailController::class,
            "show",
        ])->name("emails.show");
        Route::post("/emails/{email}/reply", [
            EmailController::class,
            "reply",
        ])->name("emails.reply");
        Route::post("/emails/{email}/forward", [
            EmailController::class,
            "forward",
        ])->name("emails.forward");
        Route::post("/emails/sync", [EmailController::class, "sync"])->name(
            "emails.sync",
        );
        Route::patch("/emails/{email}/archive", [
            EmailController::class,
            "archive",
        ])->name("emails.archive");
        Route::patch("/emails/{email}/unarchive", [
            EmailController::class,
            "unarchive",
        ])->name("emails.unarchive");
        Route::delete("/emails/{email}", [
            EmailController::class,
            "destroy",
        ])->name("emails.destroy");
        // WhatsApp
        Route::get("/whatsapp", [EmailController::class, "whatsapp"])->name(
            "whatsapp.index",
        );
        Route::post("/whatsapp/send", [
            EmailController::class,
            "sendWhatsAppBulk",
        ])->name("whatsapp.send");
    });

    // Notifications
    Route::get("/notifications", [
        NotificationController::class,
        "index",
    ])->name("notifications.index");
    Route::get("/notifications/unread-count", [
        NotificationController::class,
        "unreadCount",
    ])->name("notifications.unread-count");
    Route::patch("/notifications/{notification}/read", [
        NotificationController::class,
        "markAsRead",
    ])->name("notifications.mark-read");
    Route::post("/notifications/mark-all-read", [
        NotificationController::class,
        "markAllAsRead",
    ])->name("notifications.mark-all-read");

    // Assistant IA
    Route::middleware('throttle:chatbot')->group(function () {
        Route::post('/chatbot/message', [AiChatController::class, 'message'])->name('chatbot.message');
        Route::get('/chatbot/status', [AiChatController::class, 'status'])->name('chatbot.status');
    });
    Route::post('/configuration/ai-key', [AiChatController::class, 'saveApiKey'])->name('configuration.ai-key');
});
