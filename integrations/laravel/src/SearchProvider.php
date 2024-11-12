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

namespace CmsIg\Seal\Integration\Laravel;

use Illuminate\Support\ServiceProvider;
use CmsIg\Seal\Integration\Laravel\Console\IndexCreateCommand;
use CmsIg\Seal\Integration\Laravel\Console\IndexDropCommand;
use CmsIg\Seal\Integration\Laravel\Console\ReindexCommand;
use CmsIg\Seal\Adapter\AdapterFactory;
use CmsIg\Seal\Adapter\AdapterFactoryInterface;
use CmsIg\Seal\Adapter\AdapterInterface;
use CmsIg\Seal\Adapter\Algolia\AlgoliaAdapterFactory;
use CmsIg\Seal\Adapter\Elasticsearch\ElasticsearchAdapterFactory;
use CmsIg\Seal\Adapter\Loupe\LoupeAdapterFactory;
use CmsIg\Seal\Adapter\Meilisearch\MeilisearchAdapterFactory;
use CmsIg\Seal\Adapter\Memory\MemoryAdapterFactory;
use CmsIg\Seal\Adapter\Multi\MultiAdapterFactory;
use CmsIg\Seal\Adapter\Opensearch\OpensearchAdapterFactory;
use CmsIg\Seal\Adapter\ReadWrite\ReadWriteAdapterFactory;
use CmsIg\Seal\Adapter\RediSearch\RediSearchAdapterFactory;
use CmsIg\Seal\Adapter\Solr\SolrAdapterFactory;
use CmsIg\Seal\Adapter\Typesense\TypesenseAdapterFactory;
use CmsIg\Seal\Engine;
use CmsIg\Seal\EngineInterface;
use CmsIg\Seal\EngineRegistry;
use CmsIg\Seal\Schema\Loader\LoaderInterface;
use CmsIg\Seal\Schema\Loader\PhpFileLoader;
use CmsIg\Seal\Schema\Schema;

/**
 * @experimental
 */
final class SearchProvider extends ServiceProvider
{
    /**
     * @internal
     */
    public function register(): void
    {
        $this->publishes([
            \dirname(__DIR__) . '/config/seal.php' => config_path('seal.php'),
        ]);

        $this->mergeConfigFrom(\dirname(__DIR__) . '/config/seal.php', 'seal');
    }

    /**
     * @internal
     */
    public function boot(): void
    {
        $this->commands([
            IndexCreateCommand::class,
            IndexDropCommand::class,
            ReindexCommand::class,
        ]);

        /** @var array{seal: mixed[]} $globalConfig */
        $globalConfig = $this->app->get('config');

        /**
         * @var array{
         *     index_name_prefix: string,
         *     engines: array<string, array{adapter: string}>,
         *     schemas: array<string, array{dir: string, engine?: string}>,
         * } $config
         */
        $config = $globalConfig['seal'];
        $indexNamePrefix = $config['index_name_prefix'];
        $engines = $config['engines'];
        $schemas = $config['schemas'];

        $engineSchemaDirs = [];
        foreach ($schemas as $options) {
            $engineSchemaDirs[$options['engine'] ?? 'default'][] = $options['dir'];
        }

        $this->createAdapterFactories();
        $engineServices = [];

        foreach ($engines as $name => $engineConfig) {
            $adapterServiceId = 'seal.adapter.' . $name;
            $engineServiceId = 'seal.engine.' . $name;
            $schemaLoaderServiceId = 'seal.schema_loader.' . $name;
            $schemaId = 'seal.schema.' . $name;

            /** @var string $adapterDsn */
            $adapterDsn = $engineConfig['adapter'];
            $dirs = $engineSchemaDirs[$name] ?? [];

            $this->app->singleton($adapterServiceId, function ($app) use ($adapterDsn) {
                /** @var AdapterFactory $factory */
                $factory = $app['seal.adapter_factory'];

                return $factory->createAdapter($adapterDsn);
            });

            $this->app->singleton($schemaLoaderServiceId, fn () => new PhpFileLoader($dirs, $indexNamePrefix));

            $this->app->singleton($schemaId, function ($app) use ($schemaLoaderServiceId) {
                /** @var LoaderInterface $loader */
                $loader = $app[$schemaLoaderServiceId];

                return $loader->load();
            });

            $engineServices[$name] = $engineServiceId;
            $this->app->singleton($engineServiceId, function ($app) use ($adapterServiceId, $schemaId) {
                /** @var AdapterInterface $adapter */
                $adapter = $app->get($adapterServiceId);
                /** @var Schema $schema */
                $schema = $app->get($schemaId);

                return new Engine($adapter, $schema);
            });

            if ('default' === $name || (!isset($engines['default']) && !$this->app->has(EngineInterface::class))) {
                $this->app->alias($engineServiceId, EngineInterface::class);
                $this->app->alias($schemaId, Schema::class);
            }
        }

        $this->app->singleton('seal.engine_factory', function ($app) use ($engineServices) {
            $engines = []; // TODO use tagged like in adapter factories
            foreach ($engineServices as $name => $engineServiceId) {
                $engines[$name] = $app->get($engineServiceId);
            }

            return new EngineRegistry($engines);
        });

        $this->app->alias('seal.engine_factory', EngineRegistry::class);

        $this->app->when(ReindexCommand::class)
            ->needs('$reindexProviders')
            ->giveTagged('seal.reindex_provider');

        $this->app->tagged('seal.reindex_provider');
    }

