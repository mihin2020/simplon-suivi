<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Attendance;
use App\Models\Competence;
use App\Models\CompetenceBlock;
use App\Models\EducationLevel;
use App\Models\Formation;
use App\Models\InsertionRecord;
use App\Models\Learner;
use App\Models\LearnerInterview;
use App\Models\Partner;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Referentiel;
use App\Models\Task;
use App\Models\Trainer;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Observers\AiCacheObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The Inertia DevTools endpoint publishes the props of every page viewed (learners' e-mails, phones...) to
        // anyone who can reach the server. It stays off unless INERTIA_DEVTOOLS_ENABLED is set on purpose. Done in
        // register() because the package declares its routes in its own boot(), which runs before ours.
        if (config('inertia.devtools.enabled') === null) {
            config(['inertia.devtools.enabled' => false]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        TrustProxies::at(config('security.trusted_proxies'));

        // Avoid mixed-content blanks behind Railway HTTPS: serve Vite assets as root-relative paths.
        Vite::createAssetPathsUsing(fn (string $path, ?bool $secure = null) => '/'.ltrim($path, '/'));

        if (! $this->app->environment('local')) {
            URL::forceScheme('https');
        }

        // Invalide le cache IA dès qu'une entité métier change (tous les modèles)
        foreach ([
            Learner::class, Formation::class, Project::class,
            Trainer::class, InsertionRecord::class, Partner::class,
            Referentiel::class, CompetenceBlock::class, Competence::class,
            EducationLevel::class, TrainerProfile::class, User::class,
            Attendance::class, Phase::class, Activity::class, Task::class,
            LearnerInterview::class,
        ] as $model) {
            $model::observe(AiCacheObserver::class);
        }

        // CLAUDE.md §8: password reset requests are limited to 3 per 10 minutes.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('reset-email|'.strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinutes(10, 20)->by('reset-ip|'.$request->ip()),
        ]);

        // Setting a password with a token: tokens are 64 random characters, this only stops floods.
        RateLimiter::for('password-set', fn (Request $request) => Limit::perMinutes(10, 10)->by('set-ip|'.$request->ip()));

        RateLimiter::for('profile-credentials', fn (Request $request) => Limit::perMinutes(5, 5)->by('profile|'.($request->user()->id ?? $request->ip())));

        RateLimiter::for('chatbot', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?? $request->ip())
        );

        // Many candidates share one IP (mobile carriers' NAT, cybercafés, a
        // training room filling the form together): the IP limit is wide and
        // only stops floods; the tight limit applies to one browser.
        RateLimiter::for('forms.public', function (Request $request) {
            $token = (string) $request->route('publicToken');
            $browser = $request->hasSession() ? $request->session()->getId() : $request->ip();

            return [
                Limit::perMinute(300)->by('forms-ip|'.$request->ip()),
                Limit::perMinute(5)->by('forms-browser|'.$token.'|'.$browser),
            ];
        });
    }
}
