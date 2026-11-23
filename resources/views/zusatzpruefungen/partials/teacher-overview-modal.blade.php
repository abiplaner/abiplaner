{{-- ═══════════════════════════════════════════════════════════════
     Lehrerübersicht — Slide-over Modal
     Geöffnet via Alpine: showTeacherOverview = true
     Daten werden per AJAX von /zusatzpruefungen/teacher-overview geladen
     ═══════════════════════════════════════════════════════════════ --}}
<div x-show="showTeacherOverview"
     x-cloak
     class="fixed inset-0 z-50 flex"
     @keydown.escape.window="showTeacherOverview = false">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"
         @click="showTeacherOverview = false"></div>

    {{-- Panel --}}
    <div class="relative ml-auto w-full max-w-4xl h-full bg-white shadow-2xl flex flex-col"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 bg-gray-50 shrink-0">
            <div class="flex items-center gap-3">
                <h2 class="text-base font-semibold text-gray-800">Beteiligte Lehrkräfte</h2>
                <template x-if="teacherOverviewData.length > 0">
                    <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium"
                          x-text="teacherOverviewData.length + ' Lehrkräfte'"></span>
                </template>
            </div>
            <div class="flex items-center gap-2">
                {{-- Suchfeld --}}
                <div class="relative">
                    <input type="text"
                           x-model="teacherOverviewSearch"
                           placeholder="Suchen …"
                           class="text-sm border border-gray-300 rounded px-3 py-1.5 pr-7 w-44 focus:ring-1 focus:ring-blue-400 focus:outline-none">
                    <button x-show="teacherOverviewSearch"
                            @click="teacherOverviewSearch = ''"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-lg leading-none">×</button>
                </div>
                {{-- PDF-Link --}}
                <a href="{{ route('zusatzpruefungen.export.teacher-list-pdf') }}"
                   target="_blank"
                   class="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-red-50 hover:bg-red-100 text-red-700 rounded border border-red-200">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    PDF
                </a>
                <button @click="showTeacherOverview = false"
                        class="text-gray-400 hover:text-gray-600 ml-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto">

            {{-- Loading --}}
            <div x-show="teacherOverviewLoading" class="flex items-center justify-center py-20 text-gray-400 text-sm gap-2">
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                Lade Daten …
            </div>

            {{-- Error --}}
            <div x-show="teacherOverviewError && !teacherOverviewLoading"
                 class="m-5 p-4 bg-red-50 border border-red-200 rounded text-sm text-red-700"
                 x-text="teacherOverviewError"></div>

            {{-- Leer --}}
            <div x-show="!teacherOverviewLoading && !teacherOverviewError && teacherOverviewData.length === 0"
                 class="flex flex-col items-center justify-center py-20 text-gray-400 text-sm gap-2">
                <svg class="w-10 h-10 opacity-30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/></svg>
                Keine platzierten Prüfungen oder Aufsichten vorhanden.
            </div>

            {{-- Lehrkräfte-Liste --}}
            <template x-if="!teacherOverviewLoading && !teacherOverviewError && teacherOverviewData.length > 0">
                <div>
                    {{-- Keine Treffer bei Suche --}}
                    <template x-if="teacherOverviewFiltered.length === 0">
                        <div class="py-10 text-center text-gray-400 text-sm">Keine Lehrkraft gefunden.</div>
                    </template>

                    <template x-for="(teacher, tIdx) in teacherOverviewFiltered" :key="teacher.id">
                        <div class="border-b border-gray-100">

                            {{-- Lehrer-Header (klappbar) --}}
                            <button class="w-full flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors text-left"
                                    @click="teacher._open = !teacher._open">
                                {{-- Pfeil --}}
                                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform duration-150"
                                     :class="teacher._open ? 'rotate-90' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                                {{-- Kürzel-Badge --}}
                                <span class="text-xs font-bold font-mono bg-blue-900 text-white px-2 py-0.5 rounded shrink-0"
                                      x-text="teacher.shortcode"></span>
                                {{-- Name --}}
                                <span class="font-medium text-gray-800 text-sm" x-text="teacher.name"></span>
                                {{-- Badges --}}
                                <span class="ml-auto flex items-center gap-1.5 shrink-0">
                                    <template x-if="teacher.pruef_count > 0">
                                        <span class="text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full"
                                              x-text="teacher.pruef_count + (teacher.pruef_count === 1 ? ' Prüfung' : ' Prüfungen')"></span>
                                    </template>
                                    <template x-if="teacher.aufs_count > 0">
                                        <span class="text-xs bg-sky-50 text-sky-700 border border-sky-200 px-2 py-0.5 rounded-full"
                                              x-text="teacher.aufs_count + (teacher.aufs_count === 1 ? ' Aufsicht' : ' Aufsichten')"></span>
                                    </template>
                                </span>
                            </button>

                            {{-- Einträge-Tabelle --}}
                            <div x-show="teacher._open" x-cloak class="px-5 pb-3">
                                <table class="w-full text-xs border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-500 uppercase tracking-wide">
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-20">Funktion</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-24">Datum</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-24">Vorber.-Zeit</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-24">Prüfungs-Zeit</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-14">Raum</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200">Schüler/in</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200">Fach (Kurs)</th>
                                            <th class="px-2 py-1.5 text-left font-medium border-b border-gray-200 w-16">Mitpr.</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(entry, eIdx) in teacher.entries" :key="eIdx">
                                            <tr :class="entry.type === 'aufsicht' ? 'bg-sky-50/60' : (eIdx % 2 === 0 ? 'bg-white' : 'bg-gray-50/50')"
                                                class="border-b border-gray-100 hover:bg-blue-50/30 transition-colors">

                                                {{-- Funktion --}}
                                                <td class="px-2 py-1.5 font-medium whitespace-nowrap">
                                                    <template x-if="entry.type === 'pruefung' && entry.role === 'Erstprüfer'">
                                                        <span class="text-blue-900">Erstprüfer</span>
                                                    </template>
                                                    <template x-if="entry.type === 'pruefung' && entry.role === 'Zweitkorrektor'">
                                                        <span class="text-gray-600">Zweitkorrektor</span>
                                                    </template>
                                                    <template x-if="entry.type === 'pruefung' && entry.role === 'Gast'">
                                                        <span class="text-gray-400 italic">Gast</span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-sky-700">Aufsicht</span>
                                                    </template>
                                                </td>

                                                {{-- Datum --}}
                                                <td class="px-2 py-1.5 whitespace-nowrap text-gray-600" x-text="entry.date"></td>

                                                {{-- Vorbereitungszeit / Aufsichtszeit --}}
                                                <td class="px-2 py-1.5 whitespace-nowrap font-mono text-gray-700"
                                                    x-text="entry.type === 'pruefung' ? entry.prep_time : entry.time_range"></td>

                                                {{-- Prüfungszeit --}}
                                                <td class="px-2 py-1.5 whitespace-nowrap font-mono text-gray-700">
                                                    <template x-if="entry.type === 'pruefung'">
                                                        <span x-text="entry.exam_time"></span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-gray-300">–</span>
                                                    </template>
                                                </td>

                                                {{-- Raum --}}
                                                <td class="px-2 py-1.5 text-center font-semibold">
                                                    <template x-if="entry.type === 'pruefung'">
                                                        <span class="text-gray-700" x-text="entry.exam_room"></span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-sky-700" x-text="entry.prep_room"></span>
                                                    </template>
                                                </td>

                                                {{-- Schüler --}}
                                                <td class="px-2 py-1.5">
                                                    <template x-if="entry.type === 'pruefung'">
                                                        <span>
                                                            <span :class="entry.absent ? 'line-through text-gray-400' : 'text-gray-800'"
                                                                  x-text="entry.student_name"></span>
                                                            <template x-if="entry.absent">
                                                                <span class="text-red-400 ml-1 text-xs">(n. angetre.)</span>
                                                            </template>
                                                            <template x-if="!entry.absent && entry.nta_extra > 0">
                                                                <span class="ml-1 text-xs bg-yellow-100 text-yellow-700 px-1 rounded">NTA</span>
                                                            </template>
                                                        </span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-gray-400 italic">Vorbereitungsraum</span>
                                                    </template>
                                                </td>

                                                {{-- Fach --}}
                                                <td class="px-2 py-1.5 text-gray-600">
                                                    <template x-if="entry.type === 'pruefung'">
                                                        <span x-text="entry.subject"></span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-gray-300">–</span>
                                                    </template>
                                                </td>

                                                {{-- Mitprüfer --}}
                                                <td class="px-2 py-1.5 text-gray-500">
                                                    <template x-if="entry.type === 'pruefung'">
                                                        <span x-text="entry.co_examiners || '–'"></span>
                                                    </template>
                                                    <template x-if="entry.type === 'aufsicht'">
                                                        <span class="text-gray-300">–</span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    </template>
                </div>
            </template>
        </div>

    </div>
</div>
