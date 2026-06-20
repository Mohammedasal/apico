<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('APICO Login') }}</title>
    <style>
        :root { --ink:#18201d; --muted:#66706b; --line:#dde5e1; --bg:#f4f6f5; --accent:#176b55; --accent-strong:#0f513f; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; background:var(--bg); color:var(--ink); font-family:Arial, sans-serif; }
        html[dir="rtl"] body { font-family:Tahoma, Arial, sans-serif; }
        .login { width:min(420px, calc(100vw - 32px)); background:#fff; border:1px solid var(--line); border-radius:8px; padding:24px; box-shadow:0 12px 28px #18201d14; }
        .brand { display:flex; align-items:center; gap:10px; margin-bottom:18px; }
        .mark { display:grid; place-items:center; width:38px; height:38px; border-radius:8px; background:var(--accent); color:#fff; font-weight:bold; }
        h1 { margin:0; font-size:22px; }
        .muted { color:var(--muted); font-size:13px; margin-top:3px; }
        label { display:block; font-size:13px; font-weight:bold; margin:14px 0 6px; }
        input { width:100%; border:1px solid #cfd9d4; border-radius:6px; padding:10px; font-size:14px; }
        .check { display:flex; align-items:center; gap:8px; margin:14px 0; color:var(--muted); }
        .check input { width:auto; }
        button { width:100%; border:0; border-radius:6px; padding:11px 14px; background:var(--accent); color:#fff; font-weight:bold; cursor:pointer; }
        button:hover { background:var(--accent-strong); }
        .error { color:#b91c1c; font-size:13px; margin-top:6px; }
    </style>
</head>
<body>
<form class="login" method="post" action="{{ route('login.store') }}">
    @csrf
    <div class="brand">
        <div class="mark">A</div>
        <div><h1>{{ __('APICO Login') }}</h1><div class="muted">{{ __('Access the factory ledger') }}</div></div>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:12px">
        <a href="{{ route('language.switch', 'en') }}">English</a>
        <a href="{{ route('language.switch', 'ar') }}">العربية</a>
    </div>
    <label>{{ __('Email') }}</label>
    <input type="email" name="email" value="{{ old('email') }}" autofocus>
    @error('email')<div class="error">{{ $message }}</div>@enderror
    <label>{{ __('Password') }}</label>
    <input type="password" name="password">
    @error('password')<div class="error">{{ $message }}</div>@enderror
    <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Remember me') }}</label>
    <button>{{ __('Login') }}</button>
</form>
</body>
</html>
