<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'region' => ['required', 'in:CL,UY'],
        ]);

        $request->session()->put('region', $request->region);

        return back();
    }
}
