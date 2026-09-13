import { describe, it, expect, vi } from 'vitest'
import { MapButtonControl } from '@/utilities/MapButtonControl'

const fakeMap = {}

describe('MapButtonControl', () => {
  it('renders a MapLibre control group with a single button', () => {
    const control = new MapButtonControl({ title: 'Export KML', iconSvg: '<svg id="icon"/>' })

    const container = control.onAdd(fakeMap)

    expect(container.className).toBe('maplibregl-ctrl maplibregl-ctrl-group')
    const button = container.querySelector('button')
    expect(button.type).toBe('button')
    expect(button.className).toBe('maplibregl-ctrl-icon')
    expect(button.title).toBe('Export KML')
    expect(button.getAttribute('aria-label')).toBe('Export KML')
    expect(button.querySelector('#icon')).not.toBeNull()
  })

  it('defaults its title, icon and handler', () => {
    const control = new MapButtonControl({})

    const container = control.onAdd(fakeMap)
    const button = container.querySelector('button')

    expect(button.title).toBe('')
    expect(button.innerHTML).toBe('')
    expect(() => button.click()).not.toThrow()
  })

  it('invokes onClick and stops the event reaching the map', () => {
    const onClick = vi.fn()
    const control = new MapButtonControl({ title: 'Export KML', onClick })
    const container = control.onAdd(fakeMap)
    document.body.appendChild(container)

    const mapClick = vi.fn()
    document.body.addEventListener('click', mapClick)

    container.querySelector('button').dispatchEvent(new MouseEvent('click', { bubbles: true }))

    expect(onClick).toHaveBeenCalledTimes(1)
    expect(mapClick).not.toHaveBeenCalled()

    document.body.removeEventListener('click', mapClick)
    control.onRemove()
  })

  it('detaches itself from the DOM on removal', () => {
    const control = new MapButtonControl({ title: 'Export KML' })
    const container = control.onAdd(fakeMap)
    document.body.appendChild(container)

    control.onRemove()

    expect(container.parentNode).toBeNull()
    expect(control._map).toBeUndefined()
  })

  it('tolerates removal before it was ever attached', () => {
    const control = new MapButtonControl({ title: 'Export KML' })
    control.onAdd(fakeMap)

    expect(() => control.onRemove()).not.toThrow()
  })
})
