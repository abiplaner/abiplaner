{{-- Ansicht: Nach Schüler --}}

@php
    $studentsWithAwards = $students->filter(fn($s) => $awardsByStudent->has($s->id))
        ->sortBy([['lastname', 'asc'], ['firstname', 'asc']]);
    $studentsWithoutAwards = $students->filter(fn($s) => !$awardsByStudent->has($s->id))
        ->sortBy([['lastname', 'asc'], ['firstname', 'asc']]);
@endphp

<div class="space-y-1">

    @forelse($studentsWithAwards as $student)
        @php $studentAwards = $awardsByStudent->get($student->id, collect()); @endphp
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
             x-data="{ open: true, showAddForm: false }"
             @close-add-form.window="showAddForm = false">

            <div class="flex items-center justify-between px-3 py-2 cursor-pointer select-none
                        border-b border-gray-100 dark:border-gray-700"
                 @click="open = !open">
                <div class="flex items-center gap-x-2 min-w-0">
                    <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform"
                         :class="open ? 'rotate-180' : ''"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                    <span class="font-medium text-sm text-gray-800 dark:text-gray-100 truncate">
                        {{ $student->lastname }}, {{ $student->firstname }}
                    </span>
                    <span class="text-xs text-gray-400 whitespace-nowrap">
                        · {{ $studentAwards->count() }} {{ $studentAwards->count() === 1 ? 'Ehrung' : 'Ehrungen' }}
                    </span>
                </div>
                @unlessarchive
                <button @click.stop="open = true; showAddForm = !showAddForm"
                        class="text-xs px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30
                               text-blue-600 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50
                               font-medium whitespace-nowrap shrink-0">
                    + Ehrung
                </button>
                @endarchive
            </div>

            <div x-show="open">
                @if($studentAwards->isNotEmpty())
                    <table class="min-w-full text-xs divide-y divide-gray-50 dark:divide-gray-700/50">
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-700/50">
                            @foreach($studentAwards as $award)
                                @php
                                    $awardType = $award->getRelation('awardType');
                                    $lk        = $awardType?->getRelation('teacher');
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                    <td class="px-3 py-1 font-medium text-gray-800 dark:text-gray-200">
                                        {{ $awardType?->name ?? '–' }}
                                    </td>
                                    <td class="px-3 py-1 text-gray-500 dark:text-gray-400">
                                        {{ $lk?->shortcode ?? '–' }}
                                    </td>
                                    <td class="px-3 py-1 text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                        {{ $award->notes ?: '' }}
                                    </td>
                                    <td class="px-3 py-1 text-right whitespace-nowrap">
                                        @unlessarchive
                                        <button
                                            @click="$dispatch('open-edit-modal', {{ json_encode([
                                                'id'               => $award->id,
                                                'student_name'     => $student->lastname . ', ' . $student->firstname,
                                                'award_type_name'  => $awardType?->name ?? '–',
                                                'description_text' => $award->descriptionText,
                                                'notes'            => $award->notes,
                                            ]) }})"
                                            class="text-blue-500 hover:text-blue-700 px-1" title="Bearbeiten">✎</button>
                                        <form method="POST"
                                              action="{{ route('abiturfeier.awards.destroy', $award) }}"
                                              class="inline"
                                              data-confirm="Ehrung entfernen?">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-gray-400 hover:text-red-500 px-1 font-bold" title="Entfernen">×</button>
                                        </form>
                                        @endarchive
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div x-show="showAddForm" class="px-3 pb-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                    @include('abiturfeier.partials.add-form', [
                        'formMode'           => 'by-student',
                        'preselectedStudent' => $student,
                        'usedAwardTypeIds'   => $studentAwards->pluck('award_type_id')->toArray(),
                    ])
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-center text-gray-500">
            Noch keine Ehrungen vergeben.
        </div>
    @endforelse

    {{-- Weiteren Schüler hinzufügen --}}
    @if($studentsWithoutAwards->isNotEmpty())
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden"
             x-data="{ open: false }">
            <div class="px-3 py-2 cursor-pointer select-none text-sm text-blue-600 font-medium"
                 @click="open = !open">
                + Weiteren Schüler mit Ehrung hinzufügen
            </div>
            <div x-show="open" class="px-3 pb-3 pt-2 border-t border-gray-100 dark:border-gray-700">
                @include('abiturfeier.partials.add-form', [
                    'formMode' => 'new-student',
                ])
            </div>
        </div>
    @endif
</div>
