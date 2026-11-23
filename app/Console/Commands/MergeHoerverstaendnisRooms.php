<?php

namespace App\Console\Commands;

use App\Models\ExamAssignment;
use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Models\RoomSupervisionGrid;
use App\Models\Supervision;
use App\Models\WrittenExamAssignment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Modul 1.5 – Führt Hörverstehen-Raum-Duplikate (z.B. "MRE C001" +
 * "MRE C001 Hörverst.") wieder zu einem einzigen Raum mit zwei
 * Sitzplan-Varianten zusammen. Läuft standardmäßig nur als Vorschau
 * (Dry-Run); erst --apply schreibt tatsächlich in die Datenbank.
 *
 * Aufruf pro Schule (Tenant-Kontext nötig):
 *   php artisan tenants:run 'rooms:merge-hoerverstaendnis' --tenants=<school_number>
 *   php artisan tenants:run 'rooms:merge-hoerverstaendnis --apply' --tenants=<school_number>
 */
class MergeHoerverstaendnisRooms extends Command
{
    protected $signature = 'rooms:merge-hoerverstaendnis {--apply : Änderungen tatsächlich schreiben (sonst nur Vorschau)}';

    protected $description = 'Führt "X" + "X Hörverst."-Raum-Duplikate zu einem Raum mit zwei Sitzplan-Varianten zusammen';

    /** Erkennt Namens-Suffixe wie " Hörverst.", " Hörverstehen", " (HV)" */
    private const HV_SUFFIX_PATTERN = '/\s*(Hörverst(\.|ehen)?|\(HV\))\s*$/iu';

    public function handle(): int
    {
        $apply = $this->option('apply');
        $rooms = Room::orderBy('room_number')->get();

        $roomsByNumber = $rooms->keyBy(fn($r) => mb_strtolower(trim($r->room_number)));

        $pairs = [];
        foreach ($rooms as $room) {
            $baseNumber = trim(preg_replace(self::HV_SUFFIX_PATTERN, '', $room->room_number) ?? '');
            if ($baseNumber === '' || $baseNumber === trim($room->room_number)) {
                continue; // kein HV-Suffix erkannt
            }
            $baseRoom = $roomsByNumber->get(mb_strtolower($baseNumber));
            if ($baseRoom === null || $baseRoom->id === $room->id) {
                $this->warn("⚠ '{$room->room_number}' sieht wie eine Hörverstehen-Variante aus, aber es gibt keinen Basis-Raum '{$baseNumber}' — wird übersprungen.");
                continue;
            }
            $pairs[] = ['base' => $baseRoom, 'hv' => $room];
        }

        if (empty($pairs)) {
            $this->info('Keine Hörverstehen-Raum-Duplikate gefunden.');
            return self::SUCCESS;
        }

        $this->info(($apply ? 'Führe' : 'Vorschau:') . ' ' . count($pairs) . ' Raum-Duplikat(e) zusammen' . ($apply ? '' : ' (--apply zum tatsächlichen Ausführen)') . ':');
        $this->newLine();

        foreach ($pairs as $pair) {
            $this->processPair($pair['base'], $pair['hv'], $apply);
        }

        if (!$apply) {
            $this->newLine();
            $this->comment('Dies war nur eine Vorschau. Mit --apply werden die Änderungen tatsächlich geschrieben.');
        }

        return self::SUCCESS;
    }

    private function processPair(Room $base, Room $hv, bool $apply): void
    {
        $hvTemplateId = RoomSeatingTemplate::where('room_id', $hv->id)
            ->where('variant', RoomSeatingTemplate::VARIANT_STANDARD)
            ->value('seating_template_id') ?? $hv->seating_template_id;

        $this->line("• <fg=cyan>{$base->room_number}</> (#{$base->id}) ← <fg=yellow>{$hv->room_number}</> (#{$hv->id})");

        if ($hvTemplateId === null) {
            $this->warn("  Übersprungen: '{$hv->room_number}' hat selbst keine Sitzplan-Vorlage — nichts zu übernehmen.");
            return;
        }

        $counts = [
            'written_exam_assignments' => WrittenExamAssignment::where('room_id', $hv->id)->count(),
            'exam_assignments (Prüfungsraum)' => ExamAssignment::where('exam_room_id', $hv->id)->count(),
            'exam_assignments (Vorbereitungsraum)' => ExamAssignment::where('prep_room_id', $hv->id)->count(),
            'supervisions' => Supervision::where('room_id', $hv->id)->count(),
            'supervision_grid_assignments' => RoomSupervisionGrid::where('room_id', $hv->id)->count(),
        ];

        foreach ($counts as $label => $count) {
            if ($count > 0) {
                $this->line("    → {$count}× {$label} wird auf {$base->room_number} umgehängt");
            }
        }

        if (!$apply) {
            $this->line("    → Sitzplan-Vorlage #{$hvTemplateId} würde als 'hoerverstaendnis'-Variante von {$base->room_number} übernommen");
            $this->line("    → Raum '{$hv->room_number}' (#{$hv->id}) würde danach gelöscht");
            $this->newLine();
            return;
        }

        DB::transaction(function () use ($base, $hv, $hvTemplateId, $counts) {
            RoomSeatingTemplate::updateOrCreate(
                ['room_id' => $base->id, 'variant' => RoomSeatingTemplate::VARIANT_HOERVERSTAENDNIS],
                ['seating_template_id' => $hvTemplateId]
            );

            if ($counts['written_exam_assignments'] > 0) {
                WrittenExamAssignment::where('room_id', $hv->id)->update(['room_id' => $base->id]);
            }
            if ($counts['exam_assignments (Prüfungsraum)'] > 0) {
                ExamAssignment::where('exam_room_id', $hv->id)->update(['exam_room_id' => $base->id]);
            }
            if ($counts['exam_assignments (Vorbereitungsraum)'] > 0) {
                ExamAssignment::where('prep_room_id', $hv->id)->update(['prep_room_id' => $base->id]);
            }
            if ($counts['supervisions'] > 0) {
                Supervision::where('room_id', $hv->id)->update(['room_id' => $base->id]);
            }
            if ($counts['supervision_grid_assignments'] > 0) {
                RoomSupervisionGrid::where('room_id', $hv->id)->update(['room_id' => $base->id]);
            }

            $hv->delete();
        });

        $this->info("    ✓ Zusammengeführt, '{$hv->room_number}' gelöscht.");
        $this->newLine();
    }
}
