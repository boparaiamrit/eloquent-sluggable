<?php namespace Boparaiamrit\Sluggable;


use Illuminate\Database\Eloquent\Collection;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Class SluggableScopeHelpers
 *
 * Helper trait for defining the primary slug of a model
 * and providing useful scopes and query methods.
 *
 * @package Boparaiamrit\Sluggable
 */
trait SluggableScopeHelpers
{

    /**
     * Primary slug column of this model.
     *
     * @return string
     */
    public function getSlugKeyName()
    {
        if (property_exists($this, 'slugKeyName')) {
            return $this->slugKeyName;
        }

        $config = $this->sluggable();
        $name = reset($config);
        $key = key($config);

        // check for short configuration
        if ($key === 0) {
            return $name;
        }

        return $key;
    }

    /**
     * Primary slug value of this model.
     *
     * @return string
     */
    public function getSlugKey()
    {
        return $this->getAttribute($this->getSlugKeyName());
    }

    /**
     * Query scope for finding a model by its primary slug.
     *
     * @param \Illuminate\Database\Eloquent\Builder $scope
     * @param string                                $slug
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereSlug($scope, $slug)
    {
        return $scope->where($this->getSlugKeyName(), $slug);
    }

    /**
     * Find a model by its primary slug.
     *
     * @param string $slug
     * @param array  $columns
     *
     * @return Model|Collection|static[]|static|null
     */
    public static function findBySlug($slug, array $columns = ['*'])
    {
        return static::whereSlug($slug)->first($columns);
    }

    /**
     * Find a model by its primary slug or throw an exception.
     *
     * @param string $slug
     * @param array  $columns
     *
     * @return \Illuminate\Database\Eloquent\Model|Collection
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public static function findBySlugOrFail($slug, array $columns = ['*'])
    {
        return static::whereSlug($slug)->firstOrFail($columns);
    }
}
