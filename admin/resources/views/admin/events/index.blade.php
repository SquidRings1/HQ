@extends('admin.layouts.app')

@section('title', 'Events')

@section('content')
    <h1 style="display:flex;justify-content:space-between;align-items:center">
        Events
        <a href="{{ route('admin.events.create') }}" class="btn btn-primary">+ New event</a>
    </h1>

    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Capacity</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr>
                        <td><a href="{{ route('admin.events.show', $event) }}">{{ $event->name }}</a></td>
                        <td>{{ $event->date->format('Y-m-d') }}</td>
                        <td>{{ $event->starttime }} – {{ $event->endtime }}</td>
                        <td>{{ $event->capacity > 0 ? $event->capacity : '∞' }}</td>
                        <td><span class="badge">{{ $event->participants_count }}</span></td>
                        <td>
                            <a href="{{ route('admin.events.edit', $event) }}">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;color:#888;padding:2rem">No events yet — <a href="{{ route('admin.events.create') }}">create one</a>.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top:1rem">{{ $events->links() }}</div>
    </div>
@endsection
