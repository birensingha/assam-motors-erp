<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ASSAM MOTORS · HR Secure Login</title>
<style>
:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#153654;background:#edf3f9}
*{box-sizing:border-box}body{margin:0;min-height:100vh}
header{background:linear-gradient(115deg,#052f52,#0b5078);color:#fff;padding:24px max(20px,calc((100vw - 590px)/2));border-bottom:4px solid #ee293f}
header strong{font-size:21px}header p{color:#ccdfec;margin:6px 0 0}
main{max-width:590px;padding:22px;margin:25px auto}
article{background:#fff;border:1px solid #d8e3ee;border-radius:20px;padding:26px;box-shadow:0 12px 35px #16395416}
h1{font-size:22px;margin:0 0 8px}.muted{color:#647a8d;font-size:14px;line-height:1.55}
.account{padding:14px;border-radius:12px;background:#edf6ff;border:1px solid #cce1f0;overflow-wrap:anywhere;margin:16px 0}
label{display:block;margin-top:18px;font-weight:700;font-size:14px}
input{margin-top:7px;width:100%;font:inherit;padding:13px 14px;border:1px solid #bdcfde;border-radius:11px;min-height:47px}
input:focus{outline:2px solid #2e8fc3}.submit{display:block;width:100%;margin-top:22px;border:0;background:#0e8758;color:#fff;padding:14px;border-radius:12px;font-size:15px;font-weight:800;cursor:pointer}
.error{padding:14px;background:#fff0f1;color:#a71e31;border-radius:12px;font-size:14px}
</style>
</head>
<body>
<header><strong>ASSAM MOTORS · HR Management</strong><p>Secure HR Manager sign-in · STAGING ERP</p></header>
<main><article><h1>HR Manager Login</h1>
<p class="muted">Attendance, leave ledger, staff ROT productivity and salary-impact reports are available only to your authorized HR account.</p>
@if($currentAccount)
<div class="account">Browser mein abhi signed in: <strong>{{ $currentAccount }}</strong><p class="muted" style="margin:4px 0 0">HR credentials verify hone par session securely switch hoga.</p></div>
@endif
@if($errors->any())<div class="error" role="alert">{{ $errors->first() }}</div>@endif
<form method="post" action="{{ route('erp.hr.direct-login.submit') }}" autocomplete="on">
@csrf
<label for="email">HR Email ID</label><input id="email" name="email" type="email" autocomplete="username" required maxlength="180" value="{{ old('email') }}" />
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required />
<button class="submit" type="submit">Open HR Dashboard →</button>
</form>
<p class="muted">Normal Staff, Supervisor aur Admin accounts ko HR Manager access nahi milega. Geo-fence aur staff attendance requirements unchanged hain.</p>
</article></main>
</body></html>