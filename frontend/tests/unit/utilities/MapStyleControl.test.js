import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { MapStyleControl } from '@/utilities/MapStyleControl'

const STYLES = [
  { title: 'Streets', value: 'streets' },
  { title: 'Satellite', value: 'satellite' },
  { title: 'Terrain', value: 'terrain' },
]

const fakeMap = {}

describe('MapStyleControl', () => {
  let control
  let container
  let onStyleChange

  const button = () => container.querySelector('button')
  const menu = () => container.querySelector('.maplibregl-style-menu')
  const items = () => [...menu().children]

  const mount = (currentStyle = 'streets', styles = STYLES) => {
    onStyleChange = vi.fn()
    control = new MapStyleControl(styles, currentStyle, onStyleChange)
    container = control.onAdd(fakeMap)
    document.body.appendChild(container)
  }

  beforeEach(() => mount())

  afterEach(() => {
    control.onRemove()
  })

  it('renders a control group with a button and a hidden menu', () => {
    expect(container.className).toBe('maplibregl-ctrl maplibregl-ctrl-group')
    expect(button().title).toBe('Switch Map Style')
    expect(menu().style.display).toBe('none')
  })

  it('lists every style, highlighting the current one', () => {
    expect(items().map(el => el.innerText)).toEqual(['Streets', 'Satellite', 'Terrain'])
    expect(items()[0].style.fontWeight).toBe('bold')
    expect(items()[1].style.fontWeight).toBe('')
  })

  it('defaults to an empty style list', () => {
    const bare = new MapStyleControl(undefined, 'streets', vi.fn())

    const bareContainer = bare.onAdd(fakeMap)

    expect(bare.styles).toEqual([])
    expect(bareContainer.querySelector('.maplibregl-style-menu').children).toHaveLength(0)
    bare.onRemove()
  })

  it('toggles the menu open and closed from the button', () => {
    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(menu().style.display).toBe('block')

    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(menu().style.display).toBe('none')
  })

  it('does not let the toggle click reach the map', () => {
    const mapClick = vi.fn()
    document.body.addEventListener('click', mapClick)

    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(mapClick).not.toHaveBeenCalled()
    document.body.removeEventListener('click', mapClick)
  })

  it('reports a style change, moves the highlight and closes the menu', () => {
    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))

    items()[1].dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(onStyleChange).toHaveBeenCalledWith('satellite')
    expect(control.currentStyle).toBe('satellite')
    expect(items()[1].style.fontWeight).toBe('bold')
    expect(items()[0].style.fontWeight).toBe('normal')
    expect(menu().style.display).toBe('none')
  })

  it('closes without re-reporting when the current style is picked again', () => {
    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))

    items()[0].dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(onStyleChange).not.toHaveBeenCalled()
    expect(menu().style.display).toBe('none')
  })

  it('closes the menu on an outside click', () => {
    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(menu().style.display).toBe('block')

    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(menu().style.display).toBe('none')
  })

  it('highlights the hovered item and restores it on leave', () => {
    const satellite = items()[1]

    satellite.dispatchEvent(new MouseEvent('mouseenter'))
    expect(satellite.style.backgroundColor).toBe('rgb(240, 240, 240)')

    satellite.dispatchEvent(new MouseEvent('mouseleave'))
    expect(satellite.style.backgroundColor).toBe('transparent')
  })

  it('keeps the current item shaded after a hover', () => {
    const streets = items()[0]

    streets.dispatchEvent(new MouseEvent('mouseenter'))
    streets.dispatchEvent(new MouseEvent('mouseleave'))

    expect(streets.style.backgroundColor).toBe('rgb(245, 245, 245)')
  })

  it('detaches and stops listening to the document on removal', () => {
    button().dispatchEvent(new MouseEvent('click', { bubbles: true }))
    const detachedMenu = menu()

    control.onRemove()

    expect(container.parentNode).toBeNull()
    expect(control._map).toBeUndefined()
    expect(control._documentClickHandler).toBeNull()

    // The document listener is gone, so the orphaned menu is no longer touched.
    detachedMenu.style.display = 'block'
    document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(detachedMenu.style.display).toBe('block')

    mount() // restore state for the shared afterEach
  })
})
