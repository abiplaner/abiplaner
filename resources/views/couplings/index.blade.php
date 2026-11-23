<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Kopplungen verwalten') }} - {{ $course->course_code }} ({{ $course->subject->name }})
            </h2>
            <a href="{{ route('courses.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                ← Zurück zur Kursübersicht
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Success/Error Messages -->


            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Ungekoppelte Prüfungen -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Ungekoppelte Prüfungen ({{ $uncoupledExams->count() }})
                        </h3>

                        @if($uncoupledExams->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Alle Prüfungen sind bereits gekoppelt.
                            </p>
                        @else
                            @unlessarchive
                            <form id="coupling-form" action="{{ route('couplings.store') }}" method="POST">
                                @csrf
                                
                                <div class="space-y-2 mb-4">
                                    @foreach($uncoupledExams as $exam)
                                        <label class="flex items-center p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                                            <input 
                                                type="checkbox" 
                                                name="student_exam_ids[]" 
                                                value="{{ $exam->id }}"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            >
                                            <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">
                                                {{ $exam->student->lastname }}, {{ $exam->student->firstname }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <button 
                                    type="submit"
                                    id="couple-btn"
                                    class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled
                                >
                                    Ausgewählte koppeln
                                </button>
                            </form>

                            <script @cspNonce>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const checkboxes = document.querySelectorAll('input[name="student_exam_ids[]"]');
                                    const coupleBtn = document.getElementById('couple-btn');
                                    
                                    function updateButtonState() {
                                        const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
                                        coupleBtn.disabled = checkedCount < 2;
                                        coupleBtn.textContent = checkedCount >= 2 
                                            ? `${checkedCount} Prüfungen koppeln` 
                                            : 'Ausgewählte koppeln';
                                    }
                                    
                                    checkboxes.forEach(cb => {
                                        cb.addEventListener('change', updateButtonState);
                                    });
                                });
                            </script>
                            @else
                            {{-- Archivansicht: nur Namensliste, keine Kopplungs-Aktionen --}}
                            <div class="space-y-2 mb-4">
                                @foreach($uncoupledExams as $exam)
                                    <div class="p-3 text-sm text-gray-700 dark:text-gray-300">
                                        {{ $exam->student->lastname }}, {{ $exam->student->firstname }}
                                    </div>
                                @endforeach
                            </div>
                            @endarchive
                        @endif
                    </div>
                </div>

                <!-- Gekoppelte Gruppen -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                            Gekoppelte Gruppen ({{ $couplings->count() }})
                        </h3>

                        @if($couplings->isEmpty())
                            <p class="text-gray-500 dark:text-gray-400 text-sm">
                                Noch keine Kopplungen vorhanden.
                            </p>
                        @else
                            <div class="space-y-4">
                                @php
                                    $colors = [
                                        'bg-blue-100 dark:bg-blue-900/30 border-blue-300 dark:border-blue-700',
                                        'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700',
                                        'bg-purple-100 dark:bg-purple-900/30 border-purple-300 dark:border-purple-700',
                                        'bg-yellow-100 dark:bg-yellow-900/30 border-yellow-300 dark:border-yellow-700',
                                        'bg-pink-100 dark:bg-pink-900/30 border-pink-300 dark:border-pink-700',
                                        'bg-indigo-100 dark:bg-indigo-900/30 border-indigo-300 dark:border-indigo-700',
                                        'bg-red-100 dark:bg-red-900/30 border-red-300 dark:border-red-700',
                                        'bg-teal-100 dark:bg-teal-900/30 border-teal-300 dark:border-teal-700',
                                        'bg-orange-100 dark:bg-orange-900/30 border-orange-300 dark:border-orange-700',
                                        'bg-cyan-100 dark:bg-cyan-900/30 border-cyan-300 dark:border-cyan-700',
                                    ];
                                    $colorIndex = 0;
                                @endphp

                                @foreach($couplings as $couplingId => $exams)
                                    @php
                                        $color = $colors[$colorIndex % count($colors)];
                                        $colorIndex++;
                                    @endphp
                                    
                                    <div class="border-2 rounded-lg p-4 {{ $color }}">
                                        <div class="flex justify-between items-start mb-2">
                                            <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                                Kopplung {{ $loop->iteration }} 
                                                <span class="text-sm font-normal text-gray-600 dark:text-gray-400">
                                                    ({{ $exams->count() }} Schüler)
                                                </span>
                                            </h4>
                                            
                                            @unlessarchive
                                            <form action="{{ route('couplings.destroy') }}" method="POST" 
                                                  data-confirm="Kopplung wirklich auflösen?">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="coupling_id" value="{{ $couplingId }}">
                                                <button 
                                                    type="submit" 
                                                    class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-200 text-sm font-medium"
                                                >
                                                    Kopplung auflösen
                                                </button>
                                            </form>
                                            @endarchive
                                        </div>
                                        
                                        <ul class="space-y-1">
                                            @foreach($exams as $exam)
                                                <li class="text-sm text-gray-700 dark:text-gray-300 flex items-center">
                                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                                                    </svg>
                                                    {{ $exam->student->lastname }}, {{ $exam->student->firstname }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
