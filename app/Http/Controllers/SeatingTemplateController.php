<?php

namespace App\Http\Controllers;

use App\Models\SeatingTemplate;
use App\Models\SeatingTemplateSeat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SeatingTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = SeatingTemplate::withCount(['roomVariants', 'seats'])
            ->orderBy('name')
            ->get();
            
        return view('seating-templates.index', compact('templates'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('seating-templates.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'rows' => 'required|integer|min:1|max:50',
            'columns' => 'required|integer|min:1|max:50',
        ]);
        
        try {
            DB::beginTransaction();
            
            $template = SeatingTemplate::create([
                // 'school_id' => Auth::user()->school_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'rows' => $validated['rows'],
                'columns' => $validated['columns'],
            ]);
            
            // Alle Sitze als "available" initialisieren
            for ($row = 1; $row <= $template->rows; $row++) {
                for ($col = 1; $col <= $template->columns; $col++) {
                    SeatingTemplateSeat::create([
                        'template_id' => $template->id,
                        'row_number' => $row,
                        'column_number' => $col,
                        'seat_type' => 'available',
                    ]);
                }
            }
            
            DB::commit();
            
            return redirect()
                ->route('seating-templates.edit', $template)
                ->with('success', 'Vorlage erfolgreich erstellt. Passen Sie nun das Layout an.');
                
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Fehler beim Erstellen der Vorlage: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SeatingTemplate $seatingTemplate)
    {
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // if ($seatingTemplate->school_id !== Auth::user()->school_id) { abort(403); }
        
        $seatingTemplate->load('seats');
        
        // Seats als separate Variable für die View vorbereiten
        $seats = $seatingTemplate->seats;
        
        return view('seating-templates.edit', compact('seatingTemplate', 'seats'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SeatingTemplate $seatingTemplate)
    {
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // if ($seatingTemplate->school_id !== Auth::user()->school_id) { abort(403); }
        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'orientation' => 'nullable|in:top,bottom,left,right',
        ]);
        
        $seatingTemplate->update($validated);
        
        return back()->with('success', 'Vorlage erfolgreich aktualisiert.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SeatingTemplate $seatingTemplate)
    {
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // if ($seatingTemplate->school_id !== Auth::user()->school_id) { abort(403); }
        
        // Prüfen, ob die Vorlage noch von Räumen verwendet wird (Modul 1.5:
        // eine Sitzplan-Vorlage kann von mehreren Raum-Varianten referenziert
        // werden, z.B. gleichzeitig als Standard- und Hörverstehen-Vorlage).
        $roomCount = $seatingTemplate->roomVariants()->distinct('room_id')->count('room_id');
        if ($roomCount > 0) {
            return back()->with('error', 'Diese Vorlage wird noch von ' . $roomCount . ' Raum/Räumen verwendet und kann nicht gelöscht werden.');
        }
        
        $seatingTemplate->delete();
        
        return redirect()
            ->route('seating-templates.index')
            ->with('success', 'Vorlage erfolgreich gelöscht.');
    }
    
    /**
     * AJAX: Update a single seat in the template
     */
    public function updateSeat(Request $request, SeatingTemplate $seatingTemplate)
    {
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // if ($seatingTemplate->school_id !== Auth::user()->school_id) { abort(403); }
        
        $validated = $request->validate([
            'row' => 'required|integer|min:1',
            'column' => 'required|integer|min:1',
            'seat_type' => 'required|in:available,blocked,aisle,reserve,teacher_desk',
            'notes' => 'nullable|string',
        ]);
        
        $seat = SeatingTemplateSeat::where('template_id', $seatingTemplate->id)
            ->where('row_number', $validated['row'])
            ->where('column_number', $validated['column'])
            ->first();
            
        if (!$seat) {
            return response()->json(['error' => 'Sitz nicht gefunden'], 404);
        }
        
        $seat->update([
            'seat_type' => $validated['seat_type'],
            'notes' => $validated['notes'] ?? null,
        ]);
        
        return response()->json([
            'success' => true,
            'seat' => $seat,
        ]);
    }
    
    /**
     * AJAX: Update door positions for the template
     */
    public function updateDoors(Request $request, SeatingTemplate $seatingTemplate): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'door_positions'          => 'required|array',
            'door_positions.*.side'   => 'required|in:top,bottom,left,right',
            'door_positions.*.index'  => 'required|integer|min:1',
        ]);

        $seatingTemplate->update(['door_positions' => $validated['door_positions']]);

        return response()->json(['success' => true]);
    }

    /**
     * AJAX: Resize the template grid
     */
    public function resize(Request $request, SeatingTemplate $seatingTemplate)
    {
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // Sicherstellen, dass die Vorlage zur Schule des Users gehört
        // if ($seatingTemplate->school_id !== Auth::user()->school_id) { abort(403); }
        
        $validated = $request->validate([
            'rows' => 'required|integer|min:1|max:50',
            'columns' => 'required|integer|min:1|max:50',
        ]);
        
        try {
            DB::beginTransaction();
            
            $newRows = $validated['rows'];
            $newColumns = $validated['columns'];
            
            // Vorlage aktualisieren
            $seatingTemplate->update([
                'rows' => $newRows,
                'columns' => $newColumns,
            ]);
            
            // Sitze entfernen, die außerhalb des neuen Rasters liegen
            SeatingTemplateSeat::where('template_id', $seatingTemplate->id)
                ->where(function($query) use ($newRows, $newColumns) {
                    $query->where('row_number', '>', $newRows)
                        ->orWhere('column_number', '>', $newColumns);
                })
                ->delete();
            
            // Neue Sitze hinzufügen
            for ($row = 1; $row <= $newRows; $row++) {
                for ($col = 1; $col <= $newColumns; $col++) {
                    $exists = SeatingTemplateSeat::where('template_id', $seatingTemplate->id)
                        ->where('row_number', $row)
                        ->where('column_number', $col)
                        ->exists();
                        
                    if (!$exists) {
                        SeatingTemplateSeat::create([
                            'template_id' => $seatingTemplate->id,
                            'row_number' => $row,
                            'column_number' => $col,
                            'seat_type' => 'available',
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Größe erfolgreich angepasst',
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
