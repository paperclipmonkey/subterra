import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'

// Crepe/ProseMirror don't run in jsdom, so fake just enough of the editor to
// see which transactions MilkdownInner dispatches.
const { state } = vi.hoisted(() => ({ state: { loading: null, dispatched: [], markdown: '' } }))

vi.mock('@milkdown/vue', () => ({
  Milkdown: { template: '<div />' },
  useEditor: () => ({ get: () => fakeEditor, loading: state.loading }),
}))
vi.mock('@milkdown/crepe', () => ({ Crepe: vi.fn(), CrepeFeature: {} }))
vi.mock('@codemirror/language-data', () => ({ languages: [] }))
vi.mock('@codemirror/language', () => ({ LanguageDescription: { of: vi.fn() } }))
vi.mock('@milkdown/kit/plugin/listener', () => ({ listener: {}, listenerCtx: 'listenerCtx' }))
vi.mock('@milkdown/kit/core', () => ({ editorViewCtx: 'view', parserCtx: 'parser' }))
vi.mock('@milkdown/kit/prose/model', () => ({ Slice: class { constructor (content) { this.content = content } } }))
vi.mock('@milkdown/kit/utils', () => ({ getMarkdown: () => () => state.markdown }))

const makeTr = () => {
  const tr = { meta: {}, replaced: null }
  tr.replace = (_from, _to, slice) => { tr.replaced = slice.content; return tr }
  tr.setMeta = (key, value) => { tr.meta[key] = value; return tr }
  return tr
}
const view = {
  get state () { return { doc: { content: { size: 0 } }, tr: makeTr() } },
  dispatch: (tr) => { state.dispatched.push(tr); state.markdown = tr.replaced },
}
const ctx = { get: (key) => (key === 'view' ? view : (md) => ({ content: md })) }
const fakeEditor = { action: (fn) => fn(ctx) }

import MilkdownInner from '@/components/MilkdownInner.vue'

describe('MilkdownInner', () => {
  beforeEach(() => {
    state.loading = ref(false)
    state.dispatched = []
    state.markdown = ''
  })

  it('loads outside values without recording them as edits', async () => {
    const wrapper = mount(MilkdownInner, { props: { modelValue: '' } })
    await wrapper.setProps({ modelValue: '* item\n\nSome _text_' })
    await nextTick()

    expect(state.dispatched).toHaveLength(1)
    // The listener skips addToHistory:false transactions, so Milkdown's
    // re-serialised markdown isn't emitted back as a change.
    expect(state.dispatched[0].meta.addToHistory).toBe(false)
    expect(state.dispatched[0].replaced).toBe('* item\n\nSome _text_')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('shows a value that arrived while the editor was still loading', async () => {
    state.loading = ref(true)
    const wrapper = mount(MilkdownInner, { props: { modelValue: '' } })
    await wrapper.setProps({ modelValue: 'Loaded description' })
    await nextTick()
    expect(state.dispatched).toHaveLength(0)

    state.loading.value = false
    await nextTick()
    expect(state.dispatched).toHaveLength(1)
    expect(state.dispatched[0].replaced).toBe('Loaded description')
    expect(state.dispatched[0].meta.addToHistory).toBe(false)
  })
})
