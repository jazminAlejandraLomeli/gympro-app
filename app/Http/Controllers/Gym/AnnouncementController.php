<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AnnouncementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Announcements\SaveAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Tablón de avisos y promociones (Módulo E). Solo gym_admin (can:manage-gym). */
class AnnouncementController extends Controller
{
    private const STATUSES = [
        Announcement::STATUS_SCHEDULED,
        Announcement::STATUS_ACTIVE,
        Announcement::STATUS_EXPIRED,
    ];

    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), self::STATUSES, true) ? $request->query('status') : null;
        $now = now();

        $announcements = Announcement::query()
            ->when($status === Announcement::STATUS_SCHEDULED, fn ($q) => $q->where('starts_at', '>', $now))
            ->when($status === Announcement::STATUS_ACTIVE, fn ($q) => $q->where('starts_at', '<=', $now)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)))
            ->when($status === Announcement::STATUS_EXPIRED, fn ($q) => $q->whereNotNull('expires_at')->where('expires_at', '<=', $now))
            ->orderByDesc('pinned')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Announcement $a) => $this->present($a));

        return Inertia::render('Gym/Announcements/Index', [
            'announcements' => $announcements,
            'filters' => ['status' => $status],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Gym/Announcements/Form', [
            'announcement' => null,
            'types' => AnnouncementType::options(),
            'defaults' => ['starts_at' => now()->format('Y-m-d\TH:i')],
        ]);
    }

    public function store(SaveAnnouncementRequest $request): RedirectResponse
    {
        $announcement = new Announcement($request->announcementData());
        $announcement->created_by = $request->user()->id;
        $announcement->save();

        return to_route('gym.announcements.index')->with('success', 'Aviso publicado.');
    }

    public function edit(Announcement $announcement): Response
    {
        return Inertia::render('Gym/Announcements/Form', [
            'announcement' => $this->present($announcement),
            'types' => AnnouncementType::options(),
            'defaults' => null,
        ]);
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $announcement->update($request->announcementData());

        return to_route('gym.announcements.index')->with('success', 'Aviso actualizado.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return to_route('gym.announcements.index')->with('success', 'Aviso eliminado.');
    }

    private function present(Announcement $a): array
    {
        return [
            'id' => $a->id,
            'type' => $a->type->value,
            'typeLabel' => $a->type->label(),
            'title' => $a->title,
            'body' => $a->body,
            'pinned' => $a->pinned,
            'status' => $a->status(),
            'starts_at' => $a->starts_at->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            // Para <input type="datetime-local"> en hora del gimnasio
            'starts_at_local' => $a->starts_at->format('Y-m-d\TH:i'),
            'expires_at_local' => $a->expires_at?->format('Y-m-d\TH:i'),
        ];
    }
}
