<?php

// PHPStan Bootstrap: Simuliert die "tenant"-Datenbankverbindung
// für die statische Analyse von Tenant-Modellen.

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Tenant-Connection als Alias der MySQL-Verbindung setzen
config(['database.connections.tenant' => config('database.connections.mysql')]);
