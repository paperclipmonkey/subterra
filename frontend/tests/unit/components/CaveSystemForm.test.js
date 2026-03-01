import { mount } from '@vue/test-utils'
import CaveSystemForm from '@/components/CaveSystemForm.vue'
import { describe, it, expect } from 'vitest'

describe('CaveSystemForm.vue', () => {
    const system = {
        name: '',
        description: '',
        length: 0,
        vertical_range: 0,
        slug: '',
        references: ''
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
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'v-list': { template: '<div><slot /></div>' },
        'v-list-item': { template: '<div><slot /></div>' },
        'v-list-item-content': { template: '<div><slot /></div>' },
        'v-file-input': { template: '<input type="file" />' },
        'v-autocomplete': { template: '<input />' }
    }

    global.fetch = vi.fn(() => Promise.resolve({ ok: true, json: () => Promise.resolve([]) }))

    it('emits updates when fields change', async () => {
        const wrapper = mount(CaveSystemForm, {
            props: {
                modelValue: system
            },
            global: { stubs }
        })

        const nameInput = wrapper.find('input')
        await nameInput.setValue('New System Name')

        expect(wrapper.emitted('update:modelValue')).toBeTruthy()
        const lastEmit = wrapper.emitted('update:modelValue').pop()[0]
        expect(lastEmit.name).toBe('New System Name')
    })
})
