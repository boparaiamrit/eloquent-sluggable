<?php namespace Boparaiamrit\Sluggable;


use Boparaiamrit\Sluggable\Services\SlugService;
use Illuminate\Contracts\Events\Dispatcher;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Class SluggableObserver
 *
 * @package Boparaiamrit\Sluggable
 */
class SluggableObserver
{
	
	/**
	 * @var \Boparaiamrit\Sluggable\Services\SlugService
	 */
	private $slugService;
	
	/**
	 * @var \Illuminate\Contracts\Events\Dispatcher
	 */
	private $events;
	
	/**
	 * SluggableObserver constructor.
	 *
	 * @param \Boparaiamrit\Sluggable\Services\SlugService $slugService
	 * @param \Illuminate\Contracts\Events\Dispatcher      $events
	 */
	public function __construct(SlugService $slugService, Dispatcher $events)
	{
		$this->slugService = $slugService;
		$this->events      = $events;
	}
	
	/**
	 * @param Model $Model
	 *
	 * @return boolean|null
	 */
	public function saving(Model $Model)
	{
		return $this->generateSlug($Model, 'saving');
	}
	
	/**
	 * @param Model  $Model
	 * @param string $event
	 *
	 * @return boolean|null
	 */
	protected function generateSlug(Model $Model, $event)
	{
		// If the "slugging" event returns a value, abort
		if ($this->fireSluggingEvent($Model, $event) !== null) {
			return;
		}
		
		$wasSlugged = $this->slugService->slug($Model);
		
		$this->fireSluggedEvent($Model, $wasSlugged);
	}
	
	/**
	 * Fire the namespaced validating event.
	 *
	 * @param  Model  $Model
	 * @param  string $event
	 *
	 * @return mixed
	 */
	protected function fireSluggingEvent(Model $Model, $event)
	{
		return $this->events->until('eloquent.slugging: ' . get_class($Model), [$Model, $event]);
	}
	
	/**
	 * Fire the namespaced post-validation event.
	 *
	 * @param  Model  $Model
	 * @param  string $status
	 *
	 * @return void
	 */
	protected function fireSluggedEvent(Model $Model, $status)
	{
		$this->events->fire('eloquent.slugged: ' . get_class($Model), [$Model, $status]);
	}
}