    private function createAdapterFactories(): void
    {
        $this->app->singleton('seal.adapter_factory', function ($app) {
            $factories = [];
            /** @var AdapterFactoryInterface $service */
            foreach ($app->tagged('seal.adapter_factory') as $service) {
                $factories[$service::getName()] = $service;
            }

            return new AdapterFactory($factories);
        });

        if (\class_exists(AlgoliaAdapterFactory::class)) {
            $this->app->singleton('seal.algolia.adapter_factory', fn ($app) => new AlgoliaAdapterFactory($app));

            $this->app->tag(
                'seal.algolia.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(ElasticsearchAdapterFactory::class)) {
            $this->app->singleton('seal.elasticsearch.adapter_factory', fn ($app) => new ElasticsearchAdapterFactory($app));

            $this->app->tag(
                'seal.elasticsearch.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(LoupeAdapterFactory::class)) {
            $this->app->singleton('seal.loupe.adapter_factory', fn ($app) => new LoupeAdapterFactory($app));

            $this->app->tag(
                'seal.loupe.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(OpensearchAdapterFactory::class)) {
            $this->app->singleton('seal.opensearch.adapter_factory', fn ($app) => new OpensearchAdapterFactory($app));

            $this->app->tag(
                'seal.opensearch.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(MeilisearchAdapterFactory::class)) {
            $this->app->singleton('seal.meilisearch.adapter_factory', fn ($app) => new MeilisearchAdapterFactory($app));

            $this->app->tag(
                'seal.meilisearch.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(MemoryAdapterFactory::class)) {
            $this->app->singleton('seal.memory.adapter_factory', fn () => new MemoryAdapterFactory());

            $this->app->tag(
                'seal.memory.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(RediSearchAdapterFactory::class)) {
            $this->app->singleton('seal.redis.adapter_factory', fn ($app) => new RediSearchAdapterFactory($app));

            $this->app->tag(
                'seal.redis.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(SolrAdapterFactory::class)) {
            $this->app->singleton('seal.solr.adapter_factory', fn ($app) => new SolrAdapterFactory($app));

            $this->app->tag(
                'seal.solr.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(TypesenseAdapterFactory::class)) {
            $this->app->singleton('seal.typesense.adapter_factory', fn ($app) => new TypesenseAdapterFactory($app));

            $this->app->tag(
                'seal.typesense.adapter_factory',
                'seal.adapter_factory',
            );
        }

        // ...

        if (\class_exists(ReadWriteAdapterFactory::class)) {
            $this->app->singleton('seal.read_write.adapter_factory', fn ($app) => new ReadWriteAdapterFactory(
                $app,
                'seal.adapter.',
            ));

            $this->app->tag(
                'seal.read_write.adapter_factory',
                'seal.adapter_factory',
            );
        }

        if (\class_exists(MultiAdapterFactory::class)) {
            $this->app->singleton('seal.multi.adapter_factory', fn ($app) => new MultiAdapterFactory(
                $app,
                'seal.adapter.',
            ));

            $this->app->tag(
                'seal.multi.adapter_factory',
                'seal.adapter_factory',
            );
        }
    }
}
