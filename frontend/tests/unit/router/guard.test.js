import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'

const getUser = vi.fn()
vi.mock('@/stores/app', () => ({ useAppStore: () => ({ getUser }) }))

const { authGuard, afterEachHook, handleRouterError, scrollBehavior } = await import('@/router/guard')

const setOnline = (value) => {
  Object.defineProperty(window.navigator, 'onLine', { value, configurable: true })
}

const ANON = { name: '', email: '', is_admin: false, clubs: [] }
const user = (overrides = {}) => ({
  id: 1,
  name: 'Ada Lovelace',
  email: 'ada@example.com',
  is_admin: false,
  clubs: [],
  roles: [],
  ...overrides,
})
const withRoles = (...slugs) => user({ is_admin: true, roles: slugs.map(slug => ({ slug })) })

/** Run the guard for a path and report what it did with `next`. */
async function navigate(path, { name, params = {}, fullPath = path } = {}) {
  const next = vi.fn()
  await authGuard({ path, name, params, fullPath }, { path: '/' }, next)
  return next
}

describe('router auth guard', () => {
  beforeEach(() => {
    getUser.mockReset()
    getUser.mockResolvedValue(user())
    setOnline(true)
    sessionStorage.clear()
    localStorage.clear()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  describe('public routes', () => {
    it('lets the demo page through without touching the session', async () => {
      const next = await navigate('/demo')

      expect(next).toHaveBeenCalledWith()
      expect(getUser).not.toHaveBeenCalled()
    })

    it('lets embed pages through without a session', async () => {
      const next = await navigate('/embed/permits/burrington')

      expect(next).toHaveBeenCalledWith()
      expect(getUser).not.toHaveBeenCalled()
    })

    it('lets the offline page through but still warms the user cache', async () => {
      const next = await navigate('/offline')

      expect(next).toHaveBeenCalledWith()
      expect(getUser).toHaveBeenCalled()
    })

    it('does not fail the offline page when warming the cache rejects', async () => {
      getUser.mockRejectedValue(new Error('offline'))

      const next = await navigate('/offline')

      expect(next).toHaveBeenCalledWith()
    })

    it.each([
      ['/magiclink/abc123'],
      ['/pages/about'],
      ['/callout/active'],
    ])('lets %s through for an anonymous visitor', async (path) => {
      getUser.mockResolvedValue(ANON)

      const next = await navigate(path)

      expect(next).toHaveBeenCalledWith()
    })

    it('lets a shared trip report through when the id looks like a share token', async () => {
      getUser.mockResolvedValue(ANON)

      const next = await navigate('/trips/abcd1234efgh', { params: { id: 'abcd1234efgh' } })

      expect(next).toHaveBeenCalledWith()
    })

    it('does not treat a numeric trip id as a share token', async () => {
      getUser.mockResolvedValue(ANON)

      const next = await navigate('/trips/42', { params: { id: '42' } })

      expect(next).toHaveBeenCalledWith({ path: '/' })
    })
  })

  describe('session refresh', () => {
    it('forces a refresh for profile routes', async () => {
      await navigate('/profile/1')
      expect(getUser).toHaveBeenCalledWith(true)
    })

    it('forces a refresh for callout routes', async () => {
      await navigate('/callout/create')
      expect(getUser).toHaveBeenCalledWith(true)
    })

    it('uses the cached session elsewhere', async () => {
      await navigate('/caves')
      expect(getUser).toHaveBeenCalledWith(false)
    })
  })

  describe('landing page', () => {
    it('sends a signed-in user to their trips', async () => {
      const next = await navigate('/', { name: '/' })

      expect(next).toHaveBeenCalledWith({ path: '/trips' })
    })

    it('shows the landing page to an anonymous visitor', async () => {
      getUser.mockResolvedValue(ANON)

      const next = await navigate('/', { name: '/' })

      expect(next).toHaveBeenCalledWith()
    })
  })

  describe('admin routes', () => {
    it('turns a non-admin away', async () => {
      const next = await navigate('/admin')

      expect(next).toHaveBeenCalledWith({ path: '/trips' })
    })

    it('lets an admin reach the admin index', async () => {
      getUser.mockResolvedValue(user({ is_admin: true }))

      const next = await navigate('/admin')

      expect(next).toHaveBeenCalledWith()
    })

    it.each([
      ['/admin/users', 'platform_admin'],
      ['/admin/clubs', 'platform_admin'],
      ['/admin/communications', 'platform_admin'],
      ['/admin/dashboard', 'platform_admin'],
      ['/admin/callout', 'duty_officer'],
      ['/admin/rota', 'duty_officer'],
      ['/admin/catchments', 'data_admin'],
      ['/admin/cave-system-with-cave', 'data_admin'],
      ['/admin/permits', 'access_officer'],
      ['/admin/bookings', 'access_officer'],
      ['/admin/pages', 'platform_admin'],
      ['/admin/suggested-edits', 'data_admin'],
      ['/admin/tasks', 'data_admin'],
    ])('allows %s for a user holding %s', async (path, role) => {
      getUser.mockResolvedValue(withRoles(role))

      const next = await navigate(path)

      expect(next).toHaveBeenCalledWith()
    })

    it.each([
      ['/admin/users'],
      ['/admin/clubs'],
      ['/admin/communications'],
      ['/admin/dashboard'],
      ['/admin/callout'],
      ['/admin/rota'],
      ['/admin/catchments'],
      ['/admin/cave-system-with-cave'],
      ['/admin/permits'],
      ['/admin/bookings'],
      ['/admin/pages'],
      ['/admin/suggested-edits'],
      ['/admin/tasks'],
    ])('sends an admin without the right role from %s back to /admin', async (path) => {
      getUser.mockResolvedValue(withRoles('some_unrelated_role'))

      const next = await navigate(path)

      expect(next).toHaveBeenCalledWith({ path: '/admin' })
    })

    it('lets a platform admin into the access-officer routes', async () => {
      getUser.mockResolvedValue(withRoles('platform_admin'))

      expect(await navigate('/admin/permits')).toHaveBeenCalledWith()
      expect(await navigate('/admin/bookings')).toHaveBeenCalledWith()
    })

    it('does not let a data admin into the platform-admin routes', async () => {
      getUser.mockResolvedValue(withRoles('data_admin'))

      expect(await navigate('/admin/users')).toHaveBeenCalledWith({ path: '/admin' })
    })

    it('handles an admin with no roles array at all', async () => {
      getUser.mockResolvedValue({ ...user({ is_admin: true }), roles: undefined })

      const next = await navigate('/admin/users')

      expect(next).toHaveBeenCalledWith({ path: '/admin' })
    })
  })

  describe('pip access', () => {
    it.each([['platform_admin'], ['pip_access']])('allows a user with %s', async (role) => {
      getUser.mockResolvedValue(withRoles(role))

      expect(await navigate('/pip')).toHaveBeenCalledWith()
    })

    it('turns away a user without the role', async () => {
      getUser.mockResolvedValue(user())

      expect(await navigate('/pip')).toHaveBeenCalledWith({ path: '/trips' })
    })
  })

  describe('callout access', () => {
    it.each([['platform_admin'], ['duty_officer'], ['callout_access']])('allows a user with %s', async (role) => {
      getUser.mockResolvedValue(withRoles(role))

      expect(await navigate('/callout/create')).toHaveBeenCalledWith()
    })

    it('turns away a user without any callout role', async () => {
      getUser.mockResolvedValue(user())

      expect(await navigate('/callout/create')).toHaveBeenCalledWith({ path: '/trips' })
    })

    it('leaves the public active-callout page open', async () => {
      getUser.mockResolvedValue(user())

      expect(await navigate('/callout/active')).toHaveBeenCalledWith()
    })

    it('turns everyone away when callouts are switched off globally', async () => {
      getUser.mockResolvedValue({ ...withRoles('duty_officer'), features: { callouts: false } })

      expect(await navigate('/callout/create')).toHaveBeenCalledWith({ path: '/trips' })
    })

    it('still lets an in-flight callout be stood down when the feature is off', async () => {
      // Turning the feature off must never strand someone underground.
      getUser.mockResolvedValue({ ...user(), features: { callouts: false } })

      expect(await navigate('/callout/active')).toHaveBeenCalledWith()
    })

    it('treats a missing features block as enabled', async () => {
      // A user record cached before the flag existed should not hide a live feature.
      getUser.mockResolvedValue(withRoles('duty_officer'))

      expect(await navigate('/callout/create')).toHaveBeenCalledWith()
    })
  })

  describe('incomplete profile', () => {
    it('sends a signed-in user with no name to their profile editor', async () => {
      getUser.mockResolvedValue(user({ id: 42, name: '' }))

      const next = await navigate('/caves')

      expect(next).toHaveBeenCalledWith({ name: '/profile/[id].edit', params: { id: 42 } })
    })

    it('treats a whitespace-only name as missing', async () => {
      getUser.mockResolvedValue(user({ id: 42, name: '   ' }))

      const next = await navigate('/caves')

      expect(next).toHaveBeenCalledWith({ name: '/profile/[id].edit', params: { id: 42 } })
    })

    it('does not redirect away from the profile editor itself', async () => {
      getUser.mockResolvedValue(user({ id: 42, name: '' }))

      const next = await navigate('/profile/42/edit', { name: '/profile/[id].edit' })

      expect(next).toHaveBeenCalledWith()
    })

    it('does not block logging out', async () => {
      getUser.mockResolvedValue(user({ id: 42, name: '' }))

      const next = await navigate('/logout')

      expect(next).toHaveBeenCalledWith()
    })
  })

  describe('unauthenticated access', () => {
    it('remembers the target and sends the visitor to the landing page', async () => {
      getUser.mockResolvedValue(ANON)

      const next = await navigate('/caves', { fullPath: '/caves?tag=Sump' })

      expect(sessionStorage.getItem('redirectAfterLogin')).toBe('/caves?tag=Sump')
      expect(next).toHaveBeenCalledWith({ path: '/' })
    })

    it.each([
      ['/caves'],
      ['/caves/swildons-hole'],
      ['/pages/about'],
    ])('lets an offline visitor reach %s', async (path) => {
      getUser.mockResolvedValue(ANON)
      setOnline(false)

      const next = await navigate(path)

      expect(next).toHaveBeenCalledWith()
      expect(sessionStorage.getItem('redirectAfterLogin')).toBeNull()
    })

    it('sends an offline visitor anywhere else to the offline page', async () => {
      getUser.mockResolvedValue(ANON)
      setOnline(false)

      const next = await navigate('/trips')

      expect(next).toHaveBeenCalledWith({ path: '/offline' })
    })

    it('lets a signed-in user navigate freely while offline', async () => {
      setOnline(false)

      const next = await navigate('/trips')

      expect(next).toHaveBeenCalledWith()
    })
  })
})

describe('afterEachHook', () => {
  it('resets the title and clears the reload flag', () => {
    document.title = 'Something - subterra.world'
    localStorage.setItem('vuetify:dynamic-reload', 'true')

    afterEachHook()

    expect(document.title).toBe('subterra.world')
    expect(localStorage.getItem('vuetify:dynamic-reload')).toBeNull()
  })

  it('leaves scrolling to scrollBehavior', () => {
    // Scrolling here fought scroll restoration: it ran on every navigation,
    // including the back-navigation whose position we want to keep.
    window.scrollTo = vi.fn()

    afterEachHook()

    expect(window.scrollTo).not.toHaveBeenCalled()
  })
})

describe('scrollBehavior', () => {
  it('returns to where the user was on back/forward', () => {
    const saved = { top: 2400, left: 0 }
    expect(scrollBehavior({ path: '/caves' }, { path: '/caves/swildons' }, saved)).toBe(saved)
  })

  it('scrolls to the top when opening a different page', () => {
    expect(scrollBehavior({ path: '/caves/swildons' }, { path: '/caves' }, null)).toEqual({ top: 0 })
  })

  it('stays put when only the query changes', () => {
    // Filter and view-toggle updates on the caves page rewrite the query; the
    // user is still looking at the same page and should not be thrown to the top.
    expect(scrollBehavior(
      { path: '/caves', query: { tags: 'Yorkshire' } },
      { path: '/caves', query: {} },
      null,
    )).toBe(false)
  })

  it('honours an anchor', () => {
    expect(scrollBehavior({ path: '/pages/terms', hash: '#privacy' }, { path: '/' }, null))
      .toEqual({ el: '#privacy' })
  })
})

describe('handleRouterError', () => {
  beforeEach(() => {
    localStorage.clear()
    vi.spyOn(console, 'warn').mockImplementation(() => {})
    vi.spyOn(console, 'error').mockImplementation(() => {})
    window.location.assign = vi.fn()
    window.alert = vi.fn()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('reloads once on a stale chunk manifest', () => {
    handleRouterError(new Error('Failed to fetch dynamically imported module: /assets/x.js'), { fullPath: '/caves' })

    expect(localStorage.getItem('vuetify:dynamic-reload')).toBe('true')
    expect(window.location.assign).toHaveBeenCalledWith('/caves')
  })

  it('gives up rather than looping when the reload did not help', () => {
    localStorage.setItem('vuetify:dynamic-reload', 'true')

    handleRouterError(new Error('Failed to fetch dynamically imported module'), { fullPath: '/caves' })

    expect(window.location.assign).not.toHaveBeenCalled()
    expect(window.alert).toHaveBeenCalled()
  })

  it('just logs any other router error', () => {
    handleRouterError(new Error('Some other problem'), { fullPath: '/caves' })

    expect(window.location.assign).not.toHaveBeenCalled()
    expect(window.alert).not.toHaveBeenCalled()
    expect(console.error).toHaveBeenCalled()
  })

  it('tolerates an error with no message', () => {
    expect(() => handleRouterError(undefined, { fullPath: '/caves' })).not.toThrow()
  })
})
