// A cave system's 3D survey: a cave-viewer page (the CaveSurveying sites), named
// by the system's viewer_url. These build its addresses.

// the page itself, without any #… after it
export const viewerBase = url => (url || '').split('#')[0]

// the still of the view the site builds beside it, for a card
export const viewerPreview = url => {
  const base = viewerBase(url)
  return base ? base.replace(/\/?$/, '/') + 'preview.png' : null
}

// where the view opens: on a place (an entrance, or a survey: cave-viewer's #at=)
export const atHash = name => name ? '#at=' + encodeURIComponent(name) : ''

// the origin its messages come from
export const viewerOrigin = url => { try { return new URL(viewerBase(url)).origin } catch { return null } }
