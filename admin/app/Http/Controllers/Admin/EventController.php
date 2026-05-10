<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = Event::query()
            ->withCount('participants')
            ->orderByDesc('date')
            ->paginate(20);

        return view('admin.events.index', compact('events'));
    }

    public function create(): View
    {
        return view('admin.events.form', ['event' => new Event]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by_admin_id'] = Auth::id();

        $event = Event::create($data);

        return redirect()
            ->route('admin.events.show', $event)
            ->with('status', 'Event created.');
    }

    public function show(Event $event): View
    {
        $event->load(['participants' => fn ($q) => $q->orderByPivot('joined_at', 'desc')]);

        return view('admin.events.show', compact('event'));
    }

    public function edit(Event $event): View
    {
        return view('admin.events.form', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request));

        return redirect()
            ->route('admin.events.show', $event)
            ->with('status', 'Event updated.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()
            ->route('admin.events.index')
            ->with('status', 'Event deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:2000'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'date' => ['required', 'date'],
            'starttime' => ['nullable', 'string', 'max:16'],
            'endtime' => ['nullable', 'string', 'max:16'],
            'capacity' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);
    }
}
