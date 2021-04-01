<?php

namespace Botble\BaoKim\Providers;

use Botble\Base\Supports\Helper;
use Botble\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\ServiceProvider;
use Botble\BaoKim\BaoKimAPI;

class BaoKimServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register()
    {
        $this->app->singleton(BaoKimAPI::class, function () {
            return new BaoKimAPI;
        });

        Helper::autoload(__DIR__ . '/../../helpers');
    }

    /**
     * @throws FileNotFoundException
     */
    public function boot()
    {
        if (is_plugin_active('payment')) {
            $this->setNamespace('plugins/baokim')
                // ->loadRoutes(['web'])
                ->loadAndPublishConfigurations([
                    'baokim'
                ])
                ->loadAndPublishViews()

                ->publishAssets();

            $this->app->register(HookServiceProvider::class);

            $this->app->make('config')->set([
                'plugins.baokim.baokim.key'    => get_payment_setting('api_key', BAOKIM_PAYMENT_METHOD_NAME),
                'plugins.baokim.baokim.secret' => get_payment_setting('secret', BAOKIM_PAYMENT_METHOD_NAME),
            ]);
        }
    }
}