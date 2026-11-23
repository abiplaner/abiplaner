# Regelwerk Externe Bewerber — §§ 61, 62 BayGSO

**Stand:** 26.08.2026. Grundlage sind die von Wilfried vorgelegten Wortlaute:
§ 61, § 62, § 48, Anlage 12 (zu § 52 Abs. 1), Anlage 14 (zu § 61 Abs. 2 und 3
sowie § 62 Abs. 1), Anlage 15 (zu § 61 Abs. 2 und 3 sowie § 64 Abs. 2).

**Zweck:** Jede Regel einzeln belegt und einzeln abnehmbar. Diese Tabelle ist die
Quelle für den Neuaufbau von `ExternalApplicantValidationService` und der
Fächerwahl-Maske (Entscheidung 3 vom 25.08.2026) sowie für die § 62-Rechnung.
**Bitte Spalte „Regel" gegen den Gesetzestext abnehmen, bevor Code entsteht.**

**Legende Bestand:** ✓ erfüllt · ~ teilweise · ✗ falsch umgesetzt · — fehlt

---

## A. Fächerwahl — § 61 Abs. 1

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| A1 | Abs. 1 S. 1 | Gegenstand der Prüfung sind acht Prüfungsfächer. | Validierung | ✓ |
| A2 | Abs. 1 S. 2 | Unter den acht Fächern müssen sein: Deutsch, Geschichte, Mathematik, eine Naturwissenschaft, zwei Fremdsprachen. | Validierung | ~ — inhaltlich richtig, aber über hart codierte Fachnamen statt der `subjects`-Flags |
| A3 | Abs. 1 S. 3 | Vier Fächer schriftlich (erster Prüfungsteil), vier weitere nur mündlich (zweiter Prüfungsteil). | Validierung | ✓ |
| A4 | Abs. 1 S. 3 | Die vier Fächer des ersten Teils werden auf Antrag des Bewerbers **oder auf Anordnung des Prüfungsausschusses** zusätzlich mündlich geprüft. | Zusatzprüfungs-Modul | — für Externe gesperrt (Z1–Z3) |
| A5 | Abs. 1 S. 4 | Wählbar sind nur Fächer, die auch für Schülerinnen und Schüler der Gymnasien als Prüfungsfächer wählbar sind (§ 48 Abs. 1 S. 8: Anlagen 3 und 4 Nr. 1). | Fächerkatalog der Maske | — `Subject::orderedForDisplay()` sortiert nur, filtert `can_abitur` nicht |
| A6 | Abs. 1 S. 5 | Mit der Anmeldung wird festgelegt, welches Fach **neben Deutsch und Mathematik** als Leistungsfach auf erhöhtem Anforderungsniveau geprüft wird. Also **genau drei eA-Fächer: Deutsch, Mathematik, Leistungsfach.** | Validierung | ✓ |
| A7 | Abs. 1 S. 6 | Bei Wahl von Kunst, Musik oder Sport als Leistungsfach gilt § 48 Abs. 1 S. 8 Nr. 1 sowie Abs. 4 und 5 entsprechend (besondere Fachprüfung mit fachpraktischem Teil). | Validierung + Planung | — |
| A8 | Abs. 1 S. 7 | Der Antrag auf mündliche Zusatzprüfung ist **spätestens am Tag nach Bekanntgabe der Ergebnisse der schriftlichen Prüfung** in Textform an den Prüfungsausschuss zu stellen. | Zusatzprüfungs-Modul (Frist) | — Modell kennt keine Frist |

