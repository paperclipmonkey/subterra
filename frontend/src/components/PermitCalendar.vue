<template>
  <v-card class="rounded-lg" elevation="2">
    <v-card-title class="d-flex align-center justify-space-between py-4">
      <v-btn icon variant="text" @click="changeMonth(-1)">
        <v-icon :icon="mdiChevronLeft" />
      </v-btn>
      <span class="text-h6">{{ calendarTitle }}</span>
      <v-btn icon variant="text" @click="changeMonth(1)">
        <v-icon :icon="mdiChevronRight" />
      </v-btn>
    </v-card-title>
    <v-divider />

    <v-alert
      v-if="permitInfo.has_season"
      type="info"
      variant="tonal"
      density="compact"
      class="ma-4 mb-0"
    >
      This permit is only open from
      <strong>{{ formatSeasonDate(permitInfo.season_start) }}</strong>
      to
      <strong>{{ formatSeasonDate(permitInfo.season_end) }}</strong>.
      Dates outside this season cannot be booked.
    </v-alert>

    <v-card-text>
      <div class="calendar-grid">
        <div v-for="day in ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']" :key="day" class="calendar-header">
          {{ day }}
        </div>
        <div
          v-for="(cell, i) in calendarCells"
          :key="i"
          class="calendar-day"
          :class="{
            'calendar-day--other': !cell.current,
            'calendar-day--today': cell.today,
            'calendar-day--past': cell.current && cell.past,
            'calendar-day--out-of-season': cell.current && !cell.past && cell.outOfSeason,
            'calendar-day--full': !cell.available && cell.current && !cell.past && !cell.outOfSeason,
            'calendar-day--at-risk': cell.atRisk && cell.current && !cell.past && !cell.outOfSeason,
            'calendar-day--clickable': isSelectable(cell),
          }"
          @click="isSelectable(cell) ? $emit('select', cell) : null"
        >
          <div class="day-number">{{ cell.day }}</div>
          <template v-if="cell.current && !cell.past">
            <div v-if="cell.outOfSeason" class="text-caption day-label day-label--season">
              Out of season
            </div>
            <template v-else>
              <div v-if="cell.bookingCount > 0" class="text-caption text-grey-darken-1">
                {{ cell.bookingCount }} booked
              </div>
              <div v-if="cell.available && cell.pendingCount > 0" class="text-caption" :class="cell.atRisk ? 'day-label--pending' : 'text-grey'">
                {{ cell.pendingCount }} pending
              </div>
              <div v-if="!cell.available" class="text-caption text-error">
                Full
              </div>
            </template>
          </template>
        </div>
      </div>

      <div class="d-flex flex-wrap ga-4 mt-4 text-caption text-grey-darken-1">
        <span class="d-flex align-center"><span class="legend-swatch legend-swatch--available" /> Available{{ readonly ? '' : ' — click to apply' }}</span>
        <span class="d-flex align-center"><span class="legend-swatch legend-swatch--at-risk" /> Pending — may fill once approved</span>
        <span class="d-flex align-center"><span class="legend-swatch legend-swatch--full" /> Full</span>
        <span class="d-flex align-center"><span class="legend-swatch legend-swatch--today" /> Today</span>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'
import { mdiChevronLeft, mdiChevronRight } from '@mdi/js'

defineOptions({ name: 'PermitCalendar' })

const props = defineProps({
  // Per-day map keyed by 'YYYY-MM-DD' → { booking_count, pending_count, available }.
  calendarData: { type: Object, default: () => ({}) },
  // Season / per-day limit info as returned by the calendar endpoint.
  permitInfo: { type: Object, default: () => ({}) },
  // The month currently displayed (any Date within it).
  currentMonth: { type: Date, required: true },
  // Read-only embeds show availability but don't let visitors open the apply dialog.
  readonly: { type: Boolean, default: false },
})

const emit = defineEmits(['update:currentMonth', 'select'])

const calendarTitle = computed(() =>
  props.currentMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
)

const isInSeason = (dateStr) => {
  const info = props.permitInfo
  if (!info.has_season || !info.season_start || !info.season_end) return true
  const md = dateStr.slice(5) // 'MM-DD'
  const start = info.season_start
  const end = info.season_end
  if (start <= end) {
    return md >= start && md <= end
  }
  // Wrap-around season (e.g. Oct–Mar)
  return md >= start || md <= end
}

