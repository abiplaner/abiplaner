    @push('scripts')
    <script @cspNonce>
        /**
         * Aktionen der Planungsmasken (Modul 7.2).
         *
         * Frueher hing an jedem Knopf ein Inline-Handler. Das ist fuer den Browser
         * ein Inline-Skript und faellt, sobald die Nonce im script-src steht.
         * Stattdessen traegt der Knopf data-aktion="…", und hier steht, was
         * dahinter steckt.
         *
         * Die Zuordnung ist bewusst eine feste Tabelle und kein Nachschlagen
         * von window[name]: sonst koennte ein eingeschleustes Attribut jede
         * beliebige globale Funktion aufrufen — genau das, was die CSP
         * verhindern soll.
         *
         * Der Knopf reicht seine data-Attribute mit; Zahlen kommen als Text an
         * und werden hier umgewandelt.
         */
        const PLANUNGS_AKTIONEN = {
            'submit-outside-period':    ()   => window.submitOutsidePeriod(),
            'open-outside-from-banner': ()   => window.openOutsidePeriodFromBanner(),
            'deactivate-illness-mode':  ()   => window.deactivateIllnessMode(),
            'close-auto-schedule':      ()   => window.closeAutoScheduleModal(),
            'start-auto-schedule':      ()   => window.startAutoSchedule(),
            'close-and-reload':         ()   => window.closeAndReload(),
            'close-validation':         ()   => closeValidationModal(),
            'submit-edit-outside':      ()   => window.submitEditOutside(),
            'revert-illness':           (el) => window.revertIllness(
                Number(el.dataset.assignment),
                Number(el.dataset.period)
            ),
            'open-edit-outside':        (el) => window.openEditOutsideModal(
                el.dataset.id,
                el.dataset.period,
                el.dataset.datum,
                el.dataset.von,
                el.dataset.bis,
                el.dataset.raum,
                el.dataset.notiz
            ),
            'show-week':                (el) => window.showWeek(Number(el.dataset.week)),
            'toggle-lock':              (el) => window.toggleLock(
                Number(el.dataset.assignment),
                Number(el.dataset.period)
            ),
            'cancel-illness':           (el) => window.cancelAsIllness(
                Number(el.dataset.assignment),
                Number(el.dataset.period)
            ),
            'swap-week':                (el) => window.swapWeek(
                Number(el.dataset.assignment),
                Number(el.dataset.period),
                Number(el.dataset.week)
            ),
            'remove-assignment':        (el) => window.removeAssignment(Number(el.dataset.assignment)),
            'open-vorgezogen-neu':      ()   => window.openVorgezogenNeu(),
            'submit-vorgezogen':        ()   => window.submitVorgezogen(),
            'open-vorgezogen-edit':     (el) => window.openVorgezogenEdit(
                el.dataset.id,
                el.dataset.datum,
                el.dataset.von,
                el.dataset.bis,
                el.dataset.raum,
                el.dataset.notiz
            ),
            'open-outside-period':      (el) => window.openOutsidePeriodModal(
                Number(el.dataset.assignment),
                Number(el.dataset.period)
            ),

            // Diese Knoepfe stehen in Dialogen, die weiter unten per innerHTML
            // erzeugt werden. Ein delegierter Listener erreicht sie trotzdem —
            // ein Inline-onclick darin waere unter scharfer CSP genauso
            // blockiert wie eines im Blade-Markup.
            'confirm-assignment':       (el) => confirmAssignment(
                Number(el.dataset.exam),
                Number(el.dataset.slot),
                Number(el.dataset.room)
            ),
            'assign-without-assessor':  (el) => assignWithoutAssessor(
                Number(el.dataset.exam),
                Number(el.dataset.slot),
                Number(el.dataset.room)
            ),
            'close-assessor':           ()   => closeAssessorModal(),
            'close-error':              ()   => closeErrorModal(),
            'close-blocked-day':        ()   => {
                document.getElementById('blocked-day-modal')?.remove();
                window.reloadKeepingWeek();
            },
            'export-warnings-pdf':      ()   => exportWarningsToPdf(),
            'copy-warnings':            ()   => copyWarningsToClipboard(),
        };

        document.addEventListener('click', (ereignis) => {
            const knopf = ereignis.target.closest('[data-aktion]');

            if (!knopf) {
                return;
            }

            const aktion = PLANUNGS_AKTIONEN[knopf.dataset.aktion];

            if (aktion) {
                aktion(knopf);
            }
        });

        // Drag and Drop
        let draggedElement = null;
        let illnessModeAssignmentId = null;  // ID des Assignments, für das Illness-Mode aktiv ist
        let illnessModeExamPeriodId = null;
        let illnessModeCard = null;          // DOM-Element der krank gemeldeten Karte

        // Baut eine Map: slotId → { examinerIds: Set, assessorIds: Set }
        // und week → studentIds: Set – basierend auf aktuellen DOM-Zuweisungen
        // cancelled-Karten werden NICHT mitgezählt
        function buildConflictMaps(excludeCard) {
            const slotExaminers = {};
            const slotAssessors = {};
            const weekStudents  = {};

            document.querySelectorAll('.time-slot-cell').forEach(cell => {
                const slotId = cell.dataset.slotId;
                const week   = cell.closest('#week-1-content') ? '1' : '2';

                if (!slotExaminers[slotId]) slotExaminers[slotId] = new Set();
                if (!slotAssessors[slotId]) slotAssessors[slotId] = new Set();
                if (!weekStudents[week])    weekStudents[week]    = new Set();

                const card = cell.querySelector('.assignment-card');
                if (card && card !== excludeCard && card.dataset.status !== 'cancelled') {
                    if (card.dataset.examinerId) slotExaminers[slotId].add(card.dataset.examinerId);
                    if (card.dataset.assessorId) slotAssessors[slotId].add(card.dataset.assessorId);
                    // Nachtermine (replaces_id gesetzt) zählen nicht zur Wochenbelegung –
                    // erkrankte Schüler dürfen in derselben Woche zwei Prüfungen haben.
                    if (card.dataset.studentId && !card.dataset.replacesId) weekStudents[week].add(card.dataset.studentId);
                }
            });

            return { slotExaminers, slotAssessors, weekStudents };
        }

        function highlightDropZones() {
            if (!draggedElement) return;

            const examinerId = draggedElement.dataset.examinerId || '';
            const assessorId = draggedElement.dataset.assessorId || '';
            const studentId  = draggedElement.dataset.studentId  || '';

            const { slotExaminers, slotAssessors, weekStudents } = buildConflictMaps(draggedElement);

            document.querySelectorAll('.time-slot-cell').forEach(cell => {
                const slotId = cell.dataset.slotId;
                const week   = cell.closest('#week-1-content') ? '1' : '2';
                const existing = cell.querySelector('.assignment-card');

                let conflict = false;
                let tooltip  = '';

                // Zelle bereits belegt?
                if (existing && existing !== draggedElement) {
                    conflict = true;
                    tooltip  = 'Zelle bereits belegt';
                }
                // Prüfer-Konflikt im gleichen Zeitslot?
                if (!conflict && examinerId && slotExaminers[slotId]?.has(examinerId)) {
                    conflict = true;
                    tooltip  = 'Prüfer hat in diesem Zeitslot bereits eine Prüfung';
                }
                // Beisitzer-Konflikt im gleichen Zeitslot?
                if (!conflict && assessorId && slotAssessors[slotId]?.has(assessorId)) {
                    conflict = true;
                    tooltip  = 'Beisitzer hat in diesem Zeitslot bereits eine Prüfung';
                }
                // Schüler bereits in dieser Woche eingeplant?
                // Im Illness-Mode erlaubt (Schüler kann in Woche 2 zwei Termine haben).
                // Außerdem: Nachtermine (replaces_id) und erkrankte Prüfungen (has-illness) sind von der Wochenregel befreit.
                const isNachtermin = draggedElement.dataset.replacesId ? true : false;
                const hasIllness   = draggedElement.dataset.hasIllness  ? true : false;
                if (!conflict && !illnessModeAssignmentId && !isNachtermin && !hasIllness && studentId && weekStudents[week]?.has(studentId)) {
                    conflict = true;
                    tooltip  = 'Schüler hat in dieser Woche bereits eine Prüfung';
                }

                if (conflict) {
                    cell.classList.add('drop-zone-invalid');
                } else {
                    cell.classList.add('drop-zone-valid');
                }
                if (tooltip) cell.title = tooltip;
            });
        }

        function clearDropZoneHighlights() {
            document.querySelectorAll('.time-slot-cell').forEach(cell => {
                cell.classList.remove('drop-zone-valid', 'drop-zone-invalid', 'border-blue-400', 'bg-blue-50');
                cell.title = '';
            });
        }

        function setupDraggable(card) {
            card.addEventListener('dragstart', function(e) {
                clearExaminerHighlights(); // Prüfer-Hover-Highlighting beenden (Modul 4.3)
                draggedElement = this;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', this.dataset.examId || this.dataset.studentExamId || '');
                this.style.opacity = '0.5';
                // Kurz verzögern, damit Browser-Ghost-Image zuerst gerendert wird
                requestAnimationFrame(highlightDropZones);
            });
            card.addEventListener('dragend', function(e) {
                this.style.opacity = '1';
                clearDropZoneHighlights();
                draggedElement = null;
            });
        }

        document.querySelectorAll('.exam-card').forEach(setupDraggable);
        document.querySelectorAll('.assignment-card[draggable="true"]').forEach(setupDraggable);

        // ==========================================
        // PRÜFER-HOVER-HIGHLIGHTING (Modul 4.3)
        // Hover über einer Prüfung hebt alle weiteren Prüfungen desselben
        // Erstprüfers hervor – im Plan (assignment-card, auch fixierte) und in
        // der Seitenleiste (exam-card). Während Drag & Drop deaktiviert, damit
        // es nicht mit den grün/roten Drop-Zonen kollidiert. Event-Delegation,
        // damit es auch nach DOM-Änderungen (Verschieben etc.) funktioniert.
        // ==========================================
        function clearExaminerHighlights() {
            document.querySelectorAll('.examiner-hover-match, .examiner-hover-source').forEach(el => {
                el.classList.remove('examiner-hover-match', 'examiner-hover-source');
            });
        }

        document.addEventListener('mouseover', function(e) {
            if (draggedElement) return; // Kein Prüfer-Highlighting während Drag & Drop
            const card = e.target.closest('.assignment-card, .exam-card');
            if (!card) return;
            if (card.classList.contains('examiner-hover-source')) return; // bereits aktiv
            const examinerId = card.dataset.examinerId;
            if (!examinerId || card.dataset.status === 'cancelled') return;

            clearExaminerHighlights();
            card.classList.add('examiner-hover-source');
            document.querySelectorAll(
                `.assignment-card[data-examiner-id="${examinerId}"], .exam-card[data-examiner-id="${examinerId}"]`
            ).forEach(el => {
                if (el !== card && el.dataset.status !== 'cancelled') {
                    el.classList.add('examiner-hover-match');
                }
            });
        });

        document.addEventListener('mouseout', function(e) {
            const card = e.target.closest('.assignment-card, .exam-card');
            if (!card) return;
            // Nur aufräumen, wenn die Maus die Karte wirklich verlässt
            // (nicht beim Wechsel auf ein Kind-Element derselben Karte)
            if (e.relatedTarget && card.contains(e.relatedTarget)) return;
            clearExaminerHighlights();
        });

        // Funktionen global verfügbar machen
        window.allowDrop = function(ev) {
            ev.preventDefault();
            const cell = ev.currentTarget;
            // Nur bei validen Zonen blauen Hover zeigen; bei invaliden nur leichten Effekt
            if (!cell.classList.contains('drop-zone-invalid')) {
                cell.classList.add('border-blue-400', 'bg-blue-50');
            } else {
                cell.classList.add('border-red-400');
            }
        }
        
        window.dragLeave = function(ev) {
            ev.currentTarget.classList.remove('border-blue-400', 'bg-blue-50', 'border-red-400');
        }

        // Frueher hingen ondrop/ondragover/ondragleave an jeder Zelle
        // (Modul 7.2). Bewusst direkt am Element gebunden und nicht ueber
        // document delegiert: die drei Funktionen arbeiten mit
        // ev.currentTarget, und das waere bei einer Delegation nicht mehr die
        // Zelle, sondern das Dokument. Die Zellen kommen ausschliesslich aus
        // Blade — nach einem Drop laedt die Seite komplett neu (siehe
        // reloadKeepingWeek), niemand baut die Matrix per innerHTML nach.
        //
        // Die Funktionen werden erst im Ereignis nachgeschlagen: window.drop
        // steht weiter unten und waere hier noch undefined.
        document.querySelectorAll('.time-slot-cell').forEach((zelle) => {
            zelle.addEventListener('dragover',  (ereignis) => window.allowDrop(ereignis));
            zelle.addEventListener('dragleave', (ereignis) => window.dragLeave(ereignis));
            zelle.addEventListener('drop',      (ereignis) => window.drop(ereignis));
        });

        window.drop = function(ev) {
            ev.preventDefault();
            ev.currentTarget.classList.remove('border-blue-400', 'bg-blue-50', 'border-red-400');
            
            if (!draggedElement) return;
            
            const cellElement = ev.currentTarget;
            const slotId = cellElement.dataset.slotId;
            const roomId = cellElement.dataset.roomId;
            
            // Unterscheide: ungeplante Prüfung (exam-card) oder bestehende Zuweisung (assignment-card)
            let examId;
            if (draggedElement.classList.contains('exam-card')) {
                examId = draggedElement.dataset.examId;
            } else if (draggedElement.classList.contains('assignment-card')) {
                examId = draggedElement.dataset.studentExamId;
            }

            if (!examId) return;

            // Illness-Mode: anderen Endpoint aufrufen
            if (illnessModeAssignmentId) {
                illnessDropToSlot(illnessModeAssignmentId, illnessModeExamPeriodId, slotId, roomId);
                return;
            }

            const assessorId = draggedElement.dataset.assessorId || null;
            assignExam(examId, slotId, roomId, assessorId);
        }

        // ── Illness-Mode ──────────────────────────────────────────────────────

        window.activateIllnessMode = function(assignmentId, examPeriodId, btnEl) {
            // Bereits aktiv? → deaktivieren
            if (illnessModeAssignmentId === assignmentId) {
                deactivateIllnessMode();
                return;
            }
            // Ggf. alten Modus deaktivieren
            deactivateIllnessMode();

            illnessModeAssignmentId = assignmentId;
            illnessModeExamPeriodId = examPeriodId;
            illnessModeCard = btnEl.closest('.assignment-card');

            // Karte optisch hervorheben
            if (illnessModeCard) {
                illnessModeCard.style.outline = '2px dashed #dc2626';
                illnessModeCard.style.opacity = '0.75';
            }
            // Banner einblenden
            document.getElementById('illness-mode-banner').classList.remove('hidden');
            requestAnimationFrame(highlightDropZones);
        }

        window.deactivateIllnessMode = function() {
            if (illnessModeCard) {
                illnessModeCard.style.outline = '';
                illnessModeCard.style.opacity = '';
            }
            illnessModeAssignmentId = null;
            illnessModeExamPeriodId = null;
            illnessModeCard = null;
            document.getElementById('illness-mode-banner').classList.add('hidden');
            clearDropZoneHighlights();
        }

        window.openOutsidePeriodFromBanner = function() {
            if (!illnessModeAssignmentId) return;
            document.getElementById('outside-assignment-id').value   = illnessModeAssignmentId;
            document.getElementById('outside-exam-period-id').value  = illnessModeExamPeriodId;
            document.getElementById('outside-period-modal').classList.remove('hidden');
            deactivateIllnessMode();
        }

        // Direktaufruf von der "Erkrankt"-Karte aus (ohne Illness-Mode)
        window.openOutsidePeriodModal = function(assignmentId, examPeriodId) {
            document.getElementById('outside-assignment-id').value  = assignmentId;
            document.getElementById('outside-exam-period-id').value = examPeriodId;
            document.getElementById('outside-free-date').value      = '';
            document.getElementById('outside-free-time-start').value = '';
            document.getElementById('outside-free-time-end').value   = '';
            document.getElementById('outside-notes').value           = '';
            document.getElementById('outside-period-modal').classList.remove('hidden');
        }

        // Edit-Modal für bestehende Außerhalb-Termine
        window.openEditOutsideModal = function(assignmentId, examPeriodId, date, timeStart, timeEnd, roomId, notes) {
            document.getElementById('edit-outside-assignment-id').value  = assignmentId;
            document.getElementById('edit-outside-exam-period-id').value = examPeriodId;
            document.getElementById('edit-outside-free-date').value      = date;
            document.getElementById('edit-outside-free-time-start').value = timeStart;
            document.getElementById('edit-outside-free-time-end').value   = timeEnd;
            document.getElementById('edit-outside-notes').value           = notes || '';
            const roomSelect = document.getElementById('edit-outside-exam-room-id');
            if (roomSelect) roomSelect.value = roomId || '';
            document.getElementById('edit-outside-modal').classList.remove('hidden');
        }

        window.submitEditOutside = function() {
            const assignmentId = document.getElementById('edit-outside-assignment-id').value;
            const examPeriodId = document.getElementById('edit-outside-exam-period-id').value;
            const freeDate     = document.getElementById('edit-outside-free-date').value;
            const timeStart    = document.getElementById('edit-outside-free-time-start').value;
            const timeEnd      = document.getElementById('edit-outside-free-time-end').value;
            const roomId       = document.getElementById('edit-outside-exam-room-id')?.value || null;
            const notes        = document.getElementById('edit-outside-notes').value;

            if (!freeDate || !timeStart || !timeEnd) {
                alert('Bitte Datum und Uhrzeit ausfüllen.');
                return;
            }

            document.getElementById('edit-outside-modal').classList.add('hidden');

            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/update-outside`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ free_date: freeDate, free_time_start: timeStart, free_time_end: timeEnd, exam_room_id: roomId || null, notes: notes || null })
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) { reloadKeepingWeek(); } else { showErrorModal(data.message || 'Fehler'); }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        /**
         * Vorgezogene muendliche Pruefungen (§ 61 Abs. 4).
         *
         * Ein Dialog fuer beides: Ohne Assignment-Id wird angelegt (dann steht
         * die Auswahl der Pruefung oben), mit Id wird geaendert (dann ist die
         * Pruefung festgelegt und die Auswahl ausgeblendet).
         */
        function vorgezogenFelderSetzen(datum, von, bis, raum, notiz) {
            document.getElementById('vorgezogen-free-date').value       = datum || '';
            document.getElementById('vorgezogen-free-time-start').value = von || '';
            document.getElementById('vorgezogen-free-time-end').value   = bis || '';
            document.getElementById('vorgezogen-notes').value           = notiz || '';
            const raumAuswahl = document.getElementById('vorgezogen-exam-room-id');
            if (raumAuswahl) raumAuswahl.value = raum || '';
        }

        window.openVorgezogenNeu = function() {
            document.getElementById('vorgezogen-assignment-id').value = '';
            document.getElementById('vorgezogen-modal-titel').textContent = '📅 Vorgezogene Prüfung eintragen';
            document.getElementById('vorgezogen-pruefung-feld').classList.remove('hidden');
            document.getElementById('vorgezogen-student-exam-id').value = '';
            vorgezogenFelderSetzen('', '', '', '', '');
            document.getElementById('vorgezogen-modal').classList.remove('hidden');
        }

        window.openVorgezogenEdit = function(assignmentId, datum, von, bis, raum, notiz) {
            document.getElementById('vorgezogen-assignment-id').value = assignmentId;
            document.getElementById('vorgezogen-modal-titel').textContent = '📅 Vorgezogene Prüfung ändern';
            document.getElementById('vorgezogen-pruefung-feld').classList.add('hidden');
            vorgezogenFelderSetzen(datum, von, bis, raum, notiz);
            document.getElementById('vorgezogen-modal').classList.remove('hidden');
        }

        window.submitVorgezogen = function() {
            const assignmentId = document.getElementById('vorgezogen-assignment-id').value;
            const examPeriodId = document.getElementById('vorgezogen-exam-period-id').value;
            const studentExam  = document.getElementById('vorgezogen-student-exam-id')?.value || '';
            const freeDate     = document.getElementById('vorgezogen-free-date').value;
            const timeStart    = document.getElementById('vorgezogen-free-time-start').value;
            const timeEnd      = document.getElementById('vorgezogen-free-time-end').value;
            const roomId       = document.getElementById('vorgezogen-exam-room-id')?.value || '';
            const notes        = document.getElementById('vorgezogen-notes').value;

            if (!assignmentId && !studentExam) {
                showErrorModal('Bitte eine Prüfung auswählen.');
                return;
            }
            if (!freeDate || !timeStart || !timeEnd) {
                showErrorModal('Bitte Datum und Uhrzeit ausfüllen.');
                return;
            }
            if (!roomId) {
                showErrorModal('Bitte einen Prüfungsraum auswählen.');
                return;
            }

            document.getElementById('vorgezogen-modal').classList.add('hidden');

            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            const nutzlast = {
                free_date: freeDate,
                free_time_start: timeStart,
                free_time_end: timeEnd,
                exam_room_id: roomId,
                notes: notes || null
            };

            let adresse = `/exam-periods/${examPeriodId}/planning/vorgezogen`;
            let methode = 'POST';

            if (assignmentId) {
                adresse += `/${assignmentId}`;
                methode = 'PATCH';
            } else {
                nutzlast.student_exam_id = studentExam;
            }

            fetch(adresse, {
                method: methode,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify(nutzlast)
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    showErrorModal(data.message || 'Der Termin konnte nicht gespeichert werden.');
                }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        function illnessDropToSlot(assignmentId, examPeriodId, slotId, roomId) {
            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            deactivateIllnessMode();

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/illness-reschedule`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ time_slot_id: slotId, exam_room_id: roomId })
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    showErrorModal(data.message || 'Fehler beim Verschieben');
                }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        window.submitOutsidePeriod = function() {
            const assignmentId = document.getElementById('outside-assignment-id').value;
            const examPeriodId = document.getElementById('outside-exam-period-id').value;
            const freeDate     = document.getElementById('outside-free-date').value;
            const timeStart    = document.getElementById('outside-free-time-start').value;
            const timeEnd      = document.getElementById('outside-free-time-end').value;
            const roomId       = document.getElementById('outside-exam-room-id').value;
            const notes        = document.getElementById('outside-notes').value;

            if (!freeDate || !timeStart || !timeEnd) {
                alert('Bitte Datum und Uhrzeit ausfüllen.');
                return;
            }

            document.getElementById('outside-period-modal').classList.add('hidden');

            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/illness-reschedule-outside`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({ free_date: freeDate, free_time_start: timeStart, free_time_end: timeEnd, exam_room_id: roomId || null, notes: notes || null })
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) { reloadKeepingWeek(); } else { showErrorModal(data.message || 'Fehler'); }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        window.cancelAsIllness = function(assignmentId, examPeriodId) {
            if (!confirm('Prüfung wegen Erkrankung absagen?\nDie Prüfung wird in die Seitenleiste verschoben und kann dort neu eingeplant werden.')) return;

            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/cancel-illness`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    showErrorModal(data.message || 'Fehler beim Absagen');
                }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        window.revertIllness = function(assignmentId, examPeriodId) {
            if (!confirm('Erkrankungs-Markierung rückgängig machen? Der Nachtermin wird gelöscht und der Originaltermin wiederhergestellt.')) return;

            const overlay = document.createElement('div');
            overlay.id = 'loading-overlay';
            overlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            overlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird gespeichert...</div>';
            document.body.appendChild(overlay);

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/revert-illness`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                body: JSON.stringify({})
            })
            .then(r => r.json().then(d => ({ status: r.status, data: d })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) { reloadKeepingWeek(); } else { showErrorModal(data.message || 'Fehler'); }
            })
            .catch(() => { document.getElementById('loading-overlay')?.remove(); showErrorModal('Netzwerkfehler'); });
        }

        window.openAssessorModal = function(examId, slotId, roomId) {
            // Erstelle Modal dynamisch
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.id = 'assessor-modal';
            
            const teachers = @json($teachers);
            
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Beisitzer auswählen</h3>
                        <select id="assessor-select" class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">-- Beisitzer wählen --</option>
                            ${teachers.map(t => `<option value="${t.id}">${t.shortcode} - ${t.lastname}</option>`).join('')}
                        </select>
                        <div class="mt-4 flex flex-col space-y-2">
                            <button data-aktion="confirm-assignment" data-exam="${examId}" data-slot="${slotId}" data-room="${roomId}" class="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Mit Beisitzer zuweisen
                            </button>
                            <button data-aktion="assign-without-assessor" data-exam="${examId}" data-slot="${slotId}" data-room="${roomId}" class="w-full px-4 py-2 bg-orange-500 text-white rounded-md hover:bg-orange-600">
                                Ohne Beisitzer zuweisen
                            </button>
                            <button data-aktion="close-assessor" class="w-full px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                                Abbrechen
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        window.closeAssessorModal = function() {
            const modal = document.getElementById('assessor-modal');
            if (modal) modal.remove();
        }
        
        window.confirmAssignment = function(examId, slotId, roomId) {
            const assessorId = document.getElementById('assessor-select').value;
            
            if (!assessorId) {
                alert('Bitte Beisitzer auswählen oder "Ohne Beisitzer zuweisen" klicken');
                return;
            }
            
            closeAssessorModal();
            assignExam(examId, slotId, roomId, assessorId);
        }
        
        window.assignWithoutAssessor = function(examId, slotId, roomId) {
            closeAssessorModal();
            assignExam(examId, slotId, roomId, null);
        }

        window.assignExam = function(examId, slotId, roomId, assessorId) {
            // Zeige Ladeindikator
            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'loading-overlay';
            loadingOverlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            loadingOverlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird zugewiesen...</div>';
            document.body.appendChild(loadingOverlay);
            
            fetch('{{ route("planning.assign", $examPeriod) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    student_exam_id: examId,
                    time_slot_id: slotId,
                    exam_room_id: roomId,
                    assessor_id: assessorId
                })
            })
            .then(response => response.json().then(data => ({ status: response.status, data })))
            .then(({ status, data }) => {
                // Entferne Ladeindikator
                document.getElementById('loading-overlay')?.remove();
                
                if (data.success) {
                    if (data.blocked_day_warning) {
                        showBlockedDayWarning(data.blocked_day_warning);
                    } else {
                        reloadKeepingWeek();
                    }
                } else {
                    // Zeige Fehlermeldung in Modal
                    showErrorModal(data.message || 'Unbekannter Fehler bei der Zuweisung');
                }
            })
            .catch(error => {
                document.getElementById('loading-overlay')?.remove();
                console.error('Error:', error);
                showErrorModal('Netzwerkfehler bei der Zuweisung');
            });
        }
        
        window.showErrorModal = function(message) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.id = 'error-modal';
            
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center mb-4">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 text-center mb-2">Zuweisung nicht möglich</h3>
                        <p class="text-sm text-gray-600 text-center mb-4">${message}</p>
                        <div class="flex justify-center">
                            <button data-aktion="close-error" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                                Verstanden
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        window.closeErrorModal = function() {
            const modal = document.getElementById('error-modal');
            if (modal) modal.remove();
        }

        window.showBlockedDayWarning = function(message) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50';
            modal.id = 'blocked-day-modal';
            modal.innerHTML = `
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex items-center mb-4">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-amber-100">
                                <svg class="h-6 w-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 text-center mb-2">Gesperrter Tag</h3>
                        <p class="text-sm text-gray-600 text-center mb-4">${message}</p>
                        <p class="text-xs text-gray-500 text-center mb-4">Die Prüfung wurde trotzdem eingetragen. Bitte prüfen Sie, ob das korrekt ist.</p>
                        <div class="flex justify-center">
                            <button data-aktion="close-blocked-day"
                                    class="px-4 py-2 bg-amber-600 text-white rounded-md hover:bg-amber-700">
                                Verstanden
                            </button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Aktive Woche und Scroll-Position vor Reload sichern, danach wiederherstellen
        window.reloadKeepingWeek = function() {
            const activeWeek    = document.getElementById('week-2-tab')?.classList.contains('bg-blue-600') ? 2 : 1;
            const rightScroll   = document.getElementById('planning-right-scroll')?.scrollTop ?? 0;
            const pageScrollY   = window.scrollY;
            sessionStorage.setItem('planningActiveWeek',  activeWeek);
            sessionStorage.setItem('planningRightScroll', rightScroll);
            sessionStorage.setItem('planningScrollY',     pageScrollY);
            location.reload();
        }

        window.swapWeek = function(assignmentId, examPeriodId, currentWeek) {
            const targetWeek = currentWeek === 1 ? 2 : 1;
            const msg = `Prüfung von Woche ${currentWeek} nach Woche ${targetWeek} verschieben?\n\nDie Prüfung wird aus dem Plan entfernt und erscheint in „Ungeplante Prüfungen" mit Ziel-Woche ${targetWeek}.\n\nFalls der Schüler eine zweite Prüfung in Woche ${targetWeek} hat, wird auch diese zurückgesetzt (Ziel: Woche ${currentWeek}).`;
            if (!confirm(msg)) return;

            const loadingOverlay = document.createElement('div');
            loadingOverlay.id = 'loading-overlay';
            loadingOverlay.className = 'fixed inset-0 bg-gray-600 bg-opacity-30 z-50 flex items-center justify-center';
            loadingOverlay.innerHTML = '<div class="bg-white p-4 rounded-lg shadow-lg">Wird verschoben...</div>';
            document.body.appendChild(loadingOverlay);

            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/swap-week`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json().then(data => ({ status: response.status, data })))
            .then(({ status, data }) => {
                document.getElementById('loading-overlay')?.remove();
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    showErrorModal(data.message || 'Unbekannter Fehler beim Woche-Wechsel');
                }
            })
            .catch(error => {
                document.getElementById('loading-overlay')?.remove();
                showErrorModal('Netzwerkfehler beim Woche-Wechsel: ' + error.message);
            });
        }

        // Hervorhebung der preferred_week-Karten beim Tab-Wechsel
        window.highlightPreferredWeek = function(activeWeek) {
            document.querySelectorAll('.exam-card').forEach(card => {
                const preferred = card.dataset.preferredWeek;
                if (!preferred) return;
                const prefNum = parseInt(preferred, 10);
                if (prefNum === activeWeek) {
                    card.style.outline = '2px solid ' + (activeWeek === 1 ? '#2563eb' : '#16a34a');
                    card.style.outlineOffset = '2px';
                } else {
                    card.style.outline = '';
                    card.style.outlineOffset = '';
                }
            });
        }

        window.removeAssignment = function(assignmentId) {
            if (!confirm('Zuweisung wirklich entfernen?')) return;
            
            fetch(`/exam-periods/{{ $examPeriod->id }}/planning/assignments/${assignmentId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    alert('Fehler beim Löschen: ' + (data.message || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler beim Löschen der Zuweisung: ' + error.message);
            });
        }

        window.toggleLock = function(assignmentId, examPeriodId) {
            const card = document.querySelector(`[data-assignment-id="${assignmentId}"]`);
            const isLocked = card?.dataset.isLocked === 'true';
            const lockReason = card?.dataset.lockReason || '';
            
            let reason = null;
            if (isLocked) {
                // Entsperren - Sicherheitsabfrage
                const reasonText = lockReason ? `\n\nGrund der Fixierung: "${lockReason}"` : '';
                if (!confirm(`Möchten Sie die Fixierung dieser Prüfung wirklich aufheben?${reasonText}\n\nDie Prüfung kann dann bei der Autoplanung verschoben werden.`)) {
                    return; // User cancelled
                }
            } else {
                // Fixieren - Grund abfragen
                reason = prompt('Grund für Fixierung (optional, z.B. "Externer Prüfer"):', '');
                if (reason === null) return; // User cancelled
            }
            
            fetch(`/exam-periods/${examPeriodId}/planning/assignments/${assignmentId}/toggle-lock`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ lock_reason: reason })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    reloadKeepingWeek();
                } else {
                    alert('Fehler: ' + (data.message || 'Unbekannter Fehler'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Fehler: ' + error.message);
            });
        }

        window.showWeek = function(weekNumber) {
            // Hide all week contents
            document.querySelectorAll('.week-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Reset all tabs
            document.querySelectorAll('.week-tab').forEach(tab => {
                tab.classList.remove('bg-blue-600', 'text-white');
                tab.classList.add('bg-gray-300', 'text-gray-700');
            });
            
            // Show selected week
            document.getElementById(`week-${weekNumber}-content`).classList.remove('hidden');
            document.getElementById(`week-${weekNumber}-tab`).classList.remove('bg-gray-300', 'text-gray-700');
            document.getElementById(`week-${weekNumber}-tab`).classList.add('bg-blue-600', 'text-white');

            // Preferred-week-Karten hervorheben
            highlightPreferredWeek(weekNumber);
        }

        // ==========================================
        // AUTO-PLANUNG MODAL
        // ==========================================
        // Hinweis: openAutoScheduleModal und closeAutoScheduleModal sind bereits oben definiert
        
        window.startAutoSchedule = function() {
            // Parameter sammeln
            const lfWeekRadio = document.querySelector('input[name="lf-preferred-week"]:checked');
            const lfWeekValue = lfWeekRadio && lfWeekRadio.value !== '' ? parseInt(lfWeekRadio.value) : null;
            const settings = {
                clear_existing: document.getElementById('clear-existing').checked,
                max_consecutive: parseInt(document.getElementById('max-consecutive').value) || 5,
                lf_preferred_week: lfWeekValue,
                bundle_teacher_days: document.getElementById('bundle-teacher-days').checked,
                respect_free_days: document.getElementById('respect-free-days').checked,
            };
            
            // Zeige Fortschritt
            document.getElementById('modal-settings').classList.add('hidden');
            document.getElementById('modal-progress').classList.remove('hidden');
            
            // Starte die Planung
            fetch('{{ route("planning.auto", $examPeriod) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(settings)
            })
            .then(response => response.json())
            .then(data => {
                // Zeige Ergebnis
                document.getElementById('modal-progress').classList.add('hidden');
                document.getElementById('modal-result').classList.remove('hidden');
                
                const resultContent = document.getElementById('result-content');
                
                if (data.success) {
                    let html = `
                        <div class="text-center mb-6">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                                <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-green-800">Planung erfolgreich!</h3>
                        </div>
                    `;
                    
                    // Statistiken
                    html += `
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <h4 class="font-semibold mb-3 text-gray-700">📊 Statistiken</h4>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Geplante Prüfungen:</span>
                                    <span class="font-semibold text-green-600">${data.scheduled_count || 0}</span>
                                </div>
                    `;
                    
                    if (data.stats) {
                        if (data.stats.total_exams !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Gesamt:</span>
                                    <span class="font-semibold">${data.stats.total_exams}</span>
                                </div>
                            `;
                        }
                        if (data.stats.unscheduled_exams !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Nicht geplant:</span>
                                    <span class="font-semibold ${data.stats.unscheduled_exams > 0 ? 'text-orange-600' : 'text-green-600'}">${data.stats.unscheduled_exams}</span>
                                </div>
                            `;
                        }
                        if (data.stats.completion_rate !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Abdeckung:</span>
                                    <span class="font-semibold">${data.stats.completion_rate}%</span>
                                </div>
                            `;
                        }
                        if (data.stats.teacher_days_avg !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Ø Prüfungstage je Lehrkraft:</span>
                                    <span class="font-semibold">${data.stats.teacher_days_avg}</span>
                                </div>
                            `;
                        }
                        if (data.stats.teacher_days_max !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Max. Prüfungstage einer Lehrkraft:</span>
                                    <span class="font-semibold">${data.stats.teacher_days_max}</span>
                                </div>
                            `;
                        }
                        if (data.stats.teacher_free_days_used !== undefined) {
                            html += `
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Prüfungstage an stundenplanfreien Tagen:</span>
                                    <span class="font-semibold ${data.stats.teacher_free_days_used > 0 ? 'text-orange-600' : 'text-green-600'}">${data.stats.teacher_free_days_used}</span>
                                </div>
                            `;
                        }
                    }
                    
                    html += '</div></div>';
                    
                    // Warnungen mit erweiterten Details
                    if (data.warnings && data.warnings.length > 0) {
                        html += `
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="font-semibold text-yellow-800">⚠️ Warnungen (${data.warnings.length})</h4>
                                    <div class="space-x-2">
                                        <button data-aktion="export-warnings-pdf" class="text-xs bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded">
                                            📄 PDF
                                        </button>
                                        <button data-aktion="copy-warnings" class="text-xs bg-yellow-600 hover:bg-yellow-700 text-white px-2 py-1 rounded">
                                            📋 Kopieren
                                        </button>
                                    </div>
                                </div>
                                <div id="warnings-content" class="space-y-2 max-h-60 overflow-y-auto">
                        `;
                        data.warnings.forEach(w => {
                            const msg = typeof w === 'string' ? w : (w.message || JSON.stringify(w));
                            html += `<div class="text-sm text-yellow-800 bg-yellow-100 p-2 rounded">`;
                            html += `<div class="font-medium">${msg}</div>`;
                            
                            // Details anzeigen falls vorhanden
                            if (w.details) {
                                html += `<div class="mt-1 text-xs text-yellow-700 pl-3 border-l-2 border-yellow-300">`;
                                if (w.details.student) html += `<div>Schüler: ${w.details.student}</div>`;
                                if (w.details.course) html += `<div>Kurs: ${w.details.course}</div>`;
                                if (w.details.examiner) html += `<div>Prüfer: ${w.details.examiner}</div>`;
                                if (w.details.size) html += `<div>Größe: ${w.details.size} Prüfungen</div>`;
                                if (w.details.week) html += `<div>Woche: ${w.details.week}</div>`;
                                if (w.details.week1_slot) html += `<div>Woche 1 Zeitslot: ${w.details.week1_slot}</div>`;
                                if (w.details.week2_slot) html += `<div>Woche 2 Zeitslot: ${w.details.week2_slot}</div>`;
                                if (w.details.students && w.details.students.length > 0) {
                                    html += `<div class="mt-1">Betroffene Schüler:<ul class="list-disc list-inside ml-2">`;
                                    w.details.students.forEach(s => html += `<li>${s}</li>`);
                                    html += `</ul></div>`;
                                }
                                if (w.details.conflicts && w.details.conflicts.length > 0) {
                                    html += `<div class="mt-1">Konflikte:<ul class="list-disc list-inside ml-2">`;
                                    w.details.conflicts.forEach(c => html += `<li>${c}</li>`);
                                    html += `</ul></div>`;
                                }
                                html += `</div>`;
                            }
                            html += `</div>`;
                        });
                        html += '</div></div>';
                        
                        // JavaScript-Variable für Clipboard-Funktion direkt setzen (nicht über innerHTML)
                        window.planningWarnings = data.warnings;
                    }
                    
                    // Konflikte
                    if (data.conflicts && data.conflicts.errors && data.conflicts.errors.length > 0) {
                        html += `
                            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
                                <h4 class="font-semibold text-red-800 mb-2">❌ Konflikte (${data.conflicts.errors.length})</h4>
                                <ul class="list-disc list-inside text-sm text-red-700 max-h-40 overflow-y-auto">
                        `;
                        data.conflicts.errors.forEach(e => {
                            const msg = typeof e === 'string' ? e : (e.message || JSON.stringify(e));
                            html += `<li>${msg}</li>`;
                        });
                        html += '</ul></div>';
                    }
                    
                    resultContent.innerHTML = html;
                    
                } else {
                    resultContent.innerHTML = `
                        <div class="text-center mb-6">
                            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                                <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-semibold text-red-800">Fehler bei der Planung</h3>
                            <p class="mt-2 text-gray-600">${data.message || data.error || 'Unbekannter Fehler'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('modal-progress').classList.add('hidden');
                document.getElementById('modal-result').classList.remove('hidden');
                
                document.getElementById('result-content').innerHTML = `
                    <div class="text-center mb-6">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
                            <svg class="h-10 w-10 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold text-red-800">Netzwerkfehler</h3>
                        <p class="mt-2 text-gray-600">${error.message}</p>
                    </div>
                `;
            });
        }
        
        window.closeAndReload = function() {
            closeAutoScheduleModal();
            reloadKeepingWeek();
        }
        
        // Funktion zum Kopieren der Warnungen in die Zwischenablage
        window.copyWarningsToClipboard = function() {
            if (!window.planningWarnings) {
                alert('Keine Warnungen zum Kopieren vorhanden.');
                return;
            }
            
            let text = '=== PLANUNGS-WARNUNGEN ===\n\n';
            
            window.planningWarnings.forEach((w, index) => {
                text += `${index + 1}. ${w.message}\n`;
                
                if (w.details) {
                    if (w.details.student) text += `   Schüler: ${w.details.student}\n`;
                    if (w.details.course) text += `   Kurs: ${w.details.course}\n`;
                    if (w.details.examiner) text += `   Prüfer: ${w.details.examiner}\n`;
                    if (w.details.size) text += `   Größe: ${w.details.size} Prüfungen\n`;
                    if (w.details.week) text += `   Woche: ${w.details.week}\n`;
                    if (w.details.week1_slot) text += `   Woche 1 Zeitslot: ${w.details.week1_slot}\n`;
                    if (w.details.week2_slot) text += `   Woche 2 Zeitslot: ${w.details.week2_slot}\n`;
                    
                    if (w.details.students && w.details.students.length > 0) {
                        text += `   Betroffene Schüler:\n`;
                        w.details.students.forEach(s => text += `      - ${s}\n`);
                    }
                    
                    if (w.details.conflicts && w.details.conflicts.length > 0) {
                        text += `   Konflikte:\n`;
                        w.details.conflicts.forEach(c => text += `      - ${c}\n`);
                    }
                }
                
                text += '\n';
            });
            
            navigator.clipboard.writeText(text).then(() => {
                alert('Warnungen in Zwischenablage kopiert!');
            }).catch(err => {
                console.error('Fehler beim Kopieren:', err);
                alert('Fehler beim Kopieren in Zwischenablage');
            });
        }
        
        // Funktion zum Exportieren der Warnungen als PDF
        window.exportWarningsToPdf = function() {
            if (!window.planningWarnings || window.planningWarnings.length === 0) {
                alert('Keine Warnungen zum Exportieren vorhanden.');
                return;
            }
            
            // Warnungen als JSON in ein verstecktes Formular packen und absenden
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("planning.export-warnings-pdf", $examPeriod) }}';
            form.target = '_blank';
            
            // CSRF Token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = '{{ csrf_token() }}';
            form.appendChild(csrfInput);
            
            // Warnungen als JSON
            const warningsInput = document.createElement('input');
            warningsInput.type = 'hidden';
            warningsInput.name = 'warnings';
            warningsInput.value = JSON.stringify(window.planningWarnings);
            form.appendChild(warningsInput);
            
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
    @endpush
