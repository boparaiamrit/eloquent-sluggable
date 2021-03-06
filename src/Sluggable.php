<?php namespace Boparaiamrit\Sluggable;


use Boparaiamrit\Sluggable\Services\SlugService;
use Jenssegers\Mongodb\Eloquent\Builder;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Class Sluggable
 *
 * @package Boparaiamrit\Sluggable
 */
trait Sluggable
{

    /**
     * Hook into the Eloquent model events to create or
     * update the slug as required.
     */
    public static function bootSluggable()
    {
        static::observe(app(SluggableObserver::class));
    }

    /**
     * Register a slugging model event with the dispatcher.
     *
     * @param \Closure|string $callback
     *
     * @return void
     */
    public static function slugging($callback)
    {
        static::registerModelEvent('slugging', $callback);
    }

    /**
     * Register a slugged model event with the dispatcher.
     *
     * @param \Closure|string $callback
     *
     * @return void
     */
    public static function slugged($callback)
    {
        static::registerModelEvent('slugged', $callback);
    }

    /**
     * Clone the model into a new, non-existing instance.
     *
     * @param  array|null $except
     *
     * @return Model
     */
    public function replicate(array $except = null)
    {
        $instance = parent::replicate($except);
        (new SlugService())->slug($instance, true);

        return $instance;
    }

    /**
     * Query scope for finding "similar" slugs, used to determine uniqueness.
     *
     * @param \Jenssegers\Mongodb\Eloquent\Builder $Query
     * @param \Jenssegers\Mongodb\Eloquent\Model   $Model
     * @param string                               $attribute
     * @param array                                $config
     * @param string                               $slug
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFindSimilarSlugs(Builder $Query, Model $Model, $attribute, $config, $slug)
    {
        $separator = $config['separator'];

        return $Query->where(function (Builder $q) use ($attribute, $slug, $separator) {
            $q->where($attribute, '=', $slug)
                ->orWhere($attribute, 'LIKE', $slug . $separator . '%');
        });
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    abstract public function sluggable();
}
