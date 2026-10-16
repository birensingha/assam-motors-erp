<style>
/* ASSAM MOTORS — WORKSHOP ENTERPRISE UI v1: shared desktop and Android-responsive styling.
   No external fonts/scripts and no changes to ERP forms, routes, calculations or permissions. */
.am-modern {
  --am-ink:#10233b;--am-muted:#63768d;--am-border:#dbe4ef;--am-canvas:#f3f6fb;
  --am-navy:#0d1e34;--am-blue:#1458b4;--am-blue-2:#0c408e;
  --am-green:#0f7d58;--am-yellow:#f6b74b;--am-red:#bf4257;
  --am-shadow:0 10px 35px rgba(16,39,72,.075);
  --am-radius:17px;
  font:14px/1.48 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
  color:var(--am-ink);background:var(--am-canvas);min-height:100vh;
  -webkit-font-smoothing:antialiased;font-variant-numeric:tabular-nums
}
.am-modern *{box-sizing:border-box}
.am-modern a{transition:background-color .15s,color .15s,border-color .15s,box-shadow .15s; text-underline-offset:3px}
.am-modern button{cursor:pointer}
.am-modern button:disabled,.am-modern input:disabled,.am-modern textarea:disabled{cursor:not-allowed;opacity:.58}
.am-modern :is(a,button,input,textarea,select):focus-visible{outline:3px solid #f1b548;outline-offset:3px}
.am-modern :is(h1,h2,h3,p){overflow-wrap:anywhere}
.am-modern .am-suite{background:#0e213a;color:#e9f3ff;box-shadow:0 2px 16px rgba(10,30,57,.14)}
.am-modern .am-suite-inner{max-width:1480px;margin:0 auto;min-height:70px;display:flex;align-items:center;justify-content:space-between;gap:18px;padding:10px 22px}
.am-modern .am-brand{display:flex;align-items:center;gap:11px;color:inherit;text-decoration:none;flex-shrink:0}
.am-modern .am-logo{height:39px;width:43px;display:grid;place-items:center;background:linear-gradient(145deg,#2587e6,#1253a7);border-radius:12px;font-weight:900;letter-spacing:-1px;color:white;border:1px solid #559ef2}
.am-modern .am-brand-name{display:flex;flex-direction:column;line-height:1.16}
.am-modern .am-brand-name b{font-size:14px;letter-spacing:.075em}
.am-modern .am-brand-name small{font-size:10px;color:#a7c1dd;letter-spacing:.075em;text-transform:uppercase;font-weight:700}
.am-modern .am-suite-nav{display:flex;gap:5px;align-items:center;flex-wrap:wrap}
.am-modern .am-suite-nav a{display:inline-flex;align-items:center;gap:7px;color:#c4d4e7;text-decoration:none;padding:9px 12px;border:1px solid transparent;border-radius:9px;font-size:12px;font-weight:750}
.am-modern .am-suite-nav a:hover{background:#1b3657;color:#fff}
.am-modern .am-suite-nav a[aria-current=page]{color:white;background:#23548e;border-color:#36699e}
.am-modern .am-suite-tag{font-size:10px;letter-spacing:.08em;color:#a6c2df;border:1px solid #3f5977;border-radius:999px;padding:5px 9px;font-weight:800;white-space:nowrap}
.am-modern .wrap{max-width:1460px!important;margin:0 auto!important;padding:25px 23px!important}
.am-modern .am-content{max-width:1460px;margin:0 auto;padding:21px 22px 45px}
.am-modern .top,.am-modern .head,.am-modern .page-head{background:linear-gradient(114deg,#112a49,#174d86 83%);color:#fff!important;border-radius:var(--am-radius);padding:23px 27px!important;border:1px solid #2e5a89;box-shadow:0 9px 26px #17427020;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.am-modern .top h1,.am-modern .head h1,.am-modern .page-head h1{font-weight:820;font-size:clamp(20px,2.2vw,28px);letter-spacing:-.035em;line-height:1.2;margin:0 0 6px}
.am-modern .top p,.am-modern .head p,.am-modern .page-head p{color:#d3e5fa!important;margin:0!important;font-size:13px;line-height:1.5}
.am-modern .top a,.am-modern .head a,.am-modern .page-head .btn,.am-modern .page-head a{display:inline-flex;align-items:center;justify-content:center;gap:7px;text-decoration:none;border-radius:9px;font-size:12px;font-weight:800}
.am-modern .top a,.am-modern .head a{background:#fff;color:#194374!important;padding:10px 14px}
.am-modern .top a:hover,.am-modern .head a:hover{background:#e7f1ff}
.am-modern .am-eyebrow{display:block;font-size:10px;letter-spacing:.15em;text-transform:uppercase;font-weight:850;color:#b6d5f5;margin-bottom:7px}
.am-modern .am-subnav{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 18px}
.am-modern .am-subnav a{display:inline-flex;gap:7px;text-decoration:none;background:#fff;color:#31567a;padding:9px 12px;border:1px solid var(--am-border);border-radius:10px;font-weight:700}
.am-modern .am-subnav a:hover{border-color:#6c9ed9;color:#1458b4}
.am-modern .card,.am-modern .cards,.am-modern .panel,.am-modern .summary,.am-modern .stat,.am-modern .am-surface{background:#fff;border:1px solid var(--am-border);border-radius:var(--am-radius);box-shadow:var(--am-shadow)}
.am-modern .panel,.am-modern .cards{padding:20px!important;margin:17px 0}
.am-modern .panel h2{font-size:17px;color:#123a63;letter-spacing:-.02em;margin:0 0 8px}
.am-modern .subtitle,.am-modern .muted,.am-modern .mini,.am-modern .hint,.am-modern .foot{color:var(--am-muted)!important;font-size:12px}
.am-modern .summary{padding:17px!important;margin:15px 0!important}
.am-modern .summary:has(.stat){padding:0!important;border:0;box-shadow:none;background:transparent;display:grid;gap:13px}
.am-modern .stat{padding:17px!important;background:#fff!important;min-width:0;position:relative;overflow:hidden}
.am-modern .stat:before{content:"";position:absolute;inset:0 auto 0 0;background:var(--am-blue);width:4px}
.am-modern .stat:nth-child(2):before{background:var(--am-green)}
.am-modern .stat:nth-child(3):before{background:var(--am-yellow)}
.am-modern .stat:nth-child(4):before{background:var(--am-blue-2)}
.am-modern .stat small{display:block;font-size:11px;color:var(--am-muted);font-weight:720;letter-spacing:.025em}
.am-modern .stat strong{display:block;font-size:clamp(16px,1.7vw,22px);font-weight:820;color:var(--am-ink);margin-top:7px}
.am-modern :is(input,textarea,select){border:1px solid #becddd;border-radius:10px;padding:10px 12px;background:#fff;color:#162f4a;font:inherit;min-height:39px}
.am-modern input[readonly]{background:#edf3f9;color:#6d7e90}
.am-modern input[type=number]{font-variant-numeric:tabular-nums}
.am-modern :is(input,textarea,select):hover:not(:disabled){border-color:#7ca5d3}
.am-modern :is(label){font-size:12px;font-weight:720;color:#37516b}
.am-modern label :is(input,select,textarea){display:block;width:100%;margin-top:7px}
.am-modern :is(.green,.link,.btn.primary,.bar button,.am-primary){display:inline-flex;align-items:center;justify-content:center;gap:8px;background:var(--am-blue)!important;color:white!important;border:1px solid var(--am-blue)!important;border-radius:10px;padding:10px 15px;min-height:40px;text-decoration:none;font-size:12px;font-weight:820;box-shadow:0 3px 9px rgba(20,88,180,.13)}
.am-modern :is(.green,.link,.btn.primary,.bar button,.am-primary):hover:not(:disabled){background:var(--am-blue-2)!important}
.am-modern :is(.btn,.am-outline){border:1px solid #c8d8e7;background:#fff;color:#225681;border-radius:10px;padding:9px 13px;min-height:40px;text-decoration:none;font-size:12px;font-weight:790}
.am-modern :is(.btn,.am-outline):hover{border-color:#76a7d6;background:#f1f7ff}
.am-modern .am-success-action{background:var(--am-green)!important;border-color:var(--am-green)!important}
.am-modern .am-success-action:hover{background:#0b6648!important}
.am-modern .bar{display:flex;gap:10px;flex-wrap:wrap;margin:17px 0;padding:16px;border:1px solid var(--am-border);background:#fff;border-radius:13px}
.am-modern .bar input{flex:1;min-width:230px;max-width:none;width:auto}
.am-modern .cards form{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.am-modern .cards form input{min-width:200px;flex:1}
.am-modern .tablewrap,.am-modern .line-scroll{border:1px solid #e1e9f2;border-radius:12px;overflow:auto;background:#fff}
.am-modern table{border-collapse:separate;border-spacing:0;width:100%;font-size:12.5px}
.am-modern th,.am-modern .row.head{position:sticky;top:0;background:#ecf2fa!important;color:#385878!important;letter-spacing:.055em;font-weight:850;font-size:10px;text-transform:uppercase;border-bottom:1px solid #d6e2ee}
.am-modern :is(td,th){padding:12px 10px;vertical-align:middle;border-bottom:1px solid #e9eef5}
.am-modern tr:hover td{background:#f6faff}
.am-modern .row{padding:13px!important;border-bottom:1px solid #e7edf4;align-items:center}
.am-modern .row:not(.head):hover{background:#f6faff}
.am-modern .row>div{min-width:0}
.am-modern .row strong,.am-modern td b{color:#164879}
.am-modern .pill,.am-modern .am-chip{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#e7f0ff;color:#1b5eab;padding:4px 10px;font-size:10px;font-weight:840;letter-spacing:.02em;white-space:nowrap}
.am-modern .am-chip--ok{background:#dcf4e9;color:#087049}
.am-modern .am-chip--warn{background:#fff1d7;color:#89570f}
.am-modern .am-chip--hold{background:#ffe7e8;color:#a83346}
.am-modern .note,.am-modern .warning{background:#fff7e7!important;color:#79551a!important;border:1px solid #efd9a7!important;border-left:4px solid #dba038!important;border-radius:11px!important;padding:13px 15px!important;font-size:12px;line-height:1.55}
.am-modern .note.danger,.am-modern .error,.am-modern [role=alert]{background:#fff1f1!important;color:#9a2d45!important;border:1px solid #efc6cb!important;border-left:4px solid #d04f61!important;border-radius:11px;padding:14px}
.am-modern .note.success,.am-modern .alert.success{background:#eaf8f1!important;color:#0d6b49!important;border:1px solid #c1ead7!important;border-left:4px solid #0f9065!important}
.am-modern .grid{gap:14px}
.am-modern .title{padding:16px 17px!important;background:linear-gradient(110deg,#eef5ff,#fff)!important}
.am-modern .title strong{font-size:16px!important;color:#133e71}
.am-modern .meta{padding:16px!important}
.am-modern .meta>div{padding-bottom:8px;border-bottom:1px solid #edf1f6}
.am-modern .buttons{display:flex;gap:8px;padding:0 16px 17px}
.am-modern .buttons a{flex:1;display:grid;place-items:center;font-size:12px;border-radius:9px;padding:10px!important;text-decoration:none;background:#1957aa;color:#fff;font-weight:800}
.am-modern .buttons a:nth-child(2){background:#e6f5ee;color:#08754e}
.am-modern .pagination{padding:15px 0}
.am-modern .am-ledger-note{font-size:11px;color:var(--am-muted);padding-top:9px;border-top:1px solid #e7edf4}
.am-modern .editline{border-radius:10px;background:#fbfdff}
.am-modern .editline textarea{width:100%;min-height:55px}
.am-modern .editline :is(input,textarea){width:100%;max-width:100%}
.am-modern .editline [data-final]{color:var(--am-green)!important;font-size:15px}
.am-modern .am-field-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:15px}
.am-modern .am-field-grid>label{display:block}
.am-modern .am-toolbar{display:flex;gap:9px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.am-modern .am-toolbar h2{margin:0;color:#173d63;font-size:17px}
.am-modern .am-payment-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:15px 0 20px}
.am-modern .am-fact{min-width:0;border:1px solid #dfe8f1;border-radius:12px;background:#f8fbff;padding:13px}
.am-modern .am-fact small{display:block;color:var(--am-muted);font-size:11px}
.am-modern .am-fact strong{display:block;margin-top:5px;overflow-wrap:anywhere;font-size:14px;color:#174772}
.am-modern .am-fact .am-amount{font-size:20px}
.am-modern .am-footnote{font-size:11px;color:#72849b;margin-top:18px}
@media(max-width:1000px){
 .am-modern .am-suite-inner{align-items:flex-start;flex-wrap:wrap;gap:10px}
 .am-modern .am-suite-nav{width:100%;overflow-x:auto;flex-wrap:nowrap;padding-bottom:4px}
 .am-modern .am-suite-nav a{white-space:nowrap}
 .am-modern .am-suite-tag{display:none}
 .am-modern .am-field-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
 .am-modern .am-payment-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:700px){
 .am-modern .wrap,.am-modern .am-content{padding:13px 12px 35px!important}
 .am-modern .top,.am-modern .head,.am-modern .page-head{padding:21px 16px!important;border-radius:14px}
 .am-modern .top h1,.am-modern .head h1,.am-modern .page-head h1{font-size:21px}
 .am-modern .am-suite-inner{padding:10px 12px}
 .am-modern .am-logo{height:36px;width:36px}
 .am-modern .grid:not(.am-payment-facts),.am-modern .am-field-grid{grid-template-columns:1fr!important}
 .am-modern .panel,.am-modern .cards{padding:12px!important}
 .am-modern .summary:has(.stat){grid-template-columns:repeat(2,minmax(0,1fr))!important}
 .am-modern .row:not(.head){grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:7px;border:1px solid #e4ebf4;margin:9px 0;border-radius:11px;background:#fff}
 .am-modern .row.head{display:none!important}
 .am-modern .row>div{font-size:12px}
 .am-modern .row>div:last-child{grid-column:span 2}
 .am-modern .row .link{width:100%}
 .am-modern .bar input{min-width:100%}
 .am-modern .tablewrap table{min-width:0!important;display:block}
 .am-modern .tablewrap thead{display:none}
 .am-modern .tablewrap tbody{display:block}
 .am-modern .tablewrap tr{display:block;border:1px solid #dfe8f1;margin:10px 0;border-radius:11px;overflow:hidden}
 .am-modern .tablewrap tr td{display:block;width:100%!important;border-bottom:0;padding:11px!important}
 .am-modern .editline{grid-template-columns:repeat(2,minmax(0,1fr))!important;padding:10px!important}
 .am-modern .editline>div:last-child{grid-column:1/-1}
 .am-modern .editline>div:last-child .green{width:100%}
 .am-modern .am-payment-facts{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:420px){
 .am-modern .am-payment-facts{grid-template-columns:1fr 1fr}
 .am-modern .am-fact{padding:10px}
 .am-modern .am-fact strong{font-size:12px}
 .am-modern .summary:has(.stat){grid-template-columns:1fr 1fr!important}
 .am-modern .stat{padding:13px!important}
 .am-modern .stat strong{font-size:16px}
}
@media(prefers-reduced-motion:reduce){.am-modern *{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
@media print{.am-modern{background:#fff}.am-modern .am-suite,.am-modern .am-subnav{display:none!important}.am-modern .panel,.am-modern .card{box-shadow:none}}
</style>