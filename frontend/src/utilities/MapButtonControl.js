// A minimal MapLibre control that renders a single icon button.
// Used to add ad-hoc actions (e.g. KML export) to the map's control stack.
export class MapButtonControl {
  constructor({ title, iconSvg, onClick }) {
    this.title = title || ''
    this.iconSvg = iconSvg || ''
    this.onClick = onClick || (() => {})
  }

  onAdd(map) {
    this._map = map
    this._container = document.createElement('div')
    this._container.className = 'maplibregl-ctrl maplibregl-ctrl-group'

    const btn = document.createElement('button')
    btn.className = 'maplibregl-ctrl-icon'
    btn.type = 'button'
    btn.title = this.title
    btn.setAttribute('aria-label', this.title)
    btn.innerHTML = this.iconSvg

    btn.addEventListener('click', (e) => {
      e.stopPropagation()
      this.onClick()
    })

    this._container.appendChild(btn)
    return this._container
  }

  onRemove() {
    if (this._container && this._container.parentNode) {
      this._container.parentNode.removeChild(this._container)
    }
    this._map = undefined
  }
}
