<?php

// Bootstrap-Datei für PHPStan und ide-helper:
// Simuliert die "tenant"-Datenbankverbindung als Alias auf die Standard-MySQL-Verbindung,
// damit PHPStan Tenant-Modelle korrekt analysieren kann.

$app = require_once __DIR__ . '/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$app->booted(function () use ($app) {
    $db = $app->make('config');
    $db->set('database.connections.tenant', $db->get('database.connections.mysql'));
});

return $app;
