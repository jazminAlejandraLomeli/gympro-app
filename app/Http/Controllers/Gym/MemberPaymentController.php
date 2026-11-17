<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Membership\RecordPaymentRequest;
use App\Models\Membership;
use App\Services\Membership\MembershipBilling;
use Illuminate\Http\RedirectResponse;

/** Registrar pago / renovación en recepción. */
class MemberPaymentController extends Controller
{
    public function store(RecordPaymentRequest $request, Membership $membership, MembershipBilling $billing): RedirectResponse
    {
        $billing->recordPayment($membership, $request->validated(), $request->user());

        $membership->refresh();
        $detail = $membership->expires_on
            ? 'Vigente hasta el '.$membership->expires_on->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : "Créditos: {$membership->credits}";

        return back()->with('success', "Pago registrado. {$detail}.");
    }
}
