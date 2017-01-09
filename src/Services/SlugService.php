<?php namespace Boparaiamrit\Sluggable\Services;

use Cocur\Slugify\Slugify;
use Illuminate\Support\Collection;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Class SlugService
 *
 * @package Boparaiamrit\Sluggable\Services
 */
class SlugService
{

    /**
     * @var Model;
     */
    protected $Model;

    /**
     * Slug the current model.
     *
     * @param Model $Model
     * @param bool  $force
     *
     * @return bool
     */
    public function slug(Model $Model, $force = false)
    {
        $this->setModel($Model);

        $attributes = [];

        /** @noinspection PhpUndefinedMethodInspection */
        foreach ($this->Model->sluggable() as $attribute => $config) {
            if (is_numeric($attribute)) {
                $attribute = $config;
                $config = $this->getConfiguration();
            } else {
                $config = $this->getConfiguration($config);
            }

            $slug = $this->buildSlug($attribute, $config, $force);

            $this->Model->setAttribute($attribute, $slug);

            $attributes[] = $attribute;
        }

        return $this->Model->isDirty($attributes);
    }

    /**
     * Get the sluggable configuration for the current model,
     * including default values where not specified.
     *
     * @param array $overrides
     *
     * @return array
     */
    public function getConfiguration(array $overrides = [])
    {
        static $defaultConfig = null;
        if ($defaultConfig === null) {
            $defaultConfig = app('config')->get('sluggable');
        }

        return array_merge($defaultConfig, $overrides);
    }

    /**
     * Build the slug for the given attribute of the current model.
     *
     * @param string $attribute
     * @param array  $config
     * @param bool   $force
     *
     * @return null|string
     */
    public function buildSlug($attribute, array $config, $force = null)
    {
        $slug = $this->Model->getAttribute($attribute);

        if ($force || $this->needsSlugging($attribute, $config)) {
            $source = $this->getSlugSource($config['source']);

            if ($source) {
                $slug = $this->generateSlug($source, $config, $attribute);
                $slug = $this->validateSlug($slug, $config, $attribute);
                $slug = $this->makeSlugUnique($slug, $config, $attribute);
            }
        }

        return $slug;
    }

    /**
     * Determines whether the model needs slugging.
     *
     * @param string $attribute
     * @param array  $config
     *
     * @return bool
     */
    protected function needsSlugging($attribute, array $config)
    {
        if (
            empty($this->Model->getAttributeValue($attribute)) ||
            $config['onUpdate'] === true
        ) {
            return true;
        }

        if ($this->Model->isDirty($attribute)) {
            return false;
        }

        return (!$this->Model->exists);
    }

    /**
     * Get the source string for the slug.
     *
     * @param mixed $from
     *
     * @return string
     */
    protected function getSlugSource($from)
    {
        if (is_null($from)) {
            return $this->Model->__toString();
        }

        $sourceStrings = array_map(function ($key) {
            return data_get($this->Model, $key);
        }, (array)$from);

        return join($sourceStrings, ' ');
    }

    /**
     * Generate a slug from the given source string.
     *
     * @param string $source
     * @param array  $config
     * @param string $attribute
     *
     * @return string
     */
    protected function generateSlug($source, array $config, $attribute)
    {
        $separator = $config['separator'];
        $method = $config['method'];
        $maxLength = $config['maxLength'];

        if ($method === null) {
            $slugEngine = $this->getSlugEngine($attribute);
            $slug = $slugEngine->slugify($source, $separator);
        } elseif (is_callable($method)) {
            $slug = call_user_func($method, $source, $separator);
        } else {
            throw new \UnexpectedValueException('Sluggable "method" for ' . get_class($this->Model) . ':' . $attribute . ' is not callable nor null.');
        }

        if (is_string($slug) && $maxLength) {
            $slug = mb_substr($slug, 0, $maxLength);
        }

        return $slug;
    }

    /**
     * Return a class that has a `slugify()` method, used to convert
     * strings into slugs.
     *
     * @param string $attribute
     *
     * @return Slugify
     */
    protected function getSlugEngine($attribute)
    {
        static $slugEngines = [];

        $key = get_class($this->Model) . '.' . $attribute;

        if (!array_key_exists($key, $slugEngines)) {
            $engine = new Slugify();
            if (method_exists($this->Model, 'customizeSlugEngine')) {
                $engine = $this->Model->customizeSlugEngine($engine, $attribute);
            }

            $slugEngines[$key] = $engine;
        }

        return $slugEngines[$key];
    }

    /**
     * Checks that the given slug is not a reserved word.
     *
     * @param string $slug
     * @param array  $config
     * @param string $attribute
     *
     * @return string
     */
    protected function validateSlug($slug, array $config, $attribute)
    {
        $separator = $config['separator'];
        $reserved = $config['reserved'];

        if ($reserved === null) {
            return $slug;
        }

        // check for reserved names
        if ($reserved instanceof \Closure) {
            $reserved = $reserved($this->Model);
        }

        if (is_array($reserved)) {
            if (in_array($slug, $reserved)) {
                return $slug . $separator . '1';
            }

            return $slug;
        }

        throw new \UnexpectedValueException('Sluggable "reserved" for ' . get_class($this->Model) . ':' . $attribute . ' is not null, an array, or a closure that returns null/array.');
    }

