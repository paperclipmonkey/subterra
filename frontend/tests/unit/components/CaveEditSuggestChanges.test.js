import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { vi, describe, it, expect, beforeEach } from 'vitest'
import CaveEdit from '@/components/CaveEdit.vue'
import { api } from '@/plugins/api'

// Uses the real CaveForm on purpose: it reshapes the cave as it loads (tags,
// image credit fields, rounded coordinates), which is what made an untouched
// form look "changed" and let empty suggestions through.

vi.mock('@/plugins/api', () => ({
    api: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() }
}))

vi.mock('maplibre-gl', () => ({
    LngLat: { convert: vi.fn((coords) => ({ lng: coords[0] || 0, lat: coords[1] || 0 })) }
}))

vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { name: 'MglMap', template: '<div><slot /></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { name: 'MglMarker', template: '<div><slot /></div>', props: ['coordinates'] },
    MglGeolocateControl: { template: '<div></div>' },
}))

vi.mock('@/stores/app', () => ({
    useAppStore: vi.fn(() => ({ user: { id: 'aB3dEfG', is_admin: false } }))
}))

vi.mock('@/stores/notifications', () => ({
    useNotificationStore: vi.fn(() => ({ showSuccess: vi.fn(), showError: vi.fn() }))
}))

const CAVE = {
    id: 1,
    name: 'Axbridge Ochre Cavern',
    slug: 'axbridge-ochre-cavern',
    description: 'A short description.',
    access_info: 'Ask at the farm.',
    location_name: 'Axbridge',
    location_country: 'England',
    // More precision than the map keeps: CaveForm rounds these on load.
    location_lat: 51.2871234,
    location_lng: -2.8201234,
    location_alt: 120,
    // Richer than the {category, tag, type} objects CaveForm writes back.
    tags: [{ id: 3, tag: 'Mine', category: 'Cave type', type: 'cave', description: 'An old mine' }],
    hero_image: { id: 9, type: 'hero', filename: 'hero.jpg', url: 'https://cdn.example/hero.jpg', title: 'Entrance', photographer: null, copyright: null },
    entrance_image: null,
    hero_video: null,
    system: { name: 'Axbridge System', caves: [], description: '', length: 0, vertical_range: 0, tags: [] },
    can_manage: false,
}

const stubs = {
    'v-container': { template: '<div><slot /></div>' },
    'v-row': { template: '<div><slot /></div>' },
    'v-col': { template: '<div><slot /></div>' },
    'v-card': { template: '<div><slot /></div>' },
    'v-card-title': { template: '<div><slot /></div>' },
    'v-card-subtitle': { template: '<div><slot /></div>' },
    'v-card-text': { template: '<div><slot /></div>' },
    'v-toolbar-title': { template: '<h1><slot /></h1>' },
    'v-divider': { template: '<hr />' },
    'v-form': { template: '<form><slot /></form>', methods: { validate: () => Promise.resolve({ valid: true }) } },
    'v-btn': { props: ['disabled', 'type'], template: '<button :type="type" :disabled="disabled"><slot /></button>' },
    'v-icon': { template: '<i />' },
    'v-text-field': {
        props: ['modelValue', 'label'],
        template: '<input :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
    },
    'v-textarea': { props: ['modelValue'], template: '<textarea :value="modelValue" />' },
    'v-file-input': { template: '<input type="file" />' },
    'v-chip-group': { template: '<div><slot /></div>' },
    'v-chip': { template: '<span><slot /></span>' },
    'v-select': true,
    'v-snackbar': true,
    'v-hover': { template: '<div><slot :isHovering="false" :props="{}" /></div>' },
    'v-overlay': { template: '<div><slot /></div>' },
    'v-img': { template: '<div />' },
    AppMap: { template: '<div />' },
    MilkdownEditor: { template: '<div />' },
    MarkdownRenderer: { template: '<div />' },
    CaveSystemFilesManager: true,
}

const router = createRouter({ history: createWebHistory(), routes: [{ path: '/caves/:id/edit', component: CaveEdit }] })

const mountEdit = async () => {
    router.push('/caves/1/edit')
    await router.isReady()
    const wrapper = mount(CaveEdit, { global: { plugins: [router], stubs } })
    await flushPromises()
    await flushPromises()
    return wrapper
}

describe('CaveEdit suggest changes button', () => {
    beforeEach(() => {
        vi.clearAllMocks()
        api.get.mockImplementation((url) => {
            if (url === '/api/tags') return Promise.resolve({ data: { 'Cave type': [{ tag: 'Mine', assignable: true }] } })
            if (url.startsWith('/api/caves/')) return Promise.resolve({ data: { data: structuredClone(CAVE) } })
            return Promise.resolve({ data: {} })
        })
    })

    it('stays disabled while nothing has been edited', async () => {
        const wrapper = await mountEdit()
        const submit = wrapper.find('button[type="submit"]')

        expect(submit.text()).toBe('Suggest Changes')
        expect(submit.attributes('disabled')).toBeDefined()
        expect(wrapper.vm.isDirty).toBe(false)
    })

    it('enables once a field is edited, and disables again if it is put back', async () => {
        const wrapper = await mountEdit()
        const name = wrapper.findAll('input').find(i => i.element.value === CAVE.name)

        await name.setValue('Axbridge Ochre Mine')
        await flushPromises()
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined()

        await name.setValue(CAVE.name)
        await flushPromises()
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
    })
})
