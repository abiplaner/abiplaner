<?php

namespace App\Services;

use App\Models\SupportAnhang;
use App\Models\SupportFall;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bildschirmfotos an einer Support-Meldung (Modul 6.4, Entscheidung 48).
 *
 * Drei Regeln, und jede hat einen Grund:
 *
 * **Der Dateiname des Melders wird nie zum Pfad.** Gespeichert wird unter
 * einer ULID mit einer Endung aus dem *erkannten* Typ; der urspruengliche Name
 * steht nur in der Datenbank und nur zur Anzeige. Damit gibt es keinen Weg von
 * einer Eingabe in einen Pfad — und `../` ist keine Frage mehr.
 *
 * **Der Typ kommt aus dem Inhalt, nicht aus der Endung.** Die Validierung im
 * Controller nutzt `mimetypes` (finfo) und `image`; hier wird die Endung aus
 * dem erkannten MIME-Typ abgeleitet, nicht aus dem, was der Browser meldet.
 *
 * **Die Dateien liegen auf `local` (storage/app/private)** und damit ausserhalb
 * des Web-Verzeichnisses. Ausgeliefert werden sie ueber eine Route hinter der
 * Policy — mit `nosniff` und `Content-Disposition: inline`, damit der Browser
 * ein Bild als Bild und nichts anderes behandelt.
 */
class SupportAnhangService
{
    public const DISK = 'local';

    public const MAX_ANZAHL = 3;

    /** In Kilobyte — dieselbe Einheit, die die Validierungsregel `max` nutzt. */
    public const MAX_KB = 5120;

    /** @var array<string, string> erkannter MIME-Typ → Endung */
    public const TYPEN = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * Die Validierungsregel fuer das Formularfeld — an einer Stelle, damit
     * Maske, Controller und Test nicht auseinanderlaufen.
     *
     * @return list<string>
     */
    public static function regeln(): array
    {
        return [
            'image',
            'mimetypes:' . implode(',', array_keys(self::TYPEN)),
            'max:' . self::MAX_KB,
        ];
    }

    /**
     * @param  list<UploadedFile>  $dateien
     * @return list<SupportAnhang>
     */
    public function speichere(SupportFall $fall, array $dateien, ?int $userId): array
    {
        $angelegt = [];

        foreach (array_slice($dateien, 0, self::MAX_ANZAHL) as $datei) {
            $mime = (string) $datei->getMimeType();

            // Doppelter Boden: Die Validierung hat das schon geprueft. Faellt
            // sie eines Tages weg oder wird der Dienst von anderswo gerufen,
            // landet trotzdem nichts Unerwartetes auf der Platte.
            if (! isset(self::TYPEN[$mime])) {
                continue;
            }

            $pfad = 'support/' . $fall->id . '/' . Str::ulid() . '.' . self::TYPEN[$mime];

            Storage::disk(self::DISK)->put($pfad, (string) file_get_contents($datei->getRealPath()));

            $angelegt[] = SupportAnhang::query()->create([
                'support_fall_id' => $fall->id,
                'dateiname' => Str::limit((string) $datei->getClientOriginalName(), 200, ''),
                'pfad' => $pfad,
                'mime' => $mime,
                'groesse' => (int) $datei->getSize(),
                'user_id' => $userId,
            ]);
        }

        return $angelegt;
    }

    /**
     * Ausliefern — nie als Datei-URL, immer ueber diese Stelle.
     *
     * `nosniff` und ein fester Content-Type: Der Browser soll das Bild als
     * Bild behandeln und auf keinen Fall etwas anderes daraus machen.
     */
    public function ausliefern(SupportAnhang $anhang): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($anhang->pfad), 404);

        return Storage::disk(self::DISK)->response($anhang->pfad, $anhang->dateiname, [
            'Content-Type' => $anhang->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="' . addslashes($anhang->dateiname) . '"',
        ]);
    }

    /**
     * Die Dateien eines Falls entfernen (Entscheidung 48: mit dem Abschluss).
     *
     * Die Zeilen fallen mit; ein Eintrag ohne Datei waere ein toter Verweis,
     * den die Maske als kaputt anzeigt.
     *
     * @return int Zahl der entfernten Anhaenge
     */
    public function loesche(SupportFall $fall): int
    {
        $anhaenge = SupportAnhang::query()->where('support_fall_id', $fall->id)->get();

        foreach ($anhaenge as $anhang) {
            Storage::disk(self::DISK)->delete($anhang->pfad);
            $anhang->delete();
        }

        // Das Verzeichnis mit, sonst bleibt je Fall ein leerer Ordner stehen.
        Storage::disk(self::DISK)->deleteDirectory('support/' . $fall->id);

        return $anhaenge->count();
    }
}
