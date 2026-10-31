
{{-- AM225_STAFF_PORTAL_COMPACT_ALERTS: compact OLD-workshop-inspired layout. --}}
<style>
  .staff-shell{display:flex!important;flex-direction:column!important;
    max-width:1320px!important;padding-top:12px!important}
  .staff-shell>.staff-cards{order:0;grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:9px!important;margin:5px 0 9px!important}
  .staff-shell>.staff-cards>div{padding:11px 14px!important;border-radius:9px!important}
  .staff-shell>.staff-cards strong{font-size:20px!important}
  .staff-shell>.permission-lock{order:-2!important;padding:11px 14px!important}
  .staff-shell>.staff-panel{order:8;margin-bottom:10px!important;padding:12px 15px!important;
    border-radius:10px!important}
  .staff-shell>.am225-attendance{order:1}
  .staff-shell>.am225-nav{order:2}
  .staff-shell>.am225-rot-panel{order:3}
  .staff-shell>.am225-inspections{order:4}
  .staff-shell>.inspection-review-panel{order:5}
  .staff-shell>.am225-notifications{order:50}
  .staff-shell .panel-head{margin-bottom:8px!important}
  .staff-shell .panel-head h2{font-size:15px!important}
  .staff-shell .rot-list,.staff-shell .inspection-review-list{gap:7px!important}
  .staff-shell .rot-row,.staff-shell .inspection-review-row{padding:10px 12px!important;
    border-radius:8px!important}
  .staff-shell .rot-row h3,.staff-shell .inspection-review-row h3{margin:2px 0!important;
    font-size:14px!important}
  .staff-shell .rot-row p,.staff-shell .inspection-review-row p{margin:3px 0!important}
  .staff-shell .staff-primary-link{min-height:31px!important;padding:5px 10px!important;
    border-radius:7px!important;font-size:11px!important}
  .staff-shell>.am225-inspections .inspection-review-list{max-height:410px;overflow:auto;
    overscroll-behavior:contain;padding-right:3px}
  .staff-shell>.inspection-review-panel .inspection-review-list{max-height:360px;overflow:auto;
    overscroll-behavior:contain;padding-right:3px}
  .am225-nav{position:sticky;top:0;z-index:20;display:flex;gap:7px;flex-wrap:wrap;
    padding:8px 9px;background:#edf4fb;border:1px solid #cad9e9;border-radius:9px;
    box-shadow:0 3px 12px rgba(11,45,83,.08)}
  .am225-nav a{display:inline-flex;align-items:center;gap:4px;
    border:1px solid #b1cadf;border-radius:7px;background:#fff;color:#174c79;
    padding:6px 10px;font-size:11px;font-weight:800;text-decoration:none;cursor:pointer}
  .am225-nav a:hover,.am225-nav a:focus-visible{background:#dbeafb;outline:1px solid #7ca9d3}
  [id^="am225-"],.staff-shell .rot-row[id^="rot-"]{scroll-margin-top:70px}
  .am225-alert-title{display:flex;align-items:center;gap:10px;cursor:pointer;
    padding:3px 2px;list-style:none;color:#153b5d;font-weight:800;font-size:14px}
  .am225-alert-title::-webkit-details-marker,
  .am225-alert-group>summary::-webkit-details-marker{display:none}
  .am225-alert-title .am225-alert-total{background:#fff1e6;color:#a75016;
    border-radius:15px;padding:3px 9px;font-size:11px}
  .am225-alert-expand{margin-left:auto;color:#607991;font-size:11px;font-weight:650}
  .am225-alert-note{margin:9px 0;font-size:11px;color:#667d91}
  .am225-alert-groups{display:grid;gap:7px}
  .am225-alert-group{border:1px solid #d5e1eb;background:#fff;
    border-radius:8px;overflow:hidden}
  .am225-alert-group>summary{list-style:none;display:flex;align-items:center;gap:10px;
    padding:10px 12px;cursor:pointer;color:#234a68;font-size:12px;background:#f4f8fc}
  .am225-alert-count{padding:2px 8px;border-radius:12px;background:#e0eaf4;font-weight:800}
  .am225-toggle-label{margin-left:auto;color:#496f8d;font-size:10px}
  .am225-alert-items{padding:7px;display:grid;gap:6px;max-height:320px;overflow-y:auto}
  .am225-alert-record{display:flex;align-items:center;gap:12px;justify-content:space-between;
    padding:8px 10px;border:1px solid #e2e9f0;border-left:3px solid #e57a61;
    border-radius:7px;background:#fff}
  .am225-alert-record.am225-escalated{border-left-color:#b42c21;background:#fff8f7}
  .am225-alert-record b{font-size:11px;color:#28465f}
  .am225-alert-record p{margin:2px 0 0;font-size:11px;color:#5e7287}
  .am225-alert-actions{flex-shrink:0;font-size:11px;max-width:220px;text-align:right}
  .am225-alert-actions a{color:#0f5798;text-decoration:underline;font-weight:800}
  .am225-no-action{font-size:10px;color:#728396}
  @media(max-width:760px){
    .staff-shell>.staff-cards{grid-template-columns:repeat(2,minmax(0,1fr))!important}
    .am225-nav{top:0;position:relative}
    .am225-alert-record{align-items:flex-start;flex-direction:column}
    .am225-alert-actions{text-align:left;max-width:none}
    .staff-shell>.am225-inspections .inspection-review-list,
    .staff-shell>.inspection-review-panel .inspection-review-list{max-height:310px}
  }
</style>
