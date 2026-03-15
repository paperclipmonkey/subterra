export class MapStyleControl {
  constructor(styles, currentStyle, onStyleChange) {
    this.styles = styles || []
    this.currentStyle = currentStyle
    this.onStyleChange = onStyleChange
  }

  onAdd(map) {
    this._map = map
    this._container = document.createElement('div')
    this._container.className = 'maplibregl-ctrl maplibregl-ctrl-group'
    
    const btn = document.createElement('button')
    btn.className = 'maplibregl-ctrl-icon'
    btn.type = 'button'
    btn.title = 'Switch Map Style'
    
    // Simple Layers SVG icon
    btn.innerHTML = `<svg style="width:20px;height:20px;margin:5px;" viewBox="0 0 24 24">
      <path fill="currentColor" d="M12,16L22,12L12,8L2,12L12,16M12,18L2,14L12,18L22,14L12,18M12,22L2,18L12,22L22,18L12,22M12,4L22,8L12,12L2,8L12,4Z" />
    </svg>`

    const menu = document.createElement('div')
    menu.className = 'maplibregl-style-menu'
    menu.style.display = 'none'
    menu.style.position = 'absolute'
    menu.style.right = '32px'
    menu.style.top = '0'
    menu.style.backgroundColor = 'white'
    menu.style.boxShadow = '0 1px 4px rgba(0,0,0,0.3)'
    menu.style.borderRadius = '4px'
    menu.style.padding = '4px 0'
    menu.style.minWidth = '120px'
    menu.style.zIndex = '1000'

    this.styles.forEach(item => {
      const itemEl = document.createElement('div')
      itemEl.innerText = item.title
      itemEl.style.padding = '6px 12px'
      itemEl.style.cursor = 'pointer'
      itemEl.style.fontSize = '12px'
      itemEl.style.color = '#333'
      
      if (this.currentStyle === item.value) {
        itemEl.style.fontWeight = 'bold'
        itemEl.style.backgroundColor = '#f5f5f5'
      }

      itemEl.addEventListener('click', () => {
        if (this.currentStyle !== item.value) {
          this.currentStyle = item.value
          this.onStyleChange(item.value)
          this._updateMenu(menu)
        }
        menu.style.display = 'none'
      })

      // Hover effects
      itemEl.addEventListener('mouseenter', () => itemEl.style.backgroundColor = '#f0f0f0')
      itemEl.addEventListener('mouseleave', () => {
        if (this.currentStyle === item.value) {
             itemEl.style.backgroundColor = '#f5f5f5'
        } else {
             itemEl.style.backgroundColor = 'transparent'
        }
      })

      menu.appendChild(itemEl)
    })

    btn.addEventListener('click', (e) => {
      e.stopPropagation()
      menu.style.display = menu.style.display === 'none' ? 'block' : 'none'
    })

    document.addEventListener('click', () => {
      menu.style.display = 'none'
    })

    this._container.appendChild(btn)
    this._container.appendChild(menu)
    return this._container
  }

  _updateMenu(menu) {
    const items = menu.children
    for (let i = 0; i < items.length; i++) {
        const itemEl = items[i]
        const styleVal = this.styles[i].value
        if (this.currentStyle === styleVal) {
            itemEl.style.fontWeight = 'bold'
            itemEl.style.backgroundColor = '#f5f5f5'
        } else {
            itemEl.style.fontWeight = 'normal'
            itemEl.style.backgroundColor = 'transparent'
        }
    }
  }

  onRemove() {
    this._container.parentNode.removeChild(this._container)
    this._map = undefined
  }
}
