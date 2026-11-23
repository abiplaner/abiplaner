<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $title }}
            </h2>
            <a href="{{ route('timetable.exam-schedule') }}" class="text-blue-600 hover:text-blue-800">
                ← Zurück zum Klausurenplan
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('timetable.view') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- School Year -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Schuljahr</label>
                            <select name="school_year" class="w-full border-gray-300 rounded-md shadow-sm">
                                @foreach(\App\Support\SchoolYear::options($schoolYear) as $jahr)
                                    <option value="{{ $jahr }}" {{ $schoolYear === $jahr ? 'selected' : '' }}>{{ $jahr }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Class Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Klasse</label>
                            <select name="class" class="w-full border-gray-300 rounded-md shadow-sm" {{ $teacherCode ? 'disabled' : '' }} data-submit-on-change>
                                @foreach($classes as $class)
                                    <option value="{{ $class }}" {{ $className == $class ? 'selected' : '' }}>
                                        {{ $class }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Teacher Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Lehrkraft (optional)</label>
                            <select name="teacher" class="w-full border-gray-300 rounded-md shadow-sm" data-submit-on-change>
                                <option value="">Keine Auswahl</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher }}" {{ $teacherCode == $teacher ? 'selected' : '' }}>
                                        {{ $teacher }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-end">
                            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                                Anzeigen
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Timetable Grid -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if($entries->isEmpty())
                        <div class="text-center py-12 text-gray-500">
                            Keine Stundenplandaten gefunden.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase w-24">
                                            Stunde
                                        </th>
                                        @foreach($days as $dayNum => $dayName)
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                {{ $dayName }}
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @for($period = 1; $period <= $maxPeriod; $period++)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-3 text-sm font-medium text-gray-900">
                                                {{ $period }}
                                            </td>
                                            @foreach($days as $dayNum => $dayName)
                                                @php
                                                    $dayEntries = $entries->get($dayNum, collect())
                                                        ->where('period', $period)
                                                        ->values();
                                                @endphp
                                                
                                                <td class="px-3 py-3 text-sm align-top border-l border-gray-200">
                                                    @if($dayEntries->isNotEmpty())
                                                        @foreach($dayEntries as $entry)
                                                            @php
                                                                $isOberstufe = in_array($entry->class_name, ['Q12', 'Q13'], true);
                                                                $isBereitschaft = $entry->course_code === 'BER';
                                                                $subjectLabel = $entry->subject;
                                                                $subjectFromCourse = null;

                                                                if ($entry->course_code && preg_match('/^([A-Za-zÄÖÜäöü]+)/u', $entry->course_code, $matches)) {
                                                                    $subjectFromCourse = $matches[1];
                                                                }
                                                                $subjectCode = null;
                                                                if ($subjectLabel) {
                                                                    $subjectCode = $subjectCodeMap[mb_strtolower($subjectLabel)] ?? null;
                                                                }
                                                                if (!$subjectFromCourse && !$subjectCode && $subjectLabel) {
                                                                    $subjectFromCourse = mb_substr($subjectLabel, 0, 1);
                                                                }

                                                                $subjectBase = $subjectCode ?: $subjectFromCourse ?: $subjectLabel;

                                                                if ($isBereitschaft) {
                                                                    $primaryLabel = 'BER';
                                                                    $secondaryLabel = null;
                                                                } elseif ($isOberstufe) {
                                                                    $primaryLabel = $subjectBase ?: $entry->course_code;
                                                                    if ($subjectBase) {
                                                                        $primaryLabel = ucfirst(mb_strtolower($primaryLabel));
                                                                    }
                                                                    $secondaryLabel = ($subjectCode || $subjectFromCourse || $subjectLabel) ? $entry->course_code : null;
                                                                } else {
                                                                    $primaryLabel = $subjectBase ?: $entry->course_code ?: $entry->class_name;
                                                                    if ($subjectBase) {
                                                                        $primaryLabel = ucfirst(mb_strtolower($primaryLabel));
                                                                    }
                                                                    $secondaryLabel = ($entry->class_name && $entry->class_name !== $primaryLabel)
                                                                        ? $entry->class_name
                                                                        : null;
                                                                }
                                                            @endphp
                                                            
                                                            <div class="mb-1 last:mb-0 p-1.5 bg-blue-50 rounded border border-blue-200 text-xs">
                                                                <span class="font-semibold text-blue-900">{{ $primaryLabel }}</span>
                                                                @if($secondaryLabel)
                                                                    <span class="text-blue-700">{{ $secondaryLabel }}</span>
                                                                @endif
                                                                @if(!$teacherCode)
                                                                    <span class="text-gray-600">({{ $entry->teacher?->shortcode }})</span>
                                                                @endif
                                                                @if($entry->room)
                                                                    <span class="text-gray-700">{{ $entry->room }}</span>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    @else
                                                        <div class="text-gray-300 text-center">—</div>
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>

                        <!-- Statistics -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">Wochenstunden:</span>
                                    <span class="font-semibold ml-2">
                                        {{ $entries->flatten(1)->count() }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">Verschiedene Fächer:</span>
                                    <span class="font-semibold ml-2">
                                        {{ $entries->flatten(1)->pluck('subject')->unique()->filter()->count() }}
                                    </span>
                                </div>
                                @if(!$teacherCode)
                                    <div>
                                        <span class="text-gray-500">Lehrkräfte:</span>
                                        <span class="font-semibold ml-2">
                                            {{ $entries->flatten(1)->pluck('teacher.shortcode')->unique()->count() }}
                                        </span>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-gray-500">Räume:</span>
                                    <span class="font-semibold ml-2">
                                        {{ $entries->flatten(1)->pluck('room')->unique()->filter()->count() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
