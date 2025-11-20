<?php

namespace App\Http\Controllers\Api;

// ... (imports sin cambios)
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TechnicianProfile; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException; 
use Illuminate\Validation\Rules; 

class AuthController extends Controller
{
    // Registro 
    public function register(Request $request){
        
        $rules = [
// ... (reglas de validación sin cambios)
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::min(8)],
            'phone' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'role' => 'required|string|in:cliente,tecnico',
        ];

        // Añadir reglas de validación condicionales para el técnico
        if ($request->role === 'tecnico') {
// ... (reglas de validación de técnico sin cambios)
             $rules['description'] = 'required|string|max:1000'; // La descripción es obligatoria para técnicos
             $rules['service_id'] = 'required|exists:services,id'; // El ID del servicio es OBLIGATORIO y debe existir
        } else {
// ... (reglas de validación de cliente sin cambios)
             $rules['description'] = 'nullable|string|max:1000';
             $rules['service_id'] = 'nullable|exists:services,id';
        }

        $request->validate($rules);

        // 1. Crear el usuario
        $user = User::create([
// ... (creación de usuario sin cambios)
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'password' => Hash::make($request->password),
        ]);

        // 2. Asignar el ROL
        $user->assignRole($request->role);

        // 3. Crear Perfil de Técnico y Asociar Servicio
        if ($request->role === 'tecnico') {
            
            // Crear el perfil del técnico
            $profile = TechnicianProfile::create([
                'user_id' => $user->id,
                'description' => $request->description,
                
                // --- ¡ESTA ES LA CORRECCIÓN! ---
                // Duplicamos la lat/lng en la tabla de perfil
                // ya que tu consulta Haversine la usa desde aquí.
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                // --- FIN DE LA CORRECCIÓN ---
            ]);

            // Asociar el servicio usando la relación many-to-many
            $profile->services()->attach($request->service_id);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

// ... (respuesta JSON sin cambios)
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('technicianProfile.services'), // Cargar relación completa
        ], 201);
    }

     // Login
    public function login(Request $request){
// ... (método login sin cambios)
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

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('technicianProfile.services'), // Cargar relación completa
        ]);
    }

    // Logout
    public function logout(Request $request){
// ... (método logout sin cambios)
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sesión cerrada correctamente']);
    }
}