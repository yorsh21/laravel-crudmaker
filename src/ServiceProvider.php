<?php 

namespace Yorsh\CrudMaker;

// use Yorsh21\CrudMaker;
use Exception;
use Yorsh\CrudMaker\CrudCommand;
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

        $this->loadRoutesFrom(__DIR__.'\\..\\routes\\routes.php');

        $this->commands([
            CrudCommand::class,
        ]);
    }
}
