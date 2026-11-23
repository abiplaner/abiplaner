{{--
    Gemeinsames Basis-Stylesheet für alle PDF-Exports (DomPDF).

    Einbindung im <head> der jeweiligen PDF-View:
        @include('exports.partials.pdf-base-styles')

    Zweck: einheitliche Export-Optik (Roadmap 5.1). Definiert die eine
    Akzentfarbe (Leitblau #1e40af), eine abgestufte Blau-Palette, neutrale
    Grautöne und Statusfarben sowie Grundstile für Header und Tabellen.

    Hinweise zu DomPDF (siehe docs / bisherige Erfahrungen):
    - Externe Stylesheets (<link>) sind unzuverlässig → daher dieser
      eingebettete <style>-Block per @include.
    - Spaltenbreiten weiterhin als inline style="width:X%" am <th>/<td>
      setzen; table-layout:fixed liest nur die erste Header-Zeile.
    - Seitenrand bevorzugt über body{margin} statt @page (zuverlässiger).
    - View-spezifische Feinheiten dürfen in der jeweiligen View per
      eigenem <style>-Block ergänzt/überschrieben werden (nach diesem
      @include eingebunden).

    CSS-Custom-Properties werden von DomPDF NICHT zuverlässig unterstützt;
    daher sind die Farbwerte hier bewusst als feste Hex-Werte ausgeschrieben
    und an einer Stelle gepflegt.
--}}
<style>
    /* ===== Grundlayout ===== */
    * { box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8pt;
        line-height: 1.2;
        color: #1f2937;          /* neutral-800: Standard-Textfarbe */
        margin: 12mm;            /* DomPDF: body-margin statt @page */
    }

    /* ===== Kopfbereich ===== */
    .pdf-header {
        text-align: center;
        margin-bottom: 8px;
        border-bottom: 2px solid #1e40af;   /* Akzent: Leitblau */
        padding-bottom: 5px;
    }
    .pdf-header h1 {
        font-size: 13pt;
        margin: 0 0 2px 0;
        color: #1e3a8a;          /* dunkleres Blau für Titel */
    }
    .pdf-subtitle {
        font-size: 8pt;
        color: #4b5563;          /* neutral-600 */
        margin-top: 2px;
    }

    /* ===== Tabellen (Standard) ===== */
    table.pdf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 8pt;
    }
    table.pdf-table thead th {
        background-color: #1e40af;   /* Akzent: Leitblau */
        color: #ffffff;
        padding: 3px 5px;
        text-align: left;
        font-weight: bold;
        border: 1px solid #1d4ed8;   /* etwas helleres Blau als Rahmen */
    }
    table.pdf-table tbody td {
        padding: 2px 5px;
        border-right: 1px solid #d1d5db;   /* neutral-300 */
        border-bottom: 1px solid #e5e7eb;  /* neutral-200 */
        vertical-align: middle;
    }
    table.pdf-table tbody td:last-child {
        border-right: none;
    }
    /* Zebra-Streifen: gerade Zeilen zart blau hinterlegt */
    table.pdf-table tbody tr.pdf-row-even td,
    table.pdf-table tbody.pdf-even tr td {
        background-color: #eff6ff;   /* blue-50 */
    }

    /* ===== Wiederkehrende Helfer ===== */
    .pdf-muted {
        color: #9ca3af;          /* neutral-400: Platzhalter / „—" */
        font-style: italic;
    }
    .pdf-bold { font-weight: bold; }
    .pdf-center { text-align: center; }
    .pdf-right { text-align: right; }
    .pdf-nowrap { white-space: nowrap; }

    /* ===== Statusfarben (einheitlich) ===== */
    .pdf-ok      { color: #047857; }   /* grün  – bestanden / frei */
    .pdf-warn    { color: #b45309; }   /* amber – Hinweis / Achtung */
    .pdf-danger  { color: #b91c1c; }   /* rot   – Fehler / belegt */

    .pdf-badge {
        display: inline-block;
        padding: 0 4px;
        border-radius: 2px;
        font-size: 7pt;
        font-weight: bold;
        color: #ffffff;
    }
    .pdf-badge-ok     { background-color: #047857; }
    .pdf-badge-warn   { background-color: #b45309; }
    .pdf-badge-danger { background-color: #b91c1c; }
    .pdf-badge-info   { background-color: #1e40af; }

    /* ===== Fußzeile ===== */
    .pdf-footer {
        margin-top: 10px;
        font-size: 7pt;
        color: #6b7280;          /* neutral-500 */
        text-align: center;
    }

    /* ===== Wiederkehrende Komponenten ===== */
    /* Diese Klassen waren in vielen Export-Views identisch dupliziert und
       sind hier zentralisiert. Klassennamen bewusst unverändert gelassen,
       damit die Views nur ihren lokalen <style>-Block verlieren, nicht ihr
       HTML-Markup. */

    /* Info-Box (9 Views): Kasten mit Kurs-/Raum-/Anzahl-Angaben */
    .info-box {
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        padding: 10px;
        margin-bottom: 20px;
        border-radius: 3px;
    }
    .info-box p { margin: 5px 0; }

    /* Statistik-Kacheln (student-list, supervision-lists) */
    .statistics {
        display: table;
        width: 100%;
        margin: 15px 0;
        background-color: #eff6ff;     /* blue-50 (vorher teils grün) */
        border: 1px solid #93c5fd;
        border-radius: 3px;
        padding: 10px;
    }
    .stat-item {
        display: table-cell;
        width: 33.33%;
        text-align: center;
        padding: 5px;
    }
    .stat-value {
        font-size: 18pt;
        font-weight: bold;
        color: #1e40af;                /* Leitblau */
    }
    .stat-label {
        font-size: 9pt;
        color: #666;
    }

    /* Unterschriften-Block (student-list u. a.) */
    .signature-section {
        margin-top: 40px;
        padding-top: 20px;
        border-top: 1px solid #cbd5e1;
    }
    .signature-line {
        margin-top: 30px;
        display: table;
        width: 100%;
    }
    .signature-field {
        display: table-cell;
        width: 50%;
        text-align: center;
    }
    .signature-field .line {
        border-top: 1px solid #000;
        margin: 0 20px 5px 20px;
    }
    .signature-field .label {
        font-size: 9pt;
        color: #666;
    }
</style>
