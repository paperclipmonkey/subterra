import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'

// The route form submits images as real files via multipart/form-data. These
// tests lock that in — the previous implementation base64-encoded the image
// into a JSON body, which the backend rejected with "must be a file".

const { apiMock, notificationsMock, appStoreState } = vi.hoisted(() => ({
  apiMock: { get: vi.fn(), post: vi.fn(), put: vi.fn() },
  notificationsMock: { showSuccess: vi.fn(), showError: vi.fn() },
  appStoreState: { user: { is_admin: true } },
}))

vi.mock('@/plugins/api', () => ({ api: apiMock }))
vi.mock('@/stores/notifications', () => ({ useNotificationStore: () => notificationsMock }))
vi.mock('@/stores/app', () => ({ useAppStore: () => appStoreState }))

// onBeforeRouteLeave needs an active router; no-op it for the unit test.
vi.mock('vue-router', () => ({ onBeforeRouteLeave: vi.fn() }))

import RouteForm from '@/components/routes/RouteForm.vue'

// Override the globally-stubbed Vuetify components so the form's slot content
// actually renders and we can drive the file input + submit.
const mountForm = (initialRoute, caveSystemId = 5) =>
  mount(RouteForm, {
    props: { initialRoute, caveSystemId },
    global: {
      stubs: {
        'v-container': { template: '<div><slot /></div>' },
        'v-row': { template: '<div><slot /></div>' },
        'v-col': { template: '<div><slot /></div>' },
        'v-form': {
          inheritAttrs: false,
          methods: { validate: () => ({ valid: true }) },
          template: '<form v-bind="$attrs"><slot /></form>',
        },
        'v-file-input': {
          inheritAttrs: false,
          template: '<input type="file" v-bind="$attrs" />',
        },
        // Real input wired to v-model so we can read/drive credit fields by label.
        'v-text-field': {
          props: ['modelValue', 'label'],
          emits: ['update:modelValue'],
          template: '<input type="text" :data-label="label" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />',
        },
      },
    },
  })

const setField = async (wrapper, label, value) => {
  const input = wrapper.find(`input[data-label="${label}"]`)
  await input.setValue(value)
}

const selectHeroImage = async (wrapper, file) => {
  // The hero image input is the first file input in the form.
  const input = wrapper.findAll('input[type=file]')[0]
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

const submit = async (wrapper) => {
  await wrapper.find('form').trigger('submit')
  await flushPromises()
}

describe('RouteForm — image submission', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    appStoreState.user = { is_admin: true }
    apiMock.get.mockResolvedValue({ data: { data: { caves: [] } } })
    apiMock.post.mockResolvedValue({ data: {} })
  })

  it('sends the hero image as a real file via multipart when creating', async () => {
    const wrapper = mountForm({ name: 'Entrance Series', tackle: [] })
    await flushPromises() // onMounted cave fetch

    const file = new File(['fake-bytes'], 'hero.png', { type: 'image/png' })
    await selectHeroImage(wrapper, file)
    await submit(wrapper)

    expect(apiMock.post).toHaveBeenCalledTimes(1)
    const [url, body, config] = apiMock.post.mock.calls[0]

    expect(url).toBe('/api/cave_systems/5/routes')
    expect(body).toBeInstanceOf(FormData)
    expect(config.headers['Content-Type']).toBe('multipart/form-data')

    // The file rides under the nested hero_image[data] key (cave-form convention).
    const hero = body.get('hero_image[data]')
    expect(hero).toBeInstanceOf(File)
    expect(hero.name).toBe('hero.png')

    // Creation must not spoof a PUT, and must never smuggle a base64 string.
    expect(body.get('_method')).toBeNull()
    for (const [, value] of body.entries()) {
      expect(String(value)).not.toContain('data:image')
    }
  })

  it('sends the photographer and copyright credits with the hero image', async () => {
    const wrapper = mountForm({ name: 'Entrance Series', tackle: [] })
    await flushPromises()

    const file = new File(['fake-bytes'], 'hero.png', { type: 'image/png' })
    await selectHeroImage(wrapper, file)

    // Credit fields only appear once an image is present.
    await setField(wrapper, 'Photographer', 'Jane Doe')
    await setField(wrapper, 'Copyright', '© Jane Doe')

    await submit(wrapper)

    const [, body] = apiMock.post.mock.calls[0]
    expect(body.get('hero_image[data]')).toBeInstanceOf(File)
    expect(body.get('hero_image[photographer]')).toBe('Jane Doe')
    expect(body.get('hero_image[copyright]')).toBe('© Jane Doe')
  })

  it('spoofs PUT and keeps the existing image while still sending credits when updating', async () => {
    const wrapper = mountForm({
      id: 42,
      slug: 'entrance-series',
      name: 'Entrance Series',
      hero_image: { url: 'https://cdn.example.com/routes/existing.jpg', photographer: 'Orig', copyright: '' },
      tackle: [],
    })
    await flushPromises()

    await submit(wrapper)

    expect(apiMock.post).toHaveBeenCalledTimes(1)
    const [url, body] = apiMock.post.mock.calls[0]

    expect(url).toBe('/api/routes/entrance-series')
    expect(body.get('_method')).toBe('PUT')
    // No new file is uploaded — the existing image URL must never be resent as
    // hero_image[data] (it would fail the backend's `file` rule).
    expect(body.get('hero_image[data]')).toBeNull()
    // Existing credits are preserved so an unrelated edit doesn't wipe them.
    expect(body.get('hero_image[photographer]')).toBe('Orig')
  })

  it('keeps the saved entrance/exit when editing a single-cave system', async () => {
    // Only one cave in the system — the new-route auto-prefill must NOT run on
    // an edit, or it would clobber an entrance that lives in another system.
    apiMock.get.mockResolvedValue({
      data: { data: { caves: [{ id: 1, name: 'Only Cave', slug: 'only-cave' }] } },
    })

    const wrapper = mountForm({
      id: 7,
      slug: 'my-route',
      name: 'My Route',
      entrance_id: 2013,
      exit_id: 2014,
      entrance: { id: 2013, name: 'Keaton', slug: 'keaton' },
      exit: { id: 2014, name: 'Hank', slug: 'hank' },
      tackle: [],
    })
    await flushPromises()

    await submit(wrapper)

    const [, body] = apiMock.post.mock.calls[0]
    expect(body.get('entrance_id')).toBe('2013')
    expect(body.get('exit_id')).toBe('2014')
  })
})
