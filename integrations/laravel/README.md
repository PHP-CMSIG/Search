> **Note**:
> This is part of the `cmsig/search` project create issues in the [main repository](https://github.com/php-cmsig/search).

---

<div align="center">
    <img alt="SEAL Logo with an abstract seal sitting on a telescope." src="https://avatars.githubusercontent.com/u/120221538?s=400&v=6" width="200" height="200">
</div>

<div align="center">Logo created by <a href="https://cargocollective.com/meinewilma">Meine Wilma</a></div>

<h1 align="center">SEAL <br /> Laravel Integration</h1>

<br />
<br />

Integration of the CMS-IG — Search Engine Abstraction Layer (SEAL) into Laravel.

> **Note**:
> This project is heavily under development and any feedback is greatly appreciated.

## Installation

Use [composer](https://getcomposer.org/) for install the package:

```bash
composer require cmsig/seal-laravel-package
```

Also install one of the listed adapters.

### List of adapters

The following adapters are available:

 - [MemoryAdapter](../../packages/seal-memory-adapter): `cmsig/seal-memory-adapter`
 - [ElasticsearchAdapter](../../packages/seal-elasticsearch-adapter): `cmsig/seal-elasticsearch-adapter`
 - [OpensearchAdapter](../../packages/seal-opensearch-adapter): `cmsig/seal-opensearch-adapter`
 - [MeilisearchAdapter](../../packages/seal-meilisearch-adapter): `cmsig/seal-meilisearch-adapter`
 - [AlgoliaAdapter](../../packages/seal-algolia-adapter): `cmsig/seal-algolia-adapter`
 - [LoupeAdapter](../../packages/seal-loupe-adapter): `cmsig/seal-loupe-adapter`
 - [SolrAdapter](../../packages/seal-solr-adapter): `cmsig/seal-solr-adapter`
 - [RediSearchAdapter](../../packages/seal-redisearch-adapter): `cmsig/seal-redisearch-adapter`
 - [TypesenseAdapter](../../packages/seal-typesense-adapter): `cmsig/seal-typesense-adapter`
 - ... more coming soon

Additional Wrapper adapters:

 - [ReadWriteAdapter](../../packages/seal-read-write-adapter): `cmsig/seal-read-write-adapter`
 - [MultiAdapter](../../packages/seal-multi-adapter): `cmsig/seal-multi-adapter`

Creating your own adapter? Add the [`seal-php-adapter`](https://github.com/topics/seal-php-adapter) Topic to your GitHub Repository.

## Usage

The following code shows how to configure the package:

```php
<?php

// config/cmsig_seal.php

return [
    /*
    |--------------------------------------------------------------------------
    | Schema configs
    |--------------------------------------------------------------------------
    |
    | Define different directories for the schema loader.
    */

    'schemas' => [
        'app' => [
            'dir' => resource_path('schemas'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | engines
    |--------------------------------------------------------------------------
    |
    | Directory where the latte templates can be found.
    */

    'engines' => [
        'default' => [
            'adapter' => env(
                'ENGINE_URL',
                'meilisearch://127.0.0.1:7700',
            ),
        ],
    ],
];
```

A more complex configuration can be here found:

```php
<?php

// config/cmsig_seal.php

return [
    /*
    |--------------------------------------------------------------------------
    | Schema configs
    |--------------------------------------------------------------------------
    |
    | Define different directories for the schema loader.
    */

    'schemas' => [
        'app' => [
            'dir' => resource_path('schemas') . '/app',
            'engine' => 'meilisearch',
        ],
        'other' => [
            'dir' => resource_path('schemas') . '/other',
            'engine' => 'algolia',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | engines
    |--------------------------------------------------------------------------
    |
    | Directory where the latte templates can be found.
    */

    'engines' => [
        'algolia' => [
            'adapter' => 'algolia://' . env('ALGOLIA_APPLICATION_ID') . ':' . env('ALGOLIA_ADMIN_API_KEY'),
        ],
        'elasticsearch' => [
            'adapter' => 'elasticsearch://127.0.0.1:9200',
        ],
        'loupe' => [
            'adapter' => 'loupe://var/indexes',
        ],
        'meilisearch' => [
            'adapter' => 'meilisearch://127.0.0.1:7700',
        ],
        'memory' => [
            'adapter' => 'memory://',
        ],
        'opensearch' => [
            'adapter' => 'opensearch://127.0.0.1:9200',
        ],
        'redisearch' => [
            'adapter' => 'redis://supersecure@127.0.0.1:6379',
        ],
        'solr' => [
            'adapter' => 'solr://127.0.0.1:8983',
        ],
        'typesense' => [
            'adapter' => 'typesense://S3CR3T@127.0.0.1:8108',
        ],
        
        // ...
        'multi' => [
            'adapter' => 'multi://elasticsearch?adapters[]=opensearch',
        ],
        'read-write' => [
            'adapter' => 'read-write://elasticsearch?write=multi',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema prefix
    |--------------------------------------------------------------------------
    |
    | Define the prefix used for the index names to avoid conflicts.
    */

    'index_name_prefix' => '',
];
```

The default engine is available as `Engine`:

```php
class Some {
    public function __construct(
        private readonly \CmsIg\Seal\EngineInterface $engine,
    ) {
    }
}
```

Multiple engines can be accessed via the `EngineRegistry`:

```php
class Some {
    private Engine $engine;

    public function __construct(
        private readonly \CmsIg\Seal\EngineRegistry $engineRegistry,
    ) {
        $this->engine = $this->engineRegistry->get('algolia');
    }
}
```

Instead of constructor injection the `Laravel` integration provides also two `Facades`
for the above services:

- `CmsIg\Seal\Integration\Laravel\Facade\Engine`
- `CmsIg\Seal\Integration\Laravel\Facade\EngineRegistry`

How to create a `Schema` file and use your `Engine` can be found [SEAL Documentation](../../README.md#usage).

### Commands

The package provides the following commands:

**Create configured indexes**

```bash
php artisan cmsig:seal:index-create --help
```

**Drop configured indexes**

```bash
php artisan cmsig:seal:index-drop --help
```

## Authors

- [Alexander Schranz](https://github.com/alexander-schranz/)
- [The Community Contributors](https://github.com/php-cmsig/search/graphs/contributors)
