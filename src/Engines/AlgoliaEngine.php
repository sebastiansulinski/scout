<?php

namespace Laravel\Scout\Engines;

use Laravel\Scout\Builder;
use AlgoliaSearch\Client as Algolia;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;

class AlgoliaEngine extends Engine
{
    /**
     * The Algolia client.
     *
     * @var \AlgoliaSearch\Client
     */
    protected $algolia;

    /**
     * Record chunk key.
     *
     * @var string
     */
    private $recordChunkLink;

    /**
     * Create a new engine instance.
     *
     * @param  \AlgoliaSearch\Client  $algolia
     * @param  string  $recordChunkLink
     */
    public function __construct(Algolia $algolia, string $recordChunkLink = '_scout_chunk-')
    {
        $this->algolia = $algolia;

        $this->recordChunkLink = $recordChunkLink;
    }

    /**
     * Get chunk object id.
     *
     * @param  mixed  $key
     * @param  int|null  $index
     * @return mixed
     */
    public function chunkObjectId($key, int $index = null)
    {
        return is_null($index) ? $key : $key.$this->recordChunkLink.$index;
    }

    /**
     * Split array to chunks.
     *
     * @param  array  $array
     * @param  string  $key
     * @param  int  $limit
     * @return array
     */
    public function splitToChunks(array $array, string $key, int $limit)
    {
        if (strlen($array[$key]) <= $limit) {
            return $array;
        }

        $chunks = explode(
            PHP_EOL,
            wordwrap(str_replace(PHP_EOL, '', $array[$key]), $limit, PHP_EOL)
        );

        return array_map(function($chunk) use ($array, $key) {
            return array_merge($array, [$key => $chunk]);
        }, $chunks);
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @throws \AlgoliaSearch\AlgoliaException
     * @return void
     */
    public function update($models)
    {
        if ($models->isEmpty()) {
            return;
        }

        $index = $this->algolia->initIndex($models->first()->searchableAs());

        if ($this->usesSoftDelete($models->first()) && config('scout.soft_delete', false)) {
            $models->each->pushSoftDeleteMetadata();
        }

        $objects = $models->reduce([$this, 'addToObjectsCollection'], new Collection);

        $index->addObjects($objects->filter()->values()->all());
    }

    /**
     * Add items to collection of objects.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $objects
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function addToObjectsCollection(Collection $objects, $model)
    {
        if (array_keys($array = $model->toSearchableArray()) !== range(0, count($array) - 1)) {
            $objects[] = $this->mapObject($array, $model);
            return $objects;
        }

        foreach($array as $key => $subArray) {
            $objects[] = $this->mapObject($subArray, $model, $key+1);
        }

        return $objects;
    }

    /**
     * Map object.
     *
     * @param  array $array
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  int $appendToKey
     * @return array
     */
    public function mapObject(array $array, $model, int $appendToKey = null)
    {
        $array = array_merge($array, $model->scoutMetadata());

        if (empty($array)) {
            return;
        }

        return array_merge([
            'recordID' => $key = $model->getScoutKey(),
            'objectID' => $this->chunkObjectId($key, $appendToKey),
        ], $array);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function delete($models)
    {
        $index = $this->algolia->initIndex($models->first()->searchableAs());

        $objects = $models->reduce([$this, 'addToObjectsCollection'], new Collection)
            ->pluck('objectID')->values()->all();

        $index->deleteObjects($objects);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function search(Builder $builder)
    {
        return $this->performSearch($builder, array_filter([
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $builder->limit,
        ]));
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        return $this->performSearch($builder, [
            'numericFilters' => $this->filters($builder),
            'hitsPerPage' => $perPage,
            'page' => $page - 1,
        ]);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  array  $options
     * @return mixed
     * @throws \AlgoliaSearch\AlgoliaException
     */
    protected function performSearch(Builder $builder, array $options = [])
    {
        $algolia = $this->algolia->initIndex(
            $builder->index ?: $builder->model->searchableAs()
        );

        if ($builder->callback) {
            return call_user_func(
                $builder->callback,
                $algolia,
                $builder->query,
                $options
            );
        }

        return $algolia->search($builder->query, $options);
    }

    /**
     * Get the filter array for the query.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return array
     */
    protected function filters(Builder $builder)
    {
        return collect($builder->wheres)->map(function ($value, $key) {
            return $key.'='.$value;
        })->values()->all();
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results)
    {
        return collect($results['hits'])->pluck('objectID')->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map($results, $model)
    {
        if (count($results['hits']) === 0) {
            return Collection::make();
        }

        $builder = in_array(SoftDeletes::class, class_uses_recursive($model))
                    ? $model->withTrashed() : $model->newQuery();

        $models = $builder->whereIn(
            $model->getQualifiedKeyName(),
            $hits = $this->extractObjectIds(collect($results['hits']))
        )->get()->keyBy($model->getKeyName());

        return Collection::make($hits)->map(function ($hit) use ($models) {
            if (isset($models[$hit])) {
                return $models[$hit];
            }
        })->filter()->values();
    }

    /**
     * Extract object ids from the collection of hits.
     *
     * @param  \Illuminate\Support\Collection  $hits
     * @return array
     */
    public function extractObjectIds(SupportCollection $hits)
    {
        return $hits->pluck('objectID')->values()->map(function($id) {
            if (is_string($id) && $length = strrpos($id, $this->recordChunkLink)) {
                return substr($id, 0, $length);
            }
            return $id;
        })->unique()->all();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results)
    {
        return $results['nbHits'];
    }

    /**
     * Determine if the given model uses soft deletes.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return bool
     */
    protected function usesSoftDelete($model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }
}
