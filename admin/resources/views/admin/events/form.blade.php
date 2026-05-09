@extends('admin.layouts.app')

@section('title', $event->exists ? 'Edit event' : 'New event')

@section('content')
    <h1>{{ $event->exists ? 'Edit event' : 'New event' }}</h1>

    @if ($errors->any())
        <div class="alert alert-error">
            <ul class="errors">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ $event->exists ? route('admin.events.update', $event) : route('admin.events.store') }}">
            @csrf
            @if ($event->exists) @method('PUT') @endif

            <div class="field">
                <label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $event->name) }}" required maxlength="160">
            </div>

            <div class="field">
                <label for="about">About</label>
                <textarea id="about" name="about" maxlength="2000">{{ old('about', $event->about) }}</textarea>
            </div>

            <div class="field">
                <label for="address">Address</label>
                <input id="address" type="text" name="address" value="{{ old('address', $event->address) }}" maxlength="255">
            </div>

            <div class="field">
                <label for="phone">Phone</label>
                <input id="phone" type="text" name="phone" value="{{ old('phone', $event->phone) }}" maxlength="32">
            </div>

            <div class="field">
                <label for="date">Date</label>
                <input id="date" type="date" name="date" value="{{ old('date', optional($event->date)->format('Y-m-d')) }}" required>
            </div>

            <div class="field" style="display:flex;gap:1rem">
                <div style="flex:1">
                    <label for="starttime">Start time</label>
                    <input id="starttime" type="text" name="starttime" value="{{ old('starttime', $event->starttime) }}" placeholder="09:00" maxlength="16">
                </div>
                <div style="flex:1">
                    <label for="endtime">End time</label>
                    <input id="endtime" type="text" name="endtime" value="{{ old('endtime', $event->endtime) }}" placeholder="18:00" maxlength="16">
                </div>
            </div>

            <div class="field">
                <label for="capacity">Capacity (0 = unlimited)</label>
                <input id="capacity" type="number" name="capacity" value="{{ old('capacity', $event->capacity ?? 0) }}" min="0" max="100000" required>
            </div>

            <button type="submit" class="btn btn-primary">{{ $event->exists ? 'Update' : 'Create' }}</button>
            <a href="{{ route('admin.events.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
@endsection
