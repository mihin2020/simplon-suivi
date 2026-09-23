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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

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

        RateLimiter::for('chatbot', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?? $request->ip())
        );

        RateLimiter::for('forms.public', function (Request $request) {
            $token = (string) $request->route('publicToken');

            return [
                Limit::perMinute(30)->by($request->ip()),
                Limit::perMinute(5)->by($token.'|'.$request->ip()),
            ];
        });
    }
}
