import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminRota from '@/pages/admin/rota.vue'
import axios from 'axios'
import { nextTick } from 'vue'

// Mock axios
vi.mock('axios', () => {
    const mock = {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
        create: vi.fn().mockReturnThis(),
        interceptors: {
            request: { use: vi.fn(), eject: vi.fn() },
            response: { use: vi.fn(), eject: vi.fn() }
        }
    }
    return {
        default: mock,
        ...mock
    }
})

describe('Admin Rota', () => {
    beforeEach(() => {
        vi.clearAllMocks()

        // Default mock responses
        axios.get.mockResolvedValue({
            data: { data: [] }
        })
    })

    it('renders correctly', async () => {
        const wrapper = mount(AdminRota, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-toolbar': { template: '<div class="v-toolbar"><slot /></div>' },
                    'v-toolbar-title': { template: '<div class="v-toolbar-title"><slot /></div>' },
                    'v-btn': { template: '<button class="v-btn" @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': { template: '<div class="v-icon"><slot /></div>' },
                    'v-spacer': { template: '<div class="v-spacer"></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-form': { template: '<form class="v-form"><slot /></form>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-select': { template: '<select class="v-select"></select>' },
                    'v-text-field': { template: '<input class="v-text-field" />' },
                    'v-data-table': { template: '<div class="v-data-table">Data Table</div>' },
                    'v-btn-toggle': { template: '<div class="v-btn-toggle"><slot /></div>' },
                    'v-btn': { template: '<button><slot /></button>' },
                    'v-icon': { template: '<i></i>' }
                },
                mocks: {
                    $router: {
                        back: vi.fn()
                    },
                    $toast: {
                        success: vi.fn(),
                        error: vi.fn(),
                        warning: vi.fn()
                    }
                }
            }
        })

        expect(wrapper.html()).toContain('On-Call Rota')
        expect(wrapper.exists()).toBe(true)
    })

    it('shows warning when deleting shift with active callouts', async () => {
        const mockShifts = [
            {
                id: 1,
                user: { name: 'Test Officer' },
                start_at: '2026-01-25T10:00:00Z',
                end_at: '2026-01-25T18:00:00Z'
            }
        ]

        const mockAffectedCallouts = [
            {
                id: 1,
                cave_name: 'Test Cave',
                user_name: 'Test User',
                callout_time: '2026-01-25T14:00:00Z'
            }
        ]

        axios.get.mockResolvedValue({
            data: { data: mockShifts }
        })

        axios.delete.mockRejectedValue({
            response: {
                status: 422,
                data: {
                    message: 'WARNING: This shift has 1 open callout(s) and cannot be removed without leaving them UNMONITORED!',
                    affected_callouts: mockAffectedCallouts,
                    count: mockAffectedCallouts.length
                }
            }
        })

        // Mock window.confirm and window.alert
        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
        const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => { })

        const wrapper = mount(AdminRota, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-toolbar': { template: '<div class="v-toolbar"><slot /></div>' },
                    'v-toolbar-title': { template: '<div class="v-toolbar-title"><slot /></div>' },
                    'v-btn': { template: '<button class="v-btn" @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': { template: '<div class="v-icon"><slot /></div>' },
                    'v-spacer': { template: '<div class="v-spacer"></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-form': { template: '<form class="v-form"><slot /></form>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-select': { template: '<select class="v-select"></select>' },
                    'v-text-field': { template: '<input class="v-text-field" />' },
                    'v-data-table': { template: '<div class="v-data-table">Data Table</div>' },
                    'v-btn-toggle': { template: '<div class="v-btn-toggle"><slot /></div>' }
                },
                mocks: {
                    $router: {
                        back: vi.fn()
                    },
                    $toast: {
                        success: vi.fn(),
                        error: vi.fn(),
                        warning: vi.fn()
                    }
                }
            }
        })

        await nextTick()

        // Call deleteShift
        await wrapper.vm.deleteShift(1)
        await nextTick()

        // Verify confirm was called
        expect(confirmSpy).toHaveBeenCalledWith('Remove this shift?')

        // Verify delete endpoint was called
        expect(axios.delete).toHaveBeenCalledWith('/api/admin/shifts/1')

        // Verify warning was shown
        expect(alertSpy).toHaveBeenCalled()
        const alertMessage = alertSpy.mock.calls[0][0]
        expect(alertMessage).toContain('WARNING')
        expect(alertMessage).toContain('1 open callout(s)')
        expect(alertMessage).toContain('Test Cave')
        expect(alertMessage).toContain('UNMONITORED')

        // Verify toast error was called with the message from API
        expect(wrapper.vm.$toast.error).toHaveBeenCalled()
        expect(wrapper.vm.$toast.error.mock.calls[0][0]).toContain('UNMONITORED')

        confirmSpy.mockRestore()
        alertSpy.mockRestore()
    })

    it('proceeds normally when deleting shift with no active callouts', async () => {
        axios.delete.mockResolvedValue({
            data: {
                message: 'Shift removed',
                affected_callouts: [],
                count: 0
            }
        })

        const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
        const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => { })

        const wrapper = mount(AdminRota, {
            global: {
                stubs: {
                    'v-container': { template: '<div class="v-container"><slot /></div>' },
                    'v-toolbar': { template: '<div class="v-toolbar"><slot /></div>' },
                    'v-toolbar-title': { template: '<div class="v-toolbar-title"><slot /></div>' },
                    'v-btn': { template: '<button class="v-btn" @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': { template: '<div class="v-icon"><slot /></div>' },
                    'v-spacer': { template: '<div class="v-spacer"></div>' },
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-card-title': { template: '<div class="v-card-title"><slot /></div>' },
                    'v-card-text': { template: '<div class="v-card-text"><slot /></div>' },
                    'v-form': { template: '<form class="v-form"><slot /></form>' },
                    'v-row': { template: '<div class="v-row"><slot /></div>' },
                    'v-col': { template: '<div class="v-col"><slot /></div>' },
                    'v-select': { template: '<select class="v-select"></select>' },
                    'v-text-field': { template: '<input class="v-text-field" />' },
                    'v-data-table': { template: '<div class="v-data-table">Data Table</div>' },
                    'v-btn-toggle': { template: '<div class="v-btn-toggle"><slot /></div>' }
                },
                mocks: {
                    $router: {
                        back: vi.fn()
                    },
                    $toast: {
                        success: vi.fn(),
                        error: vi.fn(),
                        warning: vi.fn()
                    }
                }
            }
        })

        await nextTick()

        await wrapper.vm.deleteShift(1)
        await nextTick()

        // Verify confirm was called
        expect(confirmSpy).toHaveBeenCalledWith('Remove this shift?')

        // Verify alert was NOT called (no warning needed)
        expect(alertSpy).not.toHaveBeenCalled()

        // Verify success toast was called
        expect(wrapper.vm.$toast.success).toHaveBeenCalledWith('Shift removed')

        confirmSpy.mockRestore()
        alertSpy.mockRestore()
    })
})
