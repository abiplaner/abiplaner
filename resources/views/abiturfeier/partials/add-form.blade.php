{{--
    Inline-Formular zum Hinzufügen einer Ehrung.
    Parameter:
      $formMode    = 'by-type' | 'by-student'
      $awardTypeId (nur bei by-type) — vorausgewählte Preisart-ID
      $alreadyIds  (nur bei by-type) — student_ids die bereits diesen Preis haben
      $preselectedStudent (nur bei by-student)
      $usedAwardTypeIds   (nur bei by-student)
--}}
@php
    $alreadyIds       = $alreadyIds ?? [];
    $usedAwardTypeIds = $usedAwardTypeIds ?? [];
    $availableTypes   = $awardTypes->filter(fn($t) => !in_array($t->id, $usedAwardTypeIds));

    // Bei by-type: Schüler die diesen Preis noch nicht haben
    $selectableStudents = ($formMode === 'by-type')
        ? $students->filter(fn($s) => !in_array($s->id, $alreadyIds))
        : $students;
@endphp

{{-- In der Archivansicht (Modul 1.1) wird das komplette Formular ausgeblendet —
     zentral hier im Partial, das deckt alle @include-Stellen ab. --}}
@unlessarchive
<form method="POST" action="{{ route('abiturfeier.awards.store') }}"
      class="space-y-3 p-3 bg-gray-50 dark:bg-gray-700/60 rounded-lg border border-gray-200 dark:border-gray-600"
      x-data="{ search: '' }">
    @csrf
    <input type="hidden" name="exam_period_id" value="{{ $examPeriod->id }}">

    @if($formMode === 'by-type' && isset($awardTypeId))
        <input type="hidden" name="award_type_id" value="{{ $awardTypeId }}">
    @else
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Preisart *</label>
            <select name="award_type_id" required
                    class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                <option value="">— wählen —</option>
                @foreach($availableTypes as $at)
                    <option value="{{ $at->id }}">{{ $at->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    {{-- Schüler-Mehrfachauswahl --}}
    @if($formMode === 'by-student' && isset($preselectedStudent))
        <input type="hidden" name="student_ids[]" value="{{ $preselectedStudent->id }}">
    @else
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
                Schüler *
                <span class="font-normal text-gray-400">(Mehrfachauswahl möglich)</span>
            </label>

            {{-- Suchfeld --}}
            <input type="text" x-model="search" placeholder="Suchen…"
                   class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm mb-1">

            {{-- Scrollbare Checkbox-Liste --}}
            <div class="border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-600
                        overflow-y-auto" style="max-height: 180px;">
                @forelse($selectableStudents as $s)
                    <label class="flex items-center gap-2 px-3 py-1.5 hover:bg-blue-50 dark:hover:bg-blue-900/20
                                  cursor-pointer text-sm text-gray-800 dark:text-gray-200 border-b border-gray-100
                                  dark:border-gray-500 last:border-0"
                           x-show="search === '' || '{{ strtolower($s->lastname . ' ' . $s->firstname) }}'.includes(search.toLowerCase())">
                        <input type="checkbox" name="student_ids[]" value="{{ $s->id }}"
                               class="rounded border-gray-300 text-blue-600 shrink-0">
                        <span>{{ $s->lastname }}, {{ $s->firstname }}</span>
                        @if($s->class_name)
                            <span class="text-xs text-gray-400 ml-auto shrink-0">{{ $s->class_name }}</span>
                        @endif
                    </label>
                @empty
                    <p class="px-3 py-2 text-sm text-gray-400">Alle Schüler haben diesen Preis bereits.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Rang + Kandidat --}}
    <div class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">Platzierung</label>
            <select name="rank"
                    class="text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
                <option value="">— kein Rang —</option>
                <option value="1">1. Preisträger</option>
                <option value="2">2. Preisträger</option>
                <option value="3">3. Preisträger</option>
            </select>
        </div>
        <div class="flex items-center gap-2 pb-0.5">
            <input type="hidden" name="is_candidate" value="0">
            <input type="checkbox" name="is_candidate" value="1" id="is_cand_{{ $awardTypeId ?? 'new' }}"
                   class="rounded border-gray-300 text-blue-600">
            <label for="is_cand_{{ $awardTypeId ?? 'new' }}"
                   class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                Kandidat / Vorschlag
            </label>
        </div>
    </div>

    {{-- Notiz --}}
    <div>
        <label class="block text-xs font-medium text-gray-600 dark:text-gray-300 mb-1">
            Notiz
            <span class="font-normal text-gray-400">(optional — Fach, Thema, Punkte …)</span>
        </label>
        <input type="text" name="notes" maxlength="1000"
               placeholder="z. B. Ph/MNT, 817 Punkte"
               class="w-full text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-600 rounded-md shadow-sm">
    </div>

    {{-- Buttons --}}
    <div class="flex gap-2 pt-1">
        <button type="submit"
                class="px-3 py-1.5 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700">
            Speichern
        </button>
        <button type="button" @click="showAddForm = false"
                class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-sm rounded-md hover:bg-gray-300">
            Abbrechen
        </button>
    </div>
</form>
@endarchive
