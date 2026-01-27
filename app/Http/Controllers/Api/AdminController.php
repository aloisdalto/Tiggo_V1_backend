<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Service;
use App\Models\ServiceRequest;

class AdminController extends Controller
{
    /**
     * Lista todos los usuarios con su rol y perfil técnico si existe.
     */
    public function listUsers()
    {
        // Traemos todos los usuarios, cargando sus roles y perfil técnico
        $users = User::with('roles', 'technicianProfile')->get()->map(function ($user) {
            // Simplificamos la estructura para el frontend
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->hasRole('tecnico') ? 'Técnico' : ($user->hasRole('admin') ? 'Administrador' : 'Cliente'),
                'created_at' => $user->created_at,
                // Si es técnico, añadimos info extra
                'is_available' => $user->technicianProfile ? $user->technicianProfile->is_available : null,
            ];
        });

        return response()->json($users);
    }

    /**
     * Crea un nuevo servicio en el catálogo.
     */
    public function createService(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:services,name',
            'description' => 'nullable|string',
        ]);

        $service = Service::create($request->all());

        return response()->json(['message' => 'Servicio creado exitosamente', 'service' => $service], 201);
    }

    /**
     * Reporte general: Totales de usuarios, servicios y solicitudes.
     */
    public function reports()
    {
        $stats = [
            'total_users' => User::count(),
            'total_technicians' => User::role('tecnico')->count(),
            'total_clients' => User::role('cliente')->count(),
            'total_requests' => ServiceRequest::count(),
            'completed_requests' => ServiceRequest::where('status', 'completado')->count(),
            'services_catalog' => Service::count(),
        ];

        // También podemos devolver las últimas 10 solicitudes para un feed de actividad
        $recent_requests = ServiceRequest::with('service', 'client', 'technician')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_requests' => $recent_requests
        ]);
    }

    // Métodos placeholder para update/delete si los necesitas luego
    public function updateService(Request $request, Service $service) { /* ... */ }
    public function deleteService(Service $service) { /* ... */ }
}