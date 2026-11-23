<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Display the admin dashboard for super_admin users.
     * This runs WITHOUT tenancy context.
     */
    public function index()
    {
        // Primäre Absicherung: CheckRole-Middleware (routes/central.php).
        // Dieser Check bleibt als Defense-in-Depth bestehen.
        if (!auth()->user()->isSuperAdmin()) {
            abort(403, 'Nur Super-Administratoren haben Zugriff auf dieses Dashboard.');
        }

        $totalSchools = School::count();
        $activeSchools = School::where('is_active', true)->count();
        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();
        $totalSubjects = \App\Models\Subject::count();
        $totalExamPeriods = \App\Models\ExamPeriod::count();

        $recentSchools = School::latest()->take(5)->get();
        $recentUsers = User::with('school')->latest()->take(10)->get();

        return view('dashboard-admin', compact(
            'totalSchools',
            'activeSchools',
            'totalUsers',
            'activeUsers',
            'totalSubjects',
            'totalExamPeriods',
            'recentSchools',
            'recentUsers'
        ));
    }
}