## B. Erster Prüfungsteil — § 61 Abs. 2

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| B1 | Abs. 2 S. 1 | **Die vier Fächer des ersten Prüfungsteils** müssen die drei Aufgabenfelder gemäß § 18 abdecken und den Anlagen 14 oder 15 entsprechen. | Validierung | ✗ `validateAufgabenfelder()` prüft über alle acht Fächer |
| B2 | Abs. 2 S. 2 | Für die Fächer **eins bis drei** werden die zentral gestellten Abiturprüfungsaufgaben mit deren Bearbeitungszeiten und Auswahlregeln verwendet. | Planung schriftlich | ~ Service sucht undifferenziert für alle vier einen zentralen Termin |
| B3 | Abs. 2 S. 3 | Unter den Fächern eins bis drei müssen mindestens zwei auf erhöhtem Anforderungsniveau sein, **darunter Mathematik**. | Validierung | ✓ |
| B4 | Abs. 2 S. 4 | Wird Deutsch nicht als Fach des ersten Prüfungsteils gewählt, ist verpflichtend eine Fremdsprache als Fach des ersten Teils **als Leistungsfach** zu wählen. | Validierung | ~ Maske bietet „Fach 1: Deutsch ODER Fremdsprache“, der Server prüft die Bedingung nicht |
| B5 | Abs. 2 S. 5 | Die neben Mathematik zu den Fächern eins bis drei gehörenden Fächer können **bis 31. Januar** mit einem der gewählten Prüfungsfächer vier bis acht getauscht werden. | Prozess + Frist | — keine Frist, keine Historie; ein Tausch überschreibt heute die ganze Wahl |
| B6 | Abs. 2 S. 6 | Im vierten Fach des ersten Prüfungsteils können nur Fächer auf grundlegendem Anforderungsniveau geprüft werden. | Validierung | ~ serverseitig ✓, in der Maske nicht geprüft |
| B7 | Abs. 2 S. 7 | Die Aufgabenstellung für das vierte Fach erfolgt **durch die prüfende Schule**, Bearbeitungszeit **270 Minuten in den modernen Fremdsprachen, 180 Minuten in den anderen Fächern**. | Planung schriftlich | — kein zentraler Termin möglich, heute nicht abgebildet |
| B8 | Abs. 2 S. 8 | Die Vorbereitung des Bewerbers soll nach Maßgabe der Anlage 9 nach Möglichkeit berücksichtigt werden. | organisatorisch | kein Code |
| B9 | Abs. 2 S. 9 | Die mündlichen Zusatzprüfungen in den Fächern des ersten Teils dauern in der Regel **20 Minuten** und entsprechen den Regelungen für das reguläre Abitur. | Zusatzprüfungs-Modul | ~ Dauer pro Termin abbildbar, Modul für Externe gesperrt |

