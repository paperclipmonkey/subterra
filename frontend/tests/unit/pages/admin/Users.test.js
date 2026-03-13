import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'

// Mock vue-router
vi.mock('vue-router', () => ({
    useRouter: () => ({
        push: vi.fn()
    }),
    useRoute: () => ({
        query: {}
    })
}))

import Users from '@/pages/admin/users.vue'

// Mock mande
const mockGet = vi.fn()
const mockPut = vi.fn()
const mockDelete = vi.fn()

vi.mock('mande', () => ({
    mande: vi.fn(() => ({
        get: mockGet,
        put: mockPut,
        delete: mockDelete
    }))
}))

// Mock app store
vi.mock('@/stores/app', () => ({
    useAppStore: vi.fn(() => ({
        user: { id: 1, name: 'Test Admin', is_admin: true }
    }))
}))

// Mock notification store
vi.mock('@/stores/notifications', () => ({
    useNotificationStore: vi.fn(() => ({
        showError: vi.fn(),
        showSuccess: vi.fn()
    }))
}))

const mockUsers = [
    { id: 1, name: 'User One', email: 'one@example.com', is_admin: false, clubs: [], created_at: '2024-01-01T12:00:00Z' },
    { id: 2, name: 'User Two', email: 'two@example.com', is_admin: false, clubs: [], created_at: '2024-01-02T12:00:00Z' }
]

describe('Admin Users Page', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        mockGet.mockResolvedValue({ data: mockUsers })
    })

    it('renders user list and handles deletion', async () => {
        const wrapper = mount(Users, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-data-table': {
                        props: ['items', 'headers'],
                        template: `
              <div class="v-data-table">
                <div v-for="item in items" :key="item.id" class="user-row">
                  <span class="user-name">{{ item.name }}</span>
                  <slot name="item.actions" :item="item" />
                </div>
              </div>
            `
                    },
                    'v-btn': {
                        template: '<button class="v-btn" @click="$emit(\'click\', $event)"><slot /></button>'
                    },
                    'v-icon': true,
                    'v-tooltip': { template: '<div><slot /></div>' },
                    'v-dialog': {
                        props: ['modelValue'],
                        template: '<div v-if="modelValue" class="v-dialog"><slot /></div>'
                    },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-card-actions': { template: '<div class="v-card-actions"><slot /></div>' },
                    'v-spacer': true,
                    'v-chip': true,
                    'v-text-field': true
                }
            }
        })

        // Wait for initial fetch
        await new Promise(resolve => setTimeout(resolve, 0))
        await wrapper.vm.$nextTick()

        expect(wrapper.text()).toContain('User One')
        expect(wrapper.text()).toContain('User Two')

        // Find delete button for User One (id: 1)
        const userRows = wrapper.findAll('.user-row')
        const firstUserRow = userRows.find(row => row.text().includes('User One'))
        const deleteBtn = firstUserRow.find('.v-btn') // Deletion button is in actions slot

        // Check if dialog is hidden
        expect(wrapper.find('.v-dialog').exists()).toBe(false)

        // Click delete
        await deleteBtn.trigger('click')

        // Dialog should be visible
        expect(wrapper.find('.v-dialog').exists()).toBe(true)
        expect(wrapper.find('.v-dialog').text()).toContain('Are you sure you want to delete User One?')

        // Mock successful deletion
        mockDelete.mockResolvedValue({})

        // Click confirm in dialog
        const confirmBtn = wrapper.find('.v-dialog').findAll('.v-btn').find(btn => btn.text().includes('Delete Permanently'))
        await confirmBtn.trigger('click')

        // Verify API call
        expect(mockDelete).toHaveBeenCalled()

        // Wait for state update
        await wrapper.vm.$nextTick()

        // User One should be removed from the list
        expect(wrapper.text()).not.toContain('User One')
        expect(wrapper.text()).toContain('User Two')
    })
})
