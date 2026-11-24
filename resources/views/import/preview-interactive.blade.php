<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Import-Vorschau: {{ $examPeriod->name }}
            </h2>
            <a href="{{ route('import.form') }}" 
               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="importPreview()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            {{-- Statistiken --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📊 Import-Statistik</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="text-center p-4 bg-blue-50 rounded-lg">
                            <div class="text-2xl font-bold text-blue-600" x-text="totalStudents"></div>
                            <div class="text-sm text-gray-600">Schüler gefunden</div>
                        </div>
                        <div class="text-center p-4 bg-green-50 rounded-lg">
                            <div class="text-2xl font-bold text-green-600" x-text="validStudents"></div>
                            <div class="text-sm text-gray-600">Werden importiert</div>
                        </div>
                        <div class="text-center p-4 bg-gray-50 rounded-lg">
                            <div class="text-2xl font-bold text-gray-600" x-text="skippedStudents"></div>
                            <div class="text-sm text-gray-600">Übersprungen</div>
                        </div>
                        <div class="text-center p-4 bg-red-50 rounded-lg">
                            <div class="text-2xl font-bold text-red-600" x-text="errorCount"></div>
                            <div class="text-sm text-gray-600">Mit Fehlern</div>
                        </div>
                        <div class="text-center p-4 bg-yellow-50 rounded-lg">
                            <div class="text-2xl font-bold text-yellow-600" x-text="Object.keys(corrections).length"></div>
                            <div class="text-sm text-gray-600">Korrekturen</div>
                        </div>
                    </div>
                    
                    <div x-show="errorCount > 0" class="mt-4 p-4 bg-yellow-50 border-l-4 border-yellow-400">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">⚠️ Fehler gefunden - Korrektur möglich</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    Klicken Sie auf die rot markierten Felder, um Korrekturen vorzunehmen. 
                                    Ihre Änderungen werden automatisch auf alle gleichen Fälle angewendet.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div x-show="errorCount === 0" class="mt-4 p-4 bg-green-50 border-l-4 border-green-400">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">✅ Alle Daten gültig!</h3>
                                <div class="mt-2 text-sm text-green-700">
                                    Sie können den Import jetzt durchführen.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Korrektur-Mappings Übersicht --}}
            <div x-show="Object.keys(corrections).length > 0" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">🔧 Aktive Korrekturen</h3>
                    <div class="space-y-2">
                        <template x-for="(mapping, key) in corrections" :key="key">
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-700">Lehrer</span>
                                    <span class="px-2 py-1 bg-red-100 text-red-800 rounded font-mono text-sm" x-text="key"></span>
                                    <span class="text-gray-700">→</span>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded font-mono text-sm" x-text="mapping.to"></span>
                                </div>
                                <button @click="removeCorrection(key)" 
                                        class="text-red-600 hover:text-red-800">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Legende --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📖 Legende</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-green-200 border border-green-400 rounded"></div>
                            <span>✓ Gültig - wird importiert</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-red-200 border border-red-400 rounded"></div>
                            <span>✗ Fehler (klicken zum Korrigieren)</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-200 border border-gray-400 rounded"></div>
                            <span>⊘ Übersprungen - keine Kurse</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="w-4 h-4 bg-gray-100 border border-gray-300 rounded"></div>
                            <span>- Keine Prüfung</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Schülerliste --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">👥 Schüler-Vorschau (<span x-text="totalStudents"></span> Schüler)</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nr.</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">S1</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">S2</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">S3</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">K1</th>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">K2</th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <template x-for="(student, idx) in students" :key="idx">
                                    <tr :class="{
                                        'bg-red-50': getStudentHasErrors(student),
                                        'bg-gray-50': !getStudentHasErrors(student) && !student.will_be_imported,
                                        'hover:bg-gray-50': !getStudentHasErrors(student) && student.will_be_imported
                                    }">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500" x-text="student.external_id"></td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900" 
                                            x-text="`${student.lastname}, ${student.firstname}`"></td>
                                        
                                        <template x-for="examType in ['s1', 's2', 's3', 'k1', 'k2']" :key="examType">
                                            <td class="px-3 py-4 text-sm">
                                                <div x-show="student[examType].course" 
                                                     :class="getExamClass(student, examType)"
                                                     class="p-2 rounded cursor-pointer"
                                                     @click="openEditor(student, examType, idx)">
                                                    <div class="font-medium" 
                                                         x-text="student[examType].subject_name || student[examType].subject_code || '?'"></div>
                                                    <div class="text-xs" 
                                                         x-text="`${student[examType].course} / ${getCorrectedTeacher(student[examType].teacher) || student[examType].teacher}`"></div>
                                                    <div x-show="student[examType].errors && student[examType].errors.length > 0" 
                                                         class="text-xs text-red-800 mt-1">
                                                        <template x-for="error in student[examType].errors" :key="error">
                                                            <div>• <span x-text="error"></span></div>
                                                        </template>
                                                    </div>
                                                </div>
                                                <div x-show="!student[examType].course" 
                                                     class="p-2 bg-gray-100 border border-gray-300 rounded text-center text-gray-400">-</div>
                                            </td>
                                        </template>
                                        
                                        <td class="px-3 py-4 whitespace-nowrap text-center">
                                            <span x-show="getStudentHasErrors(student)"
                                                  class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                                </svg>
                                                Fehler
                                            </span>
                                            <span x-show="!getStudentHasErrors(student) && !student.will_be_imported"
                                                  class="inline-flex items-center px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clip-rule="evenodd" />
                                                </svg>
                                                Übersprungen
                                            </span>
                                            <span x-show="!getStudentHasErrors(student) && student.will_be_imported"
                                                  class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 rounded">
                                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                                OK
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Editor Modal --}}
            <div x-show="showEditor" 
                 x-cloak
                 class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                 @click.self="closeEditor()">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                            Prüfung korrigieren
                        </h3>
                        
                        <div x-show="currentExam" class="space-y-4">
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-sm text-gray-600">Schüler:</div>
                                <div class="font-medium" x-text="currentStudent ? `${currentStudent.lastname}, ${currentStudent.firstname}` : ''"></div>
                            </div>
                            
                            <div class="bg-gray-50 p-3 rounded">
                                <div class="text-sm text-gray-600">Prüfung:</div>
                                <div class="font-medium" x-text="currentExamType?.toUpperCase()"></div>
                                <div class="text-sm" x-text="currentExam ? `${currentExam.course}` : ''"></div>
                            </div>
                            
                            <div x-show="currentExam && !currentExam.teacher_valid">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Lehrer korrigieren:
                                </label>
                                <select x-model="selectedTeacher"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                    <option value="">-- Bitte wählen --</option>
                                    <optgroup label="🎯 Empfohlen">
                                        <option value="EXTERN">EXTERN - Externe Prüfung</option>
                                    </optgroup>
                                    <optgroup label="👥 Alle Lehrer">
                                        <template x-for="teacher in availableTeachers" :key="teacher.shortcode">
                                            <option :value="teacher.shortcode" 
                                                    x-text="`${teacher.shortcode} - ${teacher.firstname} ${teacher.lastname}`"></option>
                                        </template>
                                    </optgroup>
                                </select>
                                
                                <div class="mt-3 p-3 bg-blue-50 rounded text-sm text-blue-700">
                                    💡 Diese Änderung wird automatisch auf alle Prüfungen mit dem Lehrer 
                                    "<span x-text="currentExam?.teacher" class="font-mono font-bold"></span>" angewendet.
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button @click="closeEditor()" 
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </button>
                            <button @click="applyCorrection()" 
                                    :disabled="!selectedTeacher"
                                    :class="selectedTeacher ? 'bg-blue-600 hover:bg-blue-700' : 'bg-gray-400 cursor-not-allowed'"
                                    class="px-4 py-2 text-white rounded-md">
                                Übernehmen
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Import-Formular --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <form x-ref="importForm"
                          action="{{ route('import.process') }}" 
                          method="POST" 
                          @submit.prevent="submitImport()"
                          class="space-y-4">
                        @csrf
                        
                        <input type="hidden" name="temp_path" value="{{ $tempPath }}">
                        <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                        <input type="hidden" name="corrections" x-model="correctionsJson">
                        
                        <div class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-blue-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                    <label for="grade_select" class="text-sm font-medium text-blue-900 mr-2">
                                        Jahrgangsstufe für diesen Import:
                                    </label>
                                    <select name="grade" id="grade_select"
                                            class="rounded-md border-blue-300 text-sm font-semibold text-blue-900 bg-white focus:border-blue-500 focus:ring-blue-500 py-1">
                                        <option value="13" {{ $grade === '13' ? 'selected' : '' }}>Q13 (13. Klasse)</option>
                                        <option value="12" {{ $grade === '12' ? 'selected' : '' }}>Q12 (12. Klasse)</option>
                                        <option value="11" {{ $grade === '11' ? 'selected' : '' }}>Q11 (11. Klasse)</option>
                                    </select>
                                </div>
                                @if(!empty($gradeAutoDetected))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                                        ⚡ Automatisch anhand der Kurscodes (Q{{ $grade }}) erkannt
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-blue-700 mt-1.5 ml-7">
                                Beim Import werden nur Schüler dieser Jahrgangsstufe aktualisiert/ersetzt. Schüler, Kurse und Absenzen der anderen Jahrgangsstufe bleiben vollständig erhalten.
                            </p>
                            <p class="text-xs text-blue-700 mt-1 ml-7">
                                Kurse der Q12 beginnen mit „1“, Kurse der Q13 mit „2“, jahrgangsübergreifende mit „3“. Passt die Datei nicht zur gewählten Stufe, lehnt der Import sie ab.
                            </p>
                            @if(!empty($fremdeKurse))
                                <p class="text-sm text-red-700 bg-red-50 border border-red-300 rounded px-3 py-2 mt-2 ml-7">
                                    {{ $fremdeKurseMeldung }}
                                </p>
                            @endif
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" 
                                   name="clear_existing" 
                                   id="clear_existing" 
                                   value="1"
                                   checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="clear_existing" class="ml-2 block text-sm text-gray-700">
                                Alte Schüler und Kurse der Jahrgangsstufe Q{{ $grade }} löschen (empfohlen)
                            </label>
                        </div>

                        <div class="flex justify-between">
                            <a href="{{ route('import.form') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Zurück zum Upload
                            </a>
                            
                            <button type="submit" 
                                    :disabled="errorCount > 0"
                                    :class="errorCount === 0 ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'"
                                    class="px-6 py-2 text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2"
                                    x-text="errorCount === 0 ? '✅ Import durchführen' : '❌ Import nicht möglich'">
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script @cspNonce>
        function importPreview() {
            return {
                students: @json($preview['students']),
                availableTeachers: @json($availableTeachers),
                corrections: {},
                showEditor: false,
                currentStudent: null,
                currentExam: null,
                currentExamType: null,
                currentStudentIndex: null,
                selectedTeacher: '',
                
                get totalStudents() {
                    return this.students.length;
                },
                
                get errorCount() {
                    let count = 0;
                    this.students.forEach(student => {
                        ['s1', 's2', 's3', 'k1', 'k2'].forEach(examType => {
                            if (this.getExamHasError(student, examType)) {
                                count++;
                            }
                        });
                    });
                    return count;
                },
                
                get validStudents() {
                    return this.students.filter(student => !this.getStudentHasErrors(student) && student.will_be_imported).length;
                },
                
                get skippedStudents() {
                    return this.students.filter(student => !student.will_be_imported).length;
                },
                
                get correctionsJson() {
                    return JSON.stringify(this.corrections);
                },
                
                getExamHasError(student, examType) {
                    const exam = student[examType];
                    if (!exam || !exam.course) return false;
                    
                    // Prüfe ob es einen Lehrer-Fehler gibt
                    if (!exam.teacher_valid) {
                        // Prüfe ob es eine Korrektur gibt
                        const correctedTeacher = this.getCorrectedTeacher(exam.teacher);
                        if (correctedTeacher) {
                            return false; // Korrektur vorhanden
                        }
                        return true; // Fehler und keine Korrektur
                    }
                    
                    // Andere Fehler
                    return exam.has_error || (exam.errors && exam.errors.length > 0);
                },
                
                getStudentHasErrors(student) {
                    return ['s1', 's2', 's3', 'k1', 'k2'].some(examType => 
                        this.getExamHasError(student, examType)
                    );
                },
                
                getExamClass(student, examType) {
                    const hasError = this.getExamHasError(student, examType);
                    return hasError ? 
                        'bg-red-200 border border-red-400 hover:bg-red-300' : 
                        'bg-green-200 border border-green-400';
                },
                
                getCorrectedTeacher(teacherCode) {
                    if (this.corrections[teacherCode]) {
                        return this.corrections[teacherCode].to;
                    }
                    return null;
                },
                
                openEditor(student, examType, studentIndex) {
                    const exam = student[examType];
                    
                    // Nur bei Fehlern öffnen
                    if (!this.getExamHasError(student, examType)) {
                        return;
                    }
                    
                    this.currentStudent = student;
                    this.currentExam = exam;
                    this.currentExamType = examType;
                    this.currentStudentIndex = studentIndex;
                    this.selectedTeacher = this.getCorrectedTeacher(exam.teacher) || '';
                    this.showEditor = true;
                },
                
                closeEditor() {
                    this.showEditor = false;
                    this.currentStudent = null;
                    this.currentExam = null;
                    this.currentExamType = null;
                    this.currentStudentIndex = null;
                    this.selectedTeacher = '';
                },
                
                applyCorrection() {
                    if (!this.selectedTeacher || !this.currentExam) {
                        return;
                    }
                    
                    const oldTeacher = this.currentExam.teacher;
                    const newTeacher = this.selectedTeacher;
                    
                    // Mapping speichern
                    this.corrections[oldTeacher] = {
                        to: newTeacher,
                        type: 'teacher'
                    };
                    
                    // Editor schließen
                    this.closeEditor();
                    
                    // UI aktualisieren (Alpine reactive)
                    this.$nextTick(() => {
                        console.log('Korrektur angewendet:', oldTeacher, '->', newTeacher);
                    });
                },
                
                removeCorrection(key) {
                    delete this.corrections[key];
                },
                
                submitImport() {
                    if (this.errorCount > 0) {
                        alert('Bitte korrigieren Sie alle Fehler vor dem Import!');
                        return;
                    }
                    
                    if (confirm(`Möchten Sie die ${this.totalStudents} Schüler jetzt importieren?`)) {
                        this.$refs.importForm.submit();
                    }
                }
            }
        }
    </script>
    
    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>
