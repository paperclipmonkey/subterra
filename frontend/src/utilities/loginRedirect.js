// Where to send a visitor once they have signed in. Kept in localStorage, not
// sessionStorage, because the magic-link email usually opens in a new tab with
// its own session storage. The expiry stops an abandoned attempt hijacking a
// later, unrelated login.
const KEY = 'redirectAfterLogin'
const TTL_MS = 60 * 60 * 1000

// Same-origin relative paths only, so a crafted value can't become an open
// redirect ("//evil.com" and "/\evil.com" are protocol-relative to browsers).
export function isSafeRedirect(path) {
  return typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !path.startsWith('/\\')
}

export function rememberLoginRedirect(path) {
  if (!isSafeRedirect(path)) return
  try {
    localStorage.setItem(KEY, JSON.stringify({ path, expires: Date.now() + TTL_MS }))
  } catch {
    // Storage unavailable (private mode, quota) — the user just lands on the default page.
  }
}

export function takeLoginRedirect() {
  try {
    const raw = localStorage.getItem(KEY)
    localStorage.removeItem(KEY)
    const { path, expires } = JSON.parse(raw) ?? {}
    return expires > Date.now() && isSafeRedirect(path) ? path : null
  } catch {
    return null
  }
}
