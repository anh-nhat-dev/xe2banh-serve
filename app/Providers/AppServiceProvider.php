<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Schema;
use Menu;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        Menu::addMenuLocation('header-top-link-menu' , 'Header Top Link Navigation');
    }
}
