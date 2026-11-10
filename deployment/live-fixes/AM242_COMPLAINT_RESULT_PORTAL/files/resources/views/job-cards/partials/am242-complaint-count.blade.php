{{-- AM242_RESULT_PORTAL — read-only count of complaints linked to original completed ROT. --}}
@php
    $am242LinkedCount = \Illuminate\Support\Facades\DB::table('am_rot_work_complaints')
        ->where('job_card_id', $job->id)
        ->where('rot_session_id', (string) $rot->id)
        ->count();
@endphp
@if($am242LinkedCount > 0)
<a class="btn" style="background:#fff0dc;border-color:#ddb378;color:#7a481e"
   href="{{ route('erp.rot-work-complaints.register',['job_card_id'=>$job->id]) }}">
    Complaint ({{ $am242LinkedCount }}) · View Result
</a>
@endif
