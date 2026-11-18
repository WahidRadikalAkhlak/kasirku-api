<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'owner_name'    => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'password'      => 'required|min:6',
            'store_name'    => 'required|string|max:255',
            'address'       => 'nullable|string|max:255',
            'business_type' => 'required|in:retail,coffeeShop,restaurant',
            'logo'          => 'nullable|image|max:2048',
        ]);

        $user = User::create([
            'name'     => $request->owner_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $logoUrl = null;
        if ($request->hasFile('logo')) {
        $path = $request->file('logo')->store('logos', 'public');
        $logoUrl = url('/api/files/' . $path);
        }

        $tax = $request->business_type === 'retail' ? 0 : 10;
        $service = $request->business_type === 'restaurant' ? 5 : 0;

        Store::create([
            'user_id'        => $user->id,
            'name'           => $request->store_name,
            'address'        => $request->address,
            'business_type'  => $request->business_type,
            'tax_percent'    => $tax,
            'service_charge' => $service,
            'logo_url'       => $logoUrl,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Registrasi berhasil! Silakan login untuk masuk ke aplikasi.',
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $store = Store::where('user_id', $user->id)->first();
        $token = $user->createToken('pos_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token'  => $token,
            'user'   => $user,
            'store'  => $store,
        ]);
    }
}
