
import { mount } from '@vue/test-utils'
import { describe, it, expect, vi } from 'vitest'
import RouteForm from '@/components/routes/RouteForm.vue'

// Mock api plugin
vi.mock('@/plugins/api', () => ({
    api: {
        get: vi.fn(() => Promise.resolve({ data: { data: { caves: [] } } })),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    }
}))

// Mock dependencies
vi.mock('@/components/MilkdownEditor.vue', () => ({
    default: { template: '<div class="milkdown-editor"></div>' }
}))

// Mock utilities
vi.mock('@/utilities.js', () => ({
    convertFileToBase64: vi.fn().mockResolvedValue({ data: 'base64data' })
}))

// Mock router
vi.mock('vue-router', () => ({
    useRouter: vi.fn(() => ({
        push: vi.fn(),
        replace: vi.fn(),
    })),
    useRoute: vi.fn(() => ({
        params: { id: '1' },
    })),
    onBeforeRouteLeave: vi.fn(),
}))

// Mock store
const mockUseAppStore = vi.fn()
vi.mock('@/stores/app', () => ({
    useAppStore: () => ({
        user: { is_admin: true }
    })
}))

vi.mock('@/stores/notifications', () => ({
    useNotificationStore: () => ({
        showSuccess: vi.fn(),
        showError: vi.fn(),
        showWarning: vi.fn(),
        showInfo: vi.fn(),
    })
}))

describe('RouteForm.vue', () => {
    const defaultProps = {
        caveSystemId: 1,
        initialRoute: {
            name: '',
            entrance_id: null,
            exit_id: null,
            grade: null,
            duration: '',
            description: '',
            tackle: [],
            media: []
        }
    }

    const createWrapper = (props = {}) => {
        return mount(RouteForm, {
            props: { ...defaultProps, ...props },
            global: {
                stubs: {
                    'v-container': { template: '<div><slot /></div>' },
                    'v-form': { template: '<form @submit.prevent><slot /></form>' },
                    'v-text-field': { template: '<input type="text" />', props: ['modelValue'] },
                    'v-file-input': { template: '<input type="file" />' },
                    'v-img': { template: '<img />' },
                    'v-autocomplete': { template: '<select />' },
                    'v-select': { template: '<select />' },
                    'v-combobox': { template: '<select />' },
                    'v-checkbox': { template: '<input type="checkbox" />' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' },
                    'v-icon': { template: '<span></span>' },
                    'v-list': { template: '<ul><slot /></ul>' },
                    'v-list-item': { template: '<li><slot /></li>' },
                    'v-list-item-title': { template: '<div><slot /></div>' },
                    'v-card': { template: '<div><slot /></div>' },
                    'v-card-text': { template: '<div><slot /></div>' },
                    'v-divider': { template: '<hr />' },
                }
            }
        })
    }

    it('renders correctly', () => {
        const wrapper = createWrapper()
        expect(wrapper.exists()).toBe(true)
    })

    it('adds tackle item when "Add Tackle" button is clicked', async () => {
        const wrapper = createWrapper()
        const addTackleBtn = wrapper.findAll('button').find(b => b.text().includes('Add Tackle'))

        await addTackleBtn.trigger('click')

        expect(wrapper.vm.route.tackle).toHaveLength(1)
        expect(wrapper.vm.route.tackle[0].type).toBe('srt_rope')
    })

    it('displays existing media correctly', () => {
        const props = {
            initialRoute: {
                ...defaultProps.initialRoute,
                media: [
                    { id: 1, path: 'img1.jpg', caption: 'Img 1', type: 'photo' },
                    { id: 2, path: 'doc.pdf', caption: 'Doc 1', type: 'pdf' }
                ]
            }
        }
        const wrapper = createWrapper(props)
        expect(wrapper.text()).toContain('Existing Media')
        expect(wrapper.text()).toContain('Img 1')
        expect(wrapper.text()).toContain('Doc 1')
    })

    it('marks existing media for deletion', async () => {
        const props = {
            initialRoute: {
                ...defaultProps.initialRoute,
                media: [{ id: 10, path: 'img1.jpg', caption: 'Img 1', type: 'photo' }]
            }
        }
        const wrapper = createWrapper(props)

        // Find delete button for existing media (assuming it's the one in the existing media section)
        // Since we stubbed v-btn, we need to verify logic via vm or finding the button by class/icon logic if visible
        // For simplicity, we can call the method directly or improve finding logic.
        // Let's call method directly to test logic quickly given stubs complexity.

        wrapper.vm.markMediaForDeletion(0, 10)

        expect(wrapper.vm.route.media).toHaveLength(0)
        expect(wrapper.vm.deletedMediaIds).toContain(10)
    })
})
