import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { id: '1' } }),
    useRouter: () => ({ push: vi.fn(), go: vi.fn() })
}))

vi.mock('@/stores/notifications', () => ({
    useNotificationStore: vi.fn(() => ({
        showSuccess: vi.fn(),
        showError: vi.fn()
    }))
}))

const mockGet = vi.fn()
vi.mock('@/plugins/api.js', () => ({
    api: {
        get: (...args) => mockGet(...args),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn()
    }
}))

import SuggestedEditDetail from '@/pages/admin/suggested-edits/[id].vue'

// Minimal stubs — keep v-list-item rendering so field labels appear in text
const STUBS = {
    'v-container': { template: '<div><slot /></div>' },
    'v-btn': { template: '<button @click="$emit(\'click\', $event)"><slot /></button>' },
    'v-icon': true,
    'v-chip': { template: '<span><slot /></span>' },
    'v-card': { template: '<div class="v-card"><slot /></div>' },
    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
    'v-card-actions': { template: '<div class="v-card-actions"><slot /></div>' },
    'v-list': { template: '<div class="v-list"><slot /></div>' },
    'v-list-item': { template: '<div class="v-list-item"><slot /></div>' },
    'v-divider': true,
    'v-alert': { template: '<div class="v-alert"><slot /></div>' },
    'v-spacer': true,
    'v-checkbox': true,
    'v-img': true,
    'v-row': { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-chip-group': { template: '<div><slot /></div>' },
    'v-list-item-title': { template: '<div><slot /></div>' },
    'v-dialog': { template: '<div><slot /></div>' },
    'v-textarea': true,
    'router-link': { template: '<a><slot /></a>' }
}

const makeSuggestion = (overrides = {}) => ({
    id: 1,
    status: 'pending',
    suggestable_type: 'App\\Models\\Cave',
    suggestable_id: 42,
    user: { id: 1, name: 'Test User' },
    created_at: '2024-01-01T12:00:00Z',
    suggestable: {
        id: 42,
        name: 'Test Cave',
        slug: 'test-cave',
        description: 'The original description.',
        access_info: 'Original access info.',
        location_lat: 51.8158,
        location_lng: -3.57995,
        location_alt: 304,
        tags: []
    },
    original_data: {},
    suggested_data: {
        name: 'Test Cave',
        description: 'The original description.',
        access_info: 'Original access info.',
        location_lat: '51.8158',
        location_lng: '-3.57995',
        location_alt: '304',
        tags: []
    },
    ...overrides
})

const mountAndFetch = async (overrides = {}) => {
    mockGet.mockResolvedValue({ data: makeSuggestion(overrides) })
    const wrapper = mount(SuggestedEditDetail, { global: { stubs: STUBS } })
    await flushPromises()
    return wrapper
}

describe('SuggestedEdit [id] page — changedFields comparison', () => {
    beforeEach(() => {
        vi.clearAllMocks()
    })

    it('shows no changed fields when suggested_data matches the live model', async () => {
        const wrapper = await mountAndFetch()

        // The "no differences" alert should be visible; no field labels rendered
        expect(wrapper.text()).toContain('no differences')
        expect(wrapper.text()).not.toContain('DESCRIPTION')
        expect(wrapper.text()).not.toContain('ACCESS INFO')
        expect(wrapper.text()).not.toContain('LOCATION LAT')
    })

    it('does not flag description or access_info when Milkdown adds a trailing newline', async () => {
        const wrapper = await mountAndFetch({
            suggested_data: {
                name: 'Test Cave',
                description: 'The original description.\n',  // Milkdown trailing newline
                access_info: 'Original access info.\n',       // Milkdown trailing newline
                location_lat: '51.8158',
                location_lng: '-3.57995',
                location_alt: '304',
                tags: []
            }
        })

        expect(wrapper.text()).not.toContain('DESCRIPTION')
        expect(wrapper.text()).not.toContain('ACCESS INFO')
    })

    it('does not flag numeric location fields submitted as strings', async () => {
        const wrapper = await mountAndFetch()

        expect(wrapper.text()).not.toContain('LOCATION LAT')
        expect(wrapper.text()).not.toContain('LOCATION LNG')
        expect(wrapper.text()).not.toContain('LOCATION ALT')
    })

    it('shows DESCRIPTION when content genuinely changes', async () => {
        const wrapper = await mountAndFetch({
            suggested_data: {
                name: 'Test Cave',
                description: 'Updated description with new content.\n',
                access_info: 'Original access info.',
                location_lat: '51.8158',
                location_lng: '-3.57995',
                location_alt: '304',
                tags: []
            }
        })

        expect(wrapper.text()).toContain('DESCRIPTION')
        expect(wrapper.text()).not.toContain('ACCESS INFO')
    })

    it('shows LOCATION LAT when value genuinely changes', async () => {
        const wrapper = await mountAndFetch({
            suggested_data: {
                name: 'Test Cave',
                description: 'The original description.',
                access_info: 'Original access info.',
                location_lat: '51.9999',  // Actually changed
                location_lng: '-3.57995',
                location_alt: '304',
                tags: []
            }
        })

        expect(wrapper.text()).toContain('LOCATION LAT')
        expect(wrapper.text()).not.toContain('LOCATION LNG')
    })
})

