<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\Membership\MembershipSummary;
use App\Services\Membership\QrSigner;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Credencial QR del socio (se muestra en recepción). */
class CredentialController extends Controller
{
    public function __invoke(Request $request, QrSigner $signer): Response
    {
        $user = $request->user();
        $membership = Membership::query()->where('user_id', $user->id)->first();

        return Inertia::render('Member/Credential', [
            'qr' => $membership ? $signer->payloadFor($membership) : null,
            'memberName' => $user->name,
            'membership' => MembershipSummary::fromMembership($membership),
        ]);
    }
}
