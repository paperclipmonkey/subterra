// Input types where typing "/" produces no text, so the shortcut is safe to take.
const NON_TEXT_INPUT_TYPES = new Set([
  'button', 'checkbox', 'color', 'file', 'image', 'radio', 'range', 'reset', 'submit',
])

export function isTextEntryTarget (target) {
  if (!(target instanceof Element)) return false
  if (target.isContentEditable || target.closest('[contenteditable]:not([contenteditable="false"])')) return true
  if (target.tagName === 'TEXTAREA' || target.tagName === 'SELECT') return true
  if (target.tagName === 'INPUT') return !NON_TEXT_INPUT_TYPES.has(target.type)
  return target.getAttribute('role') === 'textbox' || target.getAttribute('role') === 'combobox'
}

export function shouldOpenQuickJump (event) {
  if (event.key !== '/' || event.defaultPrevented || event.isComposing) return false
  if (event.ctrlKey || event.metaKey || event.altKey) return false
  const target = event.composedPath?.()[0] ?? event.target
  return !isTextEntryTarget(target)
}
