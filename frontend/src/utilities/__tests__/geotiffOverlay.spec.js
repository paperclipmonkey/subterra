import { describe, it, expect } from 'vitest'
import { boundsToCoordinates } from '@/utilities/geotiffOverlay'

describe('boundsToCoordinates', () => {
  it('converts [west, south, east, north] to TL, TR, BR, BL corners', () => {
    const coords = boundsToCoordinates([-2.65, 51.82, -2.60, 51.85])
    expect(coords).toEqual([
      [-2.65, 51.85], // top-left
      [-2.60, 51.85], // top-right
      [-2.60, 51.82], // bottom-right
      [-2.65, 51.82], // bottom-left
    ])
  })

  it('returns null for malformed bounds', () => {
    expect(boundsToCoordinates(null)).toBeNull()
    expect(boundsToCoordinates([1, 2, 3])).toBeNull()
    expect(boundsToCoordinates('nope')).toBeNull()
  })
})
