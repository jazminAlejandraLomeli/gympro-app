<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Services\Membership\MemberSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/** Pantalla del escáner QR + búsqueda para la entrada manual. */
class ScannerController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Gym/Scanner/Index');
    }

    public function search(Request $request, MemberSearch $search): JsonResponse
    {
        $q = Str::limit(trim((string) $request->query('q', '')), 80, '');

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $ids = array_slice($search->ids($q, 50), 0, 8);

        $members = Membership::query()
            ->with('user:id,name,email')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Membership $m) => array_search($m->id, $ids, true))
            ->values()
            ->map(fn (Membership $m) => MemberController::row($m));

        return response()->json(['data' => $members]);
    }
}