## C. Zweiter Prüfungsteil — § 61 Abs. 3

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| C1 | Abs. 3 S. 1 | Die vier Fächer des zweiten Teils dürfen **nicht bereits Gegenstand der schriftlichen Prüfung** sein. | Validierung | ✓ **abgenommen 31.08.2026.** Die Regel meldet der Validierungsdienst; zusätzlich fängt `ExternalApplicantSubjectsController::update()` ein doppelt gewähltes Fach **vor** der Transaktion ab. Der frühere 500er am UNIQUE-Index ist damit erledigt. |
| C2 | Abs. 3 S. 2 | Die mündliche Prüfung dauert je Fach in der Regel **30 Minuten**, die Vorbereitungszeit beträgt **30 Minuten**. | Planung | ✓ entspricht dem Kolloquium |
| C3 | Abs. 3 S. 3 | In den modernen Fremdsprachen findet die mündliche Prüfung **in der jeweiligen Fremdsprache** statt. | organisatorisch | kein Code |
| C4 | Abs. 3 S. 4 | Prüfungsanforderungen: Lerninhalte der letzten beiden Kurshalbjahre. | fachlich | kein Code |
| C5 | Abs. 3 S. 5 | Wird **das Leistungsfach oder Deutsch** als Fach des zweiten Teils gewählt, liegen die Lerninhalte der letzten **vier** Ausbildungsabschnitte zugrunde. | fachlich | kein Code — bestätigt aber: Deutsch und das Leistungsfach dürfen im zweiten Teil liegen |
| C6 | Abs. 3 S. 6 | Die Schwerpunktbildung erfolgt gemäß Anlage 9. | organisatorisch | kein Code |
| C7 | Abs. 3 S. 7 | Die **zweite Fremdsprache** wird nur auf dem Niveau einer spät beginnenden Fremdsprache geprüft. Mit § 48 Abs. 1 Nr. 2 heißt das: nur grundlegendes Niveau und nur als **mündliches** Prüfungsfach — sie kann also nie Leistungsfach und nie Fach des ersten Teils sein. | Validierung | ✓ **abgenommen 27.08.2026 (Entscheidung 31)**; die zweite Fremdsprache benennt der Prüfling (Entscheidung 32). **Drei Fremdsprachen** (31.08.2026, Entscheidung 54): Die Norm beschränkt nur die benannte zweite; für eine dritte sagt sie nichts. Die Maske meldet den Fall als **Hinweis** unter „Zu entscheiden" — die Fächerwahl bleibt gültig. |
| C8 | Abs. 3 S. 8 | Der Ablauf der Prüfung entspricht dem Kolloquium. | Planung | ✓ |
| C9 | Abs. 3 S. 9 | Sofern die Belange der prüfenden Schule es erlauben, findet **nur eine mündliche Prüfung an einem Tag** statt. | Zuweisung, Optimierer, Konflikterkennung | ✓ **abgenommen 30.08.2026** — **hart** durchgesetzt (Entscheidung Wilfrieds): Die Zuweisungsmaske lehnt eine zweite Prüfung am selben Tag ab, die Auto-Planung legt keine dorthin, und der Bestand wird als Fehler gemeldet. Für reguläre Prüflinge gibt es diese Regel nicht — bei ihnen ordnet die Wochenregel die Verteilung. |

## D. Vorgezogene mündliche Prüfungen — § 61 Abs. 4

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| D1 | Abs. 4 S. 1 | Auf Wunsch des Bewerbers können **zwei der vier mündlichen Prüfungen** im Zeitraum **zwei Wochen vor Beginn der schriftlichen Abiturprüfungen** stattfinden. | Planung | ✓ **abgenommen 31.08.2026** — als Termin ohne Zeitslot (`exam_week = 3`), von Hand eingetragen. Der Zeitraum wird aus `written_exam_year_dates` abgeleitet, nicht gepflegt. Hart geprüft: Datum im Fenster, höchstens zwei je Bewerber, nur die vier Fächer des zweiten Teils, C9 gilt weiter. |
| D2 | Abs. 4 S. 2 | Werden in diesen beiden Prüfungen die Bedingungen von § 62 Abs. 2 bereits nicht erfüllt, wird die Prüfung **abgebrochen**. | Meldung | ✓ **abgenommen 31.08.2026** — als **Warnung** in der Konfliktliste, kein Zustand. Auslegung: nach zwei von vier Prüfungen kann § 62 Abs. 2 nur über Nr. 1 (Fach mit 0 Punkten) schon aus sein; die 80 Punkte und die Zwei-mal-5-Regel bleiben rechnerisch erreichbar (Restpotenzial 2 × 60 = 120). |
| D3 | Abs. 4 S. 3 | Die Ergebnisse werden ansonsten erst nach Abschluss der schriftlichen Prüfungen mitgeteilt. | organisatorisch | ✓ **entschieden 31.08.2026** — bewusst nicht abgebildet. Die Punkte bleiben überall sichtbar; der Hinweis steht in der Hilfe (Kapitel *Externe Bewerber*). |

