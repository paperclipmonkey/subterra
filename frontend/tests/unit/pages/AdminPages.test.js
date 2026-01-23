import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminPagesIndex from '@/pages/admin/pages/index.vue'

// Mock mande
const mockGet = vi.fn()
const mockDelete = vi.fn()
vi.mock('mande', () => ({
    mande: () => ({
        get: mockGet,
        delete: mockDelete,
    })
}))

describe('AdminPagesIndex.vue', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('fetches and displays pages', async () => {
        mockGet.mockResolvedValue([
            { id: 1, title: 'Page 1', slug: 'page-1', access_count: 10 },
            { id: 2, title: 'Page 2', slug: 'page-2', access_count: 5 },
        ])

        const wrapper = mount(AdminPagesIndex, {
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-text-field': true,
                    'v-data-table': {
                        props: ['items'],
                        template: '<div><div v-for="item in items" :key="item.id" class="row">{{ item.title }}</div></div>'
                    },
                    'v-icon': true,
                    'ActiveCalloutMap': { template: '<div>Map</div>' },
                    'v-expand-transition': { template: '<div><slot /></div>' },
                    'v-progress-linear': true,
                    'v-chip': true,
                    'v-divider': true,
                    'v-alert': true,
                    'v-card-text': true,
                    'v-card-title': true,
                    'v-card-actions': true,
                    'v-expansion-panels': { template: '<div><slot /></div>' },
                    'v-expansion-panel': { template: '<div><slot /></div>' },
                    'v-expansion-panel-header': { template: '<div><slot /></div>' },
                    'v-expansion-panel-content': { template: '<div><slot /></div>' }
                }
            }
        })

        // Wait for onMounted fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(mockGet).toHaveBeenCalled()
        expect(wrapper.text()).toContain('Manage Pages')
        expect(wrapper.text()).toContain('Page 1')
        expect(wrapper.text()).toContain('Page 2')
    })
})
