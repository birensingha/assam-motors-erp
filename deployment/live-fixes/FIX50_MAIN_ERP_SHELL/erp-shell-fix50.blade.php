{{-- FIX50_ERP_SHELL_V1 — shared ERP layout visual alignment and shortcut styles. --}}
<style>
body.erp-app{
  --am50-side:266px;--am50-ink:#153351;--am50-line:#dce6f0;
  --am50-bg:#f3f6fb;--am50-blue:#225b9d;
  margin:0!important;padding:0!important;display:block!important;
  background:var(--am50-bg)!important;overflow-x:clip
}
body.erp-app .erp-sidebar{
  box-sizing:border-box!important;position:fixed!important;inset:0 auto 0 0!important;
  width:var(--am50-side)!important;max-width:var(--am50-side)!important;
  height:100vh!important;height:100dvh!important;
  margin:0!important;z-index:110!important;
  display:flex!important;flex-direction:column!important;
  border-right:1px solid rgba(179,203,226,.17)!important;
  box-shadow:3px 0 16px rgba(18,49,86,.12);
  overflow-y:auto!important;overscroll-behavior:contain;
  scrollbar-width:thin;scrollbar-color:#446b93 transparent
}
body.erp-app .sidebar-brand{box-sizing:border-box;min-height:78px;flex-shrink:0;display:flex;align-items:center;gap:12px;padding:17px!important;border-bottom:1px solid rgba(180,211,242,.14)}
body.erp-app .sidebar-brand img{max-width:49px;max-height:49px;object-fit:contain}
body.erp-app .sidebar-brand strong{font-size:15px;font-weight:820;letter-spacing:.008em}
body.erp-app .sidebar-brand small{display:block;font-size:10px;letter-spacing:.065em;color:#aac9e8}
body.erp-app .sidebar-nav.am-android-nav{display:flex!important;flex-direction:column!important;gap:0!important;flex:1 0 auto!important;min-height:min-content;padding:8px 6px 18px!important}
body.erp-app .sidebar-nav.am-android-nav .nav-group-label{margin:16px 9px 7px!important;padding:9px 11px!important;font-size:10px!important;letter-spacing:.1em!important}
body.erp-app .sidebar-nav.am-android-nav a{
  min-height:41px!important;margin:2px 5px!important;padding:7px 10px!important;
  line-height:1.24!important;font-size:12px!important;flex-shrink:0
}
body.erp-app .sidebar-nav.am-android-nav a > span:first-child{width:28px;height:28px;flex-basis:28px}
body.erp-app .sidebar-nav.am-android-nav .am50-section{
  margin:17px 9px 8px!important;padding:10px 10px 8px!important;
  color:#96d4f2!important;border-top:1px solid rgba(173,208,232,.22);
  border-bottom:1px solid rgba(173,208,232,.14)
}
body.erp-app .sidebar-nav.am-android-nav a.am50-finance{
  border-radius:11px!important;background:rgba(93,146,198,.08)!important;
  border-left:3px solid rgba(80,163,220,.65)!important
}
body.erp-app .sidebar-nav.am-android-nav a.am50-finance:hover{
  background:rgba(60,119,175,.29)!important
}
body.erp-app .sidebar-nav.am-android-nav a.am50-finance.active{
  background:linear-gradient(90deg,rgba(28,119,178,.4),rgba(28,65,117,.2))!important;
  border-left-color:#64bdf9!important
}
body.erp-app .sidebar-footer{margin-top:auto;padding:11px!important;border-top:1px solid rgba(185,209,231,.13)}
body.erp-app .erp-topbar{
  box-sizing:border-box!important;position:sticky!important;top:0!important;left:auto!important;
  width:calc(100% - var(--am50-side))!important;max-width:none!important;
  margin:0 0 0 var(--am50-side)!important;
  min-height:73px!important;height:auto!important;
  padding:10px clamp(14px,2.1vw,28px)!important;
  display:flex!important;align-items:center!important;justify-content:space-between!important;
  gap:clamp(8px,1.15vw,17px)!important;flex-wrap:nowrap!important;
  z-index:99!important;border-bottom:1px solid var(--am50-line)!important;
  background:rgba(255,255,255,.98)!important;
  box-shadow:0 3px 15px rgba(19,47,80,.065)!important
}
body.erp-app .erp-topbar .topbar-title{
  min-width:0;max-width:185px;flex:0 1 185px;
  font-size:clamp(13px,1.3vw,18px)!important;font-weight:820!important;
  color:var(--am50-ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis
}
body.erp-app .am50-top-shortcuts{
  display:flex;align-items:center;gap:5px;flex:1 1 auto;min-width:0;
  overflow:auto hidden;scrollbar-width:none;white-space:nowrap;padding:4px 1px
}
body.erp-app .am50-top-shortcuts::-webkit-scrollbar{display:none}
body.erp-app .am50-top-shortcuts a{
  display:inline-flex;align-items:center;gap:6px;flex-shrink:0;
  font-size:11px;font-weight:790;text-decoration:none;line-height:1.2;
  color:#285679;background:#f0f5fc;border:1px solid #e0e8f3;
  border-radius:9px;padding:10px 11px;transition:background .15s ease,color .15s ease
}
body.erp-app .am50-top-shortcuts a:hover{background:#dfeeff;color:#164e91}
body.erp-app .am50-top-shortcuts a.active{color:white;background:#215b9d;border-color:#215b9d}
body.erp-app .erp-topbar .topbar-live{
  flex-shrink:0;white-space:nowrap;margin:0!important;
  padding:6px 9px;border-radius:999px;background:#e7f7ee;color:#087548!important
}
body.erp-app .erp-topbar .topbar-date{flex-shrink:0;margin:0!important;white-space:nowrap;font-size:11px;color:#64758c}
body.erp-app .erp-topbar .userbox{flex-shrink:0;margin:0!important;display:flex;align-items:center;gap:8px;max-width:200px;min-width:0}
body.erp-app .erp-topbar .userbox span{min-width:0;max-width:112px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
body.erp-app .erp-topbar .userbox form{margin:0}
body.erp-app .erp-topbar .linkbtn{border-radius:9px!important;border:1px solid #d2deeb!important;padding:8px 10px!important;background:#f7faff}
body.erp-app .page-shell{
  box-sizing:border-box!important;width:calc(100% - var(--am50-side))!important;
  max-width:none!important;margin:0 0 0 var(--am50-side)!important;
  padding:24px clamp(15px,2.1vw,30px) 50px!important;
  min-height:calc(100dvh - 73px)!important;min-width:0!important;
  background:var(--am50-bg)!important
}
body.erp-app .page-shell>*{min-width:0}
body.erp-app .page-shell .am50-integrated-page{
  background:transparent!important;min-height:0!important;
  color:#173453!important;max-width:100%!important;
}
body.erp-app .page-shell .am50-integrated-page .wrap{
  width:100%!important;max-width:none!important;margin:0!important;
  padding:0!important
}
body.erp-app .page-shell .am50-integrated-page .top,
body.erp-app .page-shell .am50-integrated-page .head{margin-top:0}
body.erp-app .page-shell .am50-integrated-page :is(.cards,.panel,.card){max-width:100%}
body.erp-app .page-shell .am50-integrated-page :is(.tablewrap,.line-scroll){max-width:100%;overflow-x:auto}

body.erp-app .page-shell>.page-head{
  display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;
  gap:12px;padding:0 0 14px;margin:0 0 20px;
  border-bottom:1px solid #dce6f0
}
body.erp-app .page-shell>.page-head h1{line-height:1.18;font-size:clamp(20px,2vw,29px);letter-spacing:-.035em}
body.erp-app .page-shell .card{min-width:0}
body.erp-app .page-shell :is(.table-wrap,.table-responsive,.line-scroll){max-width:100%;overflow-x:auto}
body.erp-app .am50-overlay{display:none}
body.erp-app .erp-topbar .sidebar-toggle{flex-shrink:0;border-radius:9px!important;padding:8px 11px!important;min-width:39px}
body.erp-app :is(.erp-topbar,.erp-sidebar,.page-shell) *{box-sizing:border-box}
@media(max-width:1260px){
 body.erp-app .erp-topbar .topbar-date{display:none!important}
 body.erp-app .erp-topbar .topbar-title{flex-basis:158px;max-width:158px}
}
@media(max-width:1020px){
 body.erp-app{--am50-side:0px}
 body.erp-app .erp-sidebar{
   width:270px!important;max-width:270px!important;
   transform:translateX(-105%)!important;
   transition:transform .2s ease!important;z-index:180!important
 }
 body.erp-app.sidebar-open .erp-sidebar{transform:translateX(0)!important}
 body.erp-app .erp-topbar,.erp-app .page-shell{width:100%!important;margin-left:0!important}
 body.erp-app .erp-topbar .sidebar-toggle{display:inline-flex!important}
 body.erp-app .erp-topbar .topbar-date{display:none!important}
 body.erp-app .am50-overlay{
   display:block;position:fixed;inset:0;z-index:175;background:rgba(8,22,42,.42);
   opacity:0;pointer-events:none;border:0;cursor:pointer;transition:opacity .2s ease
 }
 body.erp-app.sidebar-open .am50-overlay{opacity:1;pointer-events:auto}
}
@media(max-width:680px){
 body.erp-app .erp-topbar{
   min-height:105px!important;padding:9px 12px!important;
   gap:8px!important;flex-wrap:wrap!important;align-content:center
 }
 body.erp-app .erp-topbar .topbar-title{flex:1 1 auto;max-width:none;font-size:14px!important}
 body.erp-app .erp-topbar .am50-top-shortcuts{order:10;flex:1 0 100%;max-width:100%}
 body.erp-app .erp-topbar .am50-top-shortcuts a{min-height:36px}
 body.erp-app .erp-topbar .userbox{max-width:155px}
 body.erp-app .erp-topbar .userbox>span{display:none}
 body.erp-app .erp-topbar .topbar-live{font-size:10px}
 body.erp-app .page-shell{padding:16px 12px 36px!important}
 body.erp-app .page-shell>.page-head{align-items:flex-start}
}
@media(prefers-reduced-motion:reduce){
 body.erp-app .erp-sidebar,body.erp-app .am50-overlay,body.erp-app .am50-top-shortcuts a{transition:none!important}
}
@media print{
 body.erp-app .erp-sidebar,body.erp-app .erp-topbar,body.erp-app .am50-overlay{display:none!important}
 body.erp-app .page-shell{margin:0!important;padding:0!important;width:100%!important;background:#fff!important}
}
</style>