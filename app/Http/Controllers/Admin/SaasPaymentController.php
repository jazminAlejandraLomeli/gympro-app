<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSaasPaymentRequest;
use App\Models\Gym;
use App\Services\Saas\RecordSaasPayment;
use Illuminate\Http\RedirectResponse;

/** Registro manual de pagos de licencia (cobro pasivo). */
class SaasPaymentController extends Controller
{
    public function store(StoreSaasPaymentRequest $request, Gym $gym, RecordSaasPayment $record): RedirectResponse
    {
        $wasSuspended = ! $gym->isActive();

        $payment = $record->handle($gym, $request->validated(), $request->user());

        $message = 'Pago registrado. Licencia pagada hasta el '.$payment->covers_until->locale('es')->translatedFormat('j \d\e F \d\e Y').'.';
        if ($wasSuspended) {
            $message .= ' El gimnasio se reactivó.';
        }

        return back()->with('success', $message);
    }
}
