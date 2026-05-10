# AI Assistant Architecture

A walkthrough of how the Pip assistant works end-to-end: how requests are
authenticated, how a single chat turn flows through the system, and how the
results stream back to the user.

## The pieces

```mermaid
flowchart LR
  subgraph Browser
    U[User]
    UI[Vue.js SPA<br/>Pip chat page]
  end

  subgraph LaravelAPI[Laravel API]
    R[/POST /api/assistant/chat/]
    C[AssistantController]
    S[AssistantService<br/>tool-call loop]
    T[Tool classes<br/>SearchCaves, GetCaveDetails, etc.]
    DB[(Postgres)]
  end

  subgraph External
    OR[OpenRouter<br/>LLM gateway]
    LLM[Underlying model<br/>Claude / Kimi / Llama]
  end

  U -->|types message| UI
  UI -->|fetch + Sanctum cookie| R
  R --> C
  C --> S
  S -->|chat completion w/ tools| OR
  OR -->|routed to provider| LLM
  S -->|tool calls| T
  T -->|queries| DB
  S -->|SSE events| UI
  UI -->|renders bubbles + cards| U
```

The frontend never talks to OpenRouter directly. The OpenRouter API key lives
in the Laravel `.env` and is never exposed to the browser. Every model call
goes through your own Laravel backend, which lets us:

- Authenticate the user (Sanctum) before any model tokens are spent
- Run real tool calls (cave search, weather, etc.) against the database
- Sanitise the model's output before it reaches the UI
- Bill / rate-limit per user
- Swap models or providers without redeploying the frontend

---

## How auth works

The chat endpoint is a normal authenticated Laravel API route, gated by the
same Sanctum SPA session as the rest of the app. The chat is admin-only
during the preview, so an extra middleware layer enforces the
`platform_admin` role.

```mermaid
sequenceDiagram
  autonumber
  participant B as Browser
  participant L as Laravel
  participant DB as Database

  Note over B,L: 1. User has already logged in earlier — they hold a Laravel session cookie

  B->>L: POST /api/assistant/chat<br/>(cookie + CSRF token)
  L->>L: StartSession middleware<br/>reads session from cookie
  L->>L: ApiIsAuthenticated middleware<br/>resolves the User
  L->>L: ApiIsAdmin middleware<br/>checks platform_admin role
  L->>L: throttle:50,1440<br/>(50 req / day per user)
  L->>L: AssistantChatRequest<br/>validates payload shape

  alt user is not authenticated
    L-->>B: 401 Unauthorized
  else user is not platform_admin
    L-->>B: 403 Forbidden
  else daily limit hit
    L-->>B: 429 Too Many Requests
  else payload invalid
    L-->>B: 422 Unprocessable
  else all checks pass
    L->>L: AssistantController::chat
    Note over L: Begin SSE stream...
    L-->>B: 200 OK<br/>Content-Type: text/event-stream
  end
```

A few things worth noting about the auth model:

- **Session cookie auth, not bearer tokens.** Same as the rest of the SPA —
  Laravel Sanctum's "stateful API" mode. The CSRF cookie is fetched once on
  app load and the browser sends it on every mutating request.
- **Admin gate is in middleware, not the controller.** This means a missing
  role rejects the request *before* the controller (and thus the model call)
  is ever reached. No tokens spent on unauthorised requests.
- **Rate limit is per user-id, daily.** Even an admin can't accidentally rack
  up a huge bill from one tab — `throttle:50,1440` is 50 requests in any
  rolling 24-hour window, keyed by user.
- **`session()->save()` is called inside the streaming callback** to release
  Laravel's session lock so other tabs can hit the API while a long stream is
  in flight. Without this, an SSE response in one tab blocks `/api/users/me`
  in another tab.

---

## A single chat turn

Here's what happens when you send one message. The "agentic loop" — the
back-and-forth where the model decides which tools to call — is the most
interesting part.

