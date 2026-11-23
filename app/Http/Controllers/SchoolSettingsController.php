<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\SchoolSetting;
use App\Services\SchoolDaysService;
use App\Services\YearChangeWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolSettingsController extends Controller
{
    /** Einstellungsseite anzeigen. */
    public function index(SchoolDaysService $schoolDays, YearChangeWizardService $wizard): View
    {
        $school = app(\App\Services\AktuelleSchule::class)->schule();
        if ($school !== null) {
            $wizard->syncSchoolMasterData($school);
            $school->refresh();
        }

        $settings = SchoolSetting::allKeyed();
        $autoSchooldays = $schoolDays->countSchoolDaysUntil();
        $schoolYearStart = $schoolDays->schoolYearStart();

        return view('school-settings.index', compact('settings', 'autoSchooldays', 'schoolYearStart', 'school'));
    }

    /** Einstellungen speichern. */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'settings'   => ['nullable', 'array'],
            'settings.*' => ['nullable', 'string'],
            'school'     => ['nullable', 'array'],
            'school.address'     => ['nullable', 'string', 'max:255'],
            'school.postal_code' => ['nullable', 'string', 'max:10'],
            'school.city'        => ['nullable', 'string', 'max:100'],
            'school.email'       => ['nullable', 'email', 'max:255'],
            'school.phone'       => ['nullable', 'string', 'max:50'],
        ]);

        $school = app(\App\Services\AktuelleSchule::class)->schule();

        if ($request->has('school') && $school instanceof School) {
            $schoolData = $request->input('school', []);
            $allowed = ['address', 'postal_code', 'city', 'email', 'phone'];
            $updates = [];

            foreach ($allowed as $field) {
                if (array_key_exists($field, $schoolData)) {
                    $val = trim((string) ($schoolData[$field] ?? ''));
                    $updates[$field] = $val !== '' ? $val : null;
                    SchoolSetting::set('school.' . $field, $updates[$field]);
                }
            }

            if ($updates !== []) {
                $school->update($updates);
            }
        }

        foreach ($request->input('settings', []) as $key => $value) {
            // Dot-Notation zu echtem Key umwandeln (HTML-Name "settings[a.b]" → "a.b")
            SchoolSetting::set($key, ($value === '' || $value === null) ? null : $value);
        }

        return back()->with('success', 'Einstellungen wurden gespeichert.');
    }
}
