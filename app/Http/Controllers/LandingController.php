<?php

namespace App\Http\Controllers;

class LandingController extends Controller
{
    /** Startseite: keine Werbeseite, sondern gleich zur Arbeit. */
    public function index()
    {
        return redirect()->route(auth()->check() ? 'dashboard' : 'login');
    }
}
