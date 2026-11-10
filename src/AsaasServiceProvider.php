<?php

namespace Asaas\Laravel;

use Illuminate\Support\ServiceProvider;

class AsaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/asaas.php', 'asaas');

        $this->app->singleton(AsaasSdk::class, function ($app) {
            $config = $app['config']->get('asaas', []);
            return AsaasSdk::fromConfig($config);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/asaas.php' => $this->app->configPath('asaas.php'),
            ], 'asaas-config');
        }
    }
}
