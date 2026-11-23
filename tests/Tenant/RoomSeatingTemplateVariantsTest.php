<?php

namespace Tests\Tenant;

use App\Models\Course;
use App\Models\Room;
use App\Models\RoomSeatingTemplate;
use App\Models\SeatingTemplate;
use App\Models\Teacher;
use App\Models\WrittenExamAssignment;
use Illuminate\Support\Facades\Artisan;

/**
 * Modul 1.5 – Ein Raum kann mehrere Sitzplan-Varianten haben (Standard,
 * Hörverstehen), statt dass für jede Variante ein eigener Raum-Datensatz
 * angelegt werden muss. Tests laufen gegen die echte Tenant-DB der
 * Testschule 9999 (siehe TenantTestCase).
 */
class RoomSeatingTemplateVariantsTest extends TenantTestCase
{
    private function makeTemplate(string $name): SeatingTemplate
    {
        return SeatingTemplate::create(['name' => $name, 'rows' => 1, 'columns' => 1]);
    }

    public function test_room_ohne_hv_variante_faellt_bei_seatingTemplateFor_auf_standard_zurueck(): void
    {
        $school = $this->createTestSchool();

        $standard = $this->makeTemplate('Standard');
        $room = Room::create(['room_number' => 'T1', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create([
            'room_id' => $room->id,
            'variant' => RoomSeatingTemplate::VARIANT_STANDARD,
            'seating_template_id' => $standard->id,
        ]);

        $room = $room->fresh();

        $this->assertSame($standard->id, $room->seatingTemplateFor('standard')->id);
        $this->assertSame($standard->id, $room->seatingTemplateFor('hoerverstaendnis')->id, 'Ohne eigene HV-Vorlage muss auf Standard zurückgefallen werden.');

    }

    public function test_room_mit_eigener_hv_variante_liefert_die_richtige_vorlage_je_kontext(): void
    {
        $school = $this->createTestSchool();

        $standard = $this->makeTemplate('Standard');
        $hv = $this->makeTemplate('Hörverstehen-Layout');
        $room = Room::create(['room_number' => 'T2', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create(['room_id' => $room->id, 'variant' => 'standard', 'seating_template_id' => $standard->id]);
        RoomSeatingTemplate::create(['room_id' => $room->id, 'variant' => 'hoerverstaendnis', 'seating_template_id' => $hv->id]);

        $room = $room->fresh();

        $this->assertSame($standard->id, $room->seatingTemplateFor('standard')->id);
        $this->assertSame($hv->id, $room->seatingTemplateFor('hoerverstaendnis')->id);

    }

    public function test_written_exam_assignment_get_seating_template_beruecksichtigt_hoerverstaendnis(): void
    {
        $school = $this->createTestSchool();

        $standard = $this->makeTemplate('Standard');
        $hv = $this->makeTemplate('Hörverstehen-Layout');
        $room = Room::create(['room_number' => 'T3', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create(['room_id' => $room->id, 'variant' => 'standard', 'seating_template_id' => $standard->id]);
        RoomSeatingTemplate::create(['room_id' => $room->id, 'variant' => 'hoerverstaendnis', 'seating_template_id' => $hv->id]);

        $teacher = Teacher::create(['shortcode' => 'XYZ', 'firstname' => 'Test', 'lastname' => 'Lehrer']);
        $course = Course::create([
            'exam_period_id' => 1,
            'subject_id' => 1,
            'course_code' => 'te1',
            'teacher_id' => $teacher->id,
            'level' => 'grundlegend',
        ]);

        $standardAssignment = WrittenExamAssignment::create([
            'written_exam_year_date_id' => 1,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'is_hoerverstaendnis' => false,
        ]);
        $hvAssignment = WrittenExamAssignment::create([
            'written_exam_year_date_id' => 1,
            'course_id' => $course->id,
            'room_id' => $room->id,
            'is_hoerverstaendnis' => true,
        ]);

        $this->assertSame($standard->id, $standardAssignment->getSeatingTemplate()->id);
        $this->assertSame($hv->id, $hvAssignment->getSeatingTemplate()->id);

    }

    public function test_merge_command_dry_run_aendert_nichts(): void
    {
        $school = $this->createTestSchool();

        $standard = $this->makeTemplate('Standard');
        $hvTemplate = $this->makeTemplate('HV-Layout');
        $base = Room::create(['room_number' => 'T4', 'room_type' => 'exam', 'is_active' => true]);
        $hvRoom = Room::create(['room_number' => 'T4 Hörverst.', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create(['room_id' => $base->id, 'variant' => 'standard', 'seating_template_id' => $standard->id]);
        RoomSeatingTemplate::create(['room_id' => $hvRoom->id, 'variant' => 'standard', 'seating_template_id' => $hvTemplate->id]);

        Artisan::call('rooms:merge-hoerverstaendnis');

        $this->assertNotNull(Room::find($hvRoom->id), 'Dry-Run darf den Duplikat-Raum nicht löschen.');
        $this->assertNull(
            RoomSeatingTemplate::where('room_id', $base->id)->where('variant', 'hoerverstaendnis')->first(),
            'Dry-Run darf keine HV-Variante anlegen.'
        );

    }

    public function test_merge_command_apply_fuehrt_raeume_zusammen_und_haengt_zuordnungen_um(): void
    {
        $school = $this->createTestSchool();

        $standard = $this->makeTemplate('Standard');
        $hvTemplate = $this->makeTemplate('HV-Layout');
        $base = Room::create(['room_number' => 'T5', 'room_type' => 'exam', 'is_active' => true]);
        $hvRoom = Room::create(['room_number' => 'T5 Hörverst.', 'room_type' => 'exam', 'is_active' => true]);
        RoomSeatingTemplate::create(['room_id' => $base->id, 'variant' => 'standard', 'seating_template_id' => $standard->id]);
        RoomSeatingTemplate::create(['room_id' => $hvRoom->id, 'variant' => 'standard', 'seating_template_id' => $hvTemplate->id]);

        $teacher = Teacher::create(['shortcode' => 'ABX', 'firstname' => 'Test', 'lastname' => 'Lehrer']);
        $course = Course::create([
            'exam_period_id' => 1, 'subject_id' => 1, 'course_code' => 'te2',
            'teacher_id' => $teacher->id, 'level' => 'grundlegend',
        ]);
        $hvAssignment = WrittenExamAssignment::create([
            'written_exam_year_date_id' => 1,
            'course_id' => $course->id,
            'room_id' => $hvRoom->id,
            'is_hoerverstaendnis' => true,
        ]);

        Artisan::call('rooms:merge-hoerverstaendnis', ['--apply' => true]);

        $this->assertNull(Room::find($hvRoom->id), 'Duplikat-Raum sollte nach dem Merge gelöscht sein.');

        $mergedVariant = RoomSeatingTemplate::where('room_id', $base->id)
            ->where('variant', 'hoerverstaendnis')->first();
        $this->assertNotNull($mergedVariant);
        $this->assertSame($hvTemplate->id, $mergedVariant->seating_template_id);

        $this->assertSame($base->id, $hvAssignment->fresh()->room_id, 'Zuordnung sollte auf den Basis-Raum umgehängt sein.');

    }
}
