<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'HQ Admin')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; background: #f5f5f7; color: #1d1d1f; }
        a { color: #0066cc; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .topbar { background: #1d1d1f; color: #fff; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .topbar .brand { font-weight: 600; font-size: 1.1rem; }
        .topbar a { color: #fff; margin-left: 1rem; }
        .container { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: 8px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.04); }
        h1 { margin-top: 0; }
        h2 { margin-top: 0; font-size: 1.2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 0.6rem 0.75rem; border-bottom: 1px solid #eee; }
        th { font-weight: 600; font-size: 0.85rem; color: #555; text-transform: uppercase; }
        form .field { margin-bottom: 1rem; }
        form label { display: block; font-weight: 500; margin-bottom: 0.3rem; }
        form input[type=text], form input[type=email], form input[type=password], form input[type=date], form input[type=number], form textarea {
            width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #d2d2d7; border-radius: 6px; font: inherit;
        }
        form textarea { min-height: 100px; }
        .btn { display: inline-block; padding: 0.55rem 1rem; border-radius: 6px; border: 0; cursor: pointer; font: inherit; }
        .btn-primary { background: #0066cc; color: #fff; }
        .btn-danger { background: #c62828; color: #fff; }
        .btn-secondary { background: #e8e8ed; color: #1d1d1f; }
        .stat { display: inline-block; padding: 1rem 1.5rem; margin-right: 1rem; background: #fff; border-radius: 8px; min-width: 140px; }
        .stat .num { font-size: 1.8rem; font-weight: 600; }
        .stat .label { font-size: 0.85rem; color: #666; text-transform: uppercase; }
        .alert { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #e6f4ea; color: #1e6c34; }
        .alert-error { background: #fce8e6; color: #c62828; }
        .badge { display: inline-block; padding: 0.15rem 0.5rem; background: #e8e8ed; border-radius: 4px; font-size: 0.85rem; }
        ul.errors { padding-left: 1.5rem; margin: 0.5rem 0; }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="brand">HQ Admin · Events</div>
        <div>
            @auth
                <span>{{ auth()->user()->email }}</span>
                <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a href="{{ route('admin.events.index') }}">Events</a>
                <form action="{{ route('admin.logout') }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="margin-left:1rem">Logout</button>
                </form>
            @endauth
        </div>
    </div>

    <div class="container">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</body>
</html>
