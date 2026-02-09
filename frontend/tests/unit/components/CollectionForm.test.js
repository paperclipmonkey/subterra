import { mount } from '@vue/test-utils'
import CollectionForm from '@/components/CollectionForm.vue'
import { describe, it, expect, vi } from 'vitest'

// Mock stores
vi.mock('@/stores/caves', () => ({
    useCaveStore: () => ({
        caves: [],
        getList: vi.fn()
    })
}))

describe('CollectionForm.vue', () => {
    const stubs = {
        'v-form': { template: '<form><slot /></form>', methods: { validate: () => true } },
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'v-text-field': { template: '<input />' },
        'v-select': { template: '<select />' },
        'v-file-input': { template: '<input type="file" />' },
        'v-autocomplete': { template: '<input />' },
        'v-list': { template: '<div><slot /></div>' },
        'v-list-item': { template: '<div><slot /></div>' },
        'v-list-item-title': { template: '<div><slot /></div>' },
        'v-list-item-subtitle': { template: '<div><slot /></div>' },
        'v-divider': { template: '<hr />' },
        'v-btn': { template: '<button><slot /></button>' },
        'MilkdownEditor': {
            props: ['modelValue'],
            // Expose Value class to find it easily
            template: '<textarea class="milkdown-editor" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />'
        }
    }

    it('initializes cave notes from pivot description correctly', async () => {
        const collection = {
            name: 'Test Collection',
            caves: [
                {
                    id: 1,
                    name: 'Cave 1',
                    pivot: { description: 'Pivot Note' }
                    // playlist_description is undefined here, simulating API response
                }
            ]
        }

        const wrapper = mount(CollectionForm, {
            props: {
                modelValue: collection
            },
            global: {
                stubs
            }
        })

        // Find the second MilkdownEditor (the first is for collection description, check index)
        // Collection description is first.
        // Cave notes are inside the list loop.

        const editors = wrapper.findAll('.milkdown-editor')
        expect(editors.length).toBeGreaterThan(1)

        // The first one is collection description. The subsequent ones are caves.
        const caveNoteEditor = editors[1]

        expect(caveNoteEditor.element.value).toBe('Pivot Note')
    })
})
