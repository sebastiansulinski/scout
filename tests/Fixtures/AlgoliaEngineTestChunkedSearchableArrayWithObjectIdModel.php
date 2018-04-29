<?php

namespace Tests\Fixtures;

class AlgoliaEngineTestChunkedSearchableArrayWithObjectIdModel extends TestModel
{
    public function toSearchableArray()
    {
        return [
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
            ]
        ];
    }
}
