<?php

namespace Tests;

use Mockery;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\AlgoliaEngine;
use Tests\Fixtures\AlgoliaEngineTestModel;
use Illuminate\Database\Eloquent\Collection;
use Tests\Fixtures\AlgoliaEngineTestCustomKeyModel;
use Tests\Fixtures\AlgoliaEngineTestChunkedSearchableArrayModel;
use Tests\Fixtures\AlgoliaEngineTestChunkedSearchableArrayCustomKeyModel;
use Tests\Fixtures\AlgoliaEngineTestChunkedSearchableArrayWithObjectIdModel;

class AlgoliaEngineTest extends AbstractTestCase
{
    public function test_concatenates_scout_key_with_record_chunk_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $engine = new AlgoliaEngine($client, '_test_link-');

        $this->assertEquals('1_test_link-1', $engine->chunkObjectId(1, 1));
        $this->assertEquals('custom.key.1_test_link-1', $engine->chunkObjectId('custom.key.1', 1));
    }

    public function test_splits_array_to_chunks()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $engine = new AlgoliaEngine($client);

        $body  = 'One morning, when Gregor Samsa '.PHP_EOL;
        $body .= 'woke from troubled dreams, he '.PHP_EOL;
        $body .= 'found himself transformed in his bed into a horrible vermin.';

        $array = [
            'id' => 1,
            'name' => 'Name',
            'body' => $body,
        ];

        $this->assertEquals($array, $engine->splitToChunks($array, 'body', 200));
        $this->assertEquals([
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'One morning, when'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'Gregor Samsa woke'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'from troubled'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'dreams, he found'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'himself transformed'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'in his bed into a'
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'horrible vermin.'
            ]
        ], $engine->splitToChunks($array, 'body', 20));
    }

    public function test_update_adds_objects_to_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([[
            'id' => 1,
            'objectID' => 1,
        ]]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestModel]));
    }

    public function test_update_adds_chunked_objects_to_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([
            [
                'id' => 1,
                'objectID' => '1_scout_chunk-1',
                'name' => 'Name',
                'body' => 'Body chunk 1',
            ],
            [
                'id' => 1,
                'objectID' => '1_scout_chunk-2',
                'name' => 'Name',
                'body' => 'Body chunk 2',
            ],
            [
                'id' => 1,
                'objectID' => '1_scout_chunk-3',
                'name' => 'Name',
                'body' => 'Body chunk 3',
            ],
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayModel]));
    }

    public function test_update_adds_chunked_objects_with_custom_key_to_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-1',
                'name' => 'Name',
                'body' => 'Body chunk 1',
            ],
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-2',
                'name' => 'Name',
                'body' => 'Body chunk 2',
            ],
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-3',
                'name' => 'Name',
                'body' => 'Body chunk 3',
            ],
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayCustomKeyModel]));
    }

    public function test_update_adds_chunked_objects_with_object_id_to_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([
            [
                'id' => 1,
                'objectID' => '1-1',
                'name' => 'Name',
                'body' => 'Body chunk 1',
            ],
            [
                'id' => 1,
                'objectID' => '1-2',
                'name' => 'Name',
                'body' => 'Body chunk 2',
            ],
            [
                'id' => 1,
                'objectID' => '1-3',
                'name' => 'Name',
                'body' => 'Body chunk 3',
            ],
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayWithObjectIdModel]));
    }

    public function test_delete_removes_objects_to_index()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('deleteObjects')->with([1]);

        $engine = new AlgoliaEngine($client);
        $engine->delete(Collection::make([new AlgoliaEngineTestModel]));
    }

    public function test_search_sends_correct_parameters_to_algolia()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('search')->with('zonda', [
            'numericFilters' => ['foo=1'],
        ]);

        $engine = new AlgoliaEngine($client);
        $builder = new Builder(new AlgoliaEngineTestModel, 'zonda');
        $builder->where('foo', 1);
        $engine->search($builder);
    }

    public function test_map_correctly_maps_results_to_models()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $engine = new AlgoliaEngine($client);

        $model = Mockery::mock('StdClass');
        $model->shouldReceive('newQuery')->andReturn($model);
        $model->shouldReceive('getKeyName')->andReturn('id');
        $model->shouldReceive('getQualifiedKeyName')->andReturn('id');
        $model->shouldReceive('whereIn')->once()->with('id', [1])->andReturn($model);
        $model->shouldReceive('get')->once()->andReturn(Collection::make([new AlgoliaEngineTestModel]));

        $results = $engine->map(['nbHits' => 1, 'hits' => [
            ['objectID' => 1, 'id' => 1],
        ]], $model);

        $this->assertEquals(1, count($results));
    }

    public function test_map_correctly_maps_results_to_chunked_models()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $engine = new AlgoliaEngine($client);

        $model = Mockery::mock('StdClass');
        $model->shouldReceive('newQuery')->andReturn($model);
        $model->shouldReceive('getKeyName')->andReturn('id');
        $model->shouldReceive('getQualifiedKeyName')->andReturn('id');
        $model->shouldReceive('whereIn')->once()->with('id', [1])->andReturn($model);
        $model->shouldReceive('get')->once()->andReturn(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayModel]));

        $results = $engine->map(['nbHits' => 1, 'hits' => [
            ['objectID' => '1_scout_chunk-1', 'id' => 1],
            ['objectID' => '1_scout_chunk-2', 'id' => 1],
            ['objectID' => '1_scout_chunk-3', 'id' => 1],
        ]], $model);

        $this->assertEquals(1, count($results));
    }

    public function test_a_model_is_indexed_with_a_custom_algolia_key()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([[
            'id' => 1,
            'objectID' => 'my-algolia-key.1',
        ]]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestCustomKeyModel]));
    }

    public function test_a_model_chunks_are_indexed_with_a_custom_algolia_key()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('addObjects')->with([
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-1',
                'name' => 'Name',
                'body' => 'Body chunk 1',
            ],
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-2',
                'name' => 'Name',
                'body' => 'Body chunk 2',
            ],
            [
                'id' => 1,
                'objectID' => 'my-algolia-key.1_scout_chunk-3',
                'name' => 'Name',
                'body' => 'Body chunk 3',
            ],
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->update(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayCustomKeyModel]));
    }

    public function test_a_chunked_model_is_removed()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('deleteObjects')->with([
            '1_scout_chunk-1',
            '1_scout_chunk-2',
            '1_scout_chunk-3',
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->delete(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayModel]));
    }

    public function test_a_model_is_removed_with_a_custom_algolia_key()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('deleteObjects')->with(['my-algolia-key.1']);

        $engine = new AlgoliaEngine($client);
        $engine->delete(Collection::make([new AlgoliaEngineTestCustomKeyModel]));
    }

    public function test_a_chunked_model_is_removed_with_a_custom_algolia_key()
    {
        $client = Mockery::mock('AlgoliaSearch\Client');
        $client->shouldReceive('initIndex')->with('table')->andReturn($index = Mockery::mock('StdClass'));
        $index->shouldReceive('deleteObjects')->with([
            'my-algolia-key.1_scout_chunk-1',
            'my-algolia-key.1_scout_chunk-2',
            'my-algolia-key.1_scout_chunk-3'
        ]);

        $engine = new AlgoliaEngine($client);
        $engine->delete(Collection::make([new AlgoliaEngineTestChunkedSearchableArrayCustomKeyModel]));
    }
}