const calendarCells = computed(() => {
  const year = props.currentMonth.getFullYear()
  const month = props.currentMonth.getMonth()
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const today = new Date()
  today.setHours(0, 0, 0, 0)

  let startOffset = (firstDay.getDay() + 6) % 7
  const cells = []

  for (let i = startOffset - 1; i >= 0; i--) {
    const d = new Date(year, month, -i)
    cells.push({ day: d.getDate(), current: false, today: false, available: false, past: true, bookingCount: 0 })
  }

  for (let i = 1; i <= lastDay.getDate(); i++) {
    const d = new Date(year, month, i)
    const dateStr = [d.getFullYear(), String(d.getMonth() + 1).padStart(2, '0'), String(d.getDate()).padStart(2, '0')].join('-')
    const dayData = props.calendarData[dateStr]
    const isPast = d < today
    const inSeason = isInSeason(dateStr)
    const bookingCount = dayData?.booking_count || 0
    const pendingCount = dayData?.pending_count || 0
    const available = isPast || !inSeason ? false : (dayData?.available !== false)
    const info = props.permitInfo
    // Available now, but approved + pending applications would fill the day if
    // all pending ones are approved — likely to book out.
    const atRisk = available && info.has_max_groups_per_day && (bookingCount + pendingCount) >= info.max_groups_per_day

    cells.push({
      day: i,
      current: true,
      today: d.toDateString() === today.toDateString(),
      date: dateStr,
      past: isPast,
      outOfSeason: !inSeason,
      bookingCount,
      pendingCount,
      available,
      atRisk,
    })
  }

  const remaining = 42 - cells.length
  for (let i = 1; i <= remaining; i++) {
    cells.push({ day: i, current: false, today: false, available: false, past: false, bookingCount: 0 })
  }

  return cells
})

// A day is selectable (clickable to apply) when it's an available, in-season,
// current-month day that isn't in the past — and the calendar isn't read-only.
const isSelectable = (cell) =>
  !props.readonly && cell.available && cell.current && !cell.past && !cell.outOfSeason

const changeMonth = (delta) => {
  const d = new Date(props.currentMonth)
  d.setMonth(d.getMonth() + delta)
  emit('update:currentMonth', d)
}

// Format MM-DD season boundary as "1 April"
const formatSeasonDate = (mmdd) => {
  if (!mmdd) return ''
  const [month, day] = mmdd.split('-')
  return new Date(2000, Number(month) - 1, Number(day)).toLocaleDateString('en-GB', { day: 'numeric', month: 'long' })
}
</script>

<style scoped>
.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 1px;
  background: #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
}

.calendar-header {
  background: #f5f5f5;
  padding: 8px;
  text-align: center;
  font-weight: 600;
  font-size: 0.85rem;
}

.calendar-day {
  background: white;
  min-height: 80px;
  padding: 6px;
}

.calendar-day--other {
  background: #fafafa;
  opacity: 0.4;
}

.calendar-day--past {
  opacity: 0.35;
}

.calendar-day--today {
  background: #e3f2fd;
}

.calendar-day--full {
  background: #ffebee;
}

.calendar-day--at-risk {
  background: #fff8e1;
}

.calendar-day--at-risk.calendar-day--clickable:hover {
  background: #ffecb3;
}

.day-label--pending {
  color: #f57f17;
  font-weight: 600;
}

.calendar-day--out-of-season {
  background: repeating-linear-gradient(
    135deg,
    #f5f5f5,
    #f5f5f5 4px,
    #eeeeee 4px,
    #eeeeee 8px
  );
  cursor: not-allowed;
}

.day-label {
  line-height: 1.2;
  margin-top: 2px;
}

.day-label--season {
  color: #9e9e9e;
}

.calendar-day--clickable {
  cursor: pointer;
  transition: background 0.15s;
}

.calendar-day--clickable:hover {
  background: #e8f5e9;
}

.day-number {
  font-weight: 600;
  font-size: 0.85rem;
}

.legend-swatch {
  display: inline-block;
  width: 14px;
  height: 14px;
  border-radius: 3px;
  margin-right: 6px;
  border: 1px solid #e0e0e0;
}

.legend-swatch--available {
  background: #e8f5e9;
}

.legend-swatch--at-risk {
  background: #fff8e1;
}

.legend-swatch--full {
  background: #ffebee;
}

.legend-swatch--today {
  background: #e3f2fd;
}
</style>
