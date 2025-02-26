<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', fn (): string => (new \App\Http\Controllers\SearchController())->home());
Route::get('/algolia', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->algolia());
Route::get('/elasticsearch', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->elasticsearch());
Route::get('/loupe', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->loupe());
Route::get('/meilisearch', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->meilisearch());
Route::get('/memory', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->memory());
Route::get('/opensearch', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->opensearch());
Route::get('/redisearch', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->redisearch());
Route::get('/solr', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->solr());
Route::get('/typesense', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->typesense());
Route::get('/multi', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->multi());
Route::get('/read-write', fn (): \Symfony\Component\HttpFoundation\Response => (new \App\Http\Controllers\SearchController())->readWrite());
