<?php

declare(strict_types=1);

namespace App\Handler;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Schranz\Search\SEAL\EngineRegistry;

class SearchReadWriteHandlerFactory
{
    public function __invoke(ContainerInterface $container): RequestHandlerInterface
    {
        $engineRegistry = $container->get(EngineRegistry::class);
        \assert($engineRegistry instanceof EngineRegistry);

        return new SearchReadWriteHandler($engineRegistry);
    }
}
