import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import MediaViewModal from '@/components/MediaViewModal.vue'

const STUBS = {
  'v-dialog': { template: '<div><slot /></div>' },
  'v-card': { template: '<div><slot /></div>' },
  'v-btn': { template: '<button :aria-label="ariaLabel" @click="$emit(\'click\', $event)"><slot /></button>', props: ['ariaLabel'] },
  'v-img': { template: '<div class="v-img" :data-src="src" />', props: ['src'] },
  'v-icon': true,
  'v-divider': true,
  'v-spacer': true,
  'v-progress-circular': true,
  'router-link': { template: '<a><slot /></a>' },
}

const gallery = [
  { id: 1, url: 'https://cdn/one.jpg', title: 'Entrance series', type: 'image' },
  { id: 2, url: 'https://cdn/two.jpg', title: 'Sump pool', type: 'image' },
  { id: 3, url: 'https://cdn/three.jpg', title: 'Main chamber', type: 'image' },
]

const mountModal = (props = {}) => mount(MediaViewModal, {
  props: { modelValue: true, media: gallery[1], items: gallery, ...props },
  global: { stubs: STUBS },
})

describe('MediaViewModal carousel', () => {
  beforeEach(() => {
    document.body.innerHTML = ''
  })

  it('opens on the photo that was clicked, not the first one', () => {
    const wrapper = mountModal()
    expect(wrapper.vm.currentMedia.id).toBe(2)
    expect(wrapper.text()).toContain('2 / 3')
  })

  it('steps forward and backward through the gallery', async () => {
    const wrapper = mountModal()

    wrapper.vm.showNext()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(3)

    wrapper.vm.showPrevious()
    wrapper.vm.showPrevious()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(1)
  })

  it('wraps around at both ends', async () => {
    const wrapper = mountModal({ media: gallery[2] })

    wrapper.vm.showNext()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(1)

    wrapper.vm.showPrevious()
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(3)
  })

  it('responds to the arrow keys', async () => {
    const wrapper = mountModal({ media: gallery[0] })

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(2)

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }))
    await wrapper.vm.$nextTick()
    expect(wrapper.vm.currentMedia.id).toBe(1)

    wrapper.unmount()
  })

  it('locates the opened photo even when the parent adds context to it', () => {
    // Trip.vue spreads trip_id/trip_name onto each item, so identity comparison
    // would not find it — the modal matches on the underlying record instead.
    const enriched = { ...gallery[2], trip_id: 'abc', trip_name: 'Sunday pull-through' }
    const wrapper = mountModal({ media: enriched })

    expect(wrapper.vm.currentMedia.id).toBe(3)
    expect(wrapper.text()).toContain('3 / 3')
  })

  it('hides the carousel controls for a lone image', () => {
    const wrapper = mount(MediaViewModal, {
      props: { modelValue: true, media: gallery[0] },
      global: { stubs: STUBS },
    })

    expect(wrapper.text()).not.toContain('1 / 1')
    expect(wrapper.find('[aria-label="Next photo"]').exists()).toBe(false)
    expect(wrapper.vm.currentMedia.url).toBe('https://cdn/one.jpg')
  })

  it('does not move when arrow keys are pressed with no gallery', async () => {
    const wrapper = mount(MediaViewModal, {
      props: { modelValue: true, media: gallery[0] },
      global: { stubs: STUBS },
    })

    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }))
    await wrapper.vm.$nextTick()

    expect(wrapper.vm.currentMedia.url).toBe('https://cdn/one.jpg')
    wrapper.unmount()
  })
})
