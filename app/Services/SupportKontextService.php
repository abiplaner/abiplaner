<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\School;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Der Kontext einer Support-Meldung (Modul 6.4, Stufe 1).
 *
 * **Wozu:** Der Melder soll keine technischen Angaben zusammensuchen muessen.
 * Vier Bloecke, die der Server ohnehin kennt — und die in der Maske
 * **vollstaendig im Klartext** angezeigt werden, bevor abgesendet wird. Das
 * ist keine Hoeflichkeit: Wer nicht sieht, was er mitschickt, schickt entweder
 * nichts oder zu viel. Und zwei der vier Bloecke *koennen* Namen enthalten.
 *
 * | Block          | Inhalt                                  | Personendaten |
 * |----------------|-----------------------------------------|---------------|
 * | `technik`      | Schule, Route, Zeit, Browser, Versionen | nein          |
 * | `einrichtung`  | SetupChecklistService, eingedampft      | nein          |
 * | `aktivitaeten` | die letzten zehn Schritte des Nutzers   | moeglich      |
 * | `fehler`       | Kurzbefund aus der Sitzung              | moeglich      |
 *
 * Der Kontext ist ein **Schnappschuss** und wird als JSON in die Fallakte
 * geschrieben. Nicht verknuepft: Der Einrichtungsstand einer Schule aendert
 * sich, ein Fall von vorletzter Woche muss den Stand von damals zeigen.
 *
 * **Kein Block darf die Meldung verhindern.** Jeder Erheber faengt seine
 * Fehler ab und liefert im Zweifel null — eine Meldung ueber einen kaputten
 * Mandanten wuerde sonst am kaputten Mandanten scheitern, und das ist genau
 * der Fall, fuer den es diese Maske gibt.
 */
class SupportKontextService
{
    private const AKTIVITAETEN = 10;

    public function __construct(
        private readonly SetupChecklistService $checkliste,
    ) {
    }

    /**
     * @return array{technik: array<string, mixed>, einrichtung: array<string, mixed>|null, aktivitaeten: list<array<string, mixed>>, fehler: array<string, mixed>|null}
     */
    public function erheben(Request $request, ?User $nutzer): array
    {
        return [
            'technik' => $this->technik($request, $nutzer),
            'einrichtung' => $this->einrichtung(),
            'aktivitaeten' => $this->aktivitaeten($nutzer),
            'fehler' => $this->fehler($request),
        ];
    }

    /**
     * Der Routenname, auf den sich die Meldung bezieht.
     *
     * Er kommt aus der Anfrage und nicht aus dem Verweis — dasselbe Muster wie
     * beim Hilfe-Panel: Das Panel wird von jeder Seite aus geoeffnet und weiss
     * nur, wo es steht.
     */
    public function herkunftsroute(Request $request): ?string
    {
        $name = trim((string) $request->input('herkunft_route', ''));

        return $name === '' ? null : Str::limit($name, 190, '');
    }

    public function herkunftsUrl(Request $request): ?string
    {
        $url = trim((string) $request->input('herkunft_url', ''));

        return $url === '' ? null : Str::limit($url, 490, '');
    }

    /**
     * @return array<string, mixed>
     */
    private function technik(Request $request, ?User $nutzer): array
    {
        $schule = $this->schule();

        return [
            'zeitpunkt' => now()->toIso8601String(),
            'schule' => $schule?->school_number,
            'schule_name' => $schule?->name,
            'route' => $this->herkunftsroute($request),
            'url' => $this->herkunftsUrl($request),
            'nutzer' => $nutzer?->email,
            'rolle' => $nutzer?->role,
            'browser' => Str::limit((string) $request->userAgent(), 300),
            'app_version' => $this->appVersion(),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'tenant_migrationen' => $this->tenantMigrationen(),
        ];
    }

