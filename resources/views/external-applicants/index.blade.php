<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Externe Bewerber') }} (§61 GSO) - {{ $year }}
            </h2>
            <div class="flex space-x-3">
                @unlessarchive
                @can('create', App\Models\ExternalApplicant::class)
                <a href="{{ route('external-applicants.create', ['year' => $year]) }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Neuer Bewerber
                </a>
                @endcan
                @endarchive
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Jahresfilter -->
            <div class="mb-6">
                <form method="GET" action="{{ route('external-applicants.index') }}" class="flex items-center space-x-4">
                    <label for="year" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                        Prüfungsjahr:
                    </label>
                    <select name="year" id="year" 
                            data-submit-on-change
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        @foreach($years as $y)
                            <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>
                                {{ $y }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>

            {{--
                A8/B5-Fristen (§ 61 Abs. 1 S. 7 / Abs. 2 S. 5), Entscheidung 57
                (01.09.2026): reine Anzeige, keine Sperre. `$fristen` ist null,
                wenn für dieses Jahr noch keine zentrale Prüfungsperiode
                angelegt ist.
            --}}
            @if($fristen)
                <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="{{ $fristen['a8']['verstrichen'] ? 'bg-amber-50 border-amber-400 text-amber-900' : 'bg-blue-50 border-blue-400 text-blue-700' }} border-l-4 rounded-md p-4 text-sm">
                        <p class="font-medium">A8 — Antrag auf Zusatzprüfung (§ 61 Abs. 1 S. 7)</p>
                        <p>
                            Frist: {{ $fristen['a8']['datum']->format('d.m.Y') }}
                            @if($fristen['a8']['verstrichen'])
                                <span class="font-medium">— verstrichen</span>
                            @endif
                        </p>
                    </div>
                    <div class="{{ $fristen['b5']['verstrichen'] ? 'bg-amber-50 border-amber-400 text-amber-900' : 'bg-blue-50 border-blue-400 text-blue-700' }} border-l-4 rounded-md p-4 text-sm">
                        <p class="font-medium">B5 — Fächertausch bis 31. Januar (§ 61 Abs. 2 S. 5)</p>
                        <p>
                            Frist: {{ $fristen['b5']['datum']->format('d.m.Y') }}
                            @if($fristen['b5']['verstrichen'])
                                <span class="font-medium">— verstrichen</span>
                            @endif
                        </p>
                    </div>
                </div>
            @endif

            <!-- Bewerberliste -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($applicants->isEmpty())
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Keine externen Bewerber</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Legen Sie den ersten externen Bewerber an.</p>
                            @can('create', App\Models\ExternalApplicant::class)
                            <div class="mt-6">
                                <a href="{{ route('external-applicants.create') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                    Neuer Bewerber
                                </a>
                            </div>
                            @endcan
                        </div>
                    @else
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Geburtsdatum
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Kontakt
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Fächer
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Geplant
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Aktionen
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">

                                @foreach($applicants as $applicant)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $applicant->full_name }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $applicant->birth_date->format('d.m.Y') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">
                                            {{ $applicant->email ?? '-' }}
                                        </div>
                                        @if($applicant->phone)
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ $applicant->phone }}
                                        </div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $applicant->subject_count == 8 ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                                            {{ $applicant->subject_count }}/8
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $applicant->planned_count == $applicant->plannable_count ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $applicant->planned_count }}/{{ $applicant->plannable_count }}
                                        </span>
                                        {{-- § 64 Abs. 2: zwei der acht Fächer werden eingebracht
                                             statt geprüft, der Nenner ist dann sechs. --}}
                                        @if($applicant->plannable_count < $applicant->subject_count)
                                            <span class="ml-1 text-xs text-gray-500 dark:text-gray-400"
                                                  title="§ 64 Abs. 2: Ergebnis aus 13/II der Ersatzschule">
                                                +{{ $applicant->subject_count - $applicant->plannable_count }} aus 13/II
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{-- Entscheidung 51 (01.09.2026): geht dem Validierungsstatus vor —
                                             ein abgebrochener Bewerber ist kein offener Fächerwahl-Fehler mehr. --}}
                                        @if($applicant->istAbgebrochen())
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800"
                                                  title="Abgebrochen am {{ $applicant->abgebrochen_am->format('d.m.Y') }}">
                                                ⛔ Abgebrochen
                                            </span>
                                        @elseif($applicant->validation_status == 'valid')
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                ✅ Gültig
                                            </span>
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800" 
                                                  title="{{ implode(', ', $applicant->validation_errors) }}">
                                                ⚠️ Fehler
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            
                                            <!-- PDF-Export (nur wenn Fächerwahl vorhanden) -->
                                            @if($applicant->subject_count > 0)
                                            <a href="{{ route('external-applicants.export-pdf', $applicant) }}" 
                                               class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300"
                                               title="Prüfungsplan als PDF"
                                               target="_blank">
                                                📄 PDF
                                            </a>
                                            @endif
                                            
                                            @unlessarchive
                                            @can('update', $applicant)
                                            <!-- StudentExams erstellen (nur wenn Fächerwahl gültig) -->
                                            @if($applicant->validation_status == 'valid' && $applicant->planned_count < $applicant->plannable_count)
                                            <form action="{{ route('external-applicants.create-student-exams', $applicant) }}" 
                                                  method="POST" 
                                                  class="inline">
                                                @csrf
                                                <button type="submit" 
                                                        class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300"
                                                        title="StudentExams für Planung erstellen">
                                                    📋 Planen
                                                </button>
                                            </form>
                                            @endif
                                            @endcan

                                            {{-- Der § 62-Kalkulator. Bewusst hier und nicht nur im
                                                 Zusatzprüfungs-Modul: dort hängt der Knopf an der
                                                 Zusatzprüfungs-Liste, und wer keine hat, steht dort
                                                 nicht — der zweite Prüfungsteil betrifft aber jeden
                                                 externen Prüfling. --}}
                                            @if($applicant->planned_count > 0)
                                            <a href="{{ route('external-applicants.mindestpunkte', $applicant) }}"
                                               class="text-amber-600 hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-300"
                                               title="Mindestpunkte nach § 62">
                                                📊 Punkte
                                            </a>
                                            @endif

                                            <a href="{{ route('external-applicants.subjects.edit', $applicant) }}" 
                                               class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                               title="Fächerwahl">
                                                📝 Fächer
                                            </a>
                                            @can('update', $applicant)
                                            <a href="{{ route('external-applicants.edit', $applicant) }}" 
                                               class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                               title="Bearbeiten">
                                                ✏️
                                            </a>
                                            @endcan
                                            @can('delete', $applicant)
                                            <form action="{{ route('external-applicants.destroy', $applicant) }}" 
                                                  method="POST" 
                                                  class="inline"
                                                  data-confirm="Wirklich löschen?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                        title="Löschen">
                                                    🗑️
                                                </button>
                                            </form>
                                            @endcan
                                            @endarchive
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    
                </div>
            </div>
            
        </div>
    </div>
</x-app-layout>
