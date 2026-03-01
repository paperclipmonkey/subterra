import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import CaveListList from '@/components/CaveListList.vue'

// Mock stores
vi.mock('@/stores/caves', () => ({
    useCaveStore: () => ({
        caves: [
            {
                id: 1,
                slug: 'test-cave',
                name: 'Test Cave',
                location_name: 'Test Location',
                location_country: 'Test Country',
                system: { length: 1000, vertical_range: 50 },
                tags: [{ tag: 'Tag1' }],
                previously_done: false
            }
        ],
        loading: false,
        getList: vi.fn(),
        applyFilters: vi.fn()
    })
}))

vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        user: { id: 1 }
    })
}))

vi.mock('@/stores/markAsDone', () => ({
    markCaveAsDone: vi.fn().mockResolvedValue(true)
}))

describe('CaveListList', () => {
    beforeEach(() => {
        setActivePinia(createPinia())
    })

    it('opens confirmation modal when Mark as Done is clicked', async () => {
        const wrapper = mount(CaveListList, {
            global: {
                provide: {
                    [Symbol.for('vuetify:display')]: { mobile: false }
                },
                plugins: [createPinia()],
                stubs: {
                    'v-container': { template: '<div><slot></slot></div>' },
                    'v-row': { template: '<div><slot></slot></div>' },
                    'v-col': { template: '<div><slot></slot></div>' },
                    'v-card': { template: '<div><slot></slot></div>' }, // Stub v-card to avoid link behavior issues in test
                    'v-img': { template: '<div><slot></slot><slot name="placeholder"></slot></div>' },
                    'v-icon': true,
                    'v-chip': true,
                    'v-chip-group': true,
                    'v-divider': true,
                    'v-btn': {
                        template: '<button type="button" @click="$emit(\'click\', $event)"><slot></slot></button>',
                        props: ['to', 'variant', 'color', 'size', 'icon'],
                        emits: ['click']
                    },
                    'v-hover': { template: '<div><slot :isHovering="false" :props="{}" /></div>' },
                    'v-dialog': { template: '<div v-if="modelValue"><slot></slot></div>', props: ['modelValue'] },
                    'v-card-title': true,
                    'v-card-text': true,
                    'v-card-actions': true,
                    'v-spacer': true
                }
            }
        })

        const markAsDoneBtn = wrapper.findAll('button').find(b => b.text().includes('Mark as Done'))
        expect(markAsDoneBtn.exists()).toBe(true)

        await markAsDoneBtn.trigger('click')

        expect(wrapper.vm.showConfirmModal).toBe(true)
        expect(wrapper.vm.caveToMark).toBeTruthy()
        expect(wrapper.vm.caveToMark.id).toBe(1)
    })
})
