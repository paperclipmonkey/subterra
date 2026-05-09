# AI Trip Assistant — Phase 2: Weather, Safety & Streaming

## Goal

Add safety-aware tools (weather forecast, river levels, permit availability), stream tool call
progress events to the frontend so users see activity during multi-step lookups, and enforce
a safety pre-flight check for flood-risk caves.

Depends on Phase 1 being merged.

---

## New Files

| Path | Purpose |
|------|---------|
| `app/Services/Assistant/Tools/GetWeatherForecastTool.php` | Weather forecast + river/rain gauge data |
| `app/Services/Assistant/Tools/GetUpcomingPermitsTool.php` | Permit availability calendar for a cave |

---

## Modified Files

| Path | Change |
|------|--------|
| `app/Services/AssistantService.php` | Add `$onEvent` callback, SSE streaming support, safety pre-flight |
| `app/Http/Controllers/AssistantController.php` | Return `StreamedResponse` for SSE |
| `frontend/src/stores/assistant.js` | Consume SSE events (already written in Phase 1) |
| `frontend/src/pages/admin/assistant.vue` | Show tool call status chips during streaming |

---

## New Tools

### `get_weather_forecast`

Fetches current conditions for a cave's location.

**Parameters:** `cave_id` (integer, required), `date` (string `Y-m-d`, optional)

**Internals:**
1. Load `Cave` with `system.catchment`
2. Call `WeatherService::getForecast($lat, $lng)` for 7-day forecast
3. If catchment has gauges:
   - For each river gauge: call `RiverLevelService::getEnhancedReading($rloi_id)`
   - For each rain gauge: call `RainfallService::getReadings($station_id)`
4. Call `WeatherService::getHistoricRain($lat, $lng)` for antecedent 7-day rainfall
5. Return summarised payload (see below)

**Returns:**
```json
{
  "cave_name": "Ogof Ffynnon Ddu",
  "location": { "lat": 51.87, "lng": -3.62 },
  "forecast_summary": "Heavy rain expected Saturday. Dry Sunday.",
  "daily_forecast": [
    { "date": "2026-05-17", "precip_mm": 0.2, "summary": "Partly cloudy" }
  ],
  "antecedent_rain_7d_mm": 34.5,
  "river_gauges": [
    { "name": "River Tawe", "state": "Normal", "trend": "Falling", "latest_value": 0.23 }
  ],
  "rain_gauges": [
    { "name": "Fan Fawr Gauge", "readings_24h_mm": 12.3 }
  ]
}
```

Data is summarised before being placed into the LLM context to reduce token usage. Full reading
arrays from the river level API are collapsed to latest value, trend, and state only.

---

### `get_upcoming_permits`

Checks whether a cave has an active permit scheme and returns booking availability.

**Parameters:** `cave_id` (integer, required), `date_from` (string `Y-m-d`, required),
`date_to` (string `Y-m-d`, optional — defaults to 30 days from `date_from`)

**Returns:**
```json
{
  "has_permit": true,
  "cave_name": "Peak Cavern",
  "permit_name": "DCA Peak District Permit",
  "max_groups_per_day": 4,
  "bookings_by_date": {
    "2026-05-17": 2,
    "2026-05-18": 4
  }
}
```

A `booking_count` equal to `max_groups_per_day` means that date is fully booked.

---

## Safety Pre-Flight Check

When the `AssistantService` processes a tool result from `get_weather_forecast`, it inspects
the `river_gauges` array for any gauge with `state === 'High'`. If found, it injects an
additional system-level context message:

```
[SAFETY ALERT] River gauge "{name}" is currently HIGH for cave {cave_name}.
This cave may be at flood risk. You must warn the user clearly before recommending
this cave, even if the weather forecast looks acceptable. High antecedent rainfall
can keep caves flooded for 24-48 hours after rain stops.
```

This injection happens at the PHP level, so the model cannot be instructed by the user to
ignore it.

---

## Streaming Architecture

### Why SSE over WebSockets?

