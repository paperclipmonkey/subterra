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

    it('passes mermaidPlugin to vue-markdown', () => {
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test content' }
        })
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        expect(vueMarkdown.exists()).toBe(true)
        expect(vueMarkdown.props('plugins')).toHaveLength(1)
        expect(typeof vueMarkdown.props('plugins')[0]).toBe('function')
    })

    it('initializes mermaid on mount', async () => {
        const mermaid = (await import('mermaid')).default
        mount(MarkdownRenderer, {
            props: { source: '```mermaid\ngraph TD\nA-->B\n```' }
        })
        // wait for nextTick and lazy load
        await new Promise(resolve => setTimeout(resolve, 50))

        expect(mermaid.initialize).toHaveBeenCalledWith({
            startOnLoad: false,
            theme: 'default',
            securityLevel: 'loose',
        })
    })

    it('mermaidPlugin replaces mermaid fence with div', () => {
        // Manually test the plugin logic
        const wrapper = mount(MarkdownRenderer, {
            props: { source: 'test' }
        })

        // Access the mermaidPlugin from the component's plugins prop
        const vueMarkdown = wrapper.findComponent({ name: 'VueMarkdown' })
        const plugin = vueMarkdown.props('plugins')[0]

        // Create a mock markdown-it instance
        const mockRules = {}
        const md = {
            renderer: { rules: mockRules },
            utils: { escapeHtml: (s) => s }
        }

        // Call the plugin
        plugin(md)

        // Verify the fence rule was set
        expect(typeof md.renderer.rules.fence).toBe('function')

        // Test mermaid fence rendering
        const tokens = [{
            info: 'mermaid',
            content: 'graph TD\n  A-->B'
        }]
        const result = md.renderer.rules.fence(tokens, 0, {}, {}, { renderToken: () => '' })
        expect(result).toContain('<div class="mermaid">')
        expect(result).toContain('graph TD')

        // Test non-mermaid fence falls through
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
