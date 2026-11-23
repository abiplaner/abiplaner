{{-- Prüfer-Drawer (rechts einschiebendes Panel) --}}
<div x-show="drawer.open" x-cloak class="fixed inset-0 flex justify-end" style="z-index:60" @keydown.escape.window="drawer.open = false">
    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/30" @click="drawer.open = false"></div>

    {{-- Panel --}}
    <div class="relative z-50 bg-white w-96 max-w-full h-full shadow-xl flex flex-col"
         x-show="drawer.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="translate-x-full">

        {{-- Header --}}
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h2 class="font-semibold text-gray-800">Prüfer bearbeiten</h2>
            <button @click="drawer.open = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">×</button>
        </div>

        {{-- Inhalt --}}
        <div class="flex-1 overflow-y-auto px-4 py-4">
            <template x-if="drawer.pruefungData">
                <div>
                    <div class="mb-4 text-sm">
                        <div class="font-semibold text-gray-800" x-text="drawer.pruefungData.student"></div>
                        <div class="text-gray-500" x-text="drawer.pruefungData.subject"></div>
                        <div x-show="drawer.pruefungData.nta"
                             class="inline-block text-xs mt-1 px-1.5 py-0.5 bg-yellow-100 text-yellow-700 rounded">
                            NTA +<span x-text="drawer.pruefungData.ntaPct"></span> %
                        </div>
                    </div>

                    {{-- Termin + Raum (nur wenn platziert) --}}
                    <template x-if="drawer.pruefungData.slot">
                        <div class="mb-4 rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs space-y-1">
                            <div class="font-semibold text-blue-800 mb-1">Prüfungstermin</div>
                            <div class="flex items-center gap-2 text-gray-700">
                                <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span x-text="drawer.pruefungData.slot.date"></span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-700">
                                <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>
                                    Vorbereitung: <strong x-text="drawer.pruefungData.slot.prepStart"></strong>
                                    &nbsp;·&nbsp;
                                    Prüfung: <strong x-text="drawer.pruefungData.slot.examStart + ' – ' + drawer.pruefungData.slot.examEnd"></strong>
                                </span>
                            </div>
                            <div class="flex items-center gap-2 text-gray-700">
                                <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                <span>
                                    Prüfungsraum: <strong x-text="drawer.pruefungData.slot.examRoom"></strong>
                                    <template x-if="drawer.pruefungData.slot.prepRoom">
                                        <span>&nbsp;·&nbsp; Vorbereitungsraum: <strong x-text="drawer.pruefungData.slot.prepRoom"></strong></span>
                                    </template>
                                </span>
                            </div>
                        </div>
                    </template>

                    {{-- Aktuelle Prüfer (Leseansicht) --}}
                    <template x-if="drawer.pruefungData.pruefer && drawer.pruefungData.pruefer.some(p => p.teacher_name && p.teacher_id)">
                        <div class="mb-4 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs space-y-1">
                            <div class="font-semibold text-gray-600 mb-1">Aktuelle Prüfer</div>
                            <template x-for="p in drawer.pruefungData.pruefer.filter(p => p.teacher_id)" :key="p.teacher_id">
                                <div class="flex items-center gap-2 text-gray-700">
                                    <span class="w-20 shrink-0 text-gray-400"
                                          x-text="p.role === 'erstpruefer' ? '1. Prüfer' : (p.role === 'zweitkorrektor' ? '2. Korrektor' : 'Gast')"></span>
                                    <span class="font-medium" x-text="p.teacher_name"></span>
                                </div>
                            </template>
                        </div>
                    </template>

                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Prüfer bearbeiten</div>
                    <div class="space-y-2 mb-4">
                        <template x-for="(p, i) in drawer.pruefer" :key="i">
                            <div class="flex gap-2 items-center">
                                <select x-model="p.role"
                                    class="text-xs border-gray-300 rounded py-1">
                                    <option value="erstpruefer">1. Prüfer</option>
                                    <option value="zweitkorrektor">2. Korrektor</option>
                                    <option value="gast">Gast</option>
                                </select>
                                <select x-model="p.teacher_id"
                                    class="flex-1 text-xs border-gray-300 rounded py-1">
                                    <option value="">– Lehrkraft –</option>
                                    <template x-for="t in filteredTeachersForDrawer()" :key="t.id">
                                        <option :value="t.id" x-text="t.shortcode + ' ' + t.lastname"></option>
                                    </template>
                                </select>
                                <button @click="removePruefer(i)"
                                    class="text-red-400 hover:text-red-600 text-sm shrink-0">×</button>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addPruefer()"
                        class="text-sm text-blue-500 hover:text-blue-700">+ Weitere Person</button>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-4 py-3 border-t border-gray-200 flex gap-2">
            <button type="button" @click="savePruefer()"
                class="flex-1 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded">
                Speichern
            </button>
            <button type="button" @click="drawer.open = false"
                class="px-4 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded">
                Abbrechen
            </button>
        </div>
    </div>
</div>
