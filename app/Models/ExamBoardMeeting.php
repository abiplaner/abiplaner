<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Sitzung des Abiturprüfungsausschusses (Modul 4.1).
 *
 * @property int $id
 * @property int $exam_period_id
 * @property int $number
 * @property \Carbon\Carbon $date
 * @property string|null $start_time  'HH:MM'
 * @property string|null $end_time    'HH:MM'
 * @property string|null $location
 * @property int|null $chair_teacher_id
 * @property int|null $secretary_teacher_id
 * @property list<string>|null $agenda
 * @property list<string>|null $attachments
 * @property string|null $notes
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\Teacher|null $chair
 * @property-read \App\Models\Teacher|null $secretary
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExamBoardMeetingAttendee> $attendees
 * @mixin \Eloquent
 */
class ExamBoardMeeting extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'exam_board_meetings';

    protected $fillable = [
        'exam_period_id',
        'number',
        'date',
        'start_time',
        'end_time',
        'location',
        'chair_teacher_id',
        'secretary_teacher_id',
        'agenda',
        'attachments',
        'notes',
    ];

    protected $casts = [
        'date'        => 'date',
        'agenda'      => 'array',
        'attachments' => 'array',
    ];

    public function chair(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'chair_teacher_id');
    }

    public function secretary(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'secretary_teacher_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(ExamBoardMeetingAttendee::class)->orderBy('sort_order');
    }

    /**
     * Überschrift der Niederschrift, z. B. "6. Sitzung des Abiturprüfungsausschusses".
     */
    public function heading(): string
    {
        return $this->number . '. Sitzung des Abiturprüfungsausschusses';
    }
}
