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

namespace CmsIg\Seal\Adapter\Algolia\Tests;

use CmsIg\Seal\Adapter\Algolia\AlgoliaAdapter;
use CmsIg\Seal\Testing\AbstractSearcherTestCase;

class AlgoliaSearcherTest extends AbstractSearcherTestCase
{
    public static function setUpBeforeClass(): void
    {
        $client = ClientHelper::getClient();
        self::$adapter = new AlgoliaAdapter($client);

        parent::setUpBeforeClass();
    }
}
