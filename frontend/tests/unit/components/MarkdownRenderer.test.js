import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import MarkdownRenderer from '@/components/MarkdownRenderer.vue'

// Mock mermaid
vi.mock('mermaid', () => ({
    default: {
        initialize: vi.fn(),
        run: vi.fn().mockResolvedValue(undefined),
    }
}))

// Mock vue-markdown-render to render the source directly
vi.mock('vue-markdown-render', () => ({
    default: {
        name: 'VueMarkdown',
        props: ['source', 'plugins'],
        setup(props) {
            // Simulate the plugin processing for mermaid blocks
            let processedSource = props.source || ''
            if (processedSource.includes('```mermaid')) {
                processedSource = processedSource.replace(/```mermaid\n?([\s\S]*?)\n?```/g, '<div class="mermaid">$1</div>')
            }
            return { processedSource }
        },
        template: '<div class="vue-markdown-stub" v-html="processedSource"></div>'
    }
}))

describe('MarkdownRenderer', () => {
    it('renders with source prop', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'Hello **world**' }
        })
        expect(wrapper.find('.markdown-renderer').exists()).toBe(true)
        expect(wrapper.text()).toContain('Hello')
    })

    it('passes geojson and mermaid plugins to vue-markdown', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test content' }
        })
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        expect(vueMarkdown.exists()).toBe(true)
        const plugins = vueMarkdown.props('plugins')
        expect(plugins).toHaveLength(2)
        plugins.forEach(p => expect(typeof p).toBe('function'))
    })

    it('initializes mermaid on mount', async () => {
        const mermaid = (await import('mermaid')).default
        mount(MarkdownRenderer, {
            props: { source: '```mermaid\ngraph TD\nA-->B\n```' }
        })
        // wait for nextTick and lazy load
        await new Promise(resolve => setTimeout(resolve, 350))

        expect(mermaid.initialize).toHaveBeenCalled()
    })

    it('mermaidPlugin replaces mermaid fence with div', () => {
        // Manually test the plugin logic. The mermaid plugin is the second one
        // installed (the first is the geojson plugin) and it must run last so
        // its fence rule wraps the geojson rule's output.
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test' }
        })

        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        const plugins = vueMarkdown.props('plugins')

        // Create a mock markdown-it instance and run all plugins in order
        const md = {
            renderer: { rules: {} },
            utils: { escapeHtml: (s) => s }
        }
        plugins.forEach(p => p(md))

        // Verify the fence rule was set by the chain
        expect(typeof md.renderer.rules.fence).toBe('function')

        // Test mermaid fence rendering — should be handled by the mermaid plugin
        const tokens = [{
            info: 'mermaid',
            content: 'graph TD\n  A-->B'
        }]
        const result = md.renderer.rules.fence(tokens, 0, {}, {}, { renderToken: () => '' })
        expect(result).toContain('<div class="mermaid">')
        expect(result).toContain('graph TD')

        // Test non-mermaid, non-geojson fence falls through to the default renderer
        const jsTokens = [{
            info: 'javascript',
            content: 'console.log("hi")'
        }]
        const jsResult = md.renderer.rules.fence(jsTokens, 0, {}, {}, { renderToken: () => '<code>fallback</code>' })
        expect(jsResult).toBe('<code>fallback</code>')
    })

    it('uses default empty string for source', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: {}
        })
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        expect(vueMarkdown.props('source')).toBe('')
    })
})
