@extends('admin.layouts.app')

@section('title', $event->name)

@section('content')
    <h1 style="display:flex;justify-content:space-between;align-items:center">
        {{ $event->name }}
        <span>
            <a href="{{ route('admin.events.edit', $event) }}" class="btn btn-secondary">Edit</a>
            <form method="POST" action="{{ route('admin.events.destroy', $event) }}" style="display:inline" onsubmit="return confirm('Delete this event?')">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </span>
    </h1>

    <div class="card">
        <h2>Details</h2>
        <p><strong>Date:</strong> {{ $event->date->format('Y-m-d') }} · {{ $event->starttime }} – {{ $event->endtime }}</p>
        <p><strong>Address:</strong> {{ $event->address ?: '—' }}</p>
        <p><strong>Phone:</strong> {{ $event->phone ?: '—' }}</p>
        <p><strong>Capacity:</strong> {{ $event->capacity > 0 ? $event->capacity : 'unlimited' }}</p>
        @if ($event->about)
            <p><strong>About:</strong><br>{{ $event->about }}</p>
        @endif
    </div>

    <div class="card">
        <h2>Participants ({{ $event->participants->count() }})</h2>
        @if ($event->participants->isEmpty())
            <p style="color:#888">No participants yet.</p>
        @else
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Joined at</th></tr>
                </thead>
                <tbody>
                    @foreach ($event->participants as $p)
                        <tr>
                            <td>{{ $p->name }}</td>
                            <td>{{ $p->email }}</td>
                            <td>{{ $p->pivot->joined_at }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
