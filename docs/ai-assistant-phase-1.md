# AI Trip Assistant — Phase 1: MVP

## Goal

A working end-to-end chat interface restricted to admin users. No streaming. Three tools: user
experience lookup, cave system search, and cave detail fetch. Simple JSON request/response cycle.

---

## New Files

| Path | Purpose |
|------|---------|
| `config/assistant.php` | Model, limits, enabled tools |
| `app/Services/Assistant/AssistantTool.php` | Interface all tool classes implement |
| `app/Services/Assistant/Tools/GetUserExperienceTool.php` | Returns user's trip history, medals, clubs |
| `app/Services/Assistant/Tools/SearchCavesTool.php` | Searches cave systems by region, tags, length |
| `app/Services/Assistant/Tools/GetCaveDetailsTool.php` | Full detail for one cave system |
| `app/Services/AssistantService.php` | OpenRouter HTTP client + agentic tool loop |
| `app/Http/Requests/AssistantChatRequest.php` | Validates messages array |
| `app/Http/Controllers/AssistantController.php` | `POST /api/assistant/chat` handler |
| `frontend/src/stores/assistant.js` | Pinia store — message history, loading, SSE events |
| `frontend/src/pages/admin/assistant.vue` | Chat UI page at `/admin/assistant` |

## Modified Files

| Path | Change |
|------|--------|
| `routes/api.php` | Add `POST /api/assistant/chat` behind `ApiIsAdmin` |
| `frontend/src/pages/admin/index.vue` | Add AI Assistant card to admin dashboard |

---

## API Endpoint

```
POST /api/assistant/chat
Middleware: auth:sanctum, ApiIsAdmin (platform_admin)
Throttle: 50 requests per day per user

Body:
{
  "messages": [
    { "role": "user", "content": "What should I do next?" }
  ]
}

Response (200):
{
  "content": "Based on your experience..."
}

Response (429): Rate limit exceeded
Response (503): OpenRouter unavailable
```

The `messages` array is the full conversation history (including past assistant turns), so the
backend can pass it directly to the model. The frontend stores and manages this array in the Pinia
store.

---

## Tool Definitions

### `get_user_experience`

Fetches the authenticated user's trip history, medals, and club memberships.

**Returns:**
```json
{
  "total_trips": 42,
  "unique_systems_visited": 18,
  "clubs": ["Craven Pothole Club"],
  "medals": [{ "name": "Explorer", "description": "..." }],
  "recent_trips": [
    { "cave_system": "Gaping Gill", "date": "2026-04-12", "duration_minutes": 180 }
  ]
}
```

Implementation: direct Eloquent queries on `Trip`, `Medal`, and `Club` models. No HTTP calls.

---

### `search_caves`

Searches cave systems by optional filters.

**Parameters:**
- `region` (string, optional) — matches against `caves.location_name` LIKE
- `tags` (string[], optional) — each tag must exist in `cave_system_tag` join
- `min_length` / `max_length` (number, optional) — metres
- `not_visited` (boolean, optional) — excludes systems where the user has a trip

**Returns:** Up to 10 systems with name, slug, length, vertical_range, tags, and grade summary.

Implementation: `DB::table('cave_systems')` query with dynamic joins and subqueries. Limits
result to 10 to control token usage.

---

### `get_cave_details`

Returns full detail for one cave system.

**Parameters:** `cave_system_id` (integer, required)

**Returns:** System info, all entrance caves with access_info, routes with grades and durations,
and tags. Description truncated to 1000 chars.

---

## AssistantService — Tool Loop

```
1. Build system prompt with current date and user context
2. Prepend system message to messages array (capped at max_history_messages)
3. POST to OpenRouter with tools array
4. If response.choices[0].message.tool_calls exists:
   a. For each tool call: dispatch to the correct Tool class
   b. Append assistant message (with tool_calls) to context
   c. Append tool result messages
   d. Increment iteration counter
   e. If iterations < max_tool_iterations, repeat from step 3
5. Return response.choices[0].message.content
```

The loop is synchronous (no streaming) for Phase 1. PHP's `Http::pool()` is NOT used here — tool
calls are dispatched sequentially because each may depend on knowledge gained from the previous.

---

## System Prompt (Phase 1)

```
You are Pip, a knowledgeable caving assistant for the Subterra platform.
You help cavers in the UK and Ireland choose appropriate trips.

Current date: {Y-m-d}
User: {name}
Clubs: {comma-separated approved clubs}

Guidelines:
- Always call get_user_experience before making recommendations.
- Only describe caves using data returned by your tools. Do not use general knowledge to invent
  details about specific caves.
- When you reference a cave system, format it as [Name](/cave-systems/{slug}) so the app renders
  it as a clickable link.
- Mention access restrictions (access_info) if present.
- Be conversational and ask clarifying questions if the user's intent is unclear.
- Do not invent grades, depths, or hazard information.
```

---

## Frontend — Pinia Store (`stores/assistant.js`)

State:
- `messages[]` — `{ role, content, pending? }` objects
- `isLoading` — true while waiting for API response
- `activeToolCalls[]` — tool names currently being dispatched
- `error` — string or null

Actions:
- `sendMessage(content)` — appends user message, calls API, handles SSE stream
- `handleEvent(event)` — processes incoming SSE event types
- `clearConversation()` — resets all state

The store uses `fetch()` (not axios) to support SSE streaming. Even in Phase 1, the store is
written to handle SSE so Phase 2 streaming is a backend-only change.

---

## Frontend — Chat Page (`pages/admin/assistant.vue`)

Layout: full-height container with:
- Header row with title, "Admin Preview" warning chip, and Clear button
- Scrollable message list (`v-card` with `overflow-y-auto`)
- Input row with text field + Send button
- Disclaimer text at the bottom

Message bubbles:
- User messages: right-aligned, primary colour background
- Assistant messages: left-aligned, grey background, markdown rendered via `<MarkdownRenderer />`
- Pending assistant message: shows spinner + active tool call chips

Welcome screen (no messages yet): intro text and three suggestion chips.

---

## Admin Gating

**Backend:** `POST /api/assistant/chat` has `ApiIsAdmin::class` middleware (requires
`platform_admin` role by default). Returns 403 for all other users.

**Frontend:** Page at `/admin/assistant`. The existing router guard in `router/index.js` already
redirects non-admin users away from all `/admin/*` paths, so no additional route meta is needed.

---

## Environment Variables

```env
OPENROUTER_API_KEY=sk-or-...
ASSISTANT_MODEL=anthropic/claude-3-5-haiku   # optional, config default used if absent
```

---

## Error Handling

| Scenario | Backend Response | Frontend Behaviour |
|----------|-----------------|-------------------|
| `OPENROUTER_API_KEY` not set | 503 with message | Error shown in chat |
| OpenRouter 5xx / timeout | 503 | Error shown in chat |
| Rate limit exceeded | 429 | "Daily limit reached" message |
| Invalid messages array | 422 validation error | Error shown in chat |
| Model has no content (only tool calls exceed max iterations) | 200 with fallback message | Renders normally |
