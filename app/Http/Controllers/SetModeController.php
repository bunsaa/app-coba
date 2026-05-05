<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SetModeController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:mutu,perilaku',
        ]);

        session(['mode_akses' => $request->mode]);

        return response()->json(['success' => true, 'mode' => $request->mode]);
    }
}
