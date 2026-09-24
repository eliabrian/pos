<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'tenant_id' => 'required|exists:tenants,slug',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan salah.'],
            ]);
        }

        if (! $user->tenants()->where('slug', $request->tenant_id)->exists()) {
             throw ValidationException::withMessages([
                'tenant_id' => ['Anda tidak memiliki akses ke toko ini.'],
            ]);
        }

        $token = $user->createToken('pos-app', ['tenant:' . Tenant::where('slug', $request->tenant_id)->first()->id]);

        return response()->json([
            'token' => $token->plainTextToken,
            'tenant' => [
                'name' => Tenant::where('slug', $request->tenant_id)->first()->name,
            ],
            'user' => [
                'name' => $user->name,
                'role' => $user->tenants()->where('slug', $request->tenant_id)->first()->pivot->role,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }
}
