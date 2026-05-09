@extends('admin.layouts.app')

@section('title', 'Sign in')

@section('content')
    <div class="card" style="max-width:420px;margin:4rem auto">
        <h1>Sign in</h1>

        @if ($errors->any())
            <div class="alert alert-error">
                <ul class="errors">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login') }}">
            @csrf

            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required autocomplete="current-password">
            </div>

            <div class="field">
                <label style="font-weight:400">
                    <input type="checkbox" name="remember" value="1"> Remember me
                </label>
            </div>

            <button type="submit" class="btn btn-primary">Sign in</button>
        </form>
    </div>
@endsection
