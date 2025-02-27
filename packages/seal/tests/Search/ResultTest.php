<?php

declare(strict_types=1);

/*
 * This file is part of the CMS-IG SEAL project.
 *
 * (c) Alexander Schranz <alexander@sulu.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CmsIg\Seal\Tests\Search;

use CmsIg\Seal\Search\Result;
use PHPUnit\Framework\TestCase;

class ResultTest extends TestCase
{
    public function testIteratingResult(): void
    {
        $result = new Result((static function (): \Generator {
            yield ['id' => 42];
        })(), 1);

        $this->assertSame(1, $result->total());
        $this->assertSame([['id' => 42]], \iterator_to_array($result));
    }

    public function testCreateEmptyResult(): void
    {
        $result = Result::empty();
        $this->assertSame(0, $result->total());
        $this->assertSame([], \iterator_to_array($result));
    }
}