```mermaid
sequenceDiagram
  autonumber
  participant UI as Vue UI
  participant C as AssistantController
  participant S as AssistantService
  participant OR as OpenRouter
  participant T as Tool
  participant DB as Database

  UI->>C: POST /api/assistant/chat<br/>{messages: [...]}
  C->>S: chat(messages, user, onEvent)
  S->>S: Build system prompt<br/>(user clubs, season, guidelines)
  S->>UI: SSE: thinking
  Note over S,UI: SSE stream stays open<br/>for the whole turn

  loop until model stops calling tools (max 4 iterations)
    S->>OR: POST /chat/completions<br/>{messages, tools, stream: true}
    OR-->>S: streamed response chunks

    alt Model returns text only
      S-->>UI: SSE: content_chunk (live tokens)
      Note over S: Loop ends
    else Model returns tool_calls
      loop for each tool call
        S->>UI: SSE: tool_call running<br/>(name + args)
        S->>T: dispatch(tool, args, user)
        T->>DB: query (scoped to user)
        DB-->>T: rows
        T-->>S: structured result
        S->>UI: SSE: tool_call done
        S->>S: Append tool result<br/>to context
      end
    end
  end

  alt Iteration cap reached without text
    S->>OR: Final call, no tools, low temp<br/>"Stop. Write text only."
    OR-->>S: text answer
    S->>UI: SSE: content_chunk
  end

  S->>S: Filter cave/hut/collection cards<br/>by what the reply mentioned
  S->>UI: SSE: cave_cards / hut_cards /<br/>collection_cards / trip_report_cards
  S->>UI: SSE: suggestions (follow-ups)
  S->>UI: SSE: thinking_elapsed + usage
  C->>UI: SSE: content (final string)
  C->>UI: SSE: done
```

Three subtle bits worth understanding:

1. **The model never sees the database.** Tools are the only bridge. The
   model returns a JSON object saying "call `search_caves` with these args";
   our PHP code runs the actual query and feeds the result back as a "tool"
   message. The model can't run arbitrary SQL.
2. **The loop is bounded.** `max_tool_iterations: 4` in `config/assistant.php`
   caps how many tool ↔ model round-trips can happen per turn. Without this,
   a model could spend tokens forever.
3. **SSE means the UI gets typewriter-style streaming for free.** The
   `content_chunk` events are delivered token-by-token as OpenRouter
   produces them, which is what makes the response feel responsive even
   though the model can take 10–30 seconds to finish.

---

## Tool dispatch & the agentic loop

Zooming into the loop body. This is the core of `AssistantService::chat`.

```mermaid
flowchart TD
  Start([User message arrives]) --> Sys[Build system prompt + cap history to 20 msgs]
  Sys --> Loop{Iteration<br/>< 4?}
  Loop -- yes --> Call[Call OpenRouter<br/>with current messages + tools]
  Call --> Resp{Response<br/>contains tool_calls?}
  Resp -- no --> Done([Return content to user])
  Resp -- yes --> Dedup{Same tool+args<br/>already called this turn?}
  Dedup -- yes --> Cached[Return 'you already asked this'<br/>error to model]
  Dedup -- no --> Run[Run tool, store result fingerprint]
  Run --> Buffer[Buffer cards / reports<br/>for end-of-turn emission]
  Buffer --> Append[Append tool result<br/>to context]
  Cached --> Append
  Append --> Loop

  Loop -- no --> Forced{Last response<br/>still tool_calls?}
  Forced -- yes --> Final[Force-call model with<br/>NO tools, low temp,<br/>'write text now']
  Forced -- no --> Done
  Final --> Sanitise[Strip any leaked<br/>tool-call markup]
  Sanitise --> Done
```

Several defensive measures live in this loop because LLM behaviour is messy:

- **Per-turn dedup**: a `callKey = name + canonicalised(args)` fingerprint
  catches small models that retry the same `search_caves` call with slightly
  different tag casing. Identical calls return a sharp "stop, you already
  asked this" message instead of re-running.
- **Forced final answer**: if the model exhausts its tool budget without
  ever producing text, we make one more call with `tools` omitted and
  `tool_choice: 'none'`. Without this, the user would see an empty response
  or a stale "I was unable…" fallback.
- **Output sanitiser**: certain models (DeepSeek variants especially) leak
  tool-call markup like `<｜｜DSML｜｜tool_calls>...` into their text content.
  `sanitiseAssistantText` anchors at the first such token and drops
  everything after. If the entire reply was markup, the user gets a friendly
  fallback message.

---

## Card emission

Cards (cave / hut / collection / trip-report) are the rich UI elements that
appear under each reply. They aren't sent inline with the text — they ride
on separate SSE events. This separation lets the model write a flowing
prose answer while the UI surfaces structured links and images alongside.

```mermaid
flowchart LR
  subgraph Tools[Tool results during loop]
    SC[search_caves: 8 systems]
    GD[get_cave_details: 1 system]
    GA[get_cave_system_activity: 5 reports]
    FH[find_nearby_huts: 6 huts]
    LC[list_collections: 4 collections]
  end

  subgraph Buffers[Per-turn buffers]
    CCB[caveCardBuffer<br/>by slug]
    TRB[tripReportBuffer<br/>by short_id]
    HCB[hutCardsBuffer]
    COB[collectionCardBuffer<br/>by slug]
  end

  subgraph Filter[After loop completes]
    Reply[Final reply text]
    Filt[filterMentionedSystems<br/>filterMentionedReports]
  end

  subgraph SSE[SSE events]
    EC[cave_cards]
    ET[trip_report_cards]
    EH[hut_cards]
    ECO[collection_cards]
  end

  SC --> CCB
  GD --> CCB
  GA --> TRB
  GD --> TRB
  FH --> HCB
  LC --> COB

  CCB --> Filt
  TRB --> Filt
  COB --> Filt
  Reply --> Filt

  Filt -->|systems mentioned by name/slug| EC
  Filt -->|signals: 'recent', 'condition', 'water'| ET
  HCB --> EH
  Filt --> ECO
```

