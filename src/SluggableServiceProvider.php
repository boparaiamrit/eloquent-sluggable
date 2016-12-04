<?php namespace Boparaiamrit\Sluggable;


use Boparaiamrit\Sluggable\Services\SlugService;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Class ServiceProvider
 *
 * @package Boparaiamrit\Sluggable
 */
class SluggableServiceProvider extends BaseServiceProvider
{
	
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;
	
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__ . '/../config/sluggable.php' => config_path('sluggable.php'),
		], 'config');
	}
	
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->mergeConfigFrom(__DIR__ . '/../config/sluggable.php', 'sluggable');
		
		$this->app->singleton(SluggableObserver::class, function ($app) {
			return new SluggableObserver(new SlugService(), $app['events']);
		});
	}
}
