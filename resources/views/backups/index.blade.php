<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Datensicherung
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('dashboard') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    ← Zurück zum Dashboard
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            
            
            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">Informationen zur Datensicherung</h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <li><strong>Schule:</strong> {{ $school->name }}</li>
                                <li><strong>Datenbank:</strong> <span class="font-mono text-xs break-all">{{ $datenbankDatei }}</span></li>
                                <li><strong>Status:</strong> 
                                    @if($dbExists)
                                        <span class="text-green-600 font-semibold">✓ Datenbank existiert</span>
                                    @else
                                        <span class="text-red-600 font-semibold">✗ Datenbank existiert nicht</span>
                                    @endif
                                </li>
                                <li><strong>Verfügbare Backups:</strong> {{ count($backups) }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Speicherort -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 flex flex-col sm:flex-row sm:items-center gap-4">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold mb-1">Speicherort der Sicherungen</h3>
                        <p class="font-mono text-sm text-gray-700 break-all select-all bg-gray-50 border border-gray-200 rounded px-3 py-2">{{ $sicherungsOrdner }}</p>
                        @unless($ordnerOeffnenMoeglich)
                            <p class="mt-1 text-xs text-gray-500">Der Ordner liegt auf dem Server; dort z.&nbsp;B. per Datensicherung des Servers mitsichern.</p>
                        @endunless
                    </div>
                    @if($ordnerOeffnenMoeglich)
                        <form method="POST" action="{{ route('backups.ordner') }}" class="shrink-0">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50">
                                📂 {{ $ordnerOeffnenText }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>


            <!-- Neues Backup erstellen -->
            @unlessarchive
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Neues Backup erstellen</h3>
                    
                    @if($dbExists)
                        <form method="POST" action="{{ route('backups.create') }}" class="flex gap-4 items-end">
                            @csrf
                            <div class="flex-1">
                                <x-input-label for="description" value="Beschreibung (optional)" />
                                <x-text-input id="description" class="block mt-1 w-full" type="text" name="description" 
                                    placeholder="z.B. vor_planung, nach_import" maxlength="100" />
                                <p class="mt-1 text-xs text-gray-500">Wird im Dateinamen verwendet</p>
                            </div>
                            <x-primary-button>
                                💾 Backup erstellen
                            </x-primary-button>
                        </form>
                    @else
                        <div class="text-gray-500">
                            ⚠️ Kein Backup möglich - Datenbank existiert nicht
                        </div>
                    @endif
                </div>
            </div>
            @endarchive


            <!-- Backup-Liste -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Verfügbare Backups</h3>
                    
                    @if(count($backups) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dateiname</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Größe</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Version</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Erstellt am</th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Aktionen</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($backups as $backup)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                            <div>{{ $backup['filename'] }}</div>
                                            @if(isset($backup['metadata']['description']))
                                                <div class="text-xs text-gray-500 mt-1">
                                                    📝 {{ $backup['metadata']['description'] ?? '–' }}
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $backup['size_human'] }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            @if(isset($backup['metadata']))
                                                <div class="space-y-1">
                                                    <div class="text-xs">
                                                        🔢 {{ $backup['metadata']['migration_count'] ?? 'n/a' }} Migrations
                                                    </div>
                                                    <div class="text-xs">
                                                        📊 {{ $backup['metadata']['table_count'] ?? 'n/a' }} Tabellen
                                                    </div>
                                                    @if(isset($backup['metadata']['checksum']))
                                                        <div class="text-xs text-green-600" title="Checksum vorhanden">
                                                            ✓ Validiert
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-400">
                                                    ⚠️ Keine Metadaten
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($backup['compatible'] === false)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                    ❌ Inkompatibel
                                                </span>
                                            @elseif($backup['outdated'] === true)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                    ⚡ Veraltet
                                                </span>
                                            @elseif($backup['compatible'] === true)
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    ✓ Aktuell
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    ? Unbekannt
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ $backup['created_at']->format('d.m.Y H:i') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex justify-end gap-2">
                                                <!-- Info -->
                                                @if(isset($backup['metadata']))
                                                <button type="button" 
                                                        data-modal-open="metadata{{ $loop->index }}"
                                                        class="text-blue-600 hover:text-blue-900" 
                                                        title="Metadaten anzeigen">
                                                    ℹ️
                                                </button>
                                                @endif
                                                
                                                @unlessarchive
                                                <!-- Download -->
                                                <form method="POST" action="{{ route('backups.download') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                                                    <button type="submit" class="text-blue-600 hover:text-blue-900" title="Herunterladen">
                                                        ⬇️
                                                    </button>
                                                </form>
                                                
                                                <!-- Restore -->
                                                @if($dbExists)
                                                    @if($backup['compatible'] === false)
                                                        <button type="button" 
                                                                class="text-gray-400 cursor-not-allowed" 
                                                                title="Inkompatibel - Restore nicht möglich"
                                                                disabled>
                                                            🔄
                                                        </button>
                                                    @else
                                                        @php
                                                            // Die Umbrueche muessen aus PHP kommen: im Attribut waere "\n"
                                                            // nur Text, und {{ }} wuerde eine HTML-Entity escapen.
                                                            $restoreFrage = ($backup['outdated']
                                                                ? "ACHTUNG: Backup ist veraltet! Nach Restore wird Migration empfohlen.\n\n"
                                                                : '')."Dies überschreibt die aktuelle Datenbank!\n\nFortfahren?";
                                                        @endphp
                                                        <form method="POST" 
                                                              action="{{ route('backups.restore') }}" 
                                                              class="inline"
                                                              data-confirm="{{ $restoreFrage }}">
                                                            @csrf
                                                            <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                                                            <button type="submit" 
                                                                    class="text-green-600 hover:text-green-900" 
                                                                    title="{{ $backup['outdated'] ? 'Veraltet - Migration nach Restore empfohlen' : 'Wiederherstellen' }}">
                                                                🔄
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                                
                                                <!-- Delete -->
                                                <form method="POST" action="{{ route('backups.delete') }}" 
                                                      class="inline"
                                                      data-confirm="Backup wirklich löschen?">
                                                    @csrf
                                                    <input type="hidden" name="filename" value="{{ $backup['filename'] }}">
                                                    <button type="submit" class="text-red-600 hover:text-red-900" title="Löschen">
                                                        🗑️
                                                    </button>
                                                </form>
                                                @else
                                                <span class="text-xs text-gray-400">Aktionen in der Archivansicht deaktiviert</span>
                                                @endarchive
                                            </div>
                                        </td>
                                    </tr>
                                    @if(isset($backup['metadata']))
                                    <tr id="metadata{{ $loop->index }}" class="hidden bg-gray-50">
                                        <td colspan="6" class="px-6 py-4">
                                            <div class="bg-white rounded-lg p-4 shadow-sm">
                                                <h4 class="font-semibold mb-3 text-gray-900">📊 Backup-Metadaten</h4>
                                                <div class="grid grid-cols-2 gap-4 text-sm">
                                                    <div>
                                                        <strong>Backup-Version:</strong> {{ $backup['metadata']['backup_version'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Erstellt:</strong> {{ $backup['metadata']['created_at'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Schulnummer:</strong> {{ $backup['metadata']['school_number'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Datenbank:</strong> {{ $backup['metadata']['database_name'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Laravel-Version:</strong> {{ $backup['metadata']['laravel_version'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>PHP-Version:</strong> {{ $backup['metadata']['php_version'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Schema-Version:</strong> <span class="font-mono text-xs">{{ $backup['metadata']['schema_version'] ?? '–' }}</span>
                                                    </div>
                                                    <div>
                                                        <strong>Letzte Migration:</strong> <span class="font-mono text-xs">{{ $backup['metadata']['last_migration'] ?? '–' }}</span>
                                                    </div>
                                                    <div>
                                                        <strong>Anzahl Migrations:</strong> {{ $backup['metadata']['migration_count'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Anzahl Tabellen:</strong> {{ $backup['metadata']['table_count'] ?? '–' }}
                                                    </div>
                                                    <div>
                                                        <strong>Komprimiert:</strong> {{ ($backup['metadata']['compressed'] ?? false) ? 'Ja (.gz)' : 'Nein' }}
                                                    </div>
                                                    <div>
                                                        <strong>Dateigröße:</strong> {{ number_format((int) ($backup['metadata']['size_bytes'] ?? 0)) }} Bytes
                                                    </div>
                                                    @if(isset($backup['metadata']['checksum']))
                                                    <div class="col-span-2">
                                                        <strong>Checksum (SHA256):</strong> 
                                                        <span class="font-mono text-xs text-gray-600 break-all">{{ $backup['metadata']['checksum'] ?? '–' }}</span>
                                                    </div>
                                                    @endif
                                                </div>
                                                
                                                <div class="mt-4">
                                                    <button type="button" 
                                                            data-modal-close="metadata{{ $loop->index }}"
                                                            class="text-sm text-blue-600 hover:text-blue-900">
                                                        Schließen ×
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-500">
                            📦 Noch keine Backups vorhanden
                        </div>
                    @endif
                </div>
            </div>
            
            <!-- Hinweise -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-yellow-800">Wichtige Hinweise</h3>
                        <div class="mt-2 text-sm text-yellow-700">
                            <ul class="list-disc pl-5 space-y-1">
                                <li><strong>Backup-Format:</strong> Komprimierte Kopie der Schuldatenbank (.sqlite.gz) mit Metadaten (.meta.json)</li>
                                <li><strong>Versionierung:</strong> Jedes Backup enthält Metadaten über Schema-Version und Migrations</li>
                                <li><strong>Kompatibilitätsprüfung:</strong> Beim Restore wird automatisch geprüft, ob das Backup kompatibel ist</li>
                                <li><strong>Wiederherstellung:</strong> Ersetzt die komplette Schuldatenbank. Vorher wird automatisch eine Sicherung „vor-wiederherstellung“ angelegt; ältere Sicherungen werden danach auf den aktuellen Stand migriert. Andere Nutzer sollten dabei abgemeldet sein.</li>
                                <li><strong>Automatisch:</strong> einmal täglich beim Start bzw. nachts (die letzten 14) und vor jedem Programm-Update.</li>
                                <li><strong>Datenintegrität:</strong> Alle Backups werden mit SHA256-Checksum validiert</li>
                                <li><strong>Echte Sicherung:</strong> Die Sicherungen liegen auf demselben Rechner wie die Datenbank. Bitte regelmäßig herunterladen oder den Sicherungsordner auf ein anderes Laufwerk kopieren.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gefahrenbereich: alles zurücksetzen -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-red-200"
                 x-data="{ offen: {{ $errors->has('bestaetigung') || $errors->has('password') ? 'true' : 'false' }} }">
                <div class="p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-red-700">Abiplaner zurücksetzen</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Löscht <strong>alle Daten</strong> — Schule, Benutzerkonten, Schüler, Lehrkräfte, Planungen —
                                und startet neu mit der Einrichtung. Vorher wird automatisch eine Sicherung
                                „vor-zuruecksetzen“ angelegt; sie bleibt im Sicherungsordner und lässt sich nach der
                                neuen Einrichtung hier wiederherstellen.
                            </p>
                        </div>
                        <button type="button" x-show="!offen" x-on:click="offen = true"
                                class="shrink-0 inline-flex items-center px-4 py-2 bg-white border border-red-300 rounded-md font-semibold text-xs text-red-700 uppercase tracking-widest hover:bg-red-50">
                            Zurücksetzen …
                        </button>
                    </div>

                    <form method="POST" action="{{ route('backups.zuruecksetzen') }}" x-show="offen" x-cloak
                          class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4 items-end"
                          data-confirm="Wirklich ALLE Daten löschen und Abiplaner neu einrichten?">
                        @csrf
                        <div>
                            <x-input-label for="bestaetigung" value="Zur Bestätigung ZURÜCKSETZEN eintippen" />
                            <x-text-input id="bestaetigung" name="bestaetigung" type="text" class="block mt-1 w-full" autocomplete="off" required />
                            <x-input-error :messages="$errors->get('bestaetigung')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="reset_password" value="Ihr Passwort" />
                            <x-text-input id="reset_password" name="password" type="password" class="block mt-1 w-full" autocomplete="current-password" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-danger-button class="w-full justify-center">Alle Daten löschen</x-danger-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
