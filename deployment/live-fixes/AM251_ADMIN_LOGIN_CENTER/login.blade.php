<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>ERP Login · ASSAM MOTORS</title>
  <style>
    /* AM251_LOGIN_STANDALONE_CENTER: no ERP shell, sidebar offset or transformed ancestor */
    * { box-sizing: border-box; }
    html { width: 100%; min-height: 100%; }
    body.am251-center-page {
      margin: 0; padding: 24px 16px;
      width: 100%; min-height: 100vh; min-height: 100dvh;
      display: grid; place-items: center;
      background: radial-gradient(ellipse at 15% 12%, #dbe9fa 0, transparent 50%),
                  radial-gradient(ellipse at 90% 85%, #dce7f8 0, transparent 52%), #edf3f9;
      color: #163453; font-family: Inter, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    }
    .am251-card {
      width: min(100%, 410px); max-width: 410px;
      margin: 0 auto; padding: clamp(23px, 5vw, 34px);
      background: #fff; border: 1px solid #d9e4ef; border-radius: 17px;
      box-shadow: 0 16px 55px rgba(21, 56, 98, .13);
    }
    .am251-header { text-align: center; margin-bottom: 24px; }
    .am251-logo {
      width: 67px; height: 67px; object-fit: contain; display: block; margin: 0 auto 11px;
    }
    .am251-brand { margin: 0; font-size: 13px; font-weight: 850; letter-spacing: .12em; color: #18538b; }
    .am251-header h1 { margin: 12px 0 5px; font-size: clamp(20px, 5vw, 24px); line-height: 1.22; letter-spacing: -.02em; }
    .am251-header p { margin: 0; color: #647994; font-size: 12px; }
    .am251-field { display: block; margin: 15px 0; font-size: 12px; font-weight: 750; color: #263f5b; }
    .am251-field input {
      display: block; margin-top: 6px; width: 100%; min-height: 45px; padding: 11px 13px;
      font: 400 14px system-ui, sans-serif; color: #193855;
      border: 1px solid #bfcfe0; border-radius: 9px; background: #fff;
    }
    .am251-field input:focus { outline: 2px solid #3687d4; outline-offset: 1px; border-color: #3687d4; }
    .am251-submit {
      margin-top: 16px; width: 100%; min-height: 47px;
      border: 0; border-radius: 9px; background: #124e89; color: #fff;
      font-size: 14px; font-weight: 800; cursor: pointer;
    }
    .am251-submit:hover { background: #0b3f73; }
    .am251-notice { padding: 11px 13px; margin: 0 0 14px; border-radius: 9px; font-size: 12px; line-height: 1.5; }
    .am251-error { background: #fff0f0; color: #9c2736; border: 1px solid #ebc1c1; }
    .am251-ok { background: #eaf8f0; color: #216e47; border: 1px solid #bfe3ce; }
    .am251-error ul { margin: 6px 0 0; padding-left: 17px; }
    .am251-footer { text-align: center; margin: 16px 0 0; font-size: 11px; color: #78899b; }
    @media (max-width: 480px) {
      body.am251-center-page { padding: 15px 12px; }
      .am251-card { border-radius: 12px; }
    }
    @media (prefers-reduced-motion: reduce) {
      * { scroll-behavior: auto !important; }
    }
  </style>
</head>
<body class="am251-center-page">
  <main class="am251-card">
    <div class="am251-header">
      <img class="am251-logo" src="{{ asset('legacy/images/logo.png') }}" alt="Assam Motors">
      <p class="am251-brand">ASSAM MOTORS</p>
      <h1>Admin / Supervisor Login</h1>
      <p>Secure Workshop ERP access</p>
    </div>
    @if(session('success'))
      <div class="am251-notice am251-ok" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
      <div class="am251-notice am251-error" role="alert">
        <strong>Please correct:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif
    <form method="post" action="{{ route('erp.login.submit') }}">
      @csrf
      <label class="am251-field" for="am251-email">
        Email
        <input id="am251-email" type="email" name="email" value="{{ old('email') }}"
          autocomplete="username" required autofocus>
      </label>
      <label class="am251-field" for="am251-password">
        Password
        <input id="am251-password" type="password" name="password"
          autocomplete="current-password" required>
      </label>
      <button class="am251-submit" type="submit">Login →</button>
    </form>
    <p class="am251-footer">Authorised Assam Motors ERP personnel only.</p>
  </main>
</body>
</html>