SSE is unidirectional (server → client) which is all that's needed here. It works over standard
HTTP, doesn't require a WebSocket server, and is supported natively by `EventSource` and by
`fetch()` with `ReadableStream`. Laravel's `StreamedResponse` handles it well.

### Laravel — `StreamedResponse`

```php
return response()->stream(function () use ($messages, $user) {
    if (ob_get_level()) ob_end_clean();

    $content = $this->assistantService->chat(
        $messages,
        $user,
        function (string $type, mixed $data) {
            echo 'data: ' . json_encode(['type' => $type, 'data' => $data]) . "\n\n";
            if (ob_get_level()) ob_flush();
            flush();
        }
    );

    echo 'data: ' . json_encode(['type' => 'content', 'data' => ['text' => $content]]) . "\n\n";
    echo 'data: ' . json_encode(['type' => 'done']) . "\n\n";
    if (ob_get_level()) ob_flush();
    flush();
}, 200, [
    'Content-Type'      => 'text/event-stream',
    'Cache-Control'     => 'no-cache',
    'X-Accel-Buffering' => 'no',   // required for nginx proxy
    'Connection'        => 'keep-alive',
]);
```

### Event Types

| `type` | `data` shape | Frontend action |
|--------|-------------|-----------------|
| `thinking` | `null` | Show spinner |
| `tool_call` | `{ name, status: 'running'\|'done', label }` | Add/remove chip |
| `content` | `{ text }` | Render markdown in message bubble |
| `done` | `null` | Clear loading state |
| `error` | `{ message }` | Show error, remove pending bubble |

### Frontend — `fetch()` Stream Consumer

The store's `sendMessage` uses `fetch()` with `response.body.getReader()`:

```js
const reader = response.body.getReader()
const decoder = new TextDecoder()
let buffer = ''

while (true) {
  const { done, value } = await reader.read()
  if (done) break
  buffer += decoder.decode(value, { stream: true })
  const lines = buffer.split('\n')
  buffer = lines.pop()           // retain incomplete last line
  for (const line of lines) {
    if (line.startsWith('data: ')) {
      this.handleEvent(JSON.parse(line.slice(6)))
    }
  }
}
```

`EventSource` is NOT used here because it only supports GET requests. The `fetch()` approach
works with POST and session cookies (Sanctum).

---

## Complications

### Output Buffering

PHP's output buffering stack must be empty before `flush()` takes effect. Nginx and some PHP-FPM
configurations re-enable buffering. The `X-Accel-Buffering: no` header disables nginx's proxy
buffer. In PHP, call `ob_end_clean()` at the start of the stream callback.

In development (Sail/Docker), output buffering is typically off by default, so this works without
additional configuration.

### Session Locking

Laravel's file-based session driver holds a file lock for the entire request. A long-running
SSE connection would block other tabs from making requests. Options:

1. Switch to the `cookie` or `database` session driver (recommended for production)
2. Call `session()->save()` before the streaming loop starts to release the lock early

For Phase 2, add `session()->save()` at the top of the streaming callback as a safe default.

### Reverse Proxy Timeouts

Nginx default `proxy_read_timeout` is 60 seconds. A multi-tool chain + slow model can exceed
this. The Fly.io deployment should have this configured — verify `proxy_read_timeout 120s` in
the nginx config or move to a direct HTTP connection.

---

## Updated System Prompt (Phase 2 Addition)

Append to the Phase 1 system prompt:

```
**Weather and safety:**
- Before recommending any streamway, rising phreatic, or sump-containing cave, ALWAYS call
  get_weather_forecast for the target cave's entrance.
- If any river gauge state is "High", you MUST issue a clear flood risk warning.
- Antecedent rainfall (rain in the 48-72 hours before the trip) keeps caves flooded even when
  today's forecast is dry. If antecedent_rain_7d_mm > 30, warn accordingly.

**Permits:**
- If a cave has access_info or a permit attached, always mention it.
- Use get_upcoming_permits to check availability for any date-specific planning.
```