    /**
     * Der Einrichtungsstand aus Modul 6.2 — auf Name, Status und Zahl
     * eingedampft. Beantwortet die haeufigste Rueckfrage („ist bei Ihnen der
     * Stundenplan ueberhaupt schon importiert?"), bevor sie gestellt wird.
     *
     * @return array<string, mixed>|null
     */
    private function einrichtung(): ?array
    {
        if (! app(AktuelleSchule::class)->aktiv()) {
            return null;
        }

        try {
            $bausteine = array_map(
                static fn (array $item): array => [
                    'key' => $item['key'],
                    'titel' => $item['title'],
                    'status' => $item['status'],
                    'detail' => $item['detail'],
                ],
                $this->checkliste->items()
            );

            return [
                'fortschritt' => $this->checkliste->progress(),
                'bausteine' => $bausteine,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Die letzten Schritte des Melders — was unmittelbar vor dem Fehler
     * passiert ist.
     *
     * **Kann Namen enthalten:** `new_values.description` ist Klartext, den die
     * aufrufende Stelle geschrieben hat. Deshalb steht dieser Block in der
     * Maske sichtbar da, bevor abgesendet wird.
     *
     * @return list<array<string, mixed>>
     */
    private function aktivitaeten(?User $nutzer): array
    {
        if (! $nutzer instanceof User || ! app(AktuelleSchule::class)->aktiv()) {
            return [];
        }

        try {
            $zeilen = ActivityLog::query()
                ->where('user_id', $nutzer->id)
                ->orderByDesc('created_at')
                ->limit(self::AKTIVITAETEN)
                ->get();
        } catch (Throwable) {
            return [];
        }

        return $zeilen->map(static function (ActivityLog $zeile): array {
            $werte = json_decode((string) $zeile->new_values, true);

            return [
                // Carbon::parse und nicht ->toIso8601String(): `ActivityLog`
                // setzt `$timestamps = false` und deklariert created_at ueber
                // das alte `$dates`. Ob daraus ein Carbon oder ein String wird,
                // haengt an der Framework-Version — parse traegt beides.
                'zeitpunkt' => $zeile->created_at === null
                    ? null
                    : Carbon::parse($zeile->created_at)->toIso8601String(),
                'aktion' => $zeile->action,
                'objekt' => $zeile->model_type === null
                    ? null
                    : class_basename((string) $zeile->model_type) . '#' . ($zeile->model_id ?? '?'),
                'beschreibung' => is_array($werte) && isset($werte['description'])
                    ? Str::limit((string) $werte['description'], 200)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * Der Kurzbefund des letzten Fehlers, den `bootstrap/app.php` in die
     * Sitzung gelegt hat. Er wird beim Erheben **nicht** entfernt: Der Melder
     * sieht ihn in der Maske und kann die Meldung abbrechen; erst das
     * Absenden raeumt auf.
     *
     * @return array<string, mixed>|null
     */
    private function fehler(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $befund = $request->session()->get(FehlerKennung::SESSION_SCHLUESSEL);

        return is_array($befund) ? $befund : null;
    }

    private function schule(): ?School
    {
        return app(AktuelleSchule::class)->schule();
    }

    /**
     * Wie viele Tenant-Migrationen eingespielt sind.
     *
     * Der belastbare Vergleich mit den Dateien unter
     * `database/migrations/tenant/` — die haeufigste Ursache dafuer, dass eine
     * Maske „bei mir anders aussieht".
     */
    private function tenantMigrationen(): ?int
    {
        if (! app(AktuelleSchule::class)->aktiv()) {
            return null;
        }

        try {
            return DB::table('migrations')->count();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Der ausgelieferte Stand.
     *
     * Ohne Shell-Aufruf: `.git/HEAD` zeigt auf eine Ref-Datei, in der der
     * Commit steht. Im Container gibt es `git` nicht zwingend, die Dateien
     * aber schon. Fehlt beides, traegt `config('app.version')`.
     */
    private function appVersion(): ?string
    {
        $konfiguriert = config('app.version');

        if (is_string($konfiguriert) && $konfiguriert !== '') {
            return $konfiguriert;
        }

        try {
            $head = base_path('.git/HEAD');

            if (! is_readable($head)) {
                return null;
            }

            $inhalt = trim((string) file_get_contents($head));

            if (! str_starts_with($inhalt, 'ref: ')) {
                return substr($inhalt, 0, 8); // detached HEAD
            }

            $ref = base_path('.git/' . substr($inhalt, 5));

            return is_readable($ref)
                ? substr(trim((string) file_get_contents($ref)), 0, 8)
                : null;
        } catch (Throwable) {
            return null;
        }
    }
}
