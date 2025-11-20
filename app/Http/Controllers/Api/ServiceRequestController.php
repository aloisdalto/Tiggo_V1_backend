<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use App\Events\ServiceRequestCreated;
use App\Models\TechnicianProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable; // Importar para el catch

class ServiceRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
// ... (validación sin cambios)
            'service_id' => 'required|exists:services,id',
            'client_latitude' => 'required|numeric',
            'client_longitude' => 'required|numeric',
            'comments' => 'nullable|string',
        ]);
    
        // --- ¡MEJORA! Usar una transacción ---
        // Si la búsqueda de técnico falla, se revierte la creación de la solicitud.
        try {
            $serviceRequest = DB::transaction(function () use ($request) {
                
                $lat = $request->client_latitude;
                $lng = $request->client_longitude;
            
                // 1. BUSCAR AL TÉCNICO PRIMERO
                $technicianProfile = DB::table('technician_profiles as tp')
                    ->join('service_technician as st', 'tp.id', '=', 'st.technician_profile_id')
                    ->select('tp.user_id', 'tp.latitude', 'tp.longitude',
                        DB::raw("(6371 * acos(cos(radians($lat)) * cos(radians(tp.latitude)) * cos(radians(tp.longitude) - radians($lng)) + sin(radians($lat)) * sin(radians(tp.latitude)))) AS distance")
                    )
                    ->where('st.service_id', $request->service_id)
                    ->where('tp.is_available', true)
                    ->whereNotNull('tp.latitude') // Mantenemos la seguridad
                    ->whereNotNull('tp.longitude') // Mantenemos la seguridad
                    ->orderBy('distance')
                    ->first();
                
                // 2. PREPARAR DATOS DE LA SOLICITUD
                $requestData = [
                    'cliente_id' => $request->user()->id,
                    'service_id' => $request->service_id,
                    'client_latitude' => $request->client_latitude,
                    'client_longitude' => $request->client_longitude,
                    'status' => 'pendiente',
                    'comments' => $request->comments,
                    'tecnico_id' => null, // Por defecto
                ];

                // 3. ASIGNAR TÉCNICO SI SE ENCONTRÓ
                if ($technicianProfile) {
                    $requestData['tecnico_id'] = $technicianProfile->user_id;
                    $requestData['status'] = 'asignado';
                }

                // 4. CREAR LA SOLICITUD AHORA
                $serviceRequest = ServiceRequest::create($requestData);
            
                // 5. DISPARAR EVENTO (solo si se asignó)
                if ($serviceRequest->tecnico_id) {
                    event(new ServiceRequestCreated($serviceRequest));
                }

                return $serviceRequest;
            });
            // --- Fin de la transacción ---

        } catch (Throwable $e) {
            // Si algo falla (ej. la consulta Haversine por un error de sintaxis),
            // la transacción se revierte y se lanza un 500.
            report($e); // Reporta el error al log
            return response()->json(['message' => 'Error interno al crear la solicitud.'], 500);
        }

        // Cargar relaciones y responder (¡Ahora esto no debería fallar!)
        $serviceRequest->load('service', 'technician', 'client', 'rating');
        return response()->json($serviceRequest, 201);
    }

    public function updateStatus(Request $request, ServiceRequest $serviceRequest)
    {
// ... (método updateStatus sin cambios)
        $user = $request->user();

        $validated = $request->validate([
            'status' => 'required|in:pendiente,asignado,en_progreso,completado,cancelado',
            'comment' => 'nullable|string|max:1000'
        ]);
        $newStatus = $validated['status'];

        if ($user->hasRole('tecnico')) {
            if ($serviceRequest->tecnico_id !== $user->id) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
            if (in_array($newStatus, ['en_progreso', 'completado'])) {
                $serviceRequest->status = $newStatus;
            } else {
                return response()->json(['message' => 'Acción no permitida para el técnico.'], 403);
            }
        } elseif ($user->hasRole('cliente')) {
            if ($serviceRequest->cliente_id !== $user->id) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
            if ($newStatus === 'cancelado') {
                $serviceRequest->status = $newStatus;
                if ($request->filled('comment')) {
                    $serviceRequest->comments = $validated['comment'];
                }
            } else {
                return response()->json(['message' => 'Un cliente solo puede cancelar la solicitud.'], 403);
            }
        } elseif ($user->hasRole('admin')) {
            $serviceRequest->status = $newStatus;
        } else {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $serviceRequest->save();

        $serviceRequest->load('service', 'technician', 'client', 'rating');

        return response()->json(['message' => 'Estado actualizado', 'serviceRequest' => $serviceRequest]);
    }

    public function index(Request $request)
    {
// ... (método index sin cambios)
        $user = $request->user();

        $query = ServiceRequest::with('service', 'technician', 'client', 'rating');

        if ($user->hasRole('tecnico')) {
            $query->where('tecnico_id', $user->id);
        } else {
            $query->where('cliente_id', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json($requests);
    }
}