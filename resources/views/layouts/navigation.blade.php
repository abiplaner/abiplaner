<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo & Schuljahr-Kennzeichnung -->
                <div class="shrink-0 flex items-center mr-6 gap-2.5">
                    <a href="{{ auth()->user()->isCentralAdmin() ? route('admin.dashboard') : route('dashboard') }}" class="flex items-center">
                         <img class="block h-10 w-auto" src="{{ asset('assets/landing/logo.png') }}" alt="AbiPlaner Logo" />
                    </a>

                    @php
                        $periodSwitcher = app(\App\Services\CurrentExamPeriodService::class);
                        $switcherCurrent = auth()->user()->isCentralAdmin() ? null : $periodSwitcher->current();
                        $isArchive = $periodSwitcher->isArchiveView();
                    @endphp

                    @if($switcherCurrent)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $isArchive ? 'bg-amber-100 text-amber-900 border-amber-300 shadow-sm' : 'bg-slate-100 dark:bg-gray-700 text-slate-700 dark:text-gray-300 border-slate-200 dark:border-gray-600' }}">
                            <span class="inline-block w-1.5 h-1.5 rounded-full {{ $isArchive ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                            <span>{{ $isArchive ? 'Archiv ' . $switcherCurrent->year : 'Abi ' . $switcherCurrent->year }}</span>
                        </span>
                    @endif
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:flex sm:items-center">
                    
                    @if(auth()->user()->isSchoolPlanner())
                        @php
                            // ExamPeriods sind jetzt global - nehme den neuesten
                            $activeExamPeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
                            
                            // Check active states for menu items
                            $kolloquiumActive = request()->routeIs('planning.*') || 
                                              request()->routeIs('kolloquium.forms.*') || 
                                              request()->routeIs('timetable.teacher-availability') || 
                                              request()->routeIs('supervisions.*') ||
                                              request()->routeIs('zusatzpruefungen.*');
                            
                            $abiturSchriftlichActive = request()->routeIs('written-exams.*') || 
                                                      request()->routeIs('students.*') || 
                                                      request()->routeIs('external-applicants.*') ||
                                                      request()->routeIs('exam-board.*') ||
                                                      request()->routeIs('abiturfeier.*');
                            
                            $stammdatenActive = request()->routeIs('students.index') || request()->routeIs('students.create') || request()->routeIs('students.edit') ||
                                request()->routeIs('students.store') || request()->routeIs('students.update') || request()->routeIs('students.destroy') ||
                                request()->routeIs('courses.*') || request()->routeIs('teachers.*') || request()->routeIs('rooms.*') ||
                                request()->routeIs('school-periods.*') || request()->routeIs('students.subjects') || request()->routeIs('calendar.*') ||
                                request()->routeIs('written-exams.teacher-deployment.*');
                            
                            $wahlen11Active = request()->routeIs('lk-wahlen.*') || 
                                            request()->routeIs('lk-kurse.*') ||
                                            request()->routeIs('wseminar-wahlen.*') ||
                                            request()->routeIs('wseminar-kurse.*');
                            
                            // Räume können in mehreren Menüs vorkommen
                            $roomsInKolloquium = request()->routeIs('rooms.*') && !request()->routeIs('written-exams.*');
                            $roomsInAbiturSchriftlich = request()->routeIs('rooms.*') && request()->routeIs('written-exams.*');
                            
                            $stammdatenActive = request()->routeIs('students.index') || request()->routeIs('students.create') || request()->routeIs('students.edit') ||
                                request()->routeIs('students.store') || request()->routeIs('students.update') || request()->routeIs('students.destroy') ||
                                request()->routeIs('courses.*') || request()->routeIs('teachers.*') || request()->routeIs('rooms.*') ||
                                request()->routeIs('school-periods.*') || request()->routeIs('students.subjects') || request()->routeIs('calendar.*');
                        @endphp
                        
                        {{-- 1. Abitur Dropdown --}}
                        <x-nav-dropdown :active="$abiturSchriftlichActive" align="left">
                            <x-slot name="trigger">
                                {{ __('Abitur') }}
                            </x-slot>
                            <x-slot name="content">
                                <x-nav-dropdown-link :href="route('written-exams.dates.index')" :active="request()->routeIs('written-exams.dates.*') || request()->routeIs('written-exams.assignments.*')">
                                    {{ __('Termine & Planung') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('courses.index')" :active="request()->routeIs('courses.*')">
                                    {{ __('Kurse') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('external-applicants.index')" :active="request()->routeIs('external-applicants.*')">
                                    {{ __('Externe Bewerber') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('written-exams.forms.index')" :active="request()->routeIs('written-exams.forms.*')">
                                    {{ __('Formulare') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('written-exams.supervisions.index')" :active="request()->routeIs('written-exams.supervisions.*')">
                                    {{ __('Aufsichten') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('written-exams.materials.index')" :active="request()->routeIs('written-exams.materials.*')">
                                    {{ __('Prüfungsmaterial') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('exam-board.index')" :active="request()->routeIs('exam-board.*')">
                                    {{ __('Prüfungsausschuss') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('abiturfeier.index')" :active="request()->routeIs('abiturfeier.*')">
                                    {{ __('Abiturfeier') }}
                                </x-nav-dropdown-link>
                            </x-slot>
                        </x-nav-dropdown>
                        
                        {{-- 2. Kolloquium Dropdown --}}
                        <x-nav-dropdown :active="$kolloquiumActive" align="left">
                            <x-slot name="trigger">
                                {{ __('Kolloquium') }}
                            </x-slot>
                            <x-slot name="content">
                                <x-nav-dropdown-link :href="route('planning.index')" :active="request()->routeIs('planning.index') || request()->routeIs('planning.auto') || request()->routeIs('planning.assign') || request()->routeIs('planning.remove')">
                                    {{ __('Planung') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('planning.rooms.index')" :active="request()->routeIs('planning.rooms.*')">
                                    {{ __('Räume & Tage') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('planning.simulation.index')" :active="request()->routeIs('planning.simulation.*')">
                                    {{ __('Simulation') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('planning.time-slots.index')" :active="request()->routeIs('planning.time-slots.*')">
                                    {{ __('Zeitslots') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('kolloquium.forms.index')" :active="request()->routeIs('kolloquium.forms.*')">
                                    {{ __('Formulare') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('kolloquium.topics.index')" :active="request()->routeIs('kolloquium.topics.*')">
                                    {{ __('Themenkatalog') }}
                                </x-nav-dropdown-link>

                                @if($activeExamPeriod)
                                <x-nav-dropdown-link :href="route('supervisions.index', $activeExamPeriod)" :active="request()->routeIs('supervisions.*')">
                                    {{ __('Aufsichten') }}
                                </x-nav-dropdown-link>
                                @endif

                                <x-nav-dropdown-link :href="route('zusatzpruefungen.index')" :active="request()->routeIs('zusatzpruefungen.*')">
                                    {{ __('Zusatzprüfungen') }}
                                </x-nav-dropdown-link>
                            </x-slot>
                        </x-nav-dropdown>
                        
                        {{-- 3. Wahlen 11 Dropdown --}}
                        <x-nav-dropdown :active="$wahlen11Active" align="left">
                            <x-slot name="trigger">
                                {{ __('Wahlen 11') }}
                            </x-slot>
                            <x-slot name="content">
                                <x-nav-dropdown-link :href="route('lk-wahlen.index')" :active="request()->routeIs('lk-wahlen.*') || request()->routeIs('lk-kurse.*')">
                                    {{ __('Leistungsfächer') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('wseminar-wahlen.index')" :active="request()->routeIs('wseminar-wahlen.*') || request()->routeIs('wseminar-kurse.*')">
                                    {{ __('W-Seminare') }}
                                </x-nav-dropdown-link>
                            </x-slot>
                        </x-nav-dropdown>
                        
                        {{-- 4. Klausurplanung (direkter Link) --}}
                        <x-nav-link :href="route('timetable.exam-schedule')" :active="request()->routeIs('timetable.*') && !request()->routeIs('timetable.teacher-availability')">
                            {{ __('Klausurplanung') }}
                        </x-nav-link>

                        {{-- 5. Absenzen (direkter Link) --}}
                        <x-nav-link :href="route('absences.index')" :active="request()->routeIs('absences.*')">
                            {{ __('Absenzen') }}
                        </x-nav-link>
                        
                        {{-- 6. Stammdaten Dropdown --}}
                        <x-nav-dropdown :active="$stammdatenActive" align="left">
                            <x-slot name="trigger">
                                {{ __('Stammdaten') }}
                            </x-slot>
                            <x-slot name="content">
                                <x-nav-dropdown-link :href="route('students.index')" :active="request()->routeIs('students.index') || request()->routeIs('students.create') || request()->routeIs('students.edit') || request()->routeIs('students.store') || request()->routeIs('students.update') || request()->routeIs('students.destroy')">
                                    {{ __('Schüler') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('courses.index')" :active="request()->routeIs('courses.*')">
                                    {{ __('Kurse') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('teachers.index')" :active="request()->routeIs('teachers.*')">
                                    {{ __('Lehrkräfte') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">
                                    {{ __('Räume') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('school-periods.index')" :active="request()->routeIs('school-periods.*')">
                                    {{ __('Stundenraster') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('students.subjects')" :active="request()->routeIs('students.subjects')">
                                    {{ __('Fächer') }}
                                </x-nav-dropdown-link>
                                
                                <x-nav-dropdown-link :href="route('calendar.index')" :active="request()->routeIs('calendar.*')">
                                    {{ __('Kalender') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('written-exams.teacher-deployment.index')" :active="request()->routeIs('written-exams.teacher-deployment.*')">
                                    {{ __('Lehrereinsatz') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('school-settings.index')" :active="request()->routeIs('school-settings.*')">
                                    {{ __('Schuleinstellungen') }}
                                </x-nav-dropdown-link>

                                <x-nav-dropdown-link :href="route('setup.index')" :active="request()->routeIs('setup.*')">
                                    {{ __('Einrichtung') }}
                                </x-nav-dropdown-link>
                            </x-slot>
                        </x-nav-dropdown>
                    @endif
                    
                    @if(auth()->user()->isCentralAdmin())
                        <x-nav-link :href="route('admin.written-exam-slots.index')" :active="request()->routeIs('admin.written-exam-slots.*')">
                            {{ __('Schriftliche Prüfungen') }}
                        </x-nav-link>
                        
                        <x-nav-link :href="route('admin.global-calendar.index')" :active="request()->routeIs('admin.global-calendar.*')">
                            {{ __('Globaler Kalender') }}
                        </x-nav-link>
                        
                        
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('Benutzer') }}
                        </x-nav-link>
                        
                        <x-nav-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">
                            {{ __('Fächer') }}
                        </x-nav-link>

                        {{-- Hilfekapitel (Modul 6.3): zentraler Bestand, deshalb hier --}}
                        <x-nav-link :href="route('admin.hilfe.index')" :active="request()->routeIs('admin.hilfe.*')">
                            {{ __('Hilfe') }}
                        </x-nav-link>

                    @elseif(auth()->user()->isSuperAdmin())
                        {{-- Der Super-Admin plant auch (User::isSchoolPlanner()).
                             Die zentrale Pflege steht deshalb gebündelt in einem Menü,
                             sonst läuft die Leiste über. --}}
                        <x-nav-dropdown :active="request()->routeIs('admin.*')" align="left">
                            <x-slot name="trigger">
                                {{ __('Verwaltung') }}
                            </x-slot>
                            <x-slot name="content">
                                <x-nav-dropdown-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                                    {{ __('Benutzer') }}
                                </x-nav-dropdown-link>
                                <x-nav-dropdown-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">
                                    {{ __('Fächer') }}
                                </x-nav-dropdown-link>
                                <x-nav-dropdown-link :href="route('admin.written-exam-slots.index')" :active="request()->routeIs('admin.written-exam-slots.*')">
                                    {{ __('Schriftliche Prüfungen') }}
                                </x-nav-dropdown-link>
                                <x-nav-dropdown-link :href="route('admin.global-calendar.index')" :active="request()->routeIs('admin.global-calendar.*')">
                                    {{ __('Globaler Kalender') }}
                                </x-nav-dropdown-link>
                                <x-nav-dropdown-link :href="route('admin.hilfe.index')" :active="request()->routeIs('admin.hilfe.*')">
                                    {{ __('Hilfe') }}
                                </x-nav-dropdown-link>
                                <x-nav-dropdown-link :href="route('backups.index')" :active="request()->routeIs('backups.*')">
                                    {{ __('Sicherungen') }}
                                </x-nav-dropdown-link>
                            </x-slot>
                        </x-nav-dropdown>
                    @endif
                </div>
            </div>

            <!-- Right actions & User Profile -->
            <div class="hidden sm:flex sm:items-center sm:gap-2">
                <!-- Hilfe (Modul 6.3): Kapitel zur aktuellen Maske -->
                @include('hilfe.partials.knopf')

                {{--
                    Fehler melden (Modul 6.4). Nur für Nutzer mit Schule: Die
                    Meldestrecke liegt in den Mandanten-Routen, weil sie den
                    Einrichtungsstand und die letzten Schritte braucht. Ein
                    Super-Admin ohne Schule käme dort nicht durch
                    EnsureUserHasSchool — er bearbeitet Fälle unter „Support".
                --}}
                <!-- Settings Dropdown -->
                <div class="ms-2">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        data-submit-parent>
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="auth()->user()->isCentralAdmin() ? route('admin.dashboard') : route('dashboard')" :active="request()->routeIs('dashboard') || request()->routeIs('admin.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            
            @if(auth()->user()->isSchoolPlanner())
                @php
                    // ExamPeriods sind jetzt global - nehme den neuesten
                    $activeExamPeriod = app(\App\Services\CurrentExamPeriodService::class)->current();
                    
                    // Check active states for menu items
                    $kolloquiumActive = request()->routeIs('planning.*') || 
                                      request()->routeIs('courses.*') ||
                                      request()->routeIs('kolloquium.forms.*') || 
                                      request()->routeIs('timetable.teacher-availability') || 
                                      request()->routeIs('supervisions.*') ||
                                      request()->routeIs('zusatzpruefungen.*');
                    
                    $abiturSchriftlichActive = request()->routeIs('written-exams.*') || 
                                              request()->routeIs('students.*') || 
                                              request()->routeIs('external-applicants.*') ||
                                              request()->routeIs('exam-board.*') ||
                                              request()->routeIs('abiturfeier.*');
                    
                    $stammdatenActive = request()->routeIs('students.index') || request()->routeIs('students.create') || request()->routeIs('students.edit') ||
                        request()->routeIs('students.store') || request()->routeIs('students.update') || request()->routeIs('students.destroy') ||
                        request()->routeIs('courses.*') || request()->routeIs('teachers.*') || request()->routeIs('rooms.*') ||
                        request()->routeIs('school-periods.*') || request()->routeIs('students.subjects') || request()->routeIs('calendar.*') ||
                        request()->routeIs('written-exams.teacher-deployment.*');
                @endphp
                
                {{-- 1. Abitur Dropdown --}}
                <x-responsive-nav-dropdown :active="$abiturSchriftlichActive" :title="__('Abitur')">
                    <x-responsive-nav-link :href="route('written-exams.dates.index')" :active="request()->routeIs('written-exams.dates.*') || request()->routeIs('written-exams.assignments.*')">
                        {{ __('Termine & Planung') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('courses.index')" :active="request()->routeIs('courses.*')">
                        {{ __('Kurse') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('external-applicants.index')" :active="request()->routeIs('external-applicants.*')">
                        {{ __('Externe Bewerber') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('written-exams.forms.index')" :active="request()->routeIs('written-exams.forms.*')">
                        {{ __('Formulare') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('written-exams.supervisions.index')" :active="request()->routeIs('written-exams.supervisions.*')">
                        {{ __('Aufsichten') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('written-exams.materials.index')" :active="request()->routeIs('written-exams.materials.*')">
                        {{ __('Prüfungsmaterial') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('exam-board.index')" :active="request()->routeIs('exam-board.*')">
                        {{ __('Prüfungsausschuss') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('abiturfeier.index')" :active="request()->routeIs('abiturfeier.*')">
                        {{ __('Abiturfeier') }}
                    </x-responsive-nav-link>
                </x-responsive-nav-dropdown>
                
                {{-- 2. Kolloquium Dropdown --}}
                <x-responsive-nav-dropdown :active="$kolloquiumActive" :title="__('Kolloquium')">
                    <x-responsive-nav-link :href="route('planning.index')" :active="request()->routeIs('planning.index')">
                        {{ __('Planung') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('planning.rooms.index')" :active="request()->routeIs('planning.rooms.*')">
                        {{ __('Räume & Tage') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('planning.simulation.index')" :active="request()->routeIs('planning.simulation.*')">
                        {{ __('Simulation') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('planning.time-slots.index')" :active="request()->routeIs('planning.time-slots.*')">
                        {{ __('Zeitslots') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('kolloquium.forms.index')" :active="request()->routeIs('kolloquium.forms.*')">
                        {{ __('Formulare') }}
                    </x-responsive-nav-link>

                    @if($activeExamPeriod)
                    <x-responsive-nav-link :href="route('supervisions.index', $activeExamPeriod)" :active="request()->routeIs('supervisions.*')">
                        {{ __('Aufsichten') }}
                    </x-responsive-nav-link>
                    @endif

                    <x-responsive-nav-link :href="route('zusatzpruefungen.index')" :active="request()->routeIs('zusatzpruefungen.*')">
                        {{ __('Zusatzprüfungen') }}
                    </x-responsive-nav-link>
                </x-responsive-nav-dropdown>
                
                {{-- 3. Wahlen 11 Dropdown --}}
                <x-responsive-nav-dropdown :active="$wahlen11Active" :title="__('Wahlen 11')">
                    <x-responsive-nav-link :href="route('lk-wahlen.index')" :active="request()->routeIs('lk-wahlen.*') || request()->routeIs('lk-kurse.*')">
                        {{ __('Leistungsfächer') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('wseminar-wahlen.index')" :active="request()->routeIs('wseminar-wahlen.*') || request()->routeIs('wseminar-kurse.*')">
                        {{ __('W-Seminare') }}
                    </x-responsive-nav-link>
                </x-responsive-nav-dropdown>
                
                {{-- 4. Klausurplanung (direkter Link) --}}
                <x-responsive-nav-link :href="route('timetable.exam-schedule')" :active="request()->routeIs('timetable.*') && !request()->routeIs('timetable.teacher-availability')">
                    {{ __('Klausurplanung') }}
                </x-responsive-nav-link>

                {{-- 5. Absenzen (direkter Link) --}}
                <x-responsive-nav-link :href="route('absences.index')" :active="request()->routeIs('absences.*')">
                    {{ __('Absenzen') }}
                </x-responsive-nav-link>
                
                {{-- 6. Stammdaten Dropdown --}}
                <x-responsive-nav-dropdown :active="$stammdatenActive" :title="__('Stammdaten')">
                    <x-responsive-nav-link :href="route('students.index')" :active="request()->routeIs('students.index') || request()->routeIs('students.create') || request()->routeIs('students.edit') || request()->routeIs('students.store') || request()->routeIs('students.update') || request()->routeIs('students.destroy')">
                        {{ __('Schüler') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('courses.index')" :active="request()->routeIs('courses.*')">
                        {{ __('Kurse') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('teachers.index')" :active="request()->routeIs('teachers.*')">
                        {{ __('Lehrkräfte') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">
                        {{ __('Räume') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('school-periods.index')" :active="request()->routeIs('school-periods.*')">
                        {{ __('Stundenraster') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('students.subjects')" :active="request()->routeIs('students.subjects')">
                        {{ __('Fächer') }}
                    </x-responsive-nav-link>
                    
                    <x-responsive-nav-link :href="route('calendar.index')" :active="request()->routeIs('calendar.*')">
                        {{ __('Kalender') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('written-exams.teacher-deployment.index')" :active="request()->routeIs('written-exams.teacher-deployment.*')">
                        {{ __('Lehrereinsatz') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('school-settings.index')" :active="request()->routeIs('school-settings.*')">
                        {{ __('Schuleinstellungen') }}
                    </x-responsive-nav-link>

                    <x-responsive-nav-link :href="route('setup.index')" :active="request()->routeIs('setup.*')">
                        {{ __('Einrichtung') }}
                    </x-responsive-nav-link>
                </x-responsive-nav-dropdown>
            @endif
            
            @if(auth()->user()->isSuperAdmin())
                <x-responsive-nav-link :href="route('admin.written-exam-slots.index')" :active="request()->routeIs('admin.written-exam-slots.*')">
                    {{ __('Schriftliche Prüfungen') }}
                </x-responsive-nav-link>
                
                <x-responsive-nav-link :href="route('admin.global-calendar.index')" :active="request()->routeIs('admin.global-calendar.*')">
                    {{ __('Globaler Kalender') }}
                </x-responsive-nav-link>
                
                
                <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                    {{ __('Benutzer') }}
                </x-responsive-nav-link>
                
                <x-responsive-nav-link :href="route('admin.hilfe.index')" :active="request()->routeIs('admin.hilfe.*')">
                    {{ __('Hilfe verwalten') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">
                    {{ __('Fächer') }}
                </x-responsive-nav-link>

            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                {{-- Hilfe (Modul 6.3): auf dem Telefon ohne Panel, direkt zur Übersicht --}}
                <x-responsive-nav-link :href="route('hilfe.index')" :active="request()->routeIs('hilfe.*')">
                    {{ __('Hilfe') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            data-submit-parent>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