    /**
     * Checks if the slug should be unique, and makes it so if needed.
     *
     * @param string $slug
     * @param array  $config
     * @param string $attribute
     *
     * @return string
     */
    protected function makeSlugUnique($slug, array $config, $attribute)
    {
        if (!$config['unique']) {
            return $slug;
        }

        $separator = $config['separator'];

        // find all models where the slug is like the current one
        $list = $this->getExistingSlugs($slug, $attribute, $config);

        // if ...
        // 	a) the list is empty, or
        // 	b) our slug isn't in the list
        // ... we are okay
        if (
            $list->count() === 0 ||
            $list->contains($slug) === false
        ) {
            return $slug;
        }

        // if our slug is in the list, but
        // 	a) it's for our model, or
        //  b) it looks like a suffixed version of our slug
        // ... we are also okay (use the current slug)
        if ($list->has($this->Model->getKey())) {
            $currentSlug = $list->get($this->Model->getKey());

            if (
                $currentSlug === $slug ||
                strpos($currentSlug, $slug) === 0
            ) {
                return $currentSlug;
            }
        }

        $method = $config['uniqueSuffix'];
        if ($method === null) {
            $suffix = $this->generateSuffix($slug, $separator, $list);
        } elseif (is_callable($method)) {
            $suffix = call_user_func($method, $slug, $separator, $list);
        } else {
            throw new \UnexpectedValueException('Sluggable "reserved" for ' . get_class($this->Model) . ':' . $attribute . ' is not null, an array, or a closure that returns null/array.');
        }

        return $slug . $separator . $suffix;
    }

    /**
     * Generate a unique suffix for the given slug (and list of existing, "similar" slugs.
     *
     * @param string                         $slug
     * @param string                         $separator
     * @param \Illuminate\Support\Collection $list
     *
     * @return string
     */
    protected function generateSuffix($slug, $separator, Collection $list)
    {
        $len = strlen($slug . $separator);

        // If the slug already exists, but belongs to
        // our model, return the current suffix.
        if ($list->search($slug) === $this->Model->getKey()) {
            $suffix = explode($separator, $slug);

            return end($suffix);
        }

        /** @noinspection PhpUnusedParameterInspection */
        $list->transform(function ($value, $key) use ($len) {
            return intval(substr($value, $len));
        });

        // find the highest value and return one greater.
        return $list->max() + 1;
    }

    /**
     * Get all existing slugs that are similar to the given slug.
     *
     * @param string $slug
     * @param string $attribute
     * @param array  $config
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getExistingSlugs($slug, $attribute, array $config)
    {
        $includeTrashed = $config['includeTrashed'];

        /** @noinspection PhpUndefinedMethodInspection */
        $query = $this->Model->newQuery()
            ->findSimilarSlugs($this->Model, $attribute, $config, $slug);

        // use the model scope to find similar slugs
        if (method_exists($this->Model, 'scopeWithUniqueSlugConstraints')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $query->withUniqueSlugConstraints($this->Model, $attribute, $config, $slug);
        }

        // include trashed models if required
        if ($includeTrashed && $this->usesSoftDeleting()) {
            /** @noinspection PhpUndefinedMethodInspection */
            $query->withTrashed();
        }

        // get the list of all matching slugs
        /** @noinspection PhpUndefinedMethodInspection */
        $results = $query->select([$attribute, $this->Model->getTable() . '.' . $this->Model->getKeyName()])
            ->get()
            ->toBase();

        // key the results and return
        /** @noinspection PhpUndefinedMethodInspection */
        return $results->pluck($attribute, $this->Model->getKeyName());
    }

    /**
     * Does this model use softDeleting?
     *
     * @return bool
     */
    protected function usesSoftDeleting()
    {
        return method_exists($this->Model, 'bootSoftDeletes');
    }

    /**
     * Generate a unique slug for a given string.
     *
     * @param Model|string $Model
     * @param string       $attribute
     * @param string       $fromString
     * @param array        $config
     *
     * @return string
     */
    public static function createSlug($Model, $attribute, $fromString, array $config = null)
    {
        if (is_string($Model)) {
            $Model = new $Model;
        }
        $instance = (new self())->setModel($Model);

        if ($config === null) {
            /** @noinspection PhpUndefinedMethodInspection */
            $config = array_get($Model->sluggable(), $attribute);
        } elseif (!is_array($config)) {
            throw new \UnexpectedValueException('SlugService::createSlug expects an array or null as the fourth argument; ' . gettype($config) . ' given.');
        }

        $config = $instance->getConfiguration($config);

        $slug = $instance->generateSlug($fromString, $config, $attribute);
        $slug = $instance->validateSlug($slug, $config, $attribute);
        $slug = $instance->makeSlugUnique($slug, $config, $attribute);

        return $slug;
    }

    /**
     * @param Model $Model
     *
     * @return $this
     */
    public function setModel(Model $Model)
    {
        $this->Model = $Model;

        return $this;
    }
}
