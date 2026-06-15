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
        'v-form': {
          inheritAttrs: false,
          methods: { validate: () => ({ valid: true }) },
          template: '<form v-bind="$attrs"><slot /></form>',
        },
        'v-file-input': {
          inheritAttrs: false,
          template: '<input type="file" v-bind="$attrs" />',
        },
      },
    },
  })

const selectHeroImage = async (wrapper, file) => {
  // The hero image input is the first file input in the form.
  const input = wrapper.findAll('input[type=file]')[0]
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises() // wait for the base64 preview conversion
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

    const hero = body.get('hero_image')
    expect(hero).toBeInstanceOf(File)
    expect(hero.name).toBe('hero.png')

    // Creation must not spoof a PUT, and must never smuggle a base64 string.
    expect(body.get('_method')).toBeNull()
    for (const [, value] of body.entries()) {
      expect(String(value)).not.toContain('data:image')
    }
  })

  it('spoofs PUT and omits the unchanged hero image when updating', async () => {
    const wrapper = mountForm({
      id: 42,
      slug: 'entrance-series',
      name: 'Entrance Series',
      hero_image: 'https://cdn.example.com/routes/existing.jpg',
      tackle: [],
    })
    await flushPromises()

    await submit(wrapper)

    expect(apiMock.post).toHaveBeenCalledTimes(1)
    const [url, body] = apiMock.post.mock.calls[0]

    expect(url).toBe('/api/routes/entrance-series')
    expect(body.get('_method')).toBe('PUT')
    // The existing hero image is a URL string; resending it would fail the
    // backend's `file` rule, so it must be left out entirely.
    expect(body.get('hero_image')).toBeNull()
  })
})
