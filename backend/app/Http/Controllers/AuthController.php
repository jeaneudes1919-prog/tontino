<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|min:2|max:80', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:20', 'password' => 'required|string|min:8|confirmed']);
        $colors = ['#D4AF37', '#34D399', '#60A5FA', '#F472B6', '#FBBF24', '#A78BFA'];
        $user = User::create($d + ['avatar_color' => $colors[array_rand($colors)]]);

        return response()->json(['token' => $user->createToken('web')->plainTextToken, 'user' => $user], 201);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $user = User::where('email', $d['email'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($d['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Email ou mot de passe incorrect.']]);
        }

        return response()->json(['token' => $user->createToken('web')->plainTextToken, 'user' => $user]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnecté.']);
    }

    public function me(Request $r)
    {
        return response()->json($r->user());
    }

    public function update(Request $r)
    {
        $d = $r->validate(['name' => 'sometimes|string|min:2|max:80', 'phone' => 'nullable|string|max:20']);
        $r->user()->update($d);

        return response()->json($r->user()->refresh());
    }
}