## E. Bewertung und Bestehen — § 62 mit Anlagen 12 und 14

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| E1 | Anlage 14 | **Faktoren und Höchstpunktzahlen:** schriftliche Fächer 1–4 je **Faktor 11**, je höchstens **165** Punkte; mündliche Fächer 5–8 je **Faktor 4**, je höchstens **60** Punkte. Insgesamt **900**. | Berechnung | — |
| E2 | Anlage 14 | **Positionen und Niveaus:** Fach 1 schriftlich = Mathematik (eA); Fach 2 schriftlich eA; Fach 3 schriftlich eA oder gA; Fach 4 schriftlich gA; Fach 5 mündlich eA oder gA; Fächer 6–8 mündlich gA. Es darf also **höchstens ein mündliches Fach auf eA** liegen. | Validierung + Maske | ✓ **abgenommen 31.08.2026.** `pruefePositionen()` prüft beides — Mathematik auf Position 1 und kein eA auf den Positionen 6–8 —, und die Maske fixiert Mathematik auf **Fach 1** (nicht abwählbar). Der frühere Eintrag beschrieb einen überholten Stand. |
| E3 | § 62 Abs. 1 S. 1 | Die Gesamtpunktzahl in den Fächern des ersten Prüfungsteils wird gemäß Anlage 14 ermittelt. | Berechnung | — |
| E4 | § 62 Abs. 1 S. 2 mit Anlage 12 S. 4–6 | Mit mündlicher Zusatzprüfung gilt für andere Bewerber **P = ((2s + m) / 3) × 11**, das Ergebnis wird **erst am Ende gerundet**. Bei einem Ergebnis unter **11** Punkten (elffache Wertung) ist die Abiturprüfung nicht bestanden. | Berechnung | — für Externe; für reguläre Schüler ist die Formel mit Faktor 4 umgesetzt |
| E5 | § 62 Abs. 1 S. 3 | **Erster Teil bestanden**, wenn (1) kein Fach mit 0 Punkten abgeschlossen wurde, (2) insgesamt mindestens **220** Punkte erreicht wurden und (3) in mindestens zwei der vier Fächer wenigstens **5 Punkte der einfachen Wertung**, davon eines mit erhöhtem Anforderungsniveau (Deutsch, Mathematik oder Leistungsfach). **Ein Aufrunden ist nicht zulässig.** | Berechnung | — |
| E6 | § 62 Abs. 1 S. 4 | Sind diese Bedingungen nicht erfüllt, wird die Prüfung **nach dem ersten Prüfungsteil abgebrochen**. | Meldung | ✓ **abgenommen 31.08.2026** — als **Warnung** in der Konfliktliste der Kolloquiumsplanung, kein Zustand. Gerechnet wurde der Abbruch schon vorher (`berechne()` liefert `abbruch`), sichtbar war er nur im Mindestpunkte-Modal und im PDF; die vier mündlichen Prüfungen standen unbemerkt weiter im Raster. Ob abgebrochen wird, entscheidet der Koordinator. |
| E7 | § 62 Abs. 2 | **Zweiter Teil bestanden**, wenn (1) kein Fach mit 0 Punkten, (2) insgesamt mindestens **80** Punkte und (3) in mindestens zwei der vier Fächer wenigstens 5 Punkte der einfachen Wertung. | Berechnung | — |
| E8 | § 62 Abs. 3 | Die Summe der Gesamtpunktzahlen der acht Prüfungsfächer ergibt die Punktzahl der Gesamtqualifikation. | Berechnung | — |

**Die Skala geht auf:** Teil 1 = 4 × 165 = **660**, Teil 2 = 4 × 60 = **240**,
zusammen **900**. Die Schwellen 220 und 80 sind jeweils genau ein Drittel — wie
100 von 300 beim regulären Abitur. Die Gesamtqualifikation liegt damit auf
derselben **300–900**-Skala wie bei regulären Schülern, nur anders
zusammengesetzt: keine Halbjahresleistungen, nur die acht Prüfungen.

