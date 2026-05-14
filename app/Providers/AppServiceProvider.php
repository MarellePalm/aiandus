<?php

namespace App\Providers;

use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\Category;
use App\Models\GardenObject;
use App\Models\GardenPlan;
use App\Models\Plant;
use App\Models\Seed;
use App\Policies\BedPolicy;
use App\Policies\CalendarNotePolicy;
use App\Policies\CategoryPolicy;
use App\Policies\GardenObjectPolicy;
use App\Policies\GardenPlanPolicy;
use App\Policies\PlantPolicy;
use App\Policies\SeedPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::policy(Plant::class, PlantPolicy::class);
        Gate::policy(Bed::class, BedPolicy::class);
        Gate::policy(CalendarNote::class, CalendarNotePolicy::class);
        Gate::policy(Seed::class, SeedPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(GardenPlan::class, GardenPlanPolicy::class);
        Gate::policy(GardenObject::class, GardenObjectPolicy::class);

        $this->configureDefaults();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
