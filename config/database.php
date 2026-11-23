<?php

use Illuminate\Support\Str;

$istTest = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'testing')
    || (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'testing');

/*
|--------------------------------------------------------------------------
| Eine Datenbank: SQLite
|--------------------------------------------------------------------------
|
| Seit 22.11.2026 verwaltet Abiplaner genau eine Schule in genau einer
| SQLite-Datei — auf dem Arbeitsplatz (Desktop-App) wie im Docker-Container.
| Der Pfad kommt aus ABIPLANER_DATENBANK; die Desktop-App setzt ihn beim
| Start, im Container zeigt er ins Daten-Volume.
|
| WAL + busy_timeout: In Docker arbeiten mehrere Benutzer gleichzeitig über
| denselben Prozess-Host. Auf einem Netzlaufwerk liegt die Datei NIE direkt —
| dort arbeitet die Desktop-App auf einer lokalen Kopie (Laufwerksmodus).
*/

$config = [

    'default' => 'sqlite',

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => null,
            'database' => $istTest
                ? ':memory:'
                : env('ABIPLANER_DATENBANK', database_path('abiplaner.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
            'busy_timeout' => $istTest ? null : 5000,
            'journal_mode' => $istTest ? null : 'wal',
            'synchronous' => $istTest ? null : 'normal',
            'transaction_mode' => 'DEFERRED',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];

return $config;
