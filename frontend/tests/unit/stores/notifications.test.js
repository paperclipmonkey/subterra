import { describe, it, expect, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useNotificationStore } from '@/stores/notifications'

describe('Notification Store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('should initialize with default state', () => {
    const store = useNotificationStore()
    
    expect(store.show).toBe(false)
    expect(store.message).toBe('')
    expect(store.type).toBe('info')
    expect(store.timeout).toBe(4000)
  })

  it('should show success notification', () => {
    const store = useNotificationStore()
    
    store.showSuccess('Trip saved successfully!')
    
    expect(store.show).toBe(true)
    expect(store.message).toBe('Trip saved successfully!')
    expect(store.type).toBe('success')
    expect(store.timeout).toBe(4000)
  })

  it('should show error notification with longer timeout', () => {
    const store = useNotificationStore()
    
    store.showError('Something went wrong')
    
    expect(store.show).toBe(true)
    expect(store.message).toBe('Something went wrong')
    expect(store.type).toBe('error')
    expect(store.timeout).toBe(6000)
  })

  it('should hide notification', () => {
    const store = useNotificationStore()
    
    store.showSuccess('Test message')
    expect(store.show).toBe(true)
    
    store.hideNotification()
    expect(store.show).toBe(false)
  })

  it('should show custom notification with custom timeout', () => {
    const store = useNotificationStore()
    
    store.showNotification('Custom message', 'warning', 8000)
    
    expect(store.show).toBe(true)
    expect(store.message).toBe('Custom message')
    expect(store.type).toBe('warning')
    expect(store.timeout).toBe(8000)
  })
})