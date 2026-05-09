# AI Trip Assistant — Phase 3: Weekend Planning

## Goal

Extend the assistant to support multi-day trip planning: finding nearby accommodation (huts),
listing available routes through a system, and generating a structured weekend itinerary.
Also adds deep-linking from cave system detail pages into the assistant with pre-loaded context.

Depends on Phases 1 and 2 being merged.

---

## New Files

| Path | Purpose |
|------|---------|
| `app/Services/Assistant/Tools/ListRoutesTool.php` | All surveyed routes for a cave system |
| `app/Services/Assistant/Tools/FindNearbyHutsTool.php` | Huts near a cave system, sorted by distance |

---

## Modified Files

| Path | Change |
|------|--------|
| `app/Services/AssistantService.php` | Register the two new tools in `getToolDefinitions()` |
| `frontend/src/pages/cave-systems/[slug].vue` | Add "Plan a trip" FAB that opens assistant with context |
| `frontend/src/pages/admin/assistant.vue` | Accept `?context=` query param to pre-populate first message |

---

## New Tools

### `list_routes`

Returns all surveyed routes for a cave system with grade, typical duration, and description.

**Parameters:** `cave_system_id` (integer, required)

**Returns:**
```json
{
  "cave_system_id": 7,
  "routes": [
    {
      "name": "OFD I Round Trip",
      "grade": "Moderate",
      "duration_minutes": 150,
      "description": "A circular route through the lower series..."
    }
  ]
}
```

If no routes are recorded, returns `{ "routes": [], "message": "No surveyed routes found." }`.

Implementation: `Route::where('cave_system_id', $id)->get()`. Description truncated to 500 chars.

---

### `find_nearby_huts`

Returns huts sorted by distance from a given cave system's entrance coordinates.

**Parameters:** `cave_system_id` (integer, required)

**Algorithm:**
1. Load `CaveSystem` with `caves` (eager load)
2. Find the first cave with non-null `location_lat`/`location_lng`
3. Fetch all `Hut` records with their `club`
4. Calculate Haversine distance from cave coordinates to each hut
5. Sort ascending by distance, return top 10

**Returns:**
```json
{
  "cave_system": "Ogof Ffynnon Ddu",
  "reference_cave": "OFD Main Entrance",
  "huts": [
    {
      "name": "Dan yr Ogof Bunkhouse",
      "club": "South Wales Caving Club",
      "distance_km": 3.2,
      "amenities": ["kitchen", "showers", "drying_room"],
      "booking_info": "Book via SWCC website",
      "external_url": "https://swcc.org.uk"
    }
  ]
}
```

If no cave has coordinates, returns all huts unordered with a note.

---

## Weekend Itinerary Generation

No new tool is needed for itinerary generation. The assistant uses existing tools together:

1. `get_user_experience` — understand the caver's background
2. `search_caves` — find suitable systems in the target region
3. `get_cave_details` — get route options for each candidate
4. `get_weather_forecast` — check conditions for the target weekend
5. `find_nearby_huts` — find accommodation near the best option
6. `get_upcoming_permits` — confirm access is available on those dates
7. Synthesise into a structured weekend plan

### Weekend Planning System Prompt Addition

```
**Weekend planning:**
When a user asks to plan a weekend, follow this sequence:
1. Ask for: target region, dates (if not given), group size, and experience level (if not
   determinable from get_user_experience).
2. Search for 2-3 suitable cave systems using search_caves.
3. For each candidate: call get_cave_details and get_weather_forecast.
4. Find nearby huts using find_nearby_huts for the top candidate.
5. Check permit availability using get_upcoming_permits for any permitted caves.
6. Present a structured itinerary with:
   - Saturday morning: travel and arrive, check gear
   - Saturday afternoon: [Cave System A] via [Route name] (grade, estimated time)
   - Saturday evening: dinner at [Hut name]
   - Sunday: [Cave System B] or alternative based on weather
   - Practical notes: access info, permit requirements, gear needed
Format the itinerary as a markdown document the user can screenshot or share.
```

---

## Cave System Deep Link (FAB)

On the cave system detail page (`/cave-systems/[slug]`), add a floating action button:

```vue
<v-btn
  v-if="appStore.user.is_admin"
  color="primary"
  icon
  :to="`/admin/assistant?context=${encodeURIComponent('Tell me about a trip to ' + system.name + ' (ID: ' + system.id + '). What routes are available and what are conditions like?')}`"
  position="fixed"
  style="bottom: 80px; right: 16px;"
>
  <v-icon>mdiRobotOutline</v-icon>
  <v-tooltip activator="parent">Plan a trip here with AI</v-tooltip>
</v-btn>
```

The `assistant.vue` page reads the `context` query parameter on mount and pre-populates the first
message, sending it automatically.

---

## Grade Normalisation

By Phase 3, the free-text `grade` field on `Route` should be normalised to a controlled
vocabulary. A migration adds a `grade_normalised` column with an enum:

```
easy | moderate | hard | severe | expert | srt_required
```

A one-off artisan command maps existing grade strings:

| Raw grade string (examples) | Normalised |
|----------------------------|-----------|
| Easy, Beginner, Grade 1 | `easy` |
| Moderate, Grade 2, Intermediate | `moderate` |
| Hard, Grade 3 | `hard` |
| Severe, Grade 4, Strenuous | `severe` |
| Expert, Grade 5, Very Severe | `expert` |
| SRT, Rope, Vertical | `srt_required` |

The `ListRoutesTool` and `SearchCavesTool` return `grade_normalised` so the model receives
consistent values. The raw grade string is also returned for display.

This migration is NOT in Phase 1 or 2 — it's a data quality prerequisite for good Phase 3
recommendations.

---

## Shareable Trip Plan (Stretch Goal)

After the assistant generates an itinerary, a "Save as Trip Plan" button would:
1. POST the markdown content to `POST /api/pages` (using the existing admin Pages API) as a
   draft private page
2. Return the page URL for sharing

This reuses the existing CMS page model and is a low-effort addition. The page would be marked
private/draft and only visible to the user who created it.

---

## Known Limitations at Phase 3

- **No group planning:** The assistant plans for the authenticated user only. Adding group
  members (with their own experience profiles) requires multi-user consent flows.
- **No live booking:** The assistant can check permit availability and explain how to book, but
  cannot submit a booking on the user's behalf. A "Book Now" deep link to the permits page is
  the suggested UX.
- **Hut reciprocal access:** The `Hut` model has `reciprocalClubs()` but the assistant doesn't
  currently surface whether the user's club has reciprocal access rights to a given hut.
- **No mapping:** The assistant doesn't generate maps. It links to cave system pages which have
  map embeds.
- **Token budget:** A full weekend plan (6-7 tool calls + detailed responses) can use 8,000-12,000
  tokens per conversation. At Phase 3, consider upgrading to a model with a larger context window
  or implementing conversation summarisation to stay within budget.