## F. Fachbezogene Beschränkungen — § 48, über § 61 Abs. 1 S. 4 und S. 6

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| F1 | § 48 Abs. 1 Nr. 1 | Kunst und Musik nur als **schriftliches**, Sport als schriftliches oder mündliches Prüfungsfach (besondere Fachprüfung) — und nur, wenn das Fach als Leistungsfach belegt ist. Kunst und Musik nur dann mündlich, wenn sie keine Leistungsfächer sind. | Validierung | — |
| F2 | § 48 Abs. 1 Nr. 2 | Spät beginnende Fremdsprachen, spät beginnende Informatik, Wirtschaftsinformatik und Sozialwissenschaftliche Arbeitsfelder: nur auf **grundlegendem** Niveau und nur als **mündliches** Prüfungsfach. | Validierung | ✓ trägt C7 (Entscheidung 31) |
| F3 | § 48 Abs. 1 Nr. 3 | Lehrplanalternative Biophysik: Physik nur gA und nur als mündliches Prüfungsfach. | Validierung | — Lehrplanalternativen sind im Datenmodell nicht abgebildet |
| F4 | § 48 Abs. 1 Nr. 4 | Lehrplanalternative Astrophysik: Physik nur gA. | Validierung | — |
| F5 | § 48 Abs. 1 Nr. 5 | Lehrplanalternative Geologie: Geographie nur gA und nur als mündliches Prüfungsfach. | Validierung | — |
| F6 | § 48 Abs. 4 | Ist Kunst oder Musik schriftliches Prüfungsfach, tritt an die Stelle der schriftlichen Prüfung eine **besondere Fachprüfung** mit schriftlichem und fachpraktischem Teil. | Planung | — |
| F7 | § 48 Abs. 5 | Ist Sport Prüfungsfach, besteht die Prüfung aus einer besonderen Fachprüfung mit fachpraktischem Teil; mündlich-theoretischer Teil nach § 50 Abs. 1 und 2, sportartspezifisch praxisbezogener Teil nach Anlage 8 Nr. 15. | Planung + Bewertung | ~ `zusatzpruef_exam_scores.practical_grade` existiert bereits für reguläre Sport-Fälle |

## G. Zweite Variante: Ersatzschüler nach § 64 Abs. 2 / Anlage 15

**Entscheidung Wilfried (26.08.2026): wird unterstützt.** Anlage 15 ist nicht die
Kunst/Musik/Sport-Variante, sondern der Fall **staatlich genehmigter
Ersatzschulen**, wenn von der Ersetzungsmöglichkeit nach § 64 Abs. 2 Gebrauch
gemacht wird.

