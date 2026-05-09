<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(): JsonResponse
    {
        $events = Event::query()
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(50)
            ->get(['id', 'name', 'about', 'address', 'date', 'starttime', 'endtime', 'capacity']);

        return response()->json(['events' => $events]);
    }

    public function show(Event $event): JsonResponse
    {
        $event->loadCount('participants');

        return response()->json(['event' => $event]);
    }

    public function join(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($event, $user) {
            $event = Event::lockForUpdate()->findOrFail($event->id);

            if ($event->date->isPast()) {
                return response()->json(['message' => 'Event has already happened'], 422);
            }

            if ($event->participants()->where('user_id', $user->id)->exists()) {
                return response()->json(['message' => 'Already joined'], 409);
            }

            if ($event->capacity > 0 && $event->participants()->count() >= $event->capacity) {
                return response()->json(['message' => 'Event is full'], 422);
            }

            $event->participants()->attach($user->id, ['joined_at' => now()]);

            return response()->json(['message' => 'Joined', 'event_id' => $event->id], 201);
        });
    }

    public function cancel(Request $request, Event $event): JsonResponse
    {
        $user = $request->user();

        $detached = $event->participants()->detach($user->id);

        if ($detached === 0) {
            return response()->json(['message' => 'Not joined'], 404);
        }

        return response()->json(['message' => 'Cancelled']);
    }

    public function mine(Request $request): JsonResponse
    {
        $events = $request->user()
            ->joinedEvents()
            ->orderBy('date')
            ->get(['events.id', 'events.name', 'events.about', 'events.address', 'events.date', 'events.starttime', 'events.endtime']);

        return response()->json(['events' => $events]);
    }
}
