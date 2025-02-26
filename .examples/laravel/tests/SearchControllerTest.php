<?php

declare(strict_types=1);

it('test search controller return all engines', function () {
    $response = $this->get('/');

    expect($response->status())->toBe(200);
    expect($response->content())->toContain('<title>Search Engines</title>');

    $crawler = $this->crawler($response->content());

    $crawler->filter('a')->each(function ($node) {
        $response = $this->get($node->attr('href'));

        expect($response->getStatusCode())->toBe(200);
        $crawler = $this->crawler($response->getContent());
        expect($crawler->filter('title')->first()->text())->toContain($node->text());

        expect($crawler->filter('h1')->count())->toBe(1);
        $h1 = $crawler->filter('h1')->first();

        expect($h1->text())->toContain(\str_replace('-', '', (string) $node->text()));
    });
});
