<?php

namespace App\Http\Controllers;

use App\Models\SchoolPeriod;
use Illuminate\Http\Request;

class SchoolPeriodController extends Controller
{
    public function index()
    {
        $periods = SchoolPeriod::orderBy('start_time')->get();
        return view('school-periods.index', compact('periods'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'periods' => 'required|array',
            'periods.*.id' => 'nullable',
            'periods.*.period_number' => 'nullable|integer',
            'periods.*.name' => 'nullable|string|max:255',
            'periods.*.is_break' => 'boolean',
            'periods.*.start_time' => 'required',
            'periods.*.end_time' => 'required',
            'periods.*._delete' => 'boolean',
        ]);

        foreach ($request->periods as $periodData) {
            $isDelete = $periodData['_delete'] ?? false;
            $id = !empty($periodData['id']) ? $periodData['id'] : null;
            
            if ($isDelete && $id) {
                SchoolPeriod::find($id)?->delete();
                continue;
            } elseif ($isDelete) {
                continue;
            }

            // Ensure we extract the H:i from time if seconds are sent
            $startTime = substr($periodData['start_time'], 0, 5);
            $endTime = substr($periodData['end_time'], 0, 5);

            $data = [
                'period_number' => $periodData['period_number'] ?? null,
                'name' => $periodData['name'] ?? null,
                'is_break' => !empty($periodData['is_break']),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];

            if ($id) {
                SchoolPeriod::where('id', $id)->update($data);
            } else {
                SchoolPeriod::create($data);
            }
        }

        return redirect()->route('school-periods.index')->with('success', 'Stundenraster erfolgreich gespeichert.');
    }
}
