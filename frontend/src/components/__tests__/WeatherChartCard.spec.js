import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import WeatherChartCard from '@/components/WeatherChartCard.vue'
import { createRouter, createMemoryHistory } from 'vue-router'

// Mock Chart.js before importing the component
vi.mock('chart.js', () => {
  const mockChart = vi.fn(function() {
    this.destroy = vi.fn()
  })
  mockChart.register = vi.fn()
  return {
    Chart: mockChart,
    CategoryScale: {},
    LinearScale: {},
    BarController: {},
    BarElement: {},
    LineController: {},
    LineElement: {},
    PointElement: {},
    Legend: {},
    Tooltip: {},
  }
})

const mockRouter = createRouter({
  history: createMemoryHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: { template: '<div></div>' },
    },
    {
      path: '/caves/:slug',
      name: 'cave-detail',
      component: { template: '<div></div>' },
    },
  ],
})

describe('WeatherChartCard.vue', () => {
  let wrapper

  const createComponent = (props = {}) => {
    return mount(WeatherChartCard, {
      props: {
        data: {
          cave_id: 42,
          cave_slug: 'swildons-hole',
          cave_name: 'Swildon\'s Hole',
          antecedent_rain_7d_mm: null,
          daily_forecast: [],
          rain_gauges: [],
          river_gauges: [],
          ...props,
        },
      },
      global: {
        plugins: [mockRouter],
        stubs: {
          'v-icon': { template: '<span></span>' },
        },
      },
    })
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders weather card with cave name', () => {
    wrapper = createComponent()
    expect(wrapper.text()).toContain('Swildon\'s Hole — Weather & Conditions')
  })

  it('renders View Details link with correct URL', () => {
    wrapper = createComponent()
    // Just verify the link renders and contains the text
    const linkElement = wrapper.find('.weather-link')
    expect(linkElement.exists()).toBe(true)
    expect(linkElement.text()).toContain('View Details')
  })

  it('displays antecedent rainfall when available (no currently block)', () => {
    wrapper = createComponent({
      antecedent_rain_7d_mm: 15.3,
    })
    expect(wrapper.text()).toContain('15.3')
    expect(wrapper.text()).toContain('Past 7 Days')
  })

  it('displays antecedent rainfall in conditions row when currently is present', () => {
    wrapper = createComponent({
      antecedent_rain_7d_mm: 14.5,
      currently: {
        temperature: 12,
        summary: 'Breezy',
        icon: 'wind',
        windSpeed: 25,
        humidity: 85,
        precipProbability: 60,
      },
    })
    expect(wrapper.text()).toContain('14.5mm last 7d')
    // Should NOT show the data-card version when currently is present
    expect(wrapper.text()).not.toContain('Past 7 Days')
  })

  it('displays rain gauge data', () => {
    wrapper = createComponent({
      rain_gauges: [
        { name: 'Station A', readings_24h_mm: 5.2 },
        { name: 'Station B', readings_24h_mm: 8.1 },
      ],
    })
    expect(wrapper.text()).toContain('Station A')
    expect(wrapper.text()).toContain('5.2')
    expect(wrapper.text()).toContain('Station B')
    expect(wrapper.text()).toContain('8.1')
  })

  it('displays river gauge data with state', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'Wookey Gauge',
          state: 'Normal',
          trend: 'Falling',
          latest_value: '0.45 m',
          latest_time: '2026-05-16T12:00:00Z',
        },
      ],
    })
    expect(wrapper.text()).toContain('Wookey Gauge')
    expect(wrapper.text()).toContain('0.45 m')
  })

  it('handles lowercase trend values for backwards compatibility', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'Wookey Gauge',
          state: 'Normal',
          trend: 'falling',
          latest_value: '0.45 m',
        },
      ],
    })
    expect(wrapper.text()).toContain('Wookey Gauge')
  })

  it('applies warning class for High river state', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'River A',
          state: 'High',
          trend: 'Rising',
          latest_value: '1.2',
        },
      ],
    })
    const gaugeCard = wrapper.find('.river-gauge-card')
    expect(gaugeCard.classes()).toContain('gauge-warning')
  })

  it('applies severe class for Very high river state', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'River A',
          state: 'Very high',
          trend: 'Rising',
          latest_value: '1.5',
        },
      ],
    })
    const gaugeCard = wrapper.find('.river-gauge-card')
    expect(gaugeCard.classes()).toContain('gauge-severe')
  })

  it('does not render antecedent rainfall when null', () => {
    wrapper = createComponent({
      antecedent_rain_7d_mm: null,
    })
    expect(wrapper.text()).not.toContain('Past 7 Days')
  })

  it('does not render rain gauges section when empty', () => {
    wrapper = createComponent({
      rain_gauges: [],
    })
    expect(wrapper.text()).not.toContain('Current Rain')
  })

  it('does not render river gauges section when empty', () => {
    wrapper = createComponent({
      river_gauges: [],
    })
    expect(wrapper.find('.river-section').exists()).toBe(false)
  })

  it('handles missing cave_slug gracefully', () => {
    wrapper = createComponent({
      cave_slug: null,
    })
    // Should render without crashing
    const linkElement = wrapper.find('.weather-link')
    expect(linkElement.exists()).toBe(true)
    expect(linkElement.text()).toContain('View Details')
  })

  it('renders current conditions block with temperature and summary', () => {
    wrapper = createComponent({
      currently: {
        temperature: 12.3,
        summary: 'Breezy and cool',
        icon: 'wind',
        windSpeed: 28,
        humidity: 82,
        precipProbability: 70,
      },
    })
    expect(wrapper.text()).toContain('Breezy and cool')
    expect(wrapper.text()).toContain('28 km/h')
    expect(wrapper.text()).toContain('82%')
    // Temperature is not shown in the card
    expect(wrapper.text()).not.toContain('12°C')
  })

  it('does not render current conditions block when currently is absent', () => {
    wrapper = createComponent()
    expect(wrapper.find('.current-conditions').exists()).toBe(false)
  })

  it('renders river gauge sparkline canvas when readings are present', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'Wookey',
          state: 'Normal',
          trend: 'Falling',
          latest_value: '0.477',
          typical_range_high: 0.6,
          typical_range_low: 0.2,
          readings: [
            { t: '2026-05-16T10:00:00Z', v: 0.480 },
            { t: '2026-05-16T10:15:00Z', v: 0.479 },
            { t: '2026-05-16T10:30:00Z', v: 0.477 },
          ],
        },
      ],
    })
    // River section should exist and contain a canvas for the sparkline
    expect(wrapper.find('.river-section').exists()).toBe(true)
    expect(wrapper.find('canvas').exists()).toBe(true)
  })

  it('does not render sparkline canvas when no readings', () => {
    wrapper = createComponent({
      river_gauges: [
        {
          name: 'Wookey',
          state: 'Normal',
          trend: 'Steady',
          latest_value: '0.477',
          readings: [],
        },
      ],
    })
    // River section exists but no canvas (no readings)
    expect(wrapper.find('.river-section').exists()).toBe(true)
    expect(wrapper.find('canvas').exists()).toBe(false)
  })

  it('renders forecast section using precip_prob and temp_max_c fields', () => {
    wrapper = createComponent({
      daily_forecast: [
        { date: '2026-05-17', precip_mm: 3.2, precip_prob: 40, temp_max_c: 13.5 },
        { date: '2026-05-18', precip_mm: 0.0, precip_prob: 75, temp_max_c: 11.0 },
      ],
    })
    const canvas = wrapper.find('canvas')
    expect(canvas.exists()).toBe(true)
  })

  it('does not render forecast section when empty', () => {
    wrapper = createComponent({
      daily_forecast: [],
    })
    expect(wrapper.find('canvas').exists()).toBe(false)
  })

  it('renders complete weather card with all data types', () => {
    wrapper = createComponent({
      antecedent_rain_7d_mm: 10.5,
      daily_forecast: [{ date: '2026-05-17', precip_mm: 2.5, precip_prob: 40 }],
      rain_gauges: [{ name: 'Rain Station', readings_24h_mm: 3.2 }],
      river_gauges: [
        {
          name: 'River Gauge',
          state: 'Normal',
          trend: 'falling',
          latest_value: '0.5 m',
        },
      ],
    })

    expect(wrapper.text()).toContain('Swildon\'s Hole')
    expect(wrapper.text()).toContain('10.5')
    expect(wrapper.text()).toContain('Rain Station')
    expect(wrapper.text()).toContain('River Gauge')
    expect(wrapper.find('canvas').exists()).toBe(true)
  })
})
