<?php

declare(strict_types=1);

namespace App\Search;

use Schranz\Search\SEAL\Reindex\ReindexProviderInterface;

class BlogReindexProvider implements ReindexProviderInterface
{
    public function total(): ?int
    {
        return 3;
    }

    public function provide(): \Generator
    {
        yield [
            'id' => 1,
            'title' => 'Title 1',
            'description' => 'Description 1',
        ];

        yield [
            'id' => 2,
            'title' => 'Title 2',
            'description' => 'Description 2',
        ];

        yield [
            'id' => 3,
            'title' => 'Title 3',
            'description' => 'Description 3',
        ];
    }

    public static function getIndex(): string
    {
        return 'blog';
    }
}
