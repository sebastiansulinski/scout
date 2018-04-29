<?php

namespace Tests\Fixtures;

class AlgoliaEngineTestChunkedSearchableArrayCustomKeyModel extends AlgoliaEngineTestCustomKeyModel
{
    public function toSearchableArray()
    {
        return [
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'Body chunk 1',
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'Body chunk 2',
            ],
            [
                'id' => 1,
                'name' => 'Name',
                'body' => 'Body chunk 3',
            ]
        ];
    }
}
