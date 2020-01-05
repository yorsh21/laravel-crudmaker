<?php 
namespace Yorsh21\CrudMaker;

// use Yorsh21\CrudMaker;
use Exception;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider; 


class ServiceProvider extends IlluminateServiceProvider
{
	/**
     * Register the service provider.
     *
     * @throws \Exception
     * @return void
     */
    public function register()
    {

    }

    public function boot()
    {
        //$configPath = __DIR__.'/../config/crudmaker.php';
        //$this->publishes([$configPath => config_path('crudmaker.php')], 'config');

        $this->loadRoutesFrom(__DIR__.'/routes.php');

        /*$this->commands([
            FooCommand::class,
            BarCommand::class,
        ]);*/
    }
}
