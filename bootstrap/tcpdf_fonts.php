<?php

/*
 * TCPDF-7-Font-Pfad.
 *
 * tcpdf 7 ist eine Kompatibilitäts-Fassade über tc-lib-pdf und löst Fonts über
 * den tc-lib-Font-Stack auf. Ohne definiertes K_PATH_MAIN setzt tcpdf_autoconfig.php
 * K_PATH_FONTS nicht, sodass SetFont('helvetica') fehlschlägt. Zusätzlich prüft
 * tc-lib den Font-Pfad gegen eine Allowlist, die mit realpath() normalisiert wird –
 * ein Pfad mit '..'-Segmenten oder doppeltem Slash matcht diese Allowlist nicht.
 * Deshalb setzen wir K_PATH_FONTS als sauber aufgelösten realpath, früh (via
 * composer autoload.files), bevor ein Controller new TCPDF() aufruft.
 *
 * Die Font-JSONs werden beim Docker-Image-Build generiert (siehe docker/Dockerfile,
 * "make fonts"-Schritt) und liegen flach in target/fonts/.
 */
if (! defined('K_PATH_FONTS')) {
    $tcpdfFontDir = realpath(__DIR__ . '/../vendor/tecnickcom/tc-lib-pdf-font/target/fonts');
    if ($tcpdfFontDir !== false) {
        define('K_PATH_FONTS', $tcpdfFontDir . '/');
    }
}
