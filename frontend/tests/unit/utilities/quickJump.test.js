import { describe, it, expect, afterEach } from 'vitest'
import { shouldOpenQuickJump } from '@/utilities/quickJump'

const press = (target, init = {}) => {
  const event = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true, ...init })
  let result
  const listener = e => { result = shouldOpenQuickJump(e) }
  document.addEventListener('keydown', listener)
  target.dispatchEvent(event)
  document.removeEventListener('keydown', listener)
  return result
}

const add = html => {
  const wrapper = document.createElement('div')
  wrapper.innerHTML = html
  document.body.appendChild(wrapper)
  return wrapper.firstElementChild
}

describe('shouldOpenQuickJump', () => {
  afterEach(() => { document.body.innerHTML = '' })

  it('opens when "/" is pressed outside a text field', () => {
    expect(press(add('<button>Save</button>'))).toBe(true)
    expect(press(document.body)).toBe(true)
  })

  it.each([
    ['text input', '<input type="text">'],
    ['input without a type', '<input>'],
    ['search input', '<input type="search">'],
    ['email input', '<input type="email">'],
    ['number input', '<input type="number">'],
    ['date input', '<input type="date">'],
    ['textarea', '<textarea></textarea>'],
    ['select', '<select><option>a</option></select>'],
    ['contenteditable editor', '<div contenteditable="true"></div>'],
    ['bare contenteditable', '<div contenteditable></div>'],
    ['textbox role', '<div role="textbox" tabindex="0"></div>'],
  ])('leaves "/" alone while typing in a %s', (_, html) => {
    expect(press(add(html))).toBe(false)
  })

  it('leaves "/" alone inside a nested contenteditable element', () => {
    const editor = add('<div contenteditable="true"><p><strong>bold</strong></p></div>')
    expect(press(editor.querySelector('strong'))).toBe(false)
  })

  it('opens from a non-text input like a checkbox', () => {
    expect(press(add('<input type="checkbox">'))).toBe(true)
  })

  it.each([['ctrlKey'], ['metaKey'], ['altKey']])('ignores "/" with %s held', modifier => {
    expect(press(document.body, { [modifier]: true })).toBe(false)
  })

  it('ignores other keys and IME composition', () => {
    expect(press(document.body, { key: '?' })).toBe(false)
    expect(press(document.body, { isComposing: true })).toBe(false)
  })

  it('ignores an event another handler already claimed', () => {
    const el = add('<div></div>')
    el.addEventListener('keydown', e => e.preventDefault())
    expect(press(el)).toBe(false)
  })
})
