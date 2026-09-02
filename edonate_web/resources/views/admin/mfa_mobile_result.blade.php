<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $approved ? 'Sign-in Approved' : 'Number Did Not Match' }} | eDonate</title>
    <style>
        :root { font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1d2633; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 1rem; background: linear-gradient(145deg, #fff5f5, #edf1f5); }
        .result-card { width: min(100%, 32rem); padding: 2rem 1.4rem; background: #fff; border: 1px solid {{ $approved ? '#b7e4c7' : '#f1aeb5' }}; border-radius: 1.35rem; box-shadow: 0 1.5rem 3rem rgba(31, 40, 51, .14); text-align: center; }
        .result-icon { width: 4.2rem; height: 4.2rem; display: grid; place-items: center; margin: 0 auto 1rem; color: #fff; background: {{ $approved ? '#198754' : '#b30a12' }}; border-radius: 50%; font-size: 2rem; font-weight: 800; }
        h1 { margin: 0; font-size: 1.55rem; }
        p { margin: .8rem 0 1.35rem; color: #647080; line-height: 1.5; }
        .button { display: inline-block; padding: .75rem 1rem; color: #fff; background: #b30a12; border-radius: .7rem; text-decoration: none; font-weight: 750; }
        .button.secondary { color: #394352; background: #eef1f4; border: 0; }
        .actions { display: flex; justify-content: center; flex-wrap: wrap; gap: .6rem; }
        .hint { margin-top: 1.4rem; font-size: .78rem; }
    </style>
</head>
<body>
    <main class="result-card" aria-labelledby="resultTitle">
        <div class="result-icon" aria-hidden="true">{{ $approved ? '✓' : '!' }}</div>
        <h1 id="resultTitle">{{ $approved ? 'Sign-in approved' : 'Number did not match' }}</h1>
        <p>{{ $message }}</p>

        <div class="actions">
            @if (! $approved && ! empty($approvalUrl))
                <a class="button" href="{{ $approvalUrl }}">Try again</a>
            @endif
            <button class="button secondary" type="button" onclick="window.close();">Close</button>
        </div>

        <p class="hint">Return to the eDonate admin browser to finish the sign-in.</p>
    </main>
</body>
</html>
