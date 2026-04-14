import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import ClubMembershipConfirmation from '../../../src/components/ClubMembershipConfirmation.vue'
import { api } from '@/plugins/api'

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

describe('ClubMembershipConfirmation', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        // Default api mock for fetching clubs (onMounted)
        api.get.mockResolvedValue({
            data: {
                data: [
                    { id: 1, name: 'Club Alpha', slug: 'club-alpha' },
                    { id: 2, name: 'Club Beta', slug: 'club-beta' },
                ],
            },
        })
    })

    const globalConfig = {
        stubs: {
            'v-container': { template: '<div><slot /></div>' },
            'v-card': { template: '<div><slot /></div>' },
            'v-card-title': { template: '<div><slot /></div>' },
            'v-card-text': { template: '<div><slot /></div>' },
            'v-card-actions': { template: '<div><slot /></div>' },
            'v-text-field': true,
            'v-alert': { template: '<div><slot /></div>' },
            'v-list': { template: '<div><slot /></div>' },
            'v-list-item': { template: '<div><slot name="prepend"/><slot /><slot name="append"/></div>' },
            'v-avatar': { template: '<div><slot /></div>' },
            'v-icon': { template: '<div><slot /></div>' },
            'v-list-item-title': { template: '<div><slot /></div>' },
            'v-list-item-subtitle': { template: '<div><slot /></div>' },
            'v-chip': { template: '<div><slot /></div>' },
            'v-divider': true,
            'v-row': { template: '<div><slot /></div>' },
            'v-col': { template: '<div><slot /></div>' },
            'v-autocomplete': { template: '<div><slot /></div>', methods: { blur() { } } },
            'v-spacer': true,
            'v-btn': { template: '<button><slot /></button>' },
        }
    }

    it('renders correctly', async () => {
        const wrapper = mount(ClubMembershipConfirmation, {
            global: globalConfig,
            props: {
                user: { id: 1, name: 'John Doe' },
                pendingClubs: []
            }
        })

        // Wait for clubs to fetch via fetchAllClubs onMounted
        await new Promise(resolve => setTimeout(resolve, 0))
        expect(wrapper.exists()).toBe(true)
        // The component defaults to step 1 (name confirmation)
        expect(wrapper.text()).toContain('Confirm Your Identity')
    })

    it('sends join requests for selected clubs', async () => {
        const wrapper = mount(ClubMembershipConfirmation, {
            global: globalConfig,
            props: {
                user: { id: 1, name: 'John Doe' },
                pendingClubs: []
            }
        })

        // Move to step 2 directly to bypass name update API call
        wrapper.vm.step = 2

        // Wait for clubs to fetch
        await new Promise(resolve => setTimeout(resolve, 0))

        // Set selected club
        wrapper.vm.selectedClub = [1] // Select "Club Alpha" (id: 1)

        // Override api mock for the submit action
        api.post.mockResolvedValueOnce({
            data: { message: 'Join request sent successfully.' }
        })

        await wrapper.vm.submit()

        // Assert that the join endpoint was called
        expect(api.post).toHaveBeenCalledWith('/api/clubs/club-alpha/join', { club_id: 1 })

        // Assert event was emitted
        expect(wrapper.emitted()).toHaveProperty('membershipConfirmed')
    })
})
