@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1>Dashboard</h1>

    <div>
        <div class="stat">
            <div class="num">{{ $eventCount }}</div>
            <div class="label">Total events</div>
        </div>
        <div class="stat">
            <div class="num">{{ $upcomingEventCount }}</div>
            <div class="label">Upcoming</div>
        </div>
        <div class="stat">
            <div class="num">{{ $userCount }}</div>
            <div class="label">Mobile users</div>
        </div>
    </div>

    <div class="card" style="margin-top:1.5rem">
        <h2>Quick actions</h2>
        <p>
            <a href="{{ route('admin.events.create') }}" class="btn btn-primary">Create event</a>
            <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">View all events</a>
        </p>
    </div>
@endsection
