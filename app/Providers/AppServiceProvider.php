<?php

namespace App\Providers;

use App\Models\Appointment;
use App\Models\Donor;
use App\Models\DonorAuthentication;
use App\Models\DonationRecord;
use App\Models\EligibilityStatus;
use App\Models\Location;
use App\Models\Notification;
use App\Observers\AppointmentObserver;
use App\Observers\DonorAuthenticationObserver;
use App\Observers\DonorObserver;
use App\Observers\DonationRecordObserver;
use App\Observers\EligibilityStatusObserver;
use App\Observers\LocationObserver;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Factory::class, function ($app) {
            $serviceAccount = (string) config('services.firebase.credentials', '');
            $databaseUrl = (string) config('services.firebase.database_url', '');

            $factory = new Factory();
            if (!empty($serviceAccount)) {
                $factory = $factory->withServiceAccount($serviceAccount);
            }
            if (!empty($databaseUrl)) {
                $factory = $factory->withDatabaseUri($databaseUrl);
            }

            return $factory;
        });

        $this->app->singleton('firebase.auth', function ($app) {
            return $app->make(Factory::class)->createAuth();
        });

        $this->app->singleton('firebase.database', function ($app) {
            $databaseUrl = (string) config('services.firebase.database_url', '');
            if (empty($databaseUrl)) {
                return null;
            }
            return $app->make(Factory::class)->createDatabase();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Donor::observe(DonorObserver::class);
        Location::observe(LocationObserver::class);
        Appointment::observe(AppointmentObserver::class);
        Notification::observe(NotificationObserver::class);
        DonationRecord::observe(DonationRecordObserver::class);
        EligibilityStatus::observe(EligibilityStatusObserver::class);
        DonorAuthentication::observe(DonorAuthenticationObserver::class);
    }
}