| ID | Norm | Regel | Greift in | Bestand |
|---|---|---|---|---|
| G1 | § 64 Abs. 1 S. 1 | Anträge mehrerer Bewerber derselben Ersatzschule werden **gesammelt** eingereicht; die Ersatzschule richtet bis zum **15. Dezember** eine Anzeige **unter Angabe der Prüfungsfächer** an die prüfende Schule. | Frist + Erfassung | — |
| G2 | § 64 Abs. 1 Nr. 1 | Die Abiturprüfung ist **in den Räumen der Ersatzschule** abzunehmen, wenn diese geeignet sind und die Belange der prüfenden Schule es zulassen. | Raumplanung | — Räume sind heute Bestand der eigenen Schule |
| G3 | § 64 Abs. 1 Nr. 2 | Nach Möglichkeit **eigene Fachausschüsse**; eine Lehrkraft der Ersatzschule wirkt als Mitglied mit (nicht als Vorsitz) — bei der Korrektur der schriftlichen Arbeiten und bei den mündlichen Prüfungen. | Prüferzuweisung | — schulfremde Lehrkräfte sind in `teachers` nicht vorgesehen |
| G4 | § 64 Abs. 1 Nr. 3 | Die Aufgaben im **vierten Prüfungsfach** erstellt die prüfende Schule (bestätigt B7). Für die **Fächer fünf bis acht** erstellt die Ersatzschule Aufgabenvorschläge; die **Auswahl trifft die prüfende Schule**. | Aufgabenverwaltung | — |
| G5 | § 64 Abs. 2 S. 1 | Abweichend von § 61 Abs. 3 kann in **genau zwei** der vier Fächer des zweiten Prüfungsteils **an Stelle der mündlichen Prüfung** das im letzten Ausbildungshalbjahr an der Ersatzschule erzielte Ergebnis eingebracht werden — Voraussetzung: der Bewerber hat den Unterricht in **allen** gewählten Prüfungsfächern an der Ersatzschule besucht. | Validierung + Planung | ✓ `pruefeEinbringung()`; der Unterrichtsbesuch wird **nicht** geprüft (Entscheidung 36) |
| G6 | § 64 Abs. 2 S. 7 | **Ausgenommen** von dieser Ersetzung sind die **beiden Fremdsprachen, das Leistungsfach und Deutsch.** | Validierung | ✓ als Regel über die Positionen 7 und 8; meldet auch die Fächerwahl, die keine zwei ersetzbaren Fächer lässt (Entscheidung 33) |
| G7 | § 64 Abs. 2 S. 2 | Für die ersetzten Fächer werden jeweils **eine Schulaufgabe** sowie **mindestens zwei kleine Leistungsnachweise, darunter mindestens ein mündlicher**, verlangt. | Erfassung | **entfällt** — erfasst wird nur die fertige Punktzahl (Entscheidung 35) |
| G8 | § 64 Abs. 2 S. 3 | Die Schulaufgaben sind der prüfenden Schule **vorher vorzulegen**; diese nimmt auch die **Zweitkorrektur** vor. | Prozess | — |
| G9 | § 64 Abs. 2 S. 4 | Das Ergebnis ist der **Durchschnitt aus der doppelt gewichteten Punktzahl der Schulaufgabe und dem Durchschnitt der kleinen Leistungsnachweise**. | Berechnung | **entfällt** — die Ersatzschule meldet das Ergebnis, die Anwendung rechnet es nicht nach (Entscheidung 35); der Rechenweg steht ungenutzt in `ExterneGesamtqualifikationService` |
| G10 | § 64 Abs. 2 S. 5 / Anlage 15 | Aus dieser Punktzahl **in einfacher Wertung** wird die Gesamtpunktzahl nach Anlage 15 berechnet: Faktor **4**, höchstens **60** Punkte je Fach. | Berechnung | — |
| G11 | § 64 Abs. 2 S. 8 | § 61 Abs. 4 Satz 1 bleibt unberührt — die beiden **vorgezogenen mündlichen Prüfungen** sind auch hier möglich. | Planung | — |
| G12 | Anlage 15 | Struktur: Fächer 1–4 schriftlich (Faktor 11, je 165), Fächer 5–6 mündlich (Faktor 4, je 60), Fächer 7–8 aus 13/II (Faktor 4, je 60). Faktorsummen 52 und 8, Gesamtqualifikation ebenfalls **900**. | Berechnung | — |
| G13 | § 64 Abs. 2 S. 6 | Das Nähere regelt die oder der Vorsitzende des Prüfungsausschusses. | organisatorisch | kein Code |

### Die offene Auslegungsfrage ist beantwortet

§ 64 Abs. 2 Satz 1 ersetzt **die mündliche Prüfung**, nicht das Fach: die beiden
betroffenen Fächer bleiben Fächer des zweiten Prüfungsteils. **§ 62 Abs. 2 gilt
damit über alle vier** — die 80 Punkte und „mindestens zwei der vier Fächer mit
wenigstens 5 Punkten der einfachen Wertung" schließen die eingebrachten Fächer
ein. Das deckt sich mit der Rechnung: 4 × 60 = 240, ein Drittel = 80.

Damit ist auch meine frühere Formulierung „nur sechs Prüfungen statt acht"
ungenau: Es bleiben **acht Prüfungsfächer**, von denen sechs geprüft und zwei
eingebracht werden.

### Was das zusätzlich kostet

- **Ein Unterscheidungsmerkmal am Bewerber.** `external_applicants` braucht das
  Bewertungsschema (Anlage 14 oder Anlage 15) — daran hängen Fächerzahl,
  Validierung, Planung und Berechnung. Vermutlich zusätzlich die Ersatzschule
  selbst als Angabe.
