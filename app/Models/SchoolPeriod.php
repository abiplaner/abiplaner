<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolPeriod extends Model
{
    use \App\Models\Concerns\SpeichertWieMariaDb;


    protected $fillable = [
        'period_number',
        'name',
        'is_break',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'is_break' => 'boolean',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
    ];
}
