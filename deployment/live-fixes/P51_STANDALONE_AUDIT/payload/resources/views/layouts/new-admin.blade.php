<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title','NEW Admin Console — Assam Motors')</title>
<style>
:root{--navy:#102640;--navy2:#183b61;--blue:#2163b2;--bg:#f3f6fb;--edge:#dce6f1;--text:#17314e;--muted:#697e92;--side:264px}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;-webkit-font-smoothing:antialiased}
a{color:inherit}button,input{font:inherit}
a:focus-visible,button:focus-visible{outline:3px solid #edb348;outline-offset:3px}
.am-admin-sidebar{position:fixed;inset:0 auto 0 0;width:var(--side);display:flex;flex-direction:column;min-height:100vh;height:100dvh;background:var(--navy);color:#eaf3ff;z-index:80;box-shadow:3px 0 18px #0d305427}
.am-admin-brand{display:flex;gap:12px;align-items:center;padding:20px 17px;border-bottom:1px solid #ffffff1a;min-height:80px;text-decoration:none}
.am-admin-mark{width:43px;height:43px;display:grid;place-items:center;background:linear-gradient(135deg,#267be0,#144885);border:1px solid #639beb;border-radius:12px;font-weight:900;color:white}
.am-admin-brand strong{display:block;font-size:15px;letter-spacing:.035em}.am-admin-brand small{display:block;font-size:10px;letter-spacing:.12em;color:#9bbfe2}
.am-admin-menu{padding:18px 10px;overflow:auto;flex:1;overscroll-behavior:contain;scrollbar-width:thin}
.am-admin-group{padding:14px 12px 7px;font-size:10px;letter-spacing:.115em;font-weight:900;color:#8eafcb}
.am-admin-menu a{display:flex;gap:11px;align-items:center;padding:10px 13px;border:1px solid transparent;border-radius:10px;text-decoration:none;font-weight:700;font-size:12px;margin:3px 0;min-height:43px;color:#d6e6f8}
.am-admin-menu a:hover{background:#ffffff18;color:white}
.am-admin-menu a.active{color:white;background:#275b93;border-color:#3e78b2;border-left:3px solid #65c1ff}
.am-admin-menu a span:first-child{width:25px;text-align:center;font-size:17px}
.am-admin-sidebar-foot{padding:13px;border-top:1px solid #ffffff19}
.am-admin-sidebar-foot a{display:block;text-decoration:none;padding:9px 11px;font-size:12px;color:#b9d9f6;border:1px solid #476785;border-radius:10px}
.am-admin-top{position:sticky;top:0;z-index:55;min-height:75px;background:#fff;display:flex;align-items:center;gap:13px;margin-left:var(--side);padding:12px 23px;border-bottom:1px solid var(--edge);box-shadow:0 5px 18px #1236580d}
.am-admin-toggle{display:none;border:1px solid #d5e0ea;border-radius:9px;padding:8px 11px;background:#f6faff;color:#244f7c}
.am-admin-top-title{font-size:16px;font-weight:830;min-width:0;flex:1}
.am-admin-stage{display:inline-flex;gap:7px;align-items:center;white-space:nowrap;font-size:11px;font-weight:800;border-radius:999px;padding:7px 11px;color:#175e47;background:#e4f7ed}
.am-admin-stage:before{content:"";width:7px;height:7px;border-radius:50%;background:#13a16f}
.am-admin-user{color:var(--muted);white-space:nowrap;font-size:12px}
.am-admin-logout{border:1px solid #dae4ee;background:#f9fbfe;color:#275379;border-radius:9px;padding:8px 12px;font-weight:750}
.am-admin-main{margin-left:var(--side);padding:24px clamp(15px,2.2vw,34px) 50px;min-height:calc(100vh - 75px)}
.am-admin-main .am-container{max-width:1480px;margin:auto}
.am-admin-overlay{display:none}
.am-admin-footer{margin-top:30px;border-top:1px solid var(--edge);padding-top:14px;color:var(--muted);font-size:11px}
@media(max-width:900px){
 .am-admin-sidebar{transform:translateX(-103%);transition:transform .2s ease}
 body.admin-open .am-admin-sidebar{transform:translateX(0)}
 .am-admin-top,.am-admin-main{margin-left:0}
 .am-admin-toggle{display:inline-flex}
 .am-admin-overlay{display:block;position:fixed;inset:0;background:#10243b6b;z-index:75;opacity:0;pointer-events:none;border:0;transition:opacity .2s ease}
 body.admin-open .am-admin-overlay{opacity:1;pointer-events:auto}
}
@media(max-width:600px){.am-admin-top{padding:12px;flex-wrap:wrap;gap:9px}.am-admin-top-title{font-size:13px}.am-admin-user{display:none}.am-admin-main{padding:14px 12px 35px}.am-admin-stage{font-size:10px}}
@media(prefers-reduced-motion:reduce){*{transition:none!important;scroll-behavior:auto!important}}
@media print{.am-admin-sidebar,.am-admin-top,.am-admin-overlay{display:none}.am-admin-main{margin:0;padding:0}}
</style>
@stack('head')
</head>
<body>
<aside class="am-admin-sidebar" id="newAdminSidebar">
  <a class="am-admin-brand" href="{{ route('erp.admin.console') }}">
    <span class="am-admin-mark">AM</span>
    <span><strong>ASSAM MOTORS</strong><small>NEW ADMIN CONSOLE</small></span>
  </a>
  <nav class="am-admin-menu" aria-label="NEW Admin navigation">
    <div class="am-admin-group">ADMINISTRATIVE CONTROL</div>
    <a class="{{ request()->routeIs('erp.admin.console')?'active':'' }}" href="{{ route('erp.admin.console') }}"><span>▦</span> Admin Overview</a>
    <a href="{{ route('erp.attendance') }}"><span>▣</span> Staff Attendance</a>
    <a href="{{ route('erp.staff-notifications.index') }}"><span>♢</span> Staff Notifications</a>
    <a href="{{ route('erp.configuration') }}"><span>⚙</span> ERP Configuration</a>
    <div class="am-admin-group">FINANCE & MASTER CONTROL</div>
    <a href="{{ route('erp.masters.parts') }}"><span>▤</span> Parts Master</a>
    <a href="{{ route('erp.vendors.index') }}"><span>▣</span> Vendor Master</a>
    <a href="{{ route('erp.fix49.payment-register') }}"><span>⇄</span> Payment Voucher Audit</a>
    <div class="am-admin-group">WORKSHOP OPERATIONS</div>
    <a href="{{ route('erp.dashboard') }}"><span>↗</span> Open NEW ERP</a>
  </nav>
  <div class="am-admin-sidebar-foot"><a href="{{ route('erp.dashboard') }}">← Switch to Workshop ERP</a></div>
</aside>
<button class="am-admin-overlay" aria-label="Close Admin menu" onclick="document.body.classList.remove('admin-open')"></button>
<header class="am-admin-top">
  <button class="am-admin-toggle" type="button" aria-controls="newAdminSidebar" aria-label="Open Admin navigation" onclick="document.body.classList.toggle('admin-open')">☰</button>
  <div class="am-admin-top-title">@yield('topbar_title','NEW Admin Console')</div>
  <span class="am-admin-stage">STAGING · NEW ADMIN</span>
  <span class="am-admin-user">{{ auth()->user()->name ?? 'Admin' }}</span>
  <form method="post" action="{{ route('erp.logout') }}">@csrf<button type="submit" class="am-admin-logout">Logout</button></form>
</header>
<main class="am-admin-main"><div class="am-container">
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @yield('content')
  <footer class="am-admin-footer">ASSAM MOTORS · NEW Admin Console · The OLD ERP and OLD Admin are separate and have not been modified.</footer>
</div></main>
</body>
</html>
