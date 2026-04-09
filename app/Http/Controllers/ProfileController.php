<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        return Inertia::render('Profile/Index', [
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'username'  => $user->username,
                'mail'      => $user->mail ?? '',
                'telephone' => $user->telephone ?? '',
                'type_id'   => $user->type_id,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'      => ['required', 'min:2'],
            'mail'      => ['nullable', 'email'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update([
            'name'      => trim($request->name),
            'mail'      => trim($request->mail ?? ''),
            'telephone' => trim($request->telephone ?? ''),
        ]);

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function password(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
            'password'         => ['required', 'min:6', 'confirmed'],
        ]);

        $user = Auth::user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
