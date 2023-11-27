<?php

namespace SystemNotifications;

use Illuminate\Support\ServiceProvider;

class SystemNotificationsServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register your service or helper class here

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */

    public function boot()
    {
        // Other boot logic...

        // Load the configuration file
        $this->mergeConfigFrom(__DIR__.'/../config/fcm.php', 'system-notifications.fcm');
        $this->publishes([
            __DIR__.'/../config/fcm.php' => config_path('system-notifications/fcm.php'),
        ], 'system-notifications-config');
    }


}
