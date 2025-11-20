<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Throwable;

class ServiceController extends Controller
{
    public function index()
    {
        try {
            // Intentar obtener todos los servicios
            $services = Service::all();
            return response()->json($services);
            
        } catch (Throwable $e) {
            // Si falla, reportar error y devolver mensaje claro
            report($e);
            return response()->json([
                'message' => 'Error al cargar servicios',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}