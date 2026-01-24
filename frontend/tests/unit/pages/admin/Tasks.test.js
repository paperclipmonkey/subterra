import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import Tasks from '@/pages/admin/tasks.vue'

// Mock axios
const mockTasks = {
    caves_no_photo: [
        { id: 1, name: 'Cave One', slug: 'cave-one', location_name: 'Loc A' }
    ],
    caves_no_description: [],
    caves_low_tags: [
        { id: 2, name: 'Cave Two', slug: 'cave-two', tags_count: 1 }
    ],
    systems_no_references: [
        { id: 10, name: 'System Ref Missing', slug: 'sys-ref-missing' }
    ],
    systems_no_files: []
}

vi.mock('axios', () => ({
    default: {
        get: vi.fn(() => Promise.resolve({ data: mockTasks }))
    }
}))

describe('Admin Tasks Page', () => {
    it('renders task lists correctly', async () => {
        const wrapper = mount(Tasks, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-list': { template: '<div class="v-list"><slot /></div>' },
                    'v-list-item': { template: '<div class="v-list-item" v-bind="$attrs"><slot /></div>' },
                    'v-list-item-title': { template: '<div class="v-list-item-title"><slot /></div>' },
                    'v-list-item-subtitle': { template: '<div class="v-list-item-subtitle"><slot /></div>' },
                    'v-icon': true,
                    'v-chip': { template: '<span><slot/></span>' },
                    'v-divider': true,
                    'v-snackbar': true,
                    'v-btn': true
                }
            }
        })

        // Wait for fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        // Check Headers
        expect(wrapper.text()).toContain('Data Quality Tasks')

        // Check Counts (Chips)
        // Missing Photos: 1
        expect(wrapper.text()).toContain('Missing Photos 1')
        expect(wrapper.text()).toContain('Cave One')

        // Low Tags: 1
        expect(wrapper.text()).toContain('Low Tags (< 3) 1')
        expect(wrapper.text()).toContain('Cave Two')
        expect(wrapper.text()).toContain('Has 1 tags')

        // Missing Refs: 1
        expect(wrapper.text()).toContain('Systems No References 1')
        expect(wrapper.text()).toContain('System Ref Missing')

        // Empty Lists ("Good")
        expect(wrapper.text()).toContain('Missing Descriptions 0')
        // We expect "Good" text for empty lists. Since we have multiple empty lists, "Good" appears multiple times.
        // We can check if "Good" is present.
        expect(wrapper.text()).toContain('Good')

        // Check Link structure (prop check)
        const listItems = wrapper.findAll('.v-list-item')
        // Cave One -> /caves/cave-one
        // Cave Two -> /caves/cave-two
        // System Ref Missing -> /cave-systems/10/edit

        const caveLink = listItems.find(w => w.attributes('to') === '/caves/cave-one')
        expect(caveLink).toBeDefined()

        const sysLink = listItems.find(w => w.attributes('to') === '/cave-systems/10/edit')
        expect(sysLink).toBeDefined()
    })
})
