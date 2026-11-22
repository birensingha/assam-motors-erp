{{-- AM245 drop-in partial. Include only from the real ERP's verified Job Card page.
     Required variable: $jobCardId, resolved from the server's scoped Job Card model.
     The operator must confirm consent; no browser-supplied customer or phone fields. --}}
@can('manage-customer-engagement')
    <form method="post" action="{{ route('am.feedback.invitations.open') }}"
          style="display:inline-flex;align-items:center;gap:.55rem;flex-wrap:wrap">
        @csrf
        <input type="hidden" name="job_card_id" value="{{ (int)$jobCardId }}">
        <label style="display:inline-flex;align-items:center;gap:.3rem">
            <input type="checkbox" name="consent" value="1" required>
            Customer permits WhatsApp contact
        </label>
        <button type="submit" class="btn btn-success">
            Send Feedback on WhatsApp
        </button>
    </form>
    <small>Opens WhatsApp for manual send; delivery is not confirmed.</small>
@endcan
