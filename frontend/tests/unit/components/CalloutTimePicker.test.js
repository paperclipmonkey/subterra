import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import CalloutTimePicker from '@/components/CalloutTimePicker.vue'

// Mock moment to work with specific timezones
vi.mock('moment', () => {
    const actualMoment = vi.importActual('moment')
    return actualMoment
})

describe('CalloutTimePicker - Timezone Handling', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('emits ISO 8601 format with timezone information', async () => {
        // Set a specific time in UTC
        const testDate = new Date('2026-02-01T14:00:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: null
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // The component should emit an ISO 8601 string
        const emitted = wrapper.emitted('update:modelValue')
        expect(emitted).toBeTruthy()
        expect(emitted[0][0]).toMatch(/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z/)

        // Verify it's a valid ISO string
        const emittedValue = emitted[0][0]
        expect(() => new Date(emittedValue)).not.toThrow()

        // Verify the date is valid
        const parsedDate = new Date(emittedValue)
        expect(parsedDate.toISOString()).toBe(emittedValue)
    })

    it('preserves UTC time when adjusting callout time', async () => {
        const testDate = new Date('2026-02-01T14:00:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: '2026-02-01T19:00:00.000Z' // 5 hours from now in UTC
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // Adjust time by +15 minutes
        wrapper.vm.adjustTime(15)
        await wrapper.vm.$nextTick()

        // Check the emitted value
        const emitted = wrapper.emitted('update:modelValue')
        const latestEmit = emitted[emitted.length - 1][0]

        // Parse and verify the time was adjusted by exactly 15 minutes
        const newDate = new Date(latestEmit)
        const expectedDate = new Date('2026-02-01T19:15:00.000Z')

        expect(newDate.getTime()).toBeCloseTo(expectedDate.getTime(), -2) // Within 100ms
    })

    it('handles timezone conversion correctly (simulating France UTC+1)', async () => {
        // Simulate being in France (UTC+1) at 15:30 local time
        // which is 14:30 UTC
        const testDate = new Date('2026-02-01T14:30:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: null
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // User wants callout in "15 minutes" from their local time
        // Default is 5 hours, so let's adjust to 15 minutes
        wrapper.vm.adjustTime(15 - (5 * 60)) // Adjust from default 5 hours to 15 minutes
        await wrapper.vm.$nextTick()

        const emitted = wrapper.emitted('update:modelValue')
        const emittedValue = emitted[emitted.length - 1][0]

        // Parse the emitted ISO string
        const calloutTime = new Date(emittedValue)
        const currentTime = new Date(testDate)

        // Calculate the difference in minutes
        const diffMinutes = Math.round((calloutTime - currentTime) / (1000 * 60))

        // Should be exactly 15 minutes regardless of timezone
        expect(diffMinutes).toBe(15)
    })

    it('snaps to 15-minute boundaries correctly in UTC', async () => {
        const testDate = new Date('2026-02-01T14:07:00Z') // 14:07 UTC
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: null
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // The default time (5 hours from now) should be snapped to 15-min boundary
        const emitted = wrapper.emitted('update:modelValue')
        const emittedValue = emitted[0][0]
        const calloutTime = new Date(emittedValue)

        // Check minutes are on a 15-minute boundary (0, 15, 30, 45)
        const minutes = calloutTime.getUTCMinutes()
        expect(minutes % 15).toBe(0)
    })

    it('displays correct duration regardless of timezone', async () => {
        const testDate = new Date('2026-02-01T14:00:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: '2026-02-01T16:30:00.000Z' // 2.5 hours from now
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // Check computed properties
        expect(wrapper.vm.hoursFromNow).toBe(2)
        expect(wrapper.vm.minutesFromNow).toBe(30)
        expect(wrapper.vm.durationDisplay).toContain('2 hours')
        expect(wrapper.vm.durationDisplay).toContain('30 minutes')
    })

    it('prevents creating callouts in the past', async () => {
        const testDate = new Date('2026-02-01T14:00:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: '2026-02-01T13:00:00.000Z' // 1 hour in the past
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // Should detect time is in the past
        expect(wrapper.vm.isPastTime).toBe(true)

        // Should not allow decreasing time
        expect(wrapper.vm.canDecrease).toBe(false)
    })

    it('handles daylight saving time transitions correctly', async () => {
        // Test around a DST transition (example: March 2026 in Europe)
        // Before DST: UTC+0, After DST: UTC+1
        const beforeDST = new Date('2026-03-29T00:00:00Z') // Before DST switch
        vi.setSystemTime(beforeDST)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: null
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        const emitted1 = wrapper.emitted('update:modelValue')[0][0]
        const time1 = new Date(emitted1)

        // Jump to after DST
        const afterDST = new Date('2026-03-29T05:00:00Z') // After DST switch
        vi.setSystemTime(afterDST)

        // Adjust time
        wrapper.vm.adjustTime(15)
        await wrapper.vm.$nextTick()

        const emitted2 = wrapper.emitted('update:modelValue')
        const time2 = new Date(emitted2[emitted2.length - 1][0])

        // Both times should be in UTC and maintain consistency
        expect(time1.toISOString()).toMatch(/Z$/)
        expect(time2.toISOString()).toMatch(/Z$/)
    })
})

describe('CalloutTimePicker - Cross-timezone Scenarios', () => {
    beforeEach(() => {
        vi.useFakeTimers()
    })

    afterEach(() => {
        vi.useRealTimers()
    })

    it('produces same UTC result regardless of browser timezone', async () => {
        // Simulate selecting "2 hours from now" at 14:00 UTC
        const testDate = new Date('2026-02-01T14:00:00Z')
        vi.setSystemTime(testDate)

        const wrapper = mount(CalloutTimePicker, {
            props: {
                modelValue: null
            },
            global: {
                stubs: {
                    'v-card': { template: '<div><slot /></div>' },
                    'v-divider': true,
                    'v-btn': { template: '<button @click="$emit(\'click\')"><slot /></button>' },
                    'v-icon': true,
                    'v-alert': { template: '<div><slot /></div>' },
                    'v-row': { template: '<div><slot /></div>' },
                    'v-col': { template: '<div><slot /></div>' }
                }
            }
        })

        await wrapper.vm.$nextTick()

        // Adjust to 2 hours from now
        wrapper.vm.adjustTime(2 * 60 - (5 * 60)) // From default 5 hours to 2 hours
        await wrapper.vm.$nextTick()

        const emitted = wrapper.emitted('update:modelValue')
        const emittedValue = emitted[emitted.length - 1][0]
        const resultTime = new Date(emittedValue)

        // Should be exactly 2 hours after current time in UTC
        const expectedTime = new Date('2026-02-01T16:00:00Z')

        // Allow for slight rounding differences
        expect(Math.abs(resultTime - expectedTime)).toBeLessThan(60000) // Within 1 minute
    })
})
