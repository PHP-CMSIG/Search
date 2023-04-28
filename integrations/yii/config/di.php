<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Schranz\Search\SEAL\Adapter\AdapterFactory;
use Schranz\Search\SEAL\Adapter\AdapterFactoryInterface;
use Schranz\Search\SEAL\Adapter\AdapterInterface;
use Schranz\Search\SEAL\Adapter\Algolia\AlgoliaAdapterFactory;
use Schranz\Search\SEAL\Adapter\Elasticsearch\ElasticsearchAdapterFactory;
use Schranz\Search\SEAL\Adapter\Meilisearch\MeilisearchAdapterFactory;
use Schranz\Search\SEAL\Adapter\Memory\MemoryAdapterFactory;
use Schranz\Search\SEAL\Adapter\Multi\MultiAdapterFactory;
use Schranz\Search\SEAL\Adapter\Opensearch\OpensearchAdapterFactory;
use Schranz\Search\SEAL\Adapter\ReadWrite\ReadWriteAdapterFactory;
use Schranz\Search\SEAL\Adapter\RediSearch\RediSearchAdapterFactory;
use Schranz\Search\SEAL\Adapter\Solr\SolrAdapterFactory;
use Schranz\Search\SEAL\Adapter\Typesense\TypesenseAdapterFactory;
use Schranz\Search\SEAL\Engine;
use Schranz\Search\SEAL\EngineRegistry;
use Schranz\Search\SEAL\Schema\Loader\LoaderInterface;
use Schranz\Search\SEAL\Schema\Loader\PhpFileLoader;
use Schranz\Search\SEAL\Schema\Schema;

/** @var \Yiisoft\Config\Config $config */
/** @var array{"schranz-search/yii-module": mixed[]} $params */

/**
 * @var array{
 *     prefix: string,
 *     engines: array<string, array{adapter: string}>,
 *     schemas: array<string, array{dir: string, engine?: string}>,
 * } $config
 */
$config = $params['schranz-search/yii-module'];
$prefix = $config['prefix'];
$engines = $config['engines'];
$schemas = $config['schemas'];

$engineSchemaDirs = [];
foreach ($schemas as $options) {
    $engineSchemaDirs[$options['engine'] ?? 'default'][] = $options['dir'];
}

$diConfig = [];

$adapterFactories = [];

if (\class_exists(AlgoliaAdapterFactory::class)) {
    $adapterFactories['schranz_search.algolia.adapter_factory'] = static function (ContainerInterface $container) {
        return new AlgoliaAdapterFactory($container);
    };
}

if (\class_exists(ElasticsearchAdapterFactory::class)) {
    $adapterFactories['schranz_search.elasticsearch.adapter_factory'] = static function (ContainerInterface $container) {
        return new ElasticsearchAdapterFactory($container);
    };
}

if (\class_exists(OpensearchAdapterFactory::class)) {
    $adapterFactories['schranz_search.opensearch.adapter_factory'] = static function (ContainerInterface $container) {
        return new OpensearchAdapterFactory($container);
    };
}

if (\class_exists(MeilisearchAdapterFactory::class)) {
    $adapterFactories['schranz_search.meilisearch.adapter_factory'] = static function (ContainerInterface $container) {
        return new MeilisearchAdapterFactory($container);
    };
}

if (\class_exists(MemoryAdapterFactory::class)) {
    $adapterFactories['schranz_search.memory.adapter_factory'] = static function (ContainerInterface $container) {
        return new MemoryAdapterFactory();
    };
}

if (\class_exists(RediSearchAdapterFactory::class)) {
    $adapterFactories['schranz_search.redis.adapter_factory'] = static function (ContainerInterface $container) {
        return new RediSearchAdapterFactory($container);
    };
}

if (\class_exists(SolrAdapterFactory::class)) {
    $adapterFactories['schranz_search.solr.adapter_factory'] = static function (ContainerInterface $container) {
        return new SolrAdapterFactory($container);
    };
}

if (\class_exists(TypesenseAdapterFactory::class)) {
    $adapterFactories['schranz_search.typesense.adapter_factory'] = static function (ContainerInterface $container) {
        return new TypesenseAdapterFactory($container);
    };
}

// ...

if (\class_exists(ReadWriteAdapterFactory::class)) {
    $adapterFactories['schranz_search.read_write.adapter_factory'] = static function (ContainerInterface $container) {
        return new ReadWriteAdapterFactory(
            $container,
            'schranz_search.adapter.',
        );
    };
}

if (\class_exists(MultiAdapterFactory::class)) {
    $adapterFactories['schranz_search.multi.adapter_factory'] = static function (ContainerInterface $container) {
        return new MultiAdapterFactory(
            $container,
            'schranz_search.adapter.',
        );
    };
}

$diConfig = [...$diConfig, ...$adapterFactories];
$adapterFactoryNames = array_keys($adapterFactories);

$diConfig['schranz_search.adapter_factory'] = static function (ContainerInterface $container) use ($adapterFactoryNames) {
    $factories = [];
    foreach ($adapterFactoryNames as $serviceName) {
        /** @var AdapterFactoryInterface $service */
        $service = $container->get($serviceName);

        $factories[$service::getName()] = $service;
    }

    return new AdapterFactory($factories);
};

$diConfig[AdapterFactory::class] = 'schranz_search.adapter_factory';

$engineServices = [];

foreach ($engines as $name => $engineConfig) {
    $adapterServiceId = 'schranz_search.adapter.' . $name;
    $engineServiceId = 'schranz_search.engine.' . $name;
    $schemaLoaderServiceId = 'schranz_search.schema_loader.' . $name;
    $schemaId = 'schranz_search.schema.' . $name;

    /** @var string $adapterDsn */
    $adapterDsn = $engineConfig['adapter'];
    $dirs = $engineSchemaDirs[$name] ?? [];

    $diConfig[$adapterServiceId] = static function (ContainerInterface $container) use ($adapterDsn) {
        /** @var AdapterFactory $factory */
        $factory = $container->get('schranz_search.adapter_factory');

        return $factory->createAdapter($adapterDsn);
    };

    $diConfig[$schemaLoaderServiceId] = static function (ContainerInterface $container) use ($dirs, $prefix) {
        return new PhpFileLoader($dirs, $prefix);
    };

    $diConfig[$schemaId] = static function (ContainerInterface $container) use ($schemaLoaderServiceId) {
        /** @var LoaderInterface $loader */
        $loader = $container->get($schemaLoaderServiceId);

        return $loader->load();
    };

    $engineServices[$name] = $engineServiceId;

    $diConfig[$engineServiceId] = static function (ContainerInterface $container) use ($adapterServiceId, $schemaId) {
        /** @var AdapterInterface $adapter */
        $adapter = $container->get($adapterServiceId);
        /** @var Schema $schema */
        $schema = $container->get($schemaId);

        return new Engine($adapter, $schema);
    };

    if ('default' === $name || (!isset($engines['default']) && !isset($diConfig[Engine::class]))) {
        $diConfig[Engine::class] = $engineServiceId;
    }
}

$diConfig['schranz_search.engine_factory'] = static function (ContainerInterface $container) use ($engineServices) {
    $engines = [];
    foreach ($engineServices as $name => $engineServiceId) {
        $engines[$name] = $container->get($engineServiceId);
    }

    return new EngineRegistry($engines);
};

$diConfig[EngineRegistry::class] = 'schranz_search.engine_factory';

return $diConfig;
