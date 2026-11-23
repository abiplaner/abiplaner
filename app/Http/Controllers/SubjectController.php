<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SubjectController extends Controller
{
    use AuthorizesRequests;
    
    public function index(Request $request)
    {
        $this->authorize('viewAny', Subject::class);
        
        $query = Subject::query();
        
        // Suchfunktion
        if ($search = $request->input('search')) {
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }
        
        // Sortierung
        $sortBy = $request->input('sort_by', 'code');
        $sortOrder = $request->input('sort_order', 'asc');
        
        if (in_array($sortBy, ['code', 'name'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('code', 'asc');
        }
        
        $subjects = $query->paginate(20)->withQueryString();
        
        return view('admin.subjects.index', compact('subjects'));
    }
    
    public function create()
    {
        $this->authorize('create', Subject::class);
        
        return view('admin.subjects.create');
    }
    
    public function store(Request $request)
    {
        $this->authorize('create', Subject::class);
        
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:subjects,code',
            'name' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'aufgabenfeld' => 'required|in:sprachlich_literarisch,gesellschaftswissenschaftlich,mint,sport,sonstiges',
            'sort_order' => 'nullable|integer|min:0',
            'is_foreign_language' => 'boolean',
            'is_science' => 'boolean',
            'can_be_late_starting' => 'boolean',
            'can_abitur' => 'boolean',
        ]);
        
        // Checkboxen als boolean behandeln
        $validated['is_foreign_language'] = $request->has('is_foreign_language');
        $validated['is_science'] = $request->has('is_science');
        $validated['can_be_late_starting'] = $request->has('can_be_late_starting');
        $validated['can_abitur'] = $request->has('can_abitur');
        
        $subject = Subject::create($validated);
        
        ActivityLogger::log(
            'subject_created',
            'Subject',
            $subject->id,
            "Fach {$subject->code} ({$subject->name}) erstellt"
        );
        
        return redirect()->route('admin.subjects.index')
            ->with('success', 'Fach erfolgreich erstellt.');
    }
    
    public function edit(Subject $subject)
    {
        $this->authorize('update', $subject);
        
        return view('admin.subjects.edit', compact('subject'));
    }
    
    public function update(Request $request, Subject $subject)
    {
        $this->authorize('update', $subject);
        
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:subjects,code,' . $subject->id,
            'name' => 'required|string|max:100',
            'color' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'aufgabenfeld' => 'required|in:sprachlich_literarisch,gesellschaftswissenschaftlich,mint,sport,sonstiges',
            'sort_order' => 'nullable|integer|min:0',
            'is_foreign_language' => 'boolean',
            'is_science' => 'boolean',
            'can_be_late_starting' => 'boolean',
            'can_abitur' => 'boolean',
        ]);
        
        // Checkboxen als boolean behandeln
        $validated['is_foreign_language'] = $request->has('is_foreign_language');
        $validated['is_science'] = $request->has('is_science');
        $validated['can_be_late_starting'] = $request->has('can_be_late_starting');
        $validated['can_abitur'] = $request->has('can_abitur');
        
        $subject->update($validated);
        
        ActivityLogger::log(
            'subject_updated',
            'Subject',
            $subject->id,
            "Fach {$subject->code} aktualisiert"
        );
        
        return redirect()->route('admin.subjects.index')
            ->with('success', 'Fach erfolgreich aktualisiert.');
    }
    
    public function destroy(Subject $subject)
    {
        $this->authorize('delete', $subject);
        
        try {
            $code = $subject->code;
            $subject->delete();
            
            ActivityLogger::log(
                'subject_deleted',
                'Subject',
                $subject->id,
                "Fach {$code} gelöscht"
            );
            
            return redirect()->route('admin.subjects.index')
                ->with('success', 'Fach erfolgreich gelöscht.');
                
        } catch (\Exception $e) {
            return redirect()->route('admin.subjects.index')
                ->with('error', 'Fach kann nicht gelöscht werden. Es wird möglicherweise noch in einer Schule verwendet.');
        }
    }
}
