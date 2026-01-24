import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import PrivacyNotice from '@/components/PrivacyNotice.vue'

describe('PrivacyNotice Component', () => {
    beforeEach(() => {
        localStorage.clear()
        vi.useFakeTimers()
    })

    it('renders after delay when not accepted', async () => {
        const wrapper = mount(PrivacyNotice, {
            global: {
                stubs: {
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-avatar': { template: '<div class="v-avatar"><slot /></div>' },
                    'v-icon': true,
                    'v-btn': { template: '<button class="v-btn"><slot /></button>' },
                    'v-fade-transition': { template: '<div><slot /></div>' }
                }
            }
        })

        // Should not show initially
        expect(wrapper.find('.cookie-banner-container').exists()).toBe(false)

        // Fast-forward 1 second
        vi.advanceTimersByTime(1000)
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.cookie-banner-container').exists()).toBe(true)
        expect(wrapper.text()).toContain('Cookies & Privacy')
    })

    it('does not render when already accepted', async () => {
        localStorage.setItem('subterra_cookies_accepted', 'true')

        const wrapper = mount(PrivacyNotice, {
            global: {
                stubs: {
                    'v-fade-transition': { template: '<div><slot /></div>' }
                }
            }
        })

        vi.advanceTimersByTime(1000)
        await wrapper.vm.$nextTick()

        expect(wrapper.find('.cookie-banner-container').exists()).toBe(false)
    })

    it('sets localStorage and hides when clicked', async () => {
        const wrapper = mount(PrivacyNotice, {
            global: {
                stubs: {
                    'v-card': { template: '<div class="v-card"><slot /></div>' },
                    'v-avatar': { template: '<div class="v-avatar"><slot /></div>' },
                    'v-icon': true,
                    'v-btn': { template: '<button class="v-btn" @click="$emit(\'click\')"><slot /></button>' },
                    'v-fade-transition': { template: '<div><slot /></div>' }
                }
            }
        })

        vi.advanceTimersByTime(1000)
        await wrapper.vm.$nextTick()

        const button = wrapper.find('.v-btn')
        await button.trigger('click')

        expect(localStorage.getItem('subterra_cookies_accepted')).toBe('true')
        expect(wrapper.find('.cookie-banner-container').exists()).toBe(false)
    })
})
