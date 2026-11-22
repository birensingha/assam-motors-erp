<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="referrer" content="no-referrer">
    <title>Service Feedback | Assam Motors</title>
    <style>
        body{font-family:system-ui,-apple-system,sans-serif;max-width:580px;margin:36px auto;padding:0 18px;color:#152339;background:#f8fafc}
        main{background:#fff;border:1px solid #d7dfe7;border-radius:14px;padding:26px}
        h1{font-size:1.55rem;margin-top:0}p{line-height:1.5}fieldset{border:0;padding:0;margin:16px 0}
        .ratings{display:flex;gap:14px;flex-wrap:wrap}.ratings label{cursor:pointer}
        textarea{width:100%;box-sizing:border-box;padding:12px;border:1px solid #94a3b8;border-radius:8px;font:inherit}
        button{background:#0b4f87;color:white;border:0;border-radius:8px;padding:12px 20px;font:inherit;cursor:pointer}
        .error{color:#a31818}small{color:#526179}
    </style>
</head>
<body>
<main>
    <h1>ASSAM MOTORS</h1>
    @if($submitted)
        <h2>Thank you for your feedback</h2>
        <p>Your response has been recorded successfully.</p>
    @else
        <h2>How was your service?</h2>
        <p>Please share your rating and comments. This form does not ask for payment or financial information.</p>
        @if($errors->any())
            <p class="error" role="alert">Please enter a rating from 1 to 5 and a comment of up to 3,000 characters.</p>
        @endif
        <form method="post" action="{{ route('am.feedback.public.submit', ['token' => $token]) }}">
            @csrf
            <fieldset>
                <legend>Service rating (required)</legend>
                <div class="ratings">
                    @for($rating = 1; $rating <= 5; $rating++)
                        <label><input type="radio" name="rating" value="{{ $rating }}"
                            {{ old('rating') == $rating ? 'checked' : '' }} required> {{ $rating }} ★</label>
                    @endfor
                </div>
            </fieldset>
            <label for="comment">Your feedback (required)</label>
            <textarea name="comment" id="comment" rows="5" maxlength="3000"
                required>{{ old('comment') }}</textarea>
            <p><small>Only feedback about your vehicle service is requested here.</small></p>
            <button type="submit">Submit Feedback</button>
        </form>
    @endif
</main>
</body>
</html>
