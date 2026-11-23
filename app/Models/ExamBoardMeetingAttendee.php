<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Anwesender einer Ausschusssitzung (Modul 4.1).
 *
 * Gäste ohne Lehrer-Datensatz — etwa der Ministerialbeauftragte — werden über
 * name_override erfasst, analog zu ZusatzpruefPruefer.
 *
 * @property int $id
 * @property int $exam_board_meeting_id
 * @property int|null $teacher_id
 * @property string|null $name_override
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \App\Models\ExamBoardMeeting $meeting
 * @property-read \App\Models\Teacher|null $teacher
 * @mixin \Eloquent
 */
class ExamBoardMeetingAttendee extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;

    protected $table = 'exam_board_meeting_attendees';

    protected $fillable = [
        'exam_board_meeting_id',
        'teacher_id',
        'name_override',
        'sort_order',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(ExamBoardMeeting::class, 'exam_board_meeting_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