- **`external_exam_subjects` braucht zwei neue Dinge:** eine Kennzeichnung, ob
  das Fach geprüft wird oder eingebracht wird, und die eingebrachte Punktzahl.
  Die beiden ersetzten Fächer erzeugen **keine** `student_exams`-Zeile — der
  Generierungsdienst muss sie überspringen. Der unique Fremdschlüssel auf
  `student_exams` bleibt richtig, die Beziehung wird nur von „genau eine" zu
  „höchstens eine".
- **Offene Gestaltungsfrage zu G9:** Speichern wir nur das fertige Ergebnis, das
  die Ersatzschule meldet, oder die beiden Bestandteile (Punktzahl der
  Schulaufgabe und Durchschnitt der kleinen Leistungsnachweise) und rechnen
  Satz 4 selbst? Für das Selberrechnen spricht, dass die prüfende Schule ohnehin
  die Zweitkorrektur der Schulaufgabe vornimmt (G8) und die Zahl damit
  nachvollziehbar bleibt; dagegen der zusätzliche Erfassungsaufwand.
- **Räume und Prüfer reichen über die eigene Schule hinaus** (G2, G3): die
  Prüfung kann in Räumen der Ersatzschule stattfinden, und eine Lehrkraft der
  Ersatzschule sitzt im Fachausschuss. Räume und Lehrkräfte sind heute Bestand
  der eigenen Schule. Ein Muster gibt es schon: `zusatzpruef_pruefer` führt
  `role = 'gast'` mit `name_override`, und beim Onboarding werden
  EXTERN-Platzhalter als Lehrkräfte angelegt.
- **Zwei weitere Fristen** (G1: 15. Dezember, dazu A8 und B5).
- **Es wären die ersten Halbjahresnoten überhaupt im System.** Die Anwendung
  führt heute keinerlei Halbjahres- oder Kursleistungen — die einzige Notentabelle
  ist `zusatzpruef_exam_scores` (Abiturprüfungsnoten für die
  Mindestpunkte-Rechnung), und die Gesamtpunktzahl regulärer Schüler wird von
  Hand eingetragen. Zwei Fachnoten aus 13/II sind ein kleiner, aber neuer
  Datenpunkt mit eigener Herkunft: sie kommen von der Ersatzschule, nicht aus
  der Prüfung.
- **Die § 62-Rechnung wird zweischemig**, nicht nur zweiwegig — Anlage 14 und
  Anlage 15 unterscheiden sich in Fächerzahl und Punktquelle, nicht nur in
  Faktoren.

### Entscheidungen 31 bis 39 (Wilfried, 27.08.2026)

Abgenommen anhand der Wortlaute aus `BayGSO-64.pdf`, `BayGSO-ANL_27.pdf`
(Anlage 15), `BayGSO-ANL_26.pdf` (Anlage 14), `BayGSO-61.pdf` und
`BayGSO-48.pdf`. Die Vorlage dazu steht in `ABNAHME-64-ANLAGE-15.md`.

31. **C7 ist richtig hergeleitet.** Die zweite Fremdsprache wird nur auf dem
    Niveau einer spät beginnenden geprüft — also nie Leistungsfach, nie Fach
    des ersten Prüfungsteils.
32. **Der Prüfling benennt die zweite Fremdsprache** selbst
    (`external_applicants.zweite_fremdsprache_subject_id`). Die alte Zählregel
    ("höchstens eine wie eine fortgeführte") ist damit ersetzt.
33. **Lässt die Fächerwahl keine zwei ersetzbaren Fächer, wird das gemeldet** —
    die Ersetzung verschwindet nicht stillschweigend aus der Maske.
34. **Alle acht Prüfungen sind einzubringen; bei Ersatzschülern können die
    Prüfungen 7 und 8 durch die Leistung aus 13/II ersetzt werden.** Die
    Positionen 7 und 8 sind zwingend, und das Schema folgt allein daraus:
    keine Einbringung → Anlage 14, zwei → Anlage 15, alles andere ist ein
    Verstoß. Kein Schemafeld.
