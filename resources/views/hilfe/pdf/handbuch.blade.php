<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>AbiPlaner — Benutzerhandbuch</title>
    {{--
        Die Regeln stehen hier und nicht in app.css: dompdf lädt kein
        gebündeltes Stylesheet, und eine fehlende Klasse fällt ihm nicht auf.
        Bewusst nur einfache Eigenschaften — kein flex, kein grid.
    --}}
    <style>
        @page { margin: 22mm 18mm 20mm 18mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9.5pt;
               line-height: 1.5; color: #333; }
        h1 { font-size: 16pt; color: #111; margin: 0 0 6pt; page-break-before: always; }
        h1:first-of-type { page-break-before: avoid; }
        h2 { font-size: 12.5pt; color: #111; margin: 14pt 0 4pt;
             border-bottom: 0.6pt solid #ccc; padding-bottom: 2pt; }
        h3 { font-size: 10.5pt; color: #222; margin: 11pt 0 3pt; }
        h4 { font-size: 9.5pt; color: #333; margin: 9pt 0 2pt; }
        p { margin: 4pt 0; }
        ul, ol { margin: 4pt 0 4pt 14pt; padding: 0; }
        li { margin: 2pt 0; }
        strong { font-weight: bold; color: #111; }
        code { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt;
               background: #f2f2f2; padding: 0 2pt; }
        pre { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt;
              background: #f7f7f7; border: 0.5pt solid #ddd; padding: 5pt;
              margin: 5pt 0; white-space: pre-wrap; }
        pre code { background: none; padding: 0; }
        blockquote { border-left: 2pt solid #c7d2fe; background: #eef2ff;
                     padding: 4pt 8pt; margin: 5pt 0; }
        table { width: 100%; border-collapse: collapse; margin: 5pt 0; font-size: 8.5pt; }
        th { background: #f2f2f2; text-align: left; border: 0.5pt solid #ccc; padding: 3pt 4pt; }
        td { border: 0.5pt solid #ccc; padding: 3pt 4pt; vertical-align: top; }
        hr { border: 0; border-top: 0.5pt solid #ddd; margin: 8pt 0; }
        .fusszeile { position: fixed; bottom: -12mm; left: 0; right: 0;
                     font-size: 7.5pt; color: #888; text-align: center; }
    </style>
</head>
<body>
    {{--
        Die Fusszeile wird in PHP zusammengesetzt, nicht mit @if im Text:
        Blade erkennt eine Direktive nicht, wenn unmittelbar davor ein
        Wortzeichen steht („…handbuch@if" bleibt roher Text, das @endif steht
        dann allein da und der View bricht beim Kompilieren).
    --}}
    @php
        $fussteile = array_filter([
            'AbiPlaner — Benutzerhandbuch',
            $schulname,
            $jahr ? 'Abitur ' . $jahr : null,
        ]);
    @endphp
    <div class="fusszeile">{{ implode(' · ', $fussteile) }}</div>

    {!! $inhalt !!}
</body>
</html>
