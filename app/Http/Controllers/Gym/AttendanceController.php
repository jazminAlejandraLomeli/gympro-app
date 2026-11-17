<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\ManualCheckInRequest;
use App\Http\Requests\Membership\ScanRequest;
use App\Services\Membership\AccessControl;
use Illuminate\Http\JsonResponse;

/**
 * POST /panel/asistencia/escanear  (equivale a POST /api/attendance/scan)
 * Responde {status: granted|denied|invalid, message, member: {name, initial}, expires_on, credits}
 */
class AttendanceController extends Controller
{
    public function scan(ScanRequest $request, AccessControl $access): JsonResponse
    {
        return response()->json($access->scan($request->validated('payload')));
    }

    /** Entrada manual desde recepción (socio sin celular, cámara fallando…). */
    public function manual(ManualCheckInRequest $request, AccessControl $access): JsonResponse
    {
        return response()->json($access->checkIn((int) $request->validated('user_id'), 'manual'));
    }
}
