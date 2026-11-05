<?php

namespace App\Providers;

use App\Models\Attachment;
use App\Models\TicketMessage;
use App\Policies\AttachmentPolicy;
use App\Policies\TicketMessagePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(\App\Services\Sla\BusinessScheduleRepository::class);
        $this->app->scoped(\App\Services\Sla\SlaPolicyRepository::class);
        $this->app->bind(\App\Services\Assignment\AssignmentStrategy::class, \App\Services\Assignment\CategoryWorkloadStrategy::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(TicketMessage::class, TicketMessagePolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        JsonResource::withoutWrapping();

        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(5)->by(strtolower(trim($email)).'|'.$request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(3)->by(strtolower(trim($email)).'|'.$request->ip());
        });
    }
}
