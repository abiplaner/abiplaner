<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Kolloquium – Räume &amp; Tage
            </h2>
            <span class="text-sm text-gray-500">{{ $examPeriod->name }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">

        {{-- Tab-Navigation --}}
        <div class="mb-6 border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <a href="{{ route('planning.index') }}"
                   class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    📋 Prüfungen
                </a>
                <a href="{{ route('planning.rooms.index') }}"
                   class="border-blue-500 text-blue-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    🏫 Räume &amp; Tage
                </a>
                <a href="{{ route('planning.time-slots.index') }}"
                   class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    🕐 Zeitslots
                </a>
            </nav>
        </div>

        {{-- ============================================================ --}}
        {{-- ABSCHNITT 1: Verfügbare Räume + globale Voreinstellungen     --}}
        {{-- ============================================================ --}}
        <div class="bg-white shadow-sm rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">
                        Verfügbare Räume &amp; globale Voreinstellung
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">
                        Wählen Sie die Räume aus, die grundsätzlich für das Kolloquium zur Verfügung stehen,
                        und legen Sie die Standard-Rolle fest. Diese gilt für alle Tage, solange Sie keine
                        tagesspezifische Abweichung eingetragen haben.
                    </p>
                </div>
                <button id="reload-btn"
                        data-reload
                        style="display:none"
                        class="ml-6 flex-shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md shadow-sm transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Tabellen aktualisieren
                </button>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($allRooms as $room)
                    @php
                        $globalEntry = $globalDefaults[$room->id] ?? null;
                        $isAvailable = in_array($room->id, $availableRoomIds);
                        $globalRole  = $globalEntry?->room_role;
                        $typeLabel   = match($room->room_type) {
                            'exam'        => 'Klassenzimmer',
                            'preparation' => 'Vorbereitung',
                            'waiting'     => 'Warteraum',
                            'lockdown'    => 'Bereitstellung',
                            'corridor'    => 'Gang',
                            default       => $room->room_type,
                        };
                    @endphp
                    <div class="flex items-center gap-3 p-3 rounded-lg border transition
                                {{ $isAvailable ? 'border-blue-300 bg-blue-50' : 'border-gray-200 bg-white' }}"
                         id="room-card-{{ $room->id }}">

                        {{-- Checkbox --}}
                        <input type="checkbox"
                               class="room-available-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 cursor-pointer"
                               data-room="{{ $room->id }}"
                               data-current-role="{{ $globalRole ?? 'exam' }}"
                               {{ $isAvailable ? 'checked' : '' }}
                               @archive disabled @endarchive
                               data-toggle-available>

                        {{-- Raumname --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-900">{{ $room->room_number }}</div>
                            <div class="text-xs text-gray-400">{{ $typeLabel }}</div>
                        </div>

                        {{-- Rollen-Buttons (nur aktiv wenn verfügbar; Archiv: nur Anzeige) --}}
                        <div class="inline-flex rounded gap-px {{ $isAvailable ? '' : 'opacity-30 pointer-events-none' }} @archive pointer-events-none @endarchive"
                             id="global-btns-{{ $room->id }}">
                            <button type="button" title="Prüfungsraum"
                                    data-global-role="exam" data-room="{{ $room->id }}"
                                    class="global-role-btn px-2 py-1 text-xs rounded-l border transition"
                                    style="{{ $globalRole === 'exam' ? 'background:#2563eb;color:#fff;border-color:#2563eb;font-weight:600' : 'background:#fff;color:#6b7280;border-color:#d1d5db' }}">
                                P
                            </button>
                            <button type="button" title="Vorbereitungsraum"
                                    data-global-role="preparation" data-room="{{ $room->id }}"
                                    class="global-role-btn px-2 py-1 text-xs border-t border-b transition"
                                    style="{{ $globalRole === 'preparation' ? 'background:#ca8a04;color:#fff;border-color:#ca8a04;font-weight:600' : 'background:#fff;color:#6b7280;border-color:#d1d5db' }}">
                                V
                            </button>
                            <button type="button" title="Warteraum"
                                    data-global-role="waiting" data-room="{{ $room->id }}"
                                    class="global-role-btn px-2 py-1 text-xs border-t border-b transition"
                                    style="{{ $globalRole === 'waiting' ? 'background:#16a34a;color:#fff;border-color:#16a34a;font-weight:600' : 'background:#fff;color:#6b7280;border-color:#d1d5db' }}">
                                W
                            </button>
                            <button type="button" title="Gang"
                                    data-global-role="corridor" data-room="{{ $room->id }}"
                                    class="global-role-btn px-2 py-1 text-xs rounded-r border transition"
                                    style="{{ $globalRole === 'corridor' ? 'background:#475569;color:#fff;border-color:#475569;font-weight:600' : 'background:#fff;color:#6b7280;border-color:#d1d5db' }}">
                                G
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Legende --}}
        <div class="flex flex-wrap gap-4 text-xs text-gray-600 mb-6">
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-5 rounded text-white text-center leading-5 font-bold" style="background:#2563eb">P</span>
                Prüfungsraum
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-5 rounded text-white text-center leading-5 font-bold" style="background:#ca8a04">V</span>
                Vorbereitungsraum
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-5 rounded text-white text-center leading-5 font-bold" style="background:#16a34a">W</span>
                Warteraum
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-5 rounded text-white text-center leading-5 font-bold" style="background:#475569">G</span>
                Gang
            </div>
            <div class="flex items-center gap-1.5">
                <span class="inline-block w-5 h-5 rounded border border-dashed border-gray-400 text-center leading-5 text-gray-400 text-xs">P</span>
                Aus globalem Standard übernommen
            </div>
        </div>

        @if($dates1->isEmpty() && $dates2->isEmpty())
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 text-center text-yellow-800">
                Noch keine Zeitslots vorhanden. Bitte zuerst Zeitslots anlegen.
            </div>
        @else

        @php
            // Alle Räume in der Matrix rendern; nicht-verfügbare werden per JS ein-/ausgeblendet
            $matrixRooms = $allRooms;
        @endphp

        {{-- ============================================================ --}}
        {{-- ABSCHNITT 2a: Matrix Woche 1                                  --}}
        {{-- ============================================================ --}}
        @if($dates1->isNotEmpty())
        <div class="bg-white shadow-sm rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Woche 1</h3>
            </div>
            @include('planning.rooms-table', [
                'dates'         => $dates1,
                'matrixRooms'   => $matrixRooms,
                'globalDefaults'=> $globalDefaults,
                'dayEntries'    => $dayEntries,
            ])
        </div>
        @endif

        {{-- ============================================================ --}}
        {{-- ABSCHNITT 2b: Matrix Woche 2                                  --}}
        {{-- ============================================================ --}}
        @if($dates2->isNotEmpty())
        <div class="bg-white shadow-sm rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">Woche 2</h3>
            </div>
            @include('planning.rooms-table', [
                'dates'         => $dates2,
                'matrixRooms'   => $matrixRooms,
                'globalDefaults'=> $globalDefaults,
                'dayEntries'    => $dayEntries,
            ])
        </div>
        @endif

        @if($availableRoomIds === [])
            <div id="no-rooms-hint" class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-blue-800 text-sm">
                Noch keine Räume ausgewählt. Bitte oben Räume aktivieren.
            </div>
        @endif

        @endif {{-- dates not empty --}}

        </div>
    </div>


    {{-- JavaScript --}}
    <script @cspNonce>
    const TOGGLE_URL = '{{ route('planning.rooms.toggle') }}';
    const CSRF       = '{{ csrf_token() }}';

    const ROLE_STYLES = {
        exam:        { bg: '#2563eb', border: '#2563eb' },
        preparation: { bg: '#ca8a04', border: '#ca8a04' },
        waiting:     { bg: '#16a34a', border: '#16a34a' },
        corridor:    { bg: '#475569', border: '#475569' },
    };
    const ROLE_LABELS = { exam: 'P', preparation: 'V', waiting: 'W', corridor: 'G' };

    /** Raum als verfügbar markieren / demarkieren */
    // Frueher Inline-Handler (Modul 7.2). Ueber document gebunden: die
    // Raumtabelle wird beim Umschalten neu aufgebaut, direkt gebundene
    // Listener waeren danach weg.
    document.addEventListener('click', (ereignis) => {
        if (ereignis.target.closest('[data-reload]')) {
            location.reload();

            return;
        }

        const rolle = ereignis.target.closest('[data-global-role]');

        if (rolle) {
            setGlobalRole(Number(rolle.dataset.room), rolle.dataset.globalRole);

            return;
        }

        const tagesrolle = ereignis.target.closest('[data-day-role]');

        if (tagesrolle) {
            toggleDayRole(
                tagesrolle,
                tagesrolle.dataset.date,
                Number(tagesrolle.dataset.room),
                tagesrolle.dataset.dayRole
            );
        }
    });

    document.addEventListener('change', (ereignis) => {
        const kaestchen = ereignis.target.closest('[data-toggle-available]');

        if (kaestchen) {
            toggleAvailable(kaestchen);
        }
    });

    async function toggleAvailable(checkbox) {
        const roomId = checkbox.dataset.room;
        const isNowChecked = checkbox.checked;
        const role   = isNowChecked ? (checkbox.dataset.currentRole || 'exam') : null;

        // UI-Update
        const card  = document.getElementById('room-card-' + roomId);
        const btns  = document.getElementById('global-btns-' + roomId);
        if (isNowChecked) {
            card.classList.add('border-blue-300', 'bg-blue-50');
            card.classList.remove('border-gray-200', 'bg-white');
            btns.classList.remove('opacity-30', 'pointer-events-none');
        } else {
            card.classList.remove('border-blue-300', 'bg-blue-50');
            card.classList.add('border-gray-200', 'bg-white');
            btns.classList.add('opacity-30', 'pointer-events-none');
            // Alle Rollen-Buttons deaktivieren
            renderGlobalBtns(roomId, null);
        }

        await saveRole('global', roomId, role);

        if (isNowChecked) {
            renderGlobalBtns(roomId, role);
            // Matrix-Zeilen zeigen/verstecken
        }
        refreshMatrixRows(roomId, isNowChecked);
    }

    /** Globale Rolle setzen; erneutes Klicken = Rolle nicht entfernen (bleibt immer eine aktiv) */
    async function setGlobalRole(roomId, role) {
        // Aktuelle Rolle ermitteln
        const btns = document.getElementById('global-btns-' + roomId);
        const currentRole = getActiveGlobalRole(btns);
        if (currentRole === role) return; // Keine Änderung
        renderGlobalBtns(roomId, role);
        // Checkbox-Dataset aktualisieren
        const cb = document.querySelector('[data-room="' + roomId + '"].room-available-checkbox');
        if (cb) cb.dataset.currentRole = role;
        await saveRole('global', roomId, role);
    }

    /** Tagesspezifische Rolle toggeln: gleich = zurück auf global, anders = setzen */
    async function toggleDayRole(btn, dateStr, roomId, role) {
        const group = btn.closest('[data-day-group]');
        const currentRole = group.dataset.activeRole || null;
        const newRole = (currentRole === role) ? null : role;

        renderDayBtns(group, newRole);
        group.dataset.activeRole = newRole ?? '';

        await saveRole(dateStr, roomId, newRole);
    }

    async function saveRole(date, roomId, role) {
        try {
            const res = await fetch(TOGGLE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ date, room_id: roomId, room_role: role }),
            });
            if (res.ok) {
                document.getElementById('reload-btn').style.display = '';
            } else {
                alert('Fehler beim Speichern.');
            }
        } catch (e) { alert('Netzwerkfehler.'); }
    }

    function getActiveGlobalRole(btnsEl) {
        for (const btn of btnsEl.querySelectorAll('.global-role-btn')) {
            if (btn.style.fontWeight === '600' || btn.style.fontWeight === 600) return getRoleFromLabel(btn.textContent.trim());
        }
        return null;
    }

    function getRoleFromLabel(label) {
        return { P: 'exam', V: 'preparation', W: 'waiting', G: 'corridor' }[label] ?? null;
    }

    function renderGlobalBtns(roomId, activeRole) {
        const btns = document.getElementById('global-btns-' + roomId);
        if (!btns) return;
        const roles = ['exam', 'preparation', 'waiting', 'corridor'];
        btns.querySelectorAll('.global-role-btn').forEach((btn, i) => {
            const r = roles[i];
            if (r === activeRole) {
                btn.style.cssText = `background:${ROLE_STYLES[r].bg};color:#fff;border-color:${ROLE_STYLES[r].border};font-weight:600`;
            } else {
                btn.style.cssText = 'background:#fff;color:#6b7280;border-color:#d1d5db';
            }
        });
    }

    function renderDayBtns(group, activeRole) {
        const roles = ['exam', 'preparation', 'waiting', 'corridor'];
        group.querySelectorAll('.day-role-btn').forEach((btn, i) => {
            const r = roles[i];
            if (r === activeRole) {
                btn.style.cssText = `background:${ROLE_STYLES[r].bg};color:#fff;border-color:${ROLE_STYLES[r].border};font-weight:600`;
            } else {
                btn.style.cssText = 'background:#fff;color:#6b7280;border-color:#d1d5db';
            }
        });
        // Inherited-Markierung aktualisieren
        const inherited = group.querySelector('.inherited-badge');
        if (inherited) inherited.style.display = activeRole ? 'none' : 'inline';
    }

    function refreshMatrixRows(roomId, show) {
        document.querySelectorAll('[data-matrix-room="' + roomId + '"]').forEach(row => {
            row.style.display = show ? '' : 'none';
        });
        // Hinweis ein-/ausblenden wenn gar keine Räume sichtbar
        const hint = document.getElementById('no-rooms-hint');
        if (hint) {
            const anyVisible = document.querySelector('[data-matrix-room]') &&
                [...document.querySelectorAll('[data-matrix-room]')].some(r => r.style.display !== 'none');
            hint.style.display = anyVisible ? 'none' : '';
        }
    }
    </script>
</x-app-layout>
