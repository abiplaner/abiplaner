{{-- Ansicht: Nach Preisart — kompakte Chip-Darstellung --}}

@php $openTypeId = session('open_award_type_id'); @endphp

@if($awardTypes->isEmpty())
    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-8 text-center text-gray-500">
        Keine Preisarten vorhanden. Bitte zuerst über ⚙ Preisarten anlegen.
    </div>
@else
    <div class="space-y-1">
        @foreach($awardTypes as $awardType)
            @php
                $typeAwards  = $awardsByType->get($awardType->id, collect());
                $lk          = $awardType->getRelation('teacher');
                $isOpen      = $openTypeId == $awardType->id;
                $hasWinners  = $typeAwards->isNotEmpty();
                $noWinnerNote = $awardType->no_winner_note ?? '';
                // Schüler-IDs die bereits diesen Preis haben
                $alreadyIds  = $typeAwards->pluck('student_id')->toArray();
            @endphp

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
                 x-data="{ open: {{ $isOpen ? 'true' : 'false' }}, showAddForm: {{ $isOpen ? 'true' : 'false' }} }"
                 @close-add-form.window="showAddForm = false">

                {{-- ── Kompakte Header-Zeile ── --}}
                <div class="flex items-start gap-x-3 px-4 py-2.5 cursor-pointer select-none"
                     @click="open = !open; if(!open) showAddForm = false">

                    {{-- Chevron --}}
                    <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0 transition-transform"
                         :class="open ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>

                    {{-- Name + Meta --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center flex-wrap gap-x-2 gap-y-0.5">
                            <span class="font-medium text-sm text-gray-800 dark:text-gray-100">
                                {{ $awardType->name }}
                            </span>
                            @if($awardType->category)
                                <span class="text-xs text-gray-400">· {{ $awardType->category }}</span>
                            @endif
                            @if($lk)
                                <span class="text-xs text-blue-500">· {{ $lk->shortcode }}</span>
                            @endif
                            @if(!$hasWinners && $noWinnerNote)
                                <span class="text-xs text-amber-600 italic">· kein Preisträger</span>
                            @endif
                        </div>
                    </div>

                    {{-- Zähler + Add-Button --}}
                    <div class="flex items-center gap-2 shrink-0 mt-0.5" @click.stop>
                        @if($hasWinners)
                            <span class="text-xs text-gray-400">{{ $typeAwards->count() }}</span>
                        @endif
                        @unlessarchive
                        <button @click="open = true; showAddForm = !showAddForm"
                                class="text-xs px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30
                                       text-blue-600 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50
                                       font-medium whitespace-nowrap">
                            + Schüler
                        </button>
                        @endarchive
                    </div>
                </div>

                {{-- ── Aufgeklappter Bereich ── --}}
                <div x-show="open" class="border-t border-gray-100 dark:border-gray-700">

                    {{-- Hinweis "Kein Preisträger" --}}
                    @if(!$hasWinners && $noWinnerNote)
                        <div class="px-4 py-2 bg-amber-50 dark:bg-amber-900/20 text-sm text-amber-700 dark:text-amber-300">
                            ℹ {{ $noWinnerNote }}
                        </div>
                    @endif

                    {{-- Detail-Tabelle mit Bearbeiten-Button --}}
                    @if($hasWinners)
                        <table class="min-w-full text-xs divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-1.5 text-left font-medium text-gray-500 uppercase tracking-wide">Schüler</th>
                                    <th class="px-4 py-1.5 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                    <th class="px-4 py-1.5 text-left font-medium text-gray-500 uppercase tracking-wide">Notiz</th>
                                    <th class="px-4 py-1.5 w-8"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                                @foreach($typeAwards->sortBy(fn($a) => ($a->student?->lastname ?? '') . ($a->student?->firstname ?? '')) as $award)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <td class="px-4 py-1.5 text-gray-800 dark:text-gray-200 font-medium">
                                            {{ $award->student?->lastname }}, {{ $award->student?->firstname }}
                                        </td>
                                        <td class="px-4 py-1.5 text-gray-500 dark:text-gray-400">
                                            @if($award->is_candidate)
                                                <span class="text-blue-600 italic">Kandidat</span>
                                            @elseif($award->rank)
                                                <span class="text-yellow-700 font-semibold">{{ $award->rank }}. Preis</span>
                                            @else
                                                –
                                            @endif
                                        </td>
                                        <td class="px-4 py-1.5 text-gray-500 dark:text-gray-400 max-w-xs">
                                            {{ $award->notes ?: '' }}
                                        </td>
                                        <td class="px-4 py-1.5 text-right">
                                            @unlessarchive
                                            <button
                                                @click="$dispatch('open-edit-modal', {{ json_encode([
                                                    'id'               => $award->id,
                                                    'student_name'     => ($award->student?->lastname ?? '') . ', ' . ($award->student?->firstname ?? ''),
                                                    'award_type_name'  => $awardType->name,
                                                    'description_text' => $award->descriptionText,
                                                    'notes'            => $award->notes,
                                                    'rank'             => $award->rank,
                                                    'is_candidate'     => $award->is_candidate,
                                                ]) }})"
                                                class="text-blue-500 hover:text-blue-700" title="Bearbeiten">✎</button>
                                            @endarchive
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    {{-- Add-Form --}}
                    <div x-show="showAddForm" class="px-4 pb-4 pt-3">
                        @include('abiturfeier.partials.add-form', [
                            'formMode'        => 'by-type',
                            'awardTypeId'     => $awardType->id,
                            'alreadyIds'      => $alreadyIds,
                        ])
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
