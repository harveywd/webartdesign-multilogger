<?php

namespace Webartdesign\Multilogger;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class MultiloggerServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
		$this->publishes([
			__DIR__.'/../config/multilogger.php' => config_path('multilogger.php'),
		]);
		$this->loadMigrationsFrom(__DIR__.'/../migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
		$this->mergeConfigFrom(__DIR__.'/../config/multilogger.php', 'multilogger');

        $this->app->bind('multilogger', function (Container $app) {
        	return new Multilogger($app['config'], $app['flysystem']);
		});
    }
}
