<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $time_slot_id
 * @property int|null $supervision_time_slot_id
 * @property int $room_id
 * @property int $teacher_id
 * @property string $supervision_type preparation|waiting|lockdown|corridor
 * @property int|null $written_exam_assignment_id
 * @property string|null $notes
 * @property string|null $location_text
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\SupervisionTimeSlot|null $supervisionTimeSlot
 * @property-read \App\Models\WrittenExamAssignment|null $writtenExamAssignment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supervision newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supervision newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Supervision query()
 * @property-read \App\Models\Room|null $room
 * @property-read \App\Models\Teacher|null $teacher
 * @property-read \App\Models\TimeSlot|null $timeSlot
 * @mixin \Eloquent
 */
class Supervision extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $fillable = [
        'time_slot_id',
        'supervision_time_slot_id',
        'room_id',
        'teacher_id',
        'supervision_type',
        'written_exam_assignment_id',
        'written_exam_year_date_id',
        'written_exam_supervision_time_slot_id',
        'central_supervision_type_id',
        'notes',
        'location_text',
    ];

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }
    
    public function supervisionTimeSlot(): BelongsTo
    {
        return $this->belongsTo(SupervisionTimeSlot::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
    
    public function writtenExamAssignment(): BelongsTo
    {
        return $this->belongsTo(WrittenExamAssignment::class, 'written_exam_assignment_id');
    }

    public function writtenExamYearDate(): BelongsTo
    {
        return $this->belongsTo(WrittenExamYearDate::class, 'written_exam_year_date_id');
    }

    public function writtenExamSupervisionTimeSlot(): BelongsTo
    {
        return $this->belongsTo(WrittenExamSupervisionTimeSlot::class, 'written_exam_supervision_time_slot_id');
    }

    public function centralSupervisionType(): BelongsTo
    {
        return $this->belongsTo(WrittenExamCentralSupervisionType::class, 'central_supervision_type_id');
    }
}
