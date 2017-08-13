<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use mysqli;
use Smarty;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('mysql', function () {
            return new mysqli(env('DB_HOST'), env('DB_USERNAME'), env('DB_PASSWORD'), env('DB_DATABASE'));
        });

        $this->app->singleton('smarty', function () {
            $smarty = new Smarty();
            $smarty->template_dir = TMPL_PATH;
            $smarty->compile_dir = CACHE_PATH;
            $smarty->compile_check = true;
            $smarty->force_compile = true;
            $smarty->debugging = env('smarty_debug');

            $smarty->assign('tag', crc32(THEME));

            $smarty->assign('app_name', env('APP_NAME'));
            $smarty->assign('app_full_name', env('APP_FULL_NAME'));
            $smarty->assign('app_site', env('APP_SITE'));
            $smarty->assign('app_url', env('APP_URL'));

            return $smarty;
        });
    }
}