35. **Erfasst wird nur die fertige Punktzahl in einfacher Wertung.** Wie sie an
    der Ersatzschule zustande kam, ist hier ohne Belang. Damit sind
    Entscheidung 6 und der Bestandteil-Teil von Entscheidung 17 aufgehoben;
    der Rechenweg nach S. 4 bleibt ungenutzt im Service stehen.
36. **Die Voraussetzung aus § 64 Abs. 2 S. 1** (Unterricht in allen
    Prüfungsfächern an der Ersatzschule) wird im Programm nicht geprüft.
37. **Die Ersatzschule bekommt eine eigene Tabelle** (`ersatzschulen`).
38. **G1 bis G4, G7, G8 und G11 sind organisatorisch** und nicht Gegenstand
    dieser Runde.
39. **Die Punkte der Fächer 7 und 8 stammen aus 13/II**, dem letzten
    Ausbildungshalbjahr — so beschriften es Maske und Ausdrucke.

---

## Was daraus für die Umsetzung folgt

**Die Validierung ist substanziell näher am Gesetz als gedacht.** A1, A3, A6,
B3, B6 und C1 stimmen. Falsch waren B1 (Aufgabenfelder über die falsche Menge),
C7/F2 (zweite Fremdsprache nur Warnung statt Regel) und E2 (Positionen und
eA-Verteilung nach Anlage 14) — alle drei sind beim Neuaufbau vom 26.08.
behoben, C7 seit dem 27.08. auf die benannte zweite Fremdsprache umgestellt
(Entscheidungen 31 und 32). Es fehlen weiterhin A7/F1/F6/F7 (Kunst, Musik,
Sport) und F3–F5 (Lehrplanalternativen).

**Der größere Teil der Arbeit liegt nicht in der Validierung, sondern in der
Planung und in der Bewertung** — und dieser Teil war in der Bestandsaufnahme vom
25.08. noch gar nicht sichtbar:

- **B7:** Das vierte schriftliche Fach ist eine schuleigene Aufgabe mit eigener
  Bearbeitungszeit (270/180 Minuten) und ohne zentralen Termin.
- ~~**C9:** höchstens eine mündliche Prüfung pro Tag und Bewerber.~~ *(erledigt
  30.08.2026 — und mit ihr der eigentliche Befund dahinter: Die Auto-Planung
  konnte für einen externen Bewerber überhaupt nur zwei Prüfungen platzieren,
  weil sie ihn der Wochenregel regulärer Schüler unterwarf.)*
- ~~**D1–D3:** zwei der vier Kolloquien können zwei Wochen vor den schriftlichen
  Prüfungen liegen.~~ *(erledigt 31.08.2026. Kein dritter Zeitraum neben
  `week_1`/`week_2` geworden, sondern ein Termin ohne Zeitslot mit
  `exam_week = 3` — Entscheidung Wilfrieds: von Hand, weil es wenige Bewerber
  sind und die Termine einzeln vereinbart werden. Das Fenster wird aus den
  schriftlichen Jahresterminen abgeleitet und nirgends gepflegt.)*
- **E1–E8:** die vollständige § 62-Rechnung. **Beide Abbruchtatbestände sind
  seit dem 31.08.2026 als Meldung abgebildet und nicht als Zustand** — D2 nach
  § 61 Abs. 4 S. 2 und E6 nach § 62 Abs. 1 S. 4. Die Anwendung nennt den Grund
  in der Konfliktliste; der Abbruch bleibt eine Entscheidung des Koordinators.
  Was das Datenmodell weiterhin nicht führt, ist ein Zustand „abgebrochen", der
  die restlichen Prüfungen aus der Planung nähme.
- **A8, B5:** zwei Fristen (Antrag auf Zusatzprüfung, Fächertausch bis 31.1.),
  die das Modell nicht führt.
