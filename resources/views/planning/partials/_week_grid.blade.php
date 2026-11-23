                <div class="md:col-span-4 h-full overflow-y-auto">
                    <div class="bg-white shadow-sm sm:rounded-lg flex flex-col h-full">
                        <div class="p-4 border-b flex-shrink-0">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-lg">Prüfungsplan - Matrix (Zeitslots × Räume)</h3>
                                
                                {{-- Wochen-Tabs --}}
                                <div class="flex space-x-2">
                                    <button data-aktion="show-week" data-week="1" 
                                            id="week-1-tab"
                                            class="week-tab px-4 py-2 rounded-md bg-blue-600 text-white">
                                        Woche 1
                                    </button>
                                    <button data-aktion="show-week" data-week="2" 
                                            id="week-2-tab"
                                            class="week-tab px-4 py-2 rounded-md bg-gray-300 text-gray-700">
                                        Woche 2
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        @php
                            // Coupling-Farbzuweisung: Jede eindeutige coupling_id bekommt eine Farbe aus der Palette
                            $couplingPalette = ['#FDE68A','#A7F3D0','#BFDBFE','#FBCFE8','#DDD6FE','#FCA5A5','#BAE6FD','#BBF7D0','#FED7AA','#E9D5FF'];
                            $couplingColorMap = [];
                            $colorIndex = 0;
                            foreach ($timeSlots as $daySlots) {
                                foreach ($daySlots as $slot) {
                                    foreach ($slot->examAssignments as $a) {
                                        $cid = $a->studentExam->coupling_id ?? null;
                                        if ($cid && !isset($couplingColorMap[$cid])) {
                                            $couplingColorMap[$cid] = $couplingPalette[$colorIndex % count($couplingPalette)];
                                            $colorIndex++;
                                        }
                                    }
                                }
                            }
                        @endphp
                        <div id="planning-right-scroll" class="p-4 overflow-x-auto flex-1 overflow-y-auto">
                            {{-- Woche 1 --}}
                            <div id="week-1-content" class="week-content">
                                @include('planning.partials._week_grid_woche', ['woche' => 1])
                            </div>

                            {{-- Woche 2 --}}
                            <div id="week-2-content" class="week-content hidden">
                                @include('planning.partials._week_grid_woche', ['woche' => 2])
                            </div>
                        </div>
                    </div>
