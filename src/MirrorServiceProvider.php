<?php

namespace Luminee\Mirror;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class MirrorServiceProvider extends ServiceProvider
{
    protected $root;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->root = realpath(__DIR__ . '/../');
    }

    public function boot()
    {
        View::share('root', $this->root);
    }

    public function register()
    {
        $this->mergeConfigFrom("$this->root/config/mirror.php", 'mirror');

        if (config('mirror.enabled', false)) {
            $this->registerRoutes('web');

            $this->loadViewsFrom("$this->root/resources/views", 'mirror');
        }
    }

    protected function registerRoutes()
    {
        $prefix = config('mirror.prefix', '~') . 'mirror';

        $this->app->instance('middleware.disable', true);

        Route::group(['prefix' => $prefix, 'namespace' => 'Luminee\Mirror\Http\Controllers'], function () {
            $this->loadRoutesFrom("$this->root/routes/web.php");
        });
    }
}
