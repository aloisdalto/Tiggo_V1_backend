<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TechnicianProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB; // Importante para transacciones

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::min(8)],
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'role' => 'required|string|in:cliente,tecnico',
        ];

        if ($request->role === 'tecnico') {
             $rules['description'] = 'required|string|max:1000';
             $rules['service_id'] = 'required|exists:services,id';
        } else {
             $rules['description'] = 'nullable|string|max:1000';
             $rules['service_id'] = 'nullable|exists:services,id';
        }

        $request->validate($rules);

        // Usamos una transacción para asegurar que todo se crea o nada
        $user = DB::transaction(function () use ($request) {
            
            // 1. Crear el usuario
            $newUser = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'password' => Hash::make($request->password),
            ]);

            // 2. Asignar el ROL (usando Spatie o tu lógica)
            // Si usas Spatie: $newUser->assignRole($request->role);
            // Si no usas Spatie y es una columna simple, asegúrate de haberla llenado arriba.
            // Asumiremos Spatie por el contexto previo:
            $newUser->assignRole($request->role);

            // 3. Crear Perfil de Técnico si aplica
            if ($request->role === 'tecnico') {
                $profile = TechnicianProfile::create([
                    'user_id' => $newUser->id,
                    'description' => $request->description,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'is_available' => true, // Por defecto disponible
                ]);

                // Asociar servicio
                if ($request->service_id) {
                    $profile->services()->attach($request->service_id);
                }
            }
            
            return $newUser;
        });

        // Generar Token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Cargar relaciones para devolver al frontend
        // Importante: 'roles' y 'technicianProfile' para que el frontend detecte el rol
        $user->load('roles', 'technicianProfile');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        // Cargar relaciones para la respuesta
        $user->load('roles', 'technicianProfile');

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}