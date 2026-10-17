{{-- V639 server-truth stopwatch: no change to ROT session or billing. --}}
@php($rotClock = \App\Support\RotLiveClock::snapshot($session))
<div class="am-rot-live" data-rot-session="{{ $rotClock['rot_id'] }}"
     data-am-elapsed="{{ $rotClock['elapsed_seconds'] }}"
     data-am-standard="{{ $rotClock['standard_seconds'] }}"
     data-am-running="{{ $rotClock['running'] ? '1':'0' }}"
     data-am-created="{{ $rotClock['server_epoch_ms'] }}" role="group"
     aria-label="Live ROT productive and remaining time">
  <div class="am-rot-live-head">
    <span>● {{ $rotClock['running'] ? 'LIVE ROT TIME' : 'ROT TIME' }}</span>
    <strong data-am-clock>{{ gmdate('H:i:s',$rotClock['elapsed_seconds']) }}</strong>
  </div>
  <div class="am-rot-live-track"><i data-am-progress style="width:{{ min(100,$rotClock['progress_percent']) }}%"></i></div>
  <div class="am-rot-live-metrics">
    <span data-am-percent>{{ $rotClock['progress_percent'] }}% used</span>
    <strong data-am-balance>
      @if($rotClock['overtime_seconds']) OVER +{{ gmdate('H:i:s',$rotClock['overtime_seconds']) }}
      @else REMAINING {{ gmdate('H:i:s',$rotClock['remaining_seconds']) }} @endif
    </strong>
  </div>
  <div class="am-rot-live-details">
    <span>Started (IST): <b>{{ $rotClock['started_ist'] }}</b></span>
    <span>Expected End (IST): <b>{{ $rotClock['expected_end_ist'] }}</b></span>
    <span data-am-state>
      @if($rotClock['time_warning'])⚠ RUNNING start timestamp missing
      @elseif($rotClock['running'])● WORK IN PROGRESS
      @elseif($rotClock['status']==='paused')Ⅱ PAUSED
      @else {{ strtoupper($rotClock['status']) }} @endif
    </span>
  </div>
</div>
@once
<style>
.am-rot-live{background:#f0f7fc;border:1px solid #caddf1;border-radius:11px;padding:12px;margin:10px 0;color:#153e63}
.am-rot-live-head,.am-rot-live-metrics{display:flex;justify-content:space-between;gap:10px;align-items:center;font-size:12px}
.am-rot-live-head strong{font-size:20px;font-variant-numeric:tabular-nums}.am-rot-live-track{height:8px;background:#d9e5f0;border-radius:12px;overflow:hidden;margin:8px 0}
.am-rot-live-track i{display:block;height:100%;background:#0b7ee8;max-width:100%}
.am-rot-live-metrics strong{font-variant-numeric:tabular-nums}.am-rot-live-details{display:flex;gap:10px;flex-wrap:wrap;font-size:11px;margin-top:8px}
.am-rot-live-details b{font-variant-numeric:tabular-nums}.am-rot-live.is-over{background:#fff0ef;color:#99291f;border-color:#eeada5}
.am-rot-live.is-over .am-rot-live-track i{background:#d63232}
.am-rot-live.is-near{background:#fff9e7;color:#916100;border-color:#ecd28b}.am-rot-live.is-near .am-rot-live-track i{background:#e9a11e}
</style>
<script>
(function(){
  if(window.AMROTDesktopClockV639)return;
  window.AMROTDesktopClockV639=true;
  const format=s=>{s=Math.max(0,Math.floor(s));return [Math.floor(s/3600),Math.floor(s%3600/60),s%60].map(x=>String(x).padStart(2,'0')).join(':')};
  function attach(){
    document.querySelectorAll('.am-rot-live').forEach(el=>{
      if(el.dataset.amBooted==='1')return;
      el.dataset.amBooted='1';
      // Monotonic browser clock only ticks a snapshot. Reopening the page
      // fetches a new server snapshot; it does not reset productive time.
      el._amRotAnchor=performance.now();
    });
  }
  function draw(){
    attach();
    document.querySelectorAll('.am-rot-live').forEach(el=>{
      const base=Number(el.dataset.amElapsed||0),std=Number(el.dataset.amStandard||0);
      const running=el.dataset.amRunning==='1';
      const elapsed=Math.max(0,base+(running?Math.max(0,Math.floor((performance.now()-el._amRotAnchor)/1000)):0));
      const remaining=Math.max(0,std-elapsed),overtime=std>0?Math.max(0,elapsed-std):0;
      const progress=std>0?Math.round(elapsed/std*100):0,near=running && !overtime && remaining<=Math.max(300,std*0.1);
      el.classList.toggle('is-over',overtime>0);el.classList.toggle('is-near',near);
      const set=(selector,value)=>{const node=el.querySelector(selector);if(node)node.textContent=value;};
      set('[data-am-clock]',format(elapsed));
      set('[data-am-percent]',progress+'% used');
      set('[data-am-balance]',(overtime?'OVER +':'REMAINING ')+format(overtime||remaining));
      if(running)set('[data-am-state]',overtime?'⚠ OVERTIME':(near?'⚠ NEAR STANDARD LIMIT':'● WORK IN PROGRESS'));
      const track=el.querySelector('[data-am-progress]');if(track)track.style.width=Math.max(0,Math.min(100,progress))+'%';
    });
  }
  draw();setInterval(draw,1000);document.addEventListener('visibilitychange',draw);
})();
</script>
@endonce
