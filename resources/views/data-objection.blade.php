<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Your data on Subterra</title>
    <style>
        :root {
            --ground: #f2f4f4; --surface: #fff; --ink: #17211f; --muted: #5d6b68;
            --line: #d8dfdd; --accent: #0f5e6b; --danger: #93291f; --ok: #2a6340;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --ground: #0f1514; --surface: #18201f; --ink: #e8efec; --muted: #93a19d;
                --line: #2b3634; --accent: #5cb8c4; --danger: #e98a7d; --ok: #7cc295;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--ground); color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6; padding-block: 3rem; padding-inline: 16px;
        }
        .card {
            max-width: 34rem; margin-inline: auto; background: var(--surface);
            border: 1px solid var(--line); border-radius: 10px; padding: 2rem;
        }
        h1 { font-size: 1.5rem; line-height: 1.25; margin: 0 0 1rem; }
        p { margin: 0 0 1rem; }
        .muted { color: var(--muted); font-size: 0.9rem; }
        dl { margin: 0 0 1.5rem; display: grid; grid-template-columns: auto 1fr; gap: 0.4rem 1rem; font-size: 0.92rem; }
        dt { color: var(--muted); }
        dd { margin: 0; }
        label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
        textarea {
            width: 100%; min-height: 6rem; padding: 0.6rem; border-radius: 6px;
            border: 1px solid var(--line); background: var(--ground); color: var(--ink);
            font: inherit; font-size: 0.95rem; resize: vertical;
        }
        .check { display: flex; gap: 0.6rem; align-items: flex-start; margin: 1rem 0 1.5rem; }
        .check label { font-weight: 400; font-size: 0.95rem; margin: 0; }
        button {
            background: var(--danger); color: #fff; border: 0; border-radius: 6px;
            padding: 0.7rem 1.4rem; font: inherit; font-weight: 600; cursor: pointer;
        }
        button:hover { filter: brightness(1.1); }
        button:focus-visible, textarea:focus-visible, input:focus-visible {
            outline: 2px solid var(--accent); outline-offset: 2px;
        }
        .done {
            border-left: 4px solid var(--ok); background: var(--ground);
            padding: 1rem 1.25rem; border-radius: 0 6px 6px 0; margin-bottom: 1.5rem;
        }
        .done strong { color: var(--ok); }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="card">
        @if (session('objection_recorded') || $alreadyRaised)
            <div class="done">
                <p><strong>Your request has been recorded.</strong></p>
                <p class="muted" style="margin:0;">
                    Your record is already hidden from other members. One of our moderators
                    will remove it completely, and you won't be contacted again.
                </p>
            </div>
            <h1>Nothing else to do</h1>
            <p class="muted">
                If you change your mind, or you have questions about what we held,
                reply to the email that brought you here.
            </p>
        @else
            <h1>Remove my record from Subterra</h1>
            <p>
                Someone added you as a caving contact. You never signed up, and you can
                have the record removed — you don't need an account and you don't need
                to give a reason.
            </p>

            <dl>
                <dt>Name</dt><dd>{{ $user->name ?? 'Not provided' }}</dd>
                <dt>Email</dt><dd>{{ $user->email }}</dd>
                <dt>Added</dt><dd>{{ $user->created_at?->format('j F Y') }}</dd>
            </dl>

            <form method="POST" action="{{ url()->full() }}">
                @csrf

                <label for="reason">Anything you'd like us to know? (optional)</label>
                <textarea id="reason" name="reason" placeholder="You don't have to write anything here."></textarea>

                <div class="check">
                    <input type="checkbox" id="is_minor" name="is_minor" value="1">
                    <label for="is_minor">
                        This record is for someone under 18. We prioritise these and apply
                        extra protections straight away.
                    </label>
                </div>

                <button type="submit">Remove my record</button>
            </form>
        @endif
    </div>
</body>
</html>
