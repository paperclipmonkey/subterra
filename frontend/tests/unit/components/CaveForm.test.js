import { mount, flushPromises } from '@vue/test-utils'
import CaveForm from '@/components/CaveForm.vue'
import { vi, describe, it, expect, beforeEach } from 'vitest'

// Mock maplibre-gl
vi.mock('maplibre-gl', () => ({
    LngLat: {
        convert: vi.fn((coords) => ({ lng: coords[0] || 0, lat: coords[1] || 0 }))
    }
}))

vi.mock('@indoorequal/vue-maplibre-gl', () => ({
    MglMap: { template: '<div><slot/></div>' },
    MglNavigationControl: { template: '<div></div>' },
    MglMarker: { template: '<div></div>' },
    MglGeolocateControl: { template: '<div></div>' }
}))

// Mock fetch for tags
global.fetch = vi.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
            'Cave type': [{ tag: 'Cave', assignable: true }]
        })
    })
)

describe('CaveForm.vue', () => {
    const cave = {
        name: '',
        description: '',
        location_name: '',
        location_country: '',
        location_lat: 0,
        location_lng: 0,
        tags: [],
        hero_video: null
    }

    const stubs = {
        'v-card': { template: '<div><slot /></div>' },
        'v-card-title': { template: '<div><slot /></div>' },
        'v-card-text': { template: '<div><slot /></div>' },
        'v-text-field': {
            template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
            props: ['modelValue', 'rules']
        },
        'v-textarea': {
            template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
            props: ['modelValue', 'rules']
        },
        'v-file-input': { template: '<input type="file" />' },
        'v-chip-group': { template: '<div><slot /></div>' },
        'v-chip': { template: '<span><slot /></span>' },
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'mgl-map': { template: '<div><slot /></div>' },
        'mgl-marker': { template: '<div></div>' },
        'mgl-navigation-control': { template: '<div></div>' },
        'MglGeolocateControl': { template: '<div></div>' },
        'v-hover': { template: '<div><slot :isHovering="false" :props="{}" /></div>' },
        'v-overlay': { template: '<div><slot /></div>' },
        'v-img': { template: '<div><slot /></div>' },
        'v-btn': { template: '<button><slot /></button>' },
        'v-icon': { template: '<i><slot /></i>' },
        'MilkdownEditor': { template: '<div><slot /></div>' }
    }

    it('emits updates when fields change', async () => {
        const wrapper = mount(CaveForm, {
            props: {
                modelValue: cave
            },
            global: { stubs }
        })

        const nameInput = wrapper.findAll('input')[0]
        await nameInput.setValue('New Cave Name')

        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
        const lastEmit = wrapper.emitted('update:modelValue').pop()[0]
        expect(lastEmit.name).toBe('New Cave Name')
    })

    it('syncs coordinates with map marker', async () => {
        const wrapper = mount(CaveForm, {
            props: {
                modelValue: { ...cave, location_lat: 10, location_lng: 20 }
            },
            global: { stubs }
        })

        await flushPromises()
        expect(wrapper.vm.coordinates.lat).toBe(10)
        expect(wrapper.vm.coordinates.lng).toBe(20)
    })
})
