<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Approve eDonate Sign-in</title>
    <style>
        :root {
            color-scheme: light;
            --edonate-red: #b30a12;
            --edonate-red-dark: #7f050b;
            --edonate-ink: #1d2633;
            --edonate-muted: #647080;
            --edonate-line: #e6e9ee;
            --edonate-surface: #f7f8fa;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(145deg, #fff5f5 0%, #f2f5f8 58%, #e9edf2 100%);
            color: var(--edonate-ink);
        }

        .approval-shell {
            width: min(100%, 34rem);
            min-height: 100vh;
            display: grid;
            place-items: center;
            margin: 0 auto;
            padding: 1rem;
        }

        .approval-card {
            width: 100%;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgba(127, 5, 11, .12);
            border-radius: 1.35rem;
            box-shadow: 0 1.5rem 3rem rgba(31, 40, 51, .16);
        }

        .approval-header {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: 1.25rem 1.35rem;
            color: #fff;
            background: linear-gradient(135deg, var(--edonate-red-dark), var(--edonate-red));
        }

        .brand-mark {
            width: 2.7rem;
            height: 2.7rem;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            color: var(--edonate-red);
            background: #fff;
            border-radius: 50% 50% 55% 55%;
            font-size: 1.35rem;
            font-weight: 800;
            transform: rotate(45deg);
        }

        .brand-mark span { transform: rotate(-45deg); }

        .brand-name { margin: 0; font-size: 1.15rem; font-weight: 800; letter-spacing: .02em; }
        .brand-subtitle { margin: .15rem 0 0; color: rgba(255,255,255,.8); font-size: .78rem; }

        .approval-body { padding: 1.4rem; }
        .eyebrow { margin: 0 0 .4rem; color: var(--edonate-red); font-size: .72rem; font-weight: 800; letter-spacing: .13em; }
        h1 { margin: 0; font-size: clamp(1.45rem, 6vw, 1.9rem); line-height: 1.15; }
        .intro { margin: .7rem 0 1.1rem; color: var(--edonate-muted); line-height: 1.5; }

        .account-chip {
            display: flex;
            align-items: center;
            gap: .7rem;
            margin-bottom: 1.25rem;
            padding: .75rem .85rem;
            background: var(--edonate-surface);
            border: 1px solid var(--edonate-line);
            border-radius: .8rem;
            font-size: .9rem;
        }

        .account-icon {
            width: 2rem;
            height: 2rem;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--edonate-red);
            border-radius: 50%;
            font-weight: 800;
        }

        .account-chip small { display: block; margin-bottom: .1rem; color: var(--edonate-muted); font-size: .72rem; }
        .account-chip strong { font-size: .92rem; }

        fieldset { margin: 0; padding: 0; border: 0; }
        legend { margin-bottom: .8rem; font-size: .95rem; font-weight: 750; }

        .choice-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .7rem; }

        .choice-button {
            min-height: 4.2rem;
            padding: .65rem;
            color: var(--edonate-red-dark);
            background: #fff;
            border: 2px solid #f0c8cb;
            border-radius: .85rem;
            cursor: pointer;
            font-size: 1.45rem;
            font-weight: 850;
            transition: transform .15s ease, background .15s ease, border-color .15s ease, color .15s ease;
        }

        .choice-button:hover, .choice-button:focus-visible {
            color: #fff;
            background: var(--edonate-red);
            border-color: var(--edonate-red);
            outline: none;
            transform: translateY(-1px);
        }

        .expiry {
            margin: 1rem 0 0;
            color: var(--edonate-muted);
            font-size: .78rem;
            text-align: center;
        }

        .security-note {
            margin: 1.2rem 0 0;
            padding: .8rem .9rem;
            color: #75521d;
            background: #fff8e8;
            border: 1px solid #f4ddb0;
            border-radius: .75rem;
            font-size: .78rem;
            line-height: 1.45;
        }

        .error-message {
            margin: 0 0 1rem;
            padding: .75rem .85rem;
            color: #842029;
            background: #f8d7da;
            border: 1px solid #f1aeb5;
            border-radius: .7rem;
            font-size: .85rem;
        }

        .approval-footer {
            padding: .95rem 1.35rem;
            color: var(--edonate-muted);
            background: var(--edonate-surface);
            border-top: 1px solid var(--edonate-line);
            font-size: .75rem;
            text-align: center;
        }

        @media (max-width: 360px) {
            .approval-shell { padding: .6rem; }
            .approval-body { padding: 1.1rem; }
            .choice-button { min-height: 3.7rem; font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    <main class="approval-shell">
        <section class="approval-card" aria-labelledby="approvalTitle">
            <header class="approval-header">
                <div class="brand-mark" aria-hidden="true"><span>e</span></div>
                <div>
                    <p class="brand-name">eDonate Security</p>
                    <p class="brand-subtitle">City Health Office, Lipa City</p>
                </div>
            </header>

            <div class="approval-body">
                <p class="eyebrow">NUMBER MATCHING</p>
                <h1 id="approvalTitle">Approve admin sign-in</h1>
                <p class="intro">A sign-in was started in an eDonate admin browser. Choose the number shown on that computer to continue.</p>

                <div class="account-chip">
                    <div class="account-icon" aria-hidden="true">{{ Str::upper(Str::substr($adminName, 0, 1)) }}</div>
                    <div>
                        <small>Admin account</small>
                        <strong>{{ $adminName }}</strong>
                    </div>
                </div>

                @if ($errors->any())
                    <div class="error-message" role="alert">{{ $errors->first('number') ?: 'Choose one of the numbers to continue.' }}</div>
                @endif

                <form method="POST" action="{{ $submitUrl }}">
                    @csrf
                    <fieldset>
                        <legend>Which number is on your computer?</legend>
                        <div class="choice-grid">
                            @foreach ($choices as $choice)
                                <button class="choice-button" type="submit" name="number" value="{{ $choice }}" aria-label="Choose number {{ $choice }}">{{ $choice }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                </form>

                <p class="expiry" id="approvalExpiry" data-expires-at="{{ $expiresAt }}">This approval expires in a few minutes.</p>
                <p class="security-note"><strong>Only approve this request</strong> if you started the eDonate admin sign-in. If you did not, close this page and change your password.</p>
            </div>

            <footer class="approval-footer">Your choice is sent securely and does not reveal your password or authenticator secret.</footer>
        </section>
    </main>

    <script>
        (function () {
            var expiry = document.getElementById('approvalExpiry');
            if (!expiry) return;

            var expiresAt = Number(expiry.dataset.expiresAt || 0);
            var buttons = document.querySelectorAll('.choice-button');

            function updateExpiry() {
                var remaining = Math.max(0, expiresAt - Math.floor(Date.now() / 1000));
                if (remaining <= 0) {
                    expiry.textContent = 'This approval request has expired. Start a new admin sign-in.';
                    buttons.forEach(function (button) { button.disabled = true; });
                    return;
                }

                var minutes = Math.floor(remaining / 60);
                var seconds = String(remaining % 60).padStart(2, '0');
                expiry.textContent = 'This approval expires in ' + minutes + ':' + seconds + '.';
            }

            updateExpiry();
            window.setInterval(updateExpiry, 1000);
        })();
    </script>
</body>
</html>
