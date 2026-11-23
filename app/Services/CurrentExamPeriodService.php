<?php

namespace App\Services;

use App\Models\ExamPeriod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Single Source of Truth für die "aktuelle" Prüfungsperiode (Modul 1.1, Phase 1).
 *
 * Default: die neueste zentrale ExamPeriod (höchstes Jahr) — das bisherige
 * implizite Verhalten der ~40 verstreuten orderBy('year','desc')-Stellen.
 *
 * Override: eine vom Nutzer gewählte Periode, gespeichert in der Session
 * (pro Nutzer, nicht pro Schule — zwei Planer können verschiedene Jahre
 * ansehen, ohne sich gegenseitig umzuschalten).
 *
 * Der Service ist als "scoped" Singleton registriert (AppServiceProvider),
 * die Auflösung wird also pro Request gecacht.
 */
class CurrentExamPeriodService
{
    public const SESSION_KEY = 'current_exam_period_id';

    private ?ExamPeriod $current = null;
    private bool $currentResolved = false;

    private ?ExamPeriod $latest = null;
    private bool $latestResolved = false;

    /**
     * Aktuell gewählte Periode: Session-Override, sonst die neueste.
     */
    public function current(): ?ExamPeriod
    {
        if ($this->currentResolved) {
            return $this->current;
        }

        $this->currentResolved = true;

        $overrideId = $this->sessionOverrideId();
        if ($overrideId !== null) {
            $period = ExamPeriod::find($overrideId);
            if ($period !== null) {
                return $this->current = $period;
            }
            // Verwaiste Session-Referenz (Periode gelöscht) aufräumen
            $this->forgetOverride();
        }

        return $this->current = $this->latest();
    }

    /**
     * Wie current(), wirft aber bei fehlender Periode eine
     * ModelNotFoundException (404) — gleiches Verhalten wie die
     * bisherigen firstOrFail()-Aufrufe.
     */
    public function currentOrFail(): ExamPeriod
    {
        $period = $this->current();

        if ($period === null) {
            throw (new ModelNotFoundException())->setModel(ExamPeriod::class);
        }

        return $period;
    }

    /**
     * Neueste zentrale Periode (Default ohne Override).
     */
    public function latest(): ?ExamPeriod
    {
        if (!$this->latestResolved) {
            $this->latestResolved = true;
            $this->latest = ExamPeriod::orderBy('year', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        return $this->latest;
    }

    public function currentId(): ?int
    {
        return $this->current()?->id;
    }

    public function currentYear(): ?int
    {
        return $this->current()?->year;
    }

    /**
     * true, wenn eine Alt-Periode betrachtet wird (Archivansicht).
     * Alt-Perioden sind strikt schreibgeschützt (Konzept 1.1, Kap. 3.2).
     */
    public function isArchiveView(): bool
    {
        $current = $this->current();
        $latest  = $this->latest();

        return $current !== null && $latest !== null && $current->id !== $latest->id;
    }

    /**
     * Periode wechseln (Session-Override setzen).
     */
    public function switchTo(ExamPeriod|int $period): void
    {
        $id = $period instanceof ExamPeriod ? $period->id : $period;

        if ($this->hasSession()) {
            session()->put(self::SESSION_KEY, $id);
        }

        $this->flushResolved();
    }

    /**
     * Zurück zur neuesten Periode (Override entfernen).
     */
    public function reset(): void
    {
        $this->forgetOverride();
        $this->flushResolved();
    }

    /**
     * Alle Perioden für den UI-Umschalter, neueste zuerst.
     *
     * @return Collection<int, ExamPeriod>
     */
    public function all(): Collection
    {
        return ExamPeriod::orderBy('year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    private function sessionOverrideId(): ?int
    {
        if (!$this->hasSession()) {
            return null;
        }

        $value = session()->get(self::SESSION_KEY);

        return is_numeric($value) ? (int) $value : null;
    }

    private function forgetOverride(): void
    {
        if ($this->hasSession()) {
            session()->forget(self::SESSION_KEY);
        }
    }

    /**
     * Session ist z. B. in Konsole/Queue/Tests ohne HTTP-Kontext nicht
     * verfügbar — dann gilt immer die neueste Periode.
     */
    private function hasSession(): bool
    {
        return request()->hasSession();
    }

    private function flushResolved(): void
    {
        $this->current = null;
        $this->currentResolved = false;
        $this->latest = null;
        $this->latestResolved = false;
    }
}
