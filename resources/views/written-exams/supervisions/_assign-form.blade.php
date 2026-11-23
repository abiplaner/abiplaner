{{--
    Wiederverwendbares Zuweisungs-Formular für eine Aufsicht.
    Parameter:
      $writtenExamDate   – WrittenExamYearDate Model (der Name der Variablen ist historisch)
      $slot              – WrittenExamSupervisionTimeSlot|null
      $suggestions       – Collection mit Gruppen (q13_lesson, free_already_there, free, has_lesson)
      $hiddenFields      – array<string, mixed>  z.B. ['supervision_type' => 'corridor', 'central_supervision_type_id' => 5]
      $ringColor         – string  Tailwind-Farbname für den Focus-Ring (indigo, purple, amber, orange, ...)

    In der Archivansicht (Modul 1.1) wird das komplette Formular ausgeblendet —
    zentral hier im Partial, das deckt alle @include-Stellen im Aufsichtsplan ab.
--}}
@unlessarchive
<form method="POST"
      action="{{ route('written-exams.supervisions.assign-teacher', $writtenExamDate) }}"
      class="flex items-center gap-1 mt-1">
    @csrf

    {{-- Dynamische hidden fields --}}
    @foreach($hiddenFields as $fieldName => $fieldValue)
        <input type="hidden" name="{{ $fieldName }}" value="{{ $fieldValue }}">
    @endforeach

    @if($slot)
        <input type="hidden" name="written_exam_supervision_time_slot_id" value="{{ $slot->id }}">
    @endif

    <select name="kuerzel"
            data-submit-on-change
            class="w-32 text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 rounded py-1 px-1 focus:ring-{{ $ringColor }}-500 focus:border-{{ $ringColor }}-500"
            required>
        <option value="" disabled selected>Kürzel</option>

        @if(count($suggestions->get('q13_lesson', [])) > 0)
            <optgroup label="Q13 Unterricht">
                @foreach($suggestions->get('q13_lesson', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif

        @if(count($suggestions->get('free_already_there', [])) > 0)
            <optgroup label="Freistunde (Vorher Unterr.)">
                @foreach($suggestions->get('free_already_there', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif

        @if(count($suggestions->get('free', [])) > 0)
            <optgroup label="Freistunde">
                @foreach($suggestions->get('free', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif

        @if(count($suggestions->get('has_lesson', [])) > 0)
            <optgroup label="Anderer Unterricht">
                @foreach($suggestions->get('has_lesson', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif

        @if(count($suggestions->get('trainee', [])) > 0)
            <optgroup label="Studienreferendare">
                @foreach($suggestions->get('trainee', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif

        @if(count($suggestions->get('no_title', [])) > 0)
            <optgroup label="Ohne Amtsbezeichnung">
                @foreach($suggestions->get('no_title', []) as $t)
                    <option value="{{ $t->shortcode }}">{{ $t->shortcode }} ({{ $t->lastname }})</option>
                @endforeach
            </optgroup>
        @endif
    </select>
</form>
@endarchive
