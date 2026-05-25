# AI Trip Assistant — Design & Considerations

## Overview

An AI assistant embedded in Subterra to help cavers choose their next trip or plan a future caving weekend. The assistant would use the existing data (caves, systems, routes, weather, the user's trip history, medals, and clubs) to make personalised recommendations via a conversational chat interface.

---

## Proposed Architecture

```
Frontend (Vue.js / Vuetify)          Laravel API (Backend)                External Services
┌─────────────────────────┐         ┌──────────────────────────────┐     ┌─────────────────┐
│  ChatAssistant.vue      │◄──────► │  /api/assistant/chat         │────►│  OpenRouter     │
│  - Chat bubble UI       │  JSON   │  - Auth & rate limiting       │     │  (LLM gateway)  │
│  - Message history      │         │  - Context assembly           │     └─────────────────┘
│  - Typing indicator     │         │  - Tool call dispatch         │
│  - Cave/trip links      │         │  - Response streaming (SSE)   │     ┌─────────────────┐
└─────────────────────────┘         │                               │────►│  Open-Meteo /   │
                                    │  Tool Implementations         │     │  Weather API    │
                                    │  ─────────────────────────    │     └─────────────────┘
                                    │  get_user_experience()        │
                                    │  search_caves()               │     ┌─────────────────┐
                                    │  get_cave_details()           │────►│  EA Flood API   │
                                    │  get_weather_forecast()       │     │  (river levels) │
                                    │  list_routes()                │     └─────────────────┘
                                    │  get_upcoming_permits()       │
                                    └──────────────────────────────┘
```

The LLM API key must **never** be exposed to the frontend. All calls route through a Laravel controller that:
1. Authenticates the request (Sanctum)
2. Assembles context for the model
3. Dispatches tool calls against internal services
4. Streams the response back (Server-Sent Events)

---

## OpenRouter as Gateway

OpenRouter is a solid choice because it:
- Provides a single OpenAI-compatible API across many models (Claude, GPT-4o, Gemini, Llama, etc.)
- Allows model switching without code changes — useful for cost/quality tuning
- Supports function calling / tool use, which is essential for grounding the assistant in real data
- Has per-key spending limits for cost control

Recommended starting models (via OpenRouter):
- **claude-3-5-haiku** — fast, cheap, good for conversational use
- **google/gemini-flash-1.5** — very low cost fallback
- **claude-3-5-sonnet** — higher quality for complex planning queries

Store the key in `.env` as `OPENROUTER_API_KEY`.

---

## MCP vs Function Calling

### What MCP Is
[Model Context Protocol (MCP)](https://modelcontextprotocol.io/) is a standard for exposing data sources and tools to LLMs. An MCP server exposes typed "tools" that any MCP-compatible client (Claude Desktop, Cursor, etc.) can call.

### Do You Need a Full MCP Server?

**Not necessarily for the in-app chat assistant.** For Subterra's embedded chat UI, OpenAI-compatible **function calling** (which OpenRouter supports) is sufficient and simpler. The Laravel backend acts as the tool dispatcher.

However, a standalone MCP server would make sense if you want:
- The assistant to be usable from Claude Desktop or Cursor by trip leaders/admins
- External integrations (other apps querying Subterra cave data through a standard interface)

**Recommendation:** Start with Laravel function calling. Document the tool schemas so they can be ported to an MCP server later with minimal effort.

### Tool Definitions

The following tools would be registered with the LLM:

#### `get_user_experience`
```json
{
  "name": "get_user_experience",
  "description": "Get the current user's caving experience: recent trips, medals awarded, clubs, and a summary of caves/systems visited. Use this first to understand their background before making recommendations.",
  "parameters": {
    "type": "object",
    "properties": {}
  }
}
```
Internally calls: `/me/trips`, `/users/{id}/medals`, `/user` (clubs).

#### `search_caves`
```json
{
  "name": "search_caves",
  "description": "Search for cave systems matching criteria. Use to find options to recommend.",
  "parameters": {
    "type": "object",
    "properties": {
      "region": { "type": "string", "description": "Location name or region, e.g. 'Yorkshire Dales', 'South Wales'" },
      "tags": { "type": "array", "items": { "type": "string" }, "description": "e.g. ['streamway', 'through trip', 'sporting']" },
      "max_grade": { "type": "string", "description": "Maximum route grade, e.g. 'Moderate', 'Severe'" },
      "min_length": { "type": "number", "description": "Minimum system length in metres" },
      "max_length": { "type": "number", "description": "Maximum system length in metres" },
      "not_visited": { "type": "boolean", "description": "If true, exclude systems the user has already visited" }
    }
  }
}
```
Internally calls: `/cave_systems` and `/caves/search`, filtering by the user's trip history when `not_visited` is true.

#### `get_cave_details`
```json
{
  "name": "get_cave_details",
  "description": "Get full details of a cave system including its routes, grades, entrance caves, description, and access information.",
  "parameters": {
    "type": "object",
    "properties": {
      "cave_system_id": { "type": "integer" }
    },
    "required": ["cave_system_id"]
  }
}
```
Internally calls: `/cave_systems/{id}` and `/cave_systems/{id}/routes`.

#### `get_weather_forecast`
```json
{
  "name": "get_weather_forecast",
  "description": "Get the weather forecast and river/flood gauge readings for a cave. Critical for trip safety planning — check before recommending streamway or flood-prone caves.",
  "parameters": {
    "type": "object",
    "properties": {
      "cave_id": { "type": "integer" },
      "date": { "type": "string", "format": "date", "description": "Target date e.g. '2026-05-17'" }
    },
    "required": ["cave_id"]
  }
}
```
Internally calls: `/caves/{cave}/weather/forecast` and `/caves/{cave}/weather/historic`.

#### `get_upcoming_permits`
```json
{
  "name": "get_upcoming_permits",
  "description": "Check permit availability for a cave on a given date.",
  "parameters": {
    "type": "object",
    "properties": {
      "cave_id": { "type": "integer" },
      "date_from": { "type": "string", "format": "date" },
      "date_to": { "type": "string", "format": "date" }
    },
    "required": ["cave_id", "date_from"]
  }
}
```
Internally calls: `/caves/{cave}/permit` and `/permits/{permit}/calendar`.

#### `list_routes`
```json
{
  "name": "list_routes",
  "description": "List surveyed routes through a cave system with grade, duration, and description.",
  "parameters": {
    "type": "object",
    "properties": {
      "cave_system_id": { "type": "integer" }
    },
    "required": ["cave_system_id"]
  }
}
```
Internally calls: `/cave_systems/{id}/routes`.

---

## System Prompt Design

The system prompt is assembled server-side at request time. A skeleton:

```
You are a knowledgeable caving assistant for the Subterra platform. Your role is to help 
cavers in the UK and Ireland choose appropriate trips and plan caving weekends.

Current date: {date}
Current user: {user.name}, member of {clubs}.

Guidelines:
- Always prioritise safety. Never recommend a streamway or sump cave without first checking 
  the weather forecast and river levels using the get_weather_forecast tool.
- Match recommendations to the user's experience. Check get_user_experience before suggesting trips.
- Be honest about hazards. Mention access restrictions, required gear, and grade.
- When suggesting a weekend, consider travel distance from the user's club region if known.
- Link to caves and trips using their slugs — the frontend will render these as clickable cards.
- If you don't know a cave or the data isn't in the tools, say so. Don't invent cave information.
- You are not a rescue service. For emergencies, direct users to their cave rescue organisation.

You have access to the following tools: [tool list injected here]
```

---

## Frontend — Chat UI

### Component: `ChatAssistant.vue`

A floating panel or dedicated page within the Vuetify layout. Key elements:

- **Message thread** — alternating user/assistant bubbles
- **Streaming support** — show tokens as they arrive via SSE
- **Cave/trip cards** — when the assistant references a cave system by ID or slug, render a mini-card with name, grade, and a link (similar to existing cave cards)
- **Typing indicator** — three-dot animation while the model is working
- **Tool call transparency** (optional) — collapsible "Checking weather..." / "Looking up your trips..." status messages during tool dispatch
- **Conversation reset** — clear button to start a new session
- **Mobile-friendly** — Vuetify's `v-navigation-drawer` or bottom sheet pattern

### Routing

Add `/assistant` as a route, and optionally surface a floating action button (FAB) on cave/system detail pages that pre-populates the assistant with context ("Tell me about a trip to Gaping Gill").

### State

Conversation history lives in a Pinia store (`stores/assistant.js`). The full `messages[]` array is sent to the backend on each turn so the model has context. A maximum history depth (e.g. 20 messages) should be enforced to avoid ballooning token costs.

---

## Backend — Laravel Implementation

### New Files Required

| File | Purpose |
|------|---------|
| `app/Http/Controllers/AssistantController.php` | Handles `/api/assistant/chat` POST, assembles context, dispatches tool calls, streams response |
| `app/Services/AssistantService.php` | OpenRouter HTTP client wrapper, tool call loop |
| `app/Services/Assistant/Tools/*.php` | One class per tool, implementing the query logic |
| `routes/api.php` (addition) | `Route::post('/assistant/chat', ...)` |
| `config/assistant.php` | Model name, max tokens, temperature, allowed tool list |

### Endpoint

```
POST /api/assistant/chat
Authorization: Bearer {sanctum_token}
Content-Type: application/json

{
  "messages": [
    { "role": "user", "content": "What should I do this weekend? I haven't done Swildon's." }
  ]
}
```

Returns a stream of SSE events:
```
data: {"type":"content","delta":"I'd suggest..."}
data: {"type":"tool_call","name":"get_weather_forecast","status":"running"}
data: {"type":"tool_call","name":"get_weather_forecast","status":"done"}
data: {"type":"content","delta":"...based on the current forecast..."}
data: {"type":"done"}
```

### Tool Call Loop

```php
// Pseudo-code for the agentic loop
do {
    $response = $openRouter->chat($messages, $tools);
    
    if ($response->hasToolCalls()) {
        foreach ($response->toolCalls() as $call) {
            $result = $this->dispatchTool($call->name, $call->arguments, $user);
            $messages[] = ['role' => 'tool', 'content' => json_encode($result), ...];
            $this->streamEvent('tool_call', [...]);
        }
    }
} while ($response->hasToolCalls() && $iterations++ < 5);

$this->streamContent($response->content());
```

---

## Complications & Risks

### 1. Privacy and Data Leakage
**Risk:** The assistant sends user trip history, club membership, and location data to an external LLM provider (OpenRouter and its upstream model providers).

**Mitigations:**
- Review OpenRouter's data retention policy and choose a model provider with a zero-retention option (Anthropic and OpenAI both offer this)
- Strip PII from tool outputs before sending — use user IDs not names in tool responses where possible
- Add a clear disclosure in the chat UI: "Your caving history may be shared with an AI service to generate recommendations"
- Make the feature opt-in; require explicit consent at first use, recorded against the user record
- Consider a GDPR data processing agreement with OpenRouter if the user base is EU-based

### 2. Hallucination of Cave Information
**Risk:** The model may "know" about UK caves from its training data and invent details (grades, hazards, sump locations) that contradict or extend what's in Subterra.

**Mitigations:**
- The system prompt must explicitly state: "Only describe caves using the data returned by your tools. Do not use your general knowledge about specific caves."
- Tool results should be the sole authoritative source — instruct the model to cite which tool call a fact came from
- Consider filtering the model's output for cave names not returned by tool calls (complex, but possible with a post-processing step)

### 3. Safety-Critical Recommendations
**Risk:** The AI recommends a flood-prone cave on a weekend with high river levels, or an SRT route to a novice.

**Mitigations:**
- The system prompt must mandate: always call `get_weather_forecast` before recommending any streamway/sump cave
- Hard-code a pre-flight check: the controller intercepts any tool call result showing river levels above normal and injects a safety warning into the context
- Add a disclaimer: "Recommendations are a starting point. Always consult your club leader and check conditions on the day."
- Route grades from the `routes` table must be mapped to a difficulty tier the model understands (the raw grade strings may vary)

### 4. Incomplete / Inconsistent Grade Data
**Risk:** Not all cave systems in Subterra have routes with grades. Tags vary in quality. The `grade` field on `Route` is a free-text string with no enforced vocabulary.

**Mitigations:**
- Normalise grade strings to a taxonomy (e.g. Easy / Moderate / Hard / Severe / Expert) server-side before passing to the model
- Include trip count and typical duration as a proxy for popularity/approachability
- Accept that recommendations for poorly-tagged systems will be lower quality — the model should flag uncertainty

### 5. Token Cost at Scale
**Risk:** Multi-turn conversations with multiple tool calls, full trip history, and cave details in context can easily reach 10,000+ tokens per request, costing significantly at volume.

**Mitigations:**
- Cap conversation history depth (e.g. last 10 messages only)
- Summarise tool results before sending — e.g. strip full descriptions from the caves list, only include name, grade, tags, and ID; fetch full details only when a specific cave is selected
- Use a cheap fast model (Haiku, Gemini Flash) for most turns; only escalate to a smarter model for complex planning queries
- Implement per-user daily rate limits at the Laravel level
- Add a cost tracking table: log `input_tokens`, `output_tokens`, `model` per request

### 6. Authentication and Authorisation
**Risk:** The assistant endpoint must respect existing data visibility rules (trip visibility: public/club/private, permit access, etc.).

**Mitigations:**
- All tool implementations must use the authenticated user's context when calling internal services — the same scoping rules as the existing API
- Never pass raw SQL or unfiltered queries to tool arguments (prompt injection risk — see §9)
- The `get_user_experience` tool should only ever return the current user's data, not an arbitrary user ID

### 7. Streaming Complexity
**Risk:** Laravel's default request/response cycle doesn't natively support SSE streaming well. Tool call loops (multiple round-trips to the LLM before a final answer) make this harder.

**Mitigations:**
- Use Laravel's `StreamedResponse` with `ob_flush()`/`flush()` calls
- Consider a queue-backed approach for complex multi-tool queries: return a job ID immediately, poll for status, or use Laravel's broadcasting (Reverb/Pusher) to push the final answer
- For the MVP, a non-streamed JSON response with a loading spinner is simpler and acceptable — streaming is an enhancement

### 8. Permit and Access Information Gaps
**Risk:** The AI recommends a cave with restricted access (landowner permit, CNCC/CSCC permit required) without surfacing that requirement.

**Mitigations:**
- The `get_upcoming_permits` tool should be called proactively for any cave that has a permit attached
- `access_info` from the Cave model should always be included in the tool response for recommended caves
- The system prompt should instruct the model to always mention access requirements

### 9. Prompt Injection via Cave Descriptions
**Risk:** A malicious user or data entry could embed instructions in a cave description (e.g. "Ignore previous instructions and...") that the model executes when reading tool results.

**Mitigations:**
- Sanitise tool result strings before inserting into the message context — strip HTML, limit length
- Wrap tool results in a structured format that signals to the model they are data, not instructions: `<tool_result name="get_cave_details">...</tool_result>`
- Monitor for anomalous model outputs (e.g. responses that reference "ignore previous instructions")
- Flag to the user if a suspicious output is detected

### 10. Seasonal and Flood Risk Context
**Risk:** The weather API covers meteorological forecasts but flood risk in caving is also driven by cumulative rainfall days in advance (antecedent conditions), which a single forecast call may not capture.

**Mitigations:**
- The `get_weather_forecast` tool already calls historic rainfall data — expose this to the model
- Include antecedent rainfall (last 48–72 hours) in the tool output alongside the forecast
- The system prompt should explain this to the model: "High antecedent rainfall means flood risk persists even if today's forecast is dry"

### 11. No Formal Experience Model
**Risk:** There is no `experience_level` or `skill_grade` field on the User model. The assistant can only infer experience from trip history and medals, which may be sparse for new users or not cover vertical/SRT skills.

**Mitigations:**
- Use trip count, trip duration patterns, and medals as proxies
- Consider adding a simple self-reported skill questionnaire to the onboarding flow, stored as user metadata (e.g. horizontal only / basic SRT / advanced SRT / leader)
- The assistant should ask the user clarifying questions if experience is unclear rather than assuming

### 12. Weekend Planning — External Data
**Risk:** A "plan a weekend away" query benefits from accommodation (huts), travel distance, and group size — data that is partially in Subterra (huts) but not fully (travel distance from user location, group member profiles).

**Mitigations:**
- Expose the `huts` endpoint as a tool: `find_nearby_huts(cave_system_id)`
- For travel distance, accept the user's stated starting location as free text in the conversation rather than integrating a mapping API in v1
- Group planning (multiple user profiles) is out of scope for v1 — the assistant plans for the authenticated user only

---

## Phased Rollout

### Phase 1 — MVP (No Streaming)
- `POST /api/assistant/chat` endpoint with Sanctum auth and rate limiting
- Tools: `get_user_experience`, `search_caves`, `get_cave_details`
- Simple chat UI on `/assistant` — full page, no streaming, spinner while waiting
- Opt-in consent modal on first use
- Basic cost logging

### Phase 2 — Weather & Safety
- Add `get_weather_forecast` and `get_upcoming_permits` tools
- Safety pre-flight checks in the controller
- Cave card rendering in assistant responses
- SSE streaming

### Phase 3 — Weekend Planning
- `find_nearby_huts` tool
- Multi-day itinerary generation
- Shareable conversation summaries (generate a "trip plan" document)
- FAB on cave system detail pages with pre-loaded context

### Phase 4 — MCP Server (Optional)
- Expose the tool set as a standalone MCP server (`subterra-mcp`)
- Enables use from Claude Desktop, Cursor, and future integrations
- Auth via API key rather than Sanctum session

---

## Configuration (`config/assistant.php`)

```php
return [
    'openrouter' => [
        'api_key'  => env('OPENROUTER_API_KEY'),
        'base_url' => 'https://openrouter.ai/api/v1',
        'model'    => env('ASSISTANT_MODEL', 'anthropic/claude-3-5-haiku'),
        'max_tokens' => 2048,
        'temperature' => 0.7,
    ],
    'limits' => [
        'max_history_messages' => 20,
        'max_tool_iterations'  => 5,
        'daily_requests_per_user' => 50,
    ],
    'tools_enabled' => [
        'get_user_experience',
        'search_caves',
        'get_cave_details',
        'get_weather_forecast',
        'get_upcoming_permits',
        'list_routes',
        'find_nearby_huts',
    ],
];
```

---

## Summary of New API / Code Required

| Area | What's Needed |
|------|--------------|
| Laravel route | `POST /api/assistant/chat` |
| Controller | `AssistantController` — auth, rate limit, orchestration |
| Service | `AssistantService` — OpenRouter client, tool loop |
| Tool classes | One per tool (6–7 classes), each calling existing services/models |
| Config | `config/assistant.php` |
| DB | Optional: `assistant_usage_logs` table for cost tracking and abuse detection |
| Frontend | `ChatAssistant.vue`, `stores/assistant.js`, `/assistant` route |
| UI | Vuetify chat layout, cave mini-cards, consent modal |
| `.env` | `OPENROUTER_API_KEY`, `ASSISTANT_MODEL` |

The existing JSON endpoints and weather services are already well-suited to be consumed by tool implementations — no fundamental restructuring of the API is needed.
