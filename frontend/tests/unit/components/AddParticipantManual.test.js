import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AddParticipantManual from '@/components/AddParticipantManual.vue'

describe('AddParticipantManual', () => {
  it('renders when isActive is true', () => {
    const wrapper = mount(AddParticipantManual, {
      props: {
        isActive: true
      }
    })
    
    expect(wrapper.exists()).toBe(true)
  })

  it('validates email format correctly', () => {
    const wrapper = mount(AddParticipantManual, {
      props: {
        isActive: true
      }
    })
    
    // Access the component's email validation rules
    const component = wrapper.vm
    const emailRules = component.emailRules
    
    // Test valid email
    expect(emailRules[1]('test@example.com')).toBe(true)
    
    // Test invalid email
    expect(emailRules[1]('invalid-email')).toBe('E-mail must be valid.')
    
    // Test empty email
    expect(emailRules[0]('')).toBe('E-mail is requred.')
    expect(emailRules[0]('test@example.com')).toBe(true)
  })

  it('has reactive data properties', () => {
    const wrapper = mount(AddParticipantManual, {
      props: {
        isActive: true
      }
    })
    
    expect(wrapper.vm.name).toBe('')
    expect(wrapper.vm.email).toBe('')
    expect(Array.isArray(wrapper.vm.emailRules)).toBe(true)
    expect(wrapper.vm.emailRules.length).toBe(2)
  })

  it('accepts isActive prop', () => {
    const wrapper = mount(AddParticipantManual, {
      props: {
        isActive: false
      }
    })
    
    expect(wrapper.props('isActive')).toBe(false)
  })
})