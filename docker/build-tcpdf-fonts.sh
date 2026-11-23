#!/bin/sh
#
# build-tcpdf-fonts.sh
#
# Generiert die tc-lib-pdf-Font-Definitionen (JSON) für tcpdf 7.
#
# Hintergrund: tcpdf 7 ist eine Fassade über tc-lib-pdf und liefert KEINE
# vorgenerierten Fonts mehr mit. SetFont('helvetica') scheitert sonst mit
# "unable to read file: helvetica.json". Die Fonts müssen einmalig aus den
# Quellschriften (tc-font-mirror) erzeugt und flach nach target/fonts/ gelegt
# werden. Dieses Script ist idempotent: Liegt helvetica.json bereits vor, tut
# es nichts. Es wird via composer post-install-cmd / post-update-cmd ausgeführt.

set -e

FONTPKG="vendor/tecnickcom/tc-lib-pdf-font"
TARGET="$FONTPKG/target/fonts"

# Bereits gebaut? Dann nichts tun.
if [ -f "$TARGET/helvetica.json" ]; then
    echo "tcpdf-Fonts bereits vorhanden – überspringe Build."
    exit 0
fi

# tcpdf 7 überhaupt installiert?
if [ ! -d "$FONTPKG/util" ]; then
    echo "tc-lib-pdf-font nicht installiert – überspringe Font-Build."
    exit 0
fi

echo ">>> Generiere tcpdf-7-Fonts ..."

# 1) Quellschriften (tc-font-mirror) in den util-Ordner laden.
( cd "$FONTPKG/util" && composer install --no-interaction --quiet )

# 2) bulk_convert.php erwartet ein vendor/autoload.php im Paket-Root; im
#    verschachtelten Composer-Layout existiert das nicht. Stub anlegen, der
#    auf den Haupt-Autoloader zeigt.
mkdir -p "$FONTPKG/vendor"
printf '<?php require __DIR__."/../../../autoload.php";' > "$FONTPKG/vendor/autoload.php"

# 3) Konvertierung ausführen (schreibt nach target/fonts/<kategorie>/).
( cd "$FONTPKG/util" && php bulk_convert.php )

# 4) JSON-/.z-Dateien flach nach target/fonts/ kopieren – dort sucht der
#    Font-Stack (K_PATH_FONTS + ein glob auf Unterordner, aber die flache
#    Ablage ist die robuste Variante).
( cd "$TARGET" && find . -mindepth 2 -name '*.json' -exec cp {} . \; )
( cd "$TARGET" && find . -mindepth 2 -name '*.z' -exec cp {} . \; 2>/dev/null || true )

echo ">>> tcpdf-Fonts erzeugt: $(ls "$TARGET"/*.json 2>/dev/null | wc -l) Definitionen."