The mention-filter is the bit that stops the UI being flooded. If the model
called `search_caves` to look up a cave's ID for a follow-up tool call, we
*do not* show those 10 results as cards — only the cave the model actually
named in its prose reply. Hut cards are different: if `find_nearby_huts`
ran at all, the user clearly asked about huts, so all results are surfaced.

---

## OpenRouter & provider routing

OpenRouter is a single OpenAI-compatible HTTP endpoint that proxies to many
underlying model providers. We use it because:

- One API, many models — swap from Claude to Kimi to Llama with an env var
- Per-key spending caps protect against runaway costs
- Provider routing lets us pin to faster or cheaper backends per model

```mermaid
flowchart TD
  Code[AssistantService] -->|POST /chat/completions| OR(OpenRouter)

  subgraph providerOrder[provider.order config]
    P1[Anthropic]
    P2[Cerebras]
    P3[Groq]
    P4[Together]
  end

  OR -.->|tries in order| P1
  OR -.->|fallback| P2
  OR -.->|fallback| P3
  OR -.->|fallback| P4

  P1 --> Claude[Claude Haiku/Sonnet]
  P2 --> Llama[Llama 3.3 70B<br/>fast hardware]
  P3 --> Kimi[Kimi K2.6]
  P4 --> Other[Other open-weights]
```

The `provider` block in `config/assistant.php` builds an `order: [...]`
array that's sent on every request. Setting
`ASSISTANT_PROVIDER_ORDER=Anthropic` in your `.env` pins requests to
Anthropic direct (lower latency for Claude). Leaving it empty lets
OpenRouter auto-pick the cheapest available provider for the model.

---

## Configuration knobs

Most behaviour can be tuned without touching code. The interesting ones in
`config/assistant.php`:

| Key                                  | What it does                                                          |
| ------------------------------------ | --------------------------------------------------------------------- |
| `openrouter.api_key`                 | Your OpenRouter key (from `OPENROUTER_API_KEY` in `.env`)             |
| `openrouter.model`                   | Which model to use, e.g. `anthropic/claude-haiku-4-5`                 |
| `openrouter.max_tokens`              | Cap on response length                                                |
| `openrouter.temperature`             | 0.0 = deterministic, 1.0 = creative                                   |
| `provider.order`                     | Comma-list of preferred providers (`ASSISTANT_PROVIDER_ORDER`)        |
| `streaming`                          | `false` disables SSE — useful for tests, eval CLI                     |
| `limits.max_history_messages`        | How many prior messages to send to the model (default 20)             |
| `limits.max_tool_iterations`         | Tool-call rounds per turn before forced final answer (default 4)      |

The route itself in `routes/api.php` controls auth and rate-limit:

```php
Route::post('/assistant/chat', [AssistantController::class, 'chat'])
    ->middleware([ApiIsAuthenticated::class, ApiIsAdmin::class])
    ->middleware('throttle:50,1440')
    ->name('assistant.chat');
```

---

## Where things live

If you want to read the code:

| Concern                      | File                                                                     |
| ---------------------------- | ------------------------------------------------------------------------ |
| Route + auth                 | `routes/api.php`                                                         |
| Request shape validation     | `app/Http/Requests/AssistantChatRequest.php`                             |
| SSE streaming + error events | `app/Http/Controllers/AssistantController.php`                           |
| Tool-call loop, sanitiser    | `app/Services/AssistantService.php`                                      |
| Tools                        | `app/Services/Assistant/Tools/*.php`                                     |
| Frontend chat shell          | `frontend/src/pages/admin/assistant.vue`                                 |
| Event handling, history      | `frontend/src/stores/assistant.js`                                       |
| Card components              | `frontend/src/components/{Cave,Hut,Collection,TripReport}AssistantCard.vue` |
| Markdown + map rendering     | `frontend/src/components/MarkdownRenderer.vue`                           |
| Eval CLI                     | `app/Console/Commands/AssistantEvalCommand.php`                          |
| Eval dataset                 | `tests/AssistantEval/dataset.json`                                       |
