
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import Dashboard from '@/pages/admin/dashboard.vue'
import { Line } from 'vue-chartjs'

// Mock mande
const mockGet = vi.fn()
vi.mock('mande', () => ({
    mande: () => ({
        get: mockGet
    })
}))

// Mock Chart.js register globally to avoid errors during test setup
vi.mock('chart.js', () => ({
    Chart: { register: vi.fn() },
    CategoryScale: {},
    LinearScale: {},
    PointElement: {},
    LineElement: {},
    Title: {},
    Tooltip: {},
    Legend: {},
    register: vi.fn()
}))

// Mock vue-chartjs Line component
vi.mock('vue-chartjs', () => ({
    Line: {
        name: 'Line',
        template: '<div class="mock-line-chart"></div>',
        props: ['data', 'options']
    }
}))

describe('Dashboard.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('fetches data and renders chart when data is present', async () => {
        const mockData = [
            { type: 'Cave', name: 'Cave 1', total_interactions: 10, sparkline: [1, 2, 3], identifier: 'c1' },
            { type: 'Trip', name: 'Trip 1', total_interactions: 5, sparkline: [0, 1, 0], identifier: 't1' }
        ]
        const mockLabels = ['2023-01-01', '2023-01-02', '2023-01-03']

        mockGet.mockResolvedValue({
            labels: mockLabels,
            data: mockData
        })

        const wrapper = mount(Dashboard, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-icon': true
                }
            }
        })

        // Wait for onMounted fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()
        await wrapper.vm.$nextTick() // sometimes need double tick for reactive updates

        expect(mockGet).toHaveBeenCalled()

        // Find the mocked Line component
        // Note: wrapper.findComponent(Line) might not work if import is mocked?
        // Let's find via class if template rendered
        const lineComponent = wrapper.find('.mock-line-chart')
        expect(lineComponent.exists()).toBe(true)

        // Verify the data passed to the chart
        // Since we are mocking the module, we can spy on the component usage if we mounted real component or check props if we use stubs
        // But since we stubbed vue-chartjs with an object, let's see if we can get the component instance

        // Alternative: verify chartData.value in vm (if expose works, but setup script exposes only returned)
        // However, we can use wrapper.findComponent(MockLine)

        const chartWrapper = wrapper.findComponent({ name: 'Line' })
        expect(chartWrapper.exists()).toBe(true)

        const props = chartWrapper.props()
        expect(props.data.labels).toEqual(mockLabels)
        expect(props.data.datasets).toHaveLength(2)
        expect(props.data.datasets[0].label).toContain('Cave 1')
        expect(props.data.datasets[0].data).toEqual([1, 2, 3])
    })

    it('shows "No interactions" message when empty', async () => {
        mockGet.mockResolvedValue({
            labels: [],
            data: []
        })

        const wrapper = mount(Dashboard, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-title': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-alert': { template: '<div class="alert"><slot /></div>' },
                    'v-icon': true
                }
            }
        })

        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('No API interactions recorded yet')
        expect(wrapper.find('.mock-line-chart').exists()).toBe(false)
    })
})
