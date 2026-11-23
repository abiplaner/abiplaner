<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Die Kennung des zuletzt aufgetretenen Fehlers (Modul 6.4, Stufe 1).
 *
 * **Wozu:** Der Fehler passiert in Request A, die Meldung entsteht in Request
 * B. Zwischen beiden liegt nur die Sitzung. Damit der Fall im Dashboard und
 * die Zeile in `storage/logs` zueinander finden, ohne dass jemand Zeitstempel
 * vergleicht, bekommt jeder Request eine Kennung: `bootstrap/app.php` legt sie
 * ueber `$exceptions->context()` in **jede** Logzeile und ueber
 * `$exceptions->report()` zusammen mit dem Kurzbefund in die Sitzung.
 *
 * Die Klasse ist pro Request ein Singleton (AppServiceProvider) — die Kennung
 * entsteht beim ersten Zugriff und bleibt fuer den ganzen Request dieselbe.
 *
 * **Der Kurzbefund ist bewusst arm.** Er wandert in eine Fallakte, die der
 * Melder vor dem Absenden liest, und in eine zentrale Tabelle:
 *
 * - Die Meldung wird gekuerzt.
 * - **Bei einer `QueryException` faellt sie ganz weg.** Deren `getMessage()`
 *   traegt das vollstaendige SQL **mit eingesetzten Werten** — bei uns also
 *   Schuelernamen. Uebrig bleiben SQLSTATE, Datei und Zeile; das genuegt zum
 *   Wiederfinden im Log, wo der Fehler ohnehin vollstaendig steht.
 * - Vom Aufrufweg bleiben nur Rahmen aus `app/`. Framework-Rahmen fuellen den
 *   Kasten und sagen nichts.
 */
class FehlerKennung
{
    /** Wo der Kurzbefund zwischen den beiden Requests liegt. */
    public const SESSION_SCHLUESSEL = 'support.letzter_fehler';

    private const MELDUNG_MAX = 500;

    private const RAHMEN_MAX = 5;

    private ?string $kennung = null;

    /**
     * Die Kennung dieses Requests — beim ersten Zugriff erzeugt.
     */
    public function kennung(): string
    {
        return $this->kennung ??= (string) Str::ulid();
    }

    public static function aktuelle(): string
    {
        return app(self::class)->kennung();
    }

    /**
     * Der Kurzbefund zu einem Fehler, wie er in die Sitzung und spaeter in die
     * Fallakte wandert.
     *
     * @return array{id: string, klasse: string, meldung: string, datei: string, zeile: int, rahmen: list<string>, zeitpunkt: string}
     */
    public static function erfassen(Throwable $e): array
    {
        return [
            'id' => self::aktuelle(),
            'klasse' => $e::class,
            'meldung' => self::meldung($e),
            'datei' => self::kurzerPfad($e->getFile()),
            'zeile' => $e->getLine(),
            'rahmen' => self::rahmen($e),
            'zeitpunkt' => now()->toIso8601String(),
        ];
    }

    /**
     * **Der Grund, warum diese Methode existiert:** `QueryException::getMessage()`
     * enthaelt das SQL samt eingesetzter Bindings. In dieser Anwendung heisst
     * das: Schuelernamen. Sie darf nirgends in eine Fallakte.
     */
    private static function meldung(Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return 'Datenbankfehler (SQLSTATE ' . $e->getCode() . '). '
                . 'SQL und Werte wurden entfernt — der vollstaendige Fehler steht im Server-Log.';
        }

        return Str::limit(trim($e->getMessage()), self::MELDUNG_MAX);
    }

    /**
     * @return list<string>
     */
    private static function rahmen(Throwable $e): array
    {
        $app = base_path('app');
        $rahmen = [];

        foreach ($e->getTrace() as $eintrag) {
            $datei = $eintrag['file'] ?? null;

            if (! is_string($datei) || ! str_starts_with($datei, $app)) {
                continue;
            }

            $rahmen[] = sprintf(
                '%s%s%s (%s:%s)',
                (string) ($eintrag['class'] ?? ''),
                (string) ($eintrag['type'] ?? ''),
                (string) ($eintrag['function'] ?? ''),
                self::kurzerPfad($datei),
                (string) ($eintrag['line'] ?? '?')
            );

            if (count($rahmen) >= self::RAHMEN_MAX) {
                break;
            }
        }

        return $rahmen;
    }

    /**
     * Ohne den Basispfad — der ist auf jedem Stack ein anderer und sagt nichts.
     */
    private static function kurzerPfad(string $pfad): string
    {
        $basis = base_path() . DIRECTORY_SEPARATOR;

        return str_starts_with($pfad, $basis) ? substr($pfad, strlen($basis)) : $pfad;
    }
}
