import { describe, it, expect } from 'vitest'
import { toFormData, convertFileToBase64 } from '@/utilities'

const entries = (formData) => [...formData.entries()]
const asObject = (formData) => Object.fromEntries(entries(formData))

describe('toFormData', () => {
  it('appends flat scalar fields', () => {
    expect(asObject(toFormData({ name: 'Swildons', depth: 167 })))
      .toEqual({ name: 'Swildons', depth: '167' })
  })

  it('serialises null as an empty string so Laravel nulls the column', () => {
    expect(asObject(toFormData({ description: null }))).toEqual({ description: '' })
  })

  it('skips undefined fields entirely', () => {
    expect(entries(toFormData({ name: 'Swildons', description: undefined })))
      .toEqual([['name', 'Swildons']])
  })

  it('preserves booleans as their string form', () => {
    expect(asObject(toFormData({ curated: true, hidden: false })))
      .toEqual({ curated: 'true', hidden: 'false' })
  })

  it('brackets nested objects', () => {
    expect(asObject(toFormData({ system: { id: 3, name: 'Mendip' } })))
      .toEqual({ 'system[id]': '3', 'system[name]': 'Mendip' })
  })

  it('indexes scalar arrays', () => {
    expect(asObject(toFormData({ tags: ['Sump', 'Pitch'] })))
      .toEqual({ 'tags[0]': 'Sump', 'tags[1]': 'Pitch' })
  })

  it('indexes and brackets arrays of objects', () => {
    expect(asObject(toFormData({ caves: [{ id: 1, name: 'A' }, { id: 2, name: 'B' }] })))
      .toEqual({
        'caves[0][id]': '1',
        'caves[0][name]': 'A',
        'caves[1][id]': '2',
        'caves[1][name]': 'B',
      })
  })

  it('handles arbitrarily deep nesting', () => {
    expect(asObject(toFormData({ a: { b: { c: { d: 'deep' } } } })))
      .toEqual({ 'a[b][c][d]': 'deep' })
  })

  it('appends Files without stringifying them', () => {
    const photo = new File(['bytes'], 'cover.jpg', { type: 'image/jpeg' })

    const result = toFormData({ photo })

    expect(result.get('photo')).toBeInstanceOf(File)
    expect(result.get('photo').name).toBe('cover.jpg')
  })

  it('appends Blobs without stringifying them', () => {
    const result = toFormData({ survey: new Blob(['<svg/>'], { type: 'image/svg+xml' }) })

    expect(result.get('survey')).toBeInstanceOf(Blob)
  })

  it('keeps Files inside arrays as Files', () => {
    const result = toFormData({ media: [new File(['a'], 'a.jpg')] })

    expect(result.get('media[0]')).toBeInstanceOf(File)
  })

  it('appends into an existing FormData when one is supplied', () => {
    const existing = new FormData()
    existing.append('_method', 'PUT')

    const result = toFormData({ name: 'X' }, existing)

    expect(result).toBe(existing)
    expect(asObject(result)).toEqual({ _method: 'PUT', name: 'X' })
  })

  it('returns an empty FormData for an empty object', () => {
    expect(entries(toFormData({}))).toEqual([])
  })

  it('ignores inherited properties', () => {
    const proto = { inherited: 'no' }
    const obj = Object.create(proto)
    obj.own = 'yes'

    expect(asObject(toFormData(obj))).toEqual({ own: 'yes' })
  })
})

describe('convertFileToBase64', () => {
  it('resolves with the data URL and the original filename', async () => {
    const file = new File(['hello'], 'note.txt', { type: 'text/plain' })

    const result = await convertFileToBase64(file)

    expect(result.filename).toBe('note.txt')
    expect(result.data).toMatch(/^data:text\/plain;base64,/)
    expect(atob(result.data.split(',')[1])).toBe('hello')
  })

  it('rejects when the file cannot be read', async () => {
    const file = new File([''], 'broken.txt')
    // FileReader failures surface via onerror; simulate one deterministically.
    const original = FileReader.prototype.readAsDataURL
    FileReader.prototype.readAsDataURL = function () {
      setTimeout(() => this.onerror(new Error('read failed')), 0)
    }

    await expect(convertFileToBase64(file)).rejects.toBeDefined()

    FileReader.prototype.readAsDataURL = original
  })
})
