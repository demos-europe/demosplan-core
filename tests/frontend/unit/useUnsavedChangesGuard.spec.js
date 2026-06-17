/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { useUnsavedChangesGuard, _resetUnsavedChangesGuard } from '@DpJs/composables/useUnsavedChangesGuard'

// Helper to flush all pending promises and timers
const flushPromises = () => new Promise(resolve => setTimeout(resolve, 0))

describe('useUnsavedChangesGuard', () => {
  let dialogElement
  let mockComponent1
  let mockComponent2

  beforeEach(() => {
    // Reset all module-level state to prevent test pollution
    _resetUnsavedChangesGuard()

    dialogElement = document.createElement('div')
    dialogElement.id = 'unsavedChangesDialog'
    document.body.appendChild(dialogElement)

    mockComponent1 = {
      hasUnsavedChanges: jest.fn(() => false),
      saveUnsavedChanges: jest.fn(() => Promise.resolve()),
      onDiscardChanges: jest.fn(() => Promise.resolve()),
      onCancelNavigation: jest.fn(() => Promise.resolve()),
    }

    mockComponent2 = {
      hasUnsavedChanges: jest.fn(() => false),
      saveUnsavedChanges: jest.fn(() => Promise.resolve()),
      onDiscardChanges: jest.fn(() => Promise.resolve()),
      onCancelNavigation: jest.fn(() => Promise.resolve()),
    }

    Object.defineProperty(window, 'location', {
      configurable: true,
      writable: true,
      value: {
        href: '',
      },
    })
  })

  afterEach(() => {
    if (dialogElement && dialogElement.parentNode) {
      document.body.removeChild(dialogElement)
    }

    jest.clearAllMocks()
  })

  describe('initialization', () => {
    it('should return init and cleanup functions', () => {
      const { init, cleanup } = useUnsavedChangesGuard()

      expect(typeof init).toBe('function')
      expect(typeof cleanup).toBe('function')
    })

    it('should register global listeners only once', () => {
      const addEventListenerSpy = jest.spyOn(window, 'addEventListener')

      const { init: init1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

      init1({
        componentId: 'component-1',
      })

      init2({
        componentId: 'component-2',
      })

      const beforeUnloadCalls = addEventListenerSpy.mock.calls.filter(
        ([event]) => event === 'beforeunload',
      )

      expect(beforeUnloadCalls).toHaveLength(1)
    })
  })

  describe('beforeunload handler', () => {
    it('should not prevent navigation when no unsaved changes', () => {
      const { init } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(false)

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const event = new Event('beforeunload')
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      window.dispatchEvent(event)

      expect(preventDefaultSpy).not.toHaveBeenCalled()
    })

    it('should prevent navigation when component has unsaved changes', () => {
      const { init } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const event = new Event('beforeunload', { cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      window.dispatchEvent(event)

      expect(mockComponent1.hasUnsavedChanges).toHaveBeenCalled()
      expect(preventDefaultSpy).toHaveBeenCalled()
    })

    it('should check all registered components for unsaved changes', () => {
      const { init: init1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

      mockComponent1.hasUnsavedChanges.mockReturnValue(false)
      mockComponent2.hasUnsavedChanges.mockReturnValue(true)

      init1({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'component-1',
      })

      init2({
        hasUnsavedChanges: mockComponent2.hasUnsavedChanges,
        componentId: 'component-2',
      })

      const event = new Event('beforeunload', { cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      window.dispatchEvent(event)

      expect(mockComponent1.hasUnsavedChanges).toHaveBeenCalled()
      expect(mockComponent2.hasUnsavedChanges).toHaveBeenCalled()
      expect(preventDefaultSpy).toHaveBeenCalled()
    })
  })

  describe('link click handler', () => {
    it('should not prevent navigation when no unsaved changes', () => {
      const { init } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(false)

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const event = new MouseEvent('click', { bubbles: true, cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      link.dispatchEvent(event)

      expect(preventDefaultSpy).not.toHaveBeenCalled()

      document.body.removeChild(link)
    })

    it('should prevent navigation and show dialog when link clicked with unsaved changes', async () => {
      const { init } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const dialogShowListener = jest.fn()
      document.addEventListener('unsaved-changes-dialog:show', dialogShowListener)

      const event = new MouseEvent('click', { bubbles: true, cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      link.dispatchEvent(event)

      expect(preventDefaultSpy).toHaveBeenCalled()

      await flushPromises()

      expect(dialogShowListener).toHaveBeenCalled()
      document.body.removeChild(link)
      document.removeEventListener('unsaved-changes-dialog:show', dialogShowListener)
    })

    it('should ignore clicks on non-link elements', () => {
      const { init, cleanup } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const button = document.createElement('button')
      document.body.appendChild(button)

      const event = new MouseEvent('click', { bubbles: true, cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      button.dispatchEvent(event)

      expect(preventDefaultSpy).not.toHaveBeenCalled()

      document.body.removeChild(button)
    })
  })

  describe('dialog result handling', () => {
    beforeEach(() => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
    })

    it('should call saveUnsavedChanges when save action is chosen', async () => {
      const { init } = useUnsavedChangesGuard()

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent1.saveUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true })
      link.dispatchEvent(clickEvent)

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: 'save' },
      }))

      await flushPromises()

      expect(mockComponent1.saveUnsavedChanges).toHaveBeenCalled()
      document.body.removeChild(link)
    })

    it('should call onDiscardChanges when discard action is chosen', async () => {
      const { init } = useUnsavedChangesGuard()

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onDiscardChanges: mockComponent1.onDiscardChanges,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true })
      link.dispatchEvent(clickEvent)

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: 'discard' },
      }))

      await flushPromises()

      expect(mockComponent1.onDiscardChanges).toHaveBeenCalled()
      document.body.removeChild(link)
    })

    it('should call onCancelNavigation when cancel action is chosen', async () => {
      const { init } = useUnsavedChangesGuard()

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onCancelNavigation: mockComponent1.onCancelNavigation,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true })
      link.dispatchEvent(clickEvent)

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: 'cancel' },
      }))

      await flushPromises()

      expect(mockComponent1.onCancelNavigation).toHaveBeenCalled()
      document.body.removeChild(link)
    })

    it('should save all components with unsaved changes', async () => {
      const { init: init1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
      mockComponent2.hasUnsavedChanges.mockReturnValue(true)

      init1({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent1.saveUnsavedChanges,
        componentId: 'component-1',
      })

      init2({
        hasUnsavedChanges: mockComponent2.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent2.saveUnsavedChanges,
        componentId: 'component-2',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com'
      document.body.appendChild(link)

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true })
      link.dispatchEvent(clickEvent)

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: 'save' },
      }))

      await flushPromises()

      expect(mockComponent1.saveUnsavedChanges).toHaveBeenCalled()
      expect(mockComponent2.saveUnsavedChanges).toHaveBeenCalled()
      document.body.removeChild(link)
    })

    it('should navigate after successful save', async () => {
      const { init } = useUnsavedChangesGuard()

      init({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent1.saveUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = document.createElement('a')
      link.href = 'https://example.com/test'
      document.body.appendChild(link)

      const clickEvent = new MouseEvent('click', { bubbles: true, cancelable: true })
      link.dispatchEvent(clickEvent)

      document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
        detail: { action: 'save' },
      }))

      await flushPromises()

      expect(globalThis.location.href).toBe('https://example.com/test')
      document.body.removeChild(link)
    })
  })

  describe('cleanup', () => {
    it('should stop checking component after cleanup', () => {
      const { init, cleanup } = useUnsavedChangesGuard()
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      init({
        componentId: 'test',
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
      })

      cleanup()

      const event = new Event('beforeunload', {
        cancelable: true,
      })

      window.dispatchEvent(event)

      expect(mockComponent1.hasUnsavedChanges).not.toHaveBeenCalled()
    })

    it('should only cleanup the specific component, not others', () => {
      const { init: init1, cleanup: cleanup1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
      mockComponent2.hasUnsavedChanges.mockReturnValue(true)

      init1({
        componentId: 'component-1',
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
      })

      init2({
        componentId: 'component-2',
        hasUnsavedChanges: mockComponent2.hasUnsavedChanges,
      })

      cleanup1()

      const event = new Event('beforeunload', { cancelable: true })
      window.dispatchEvent(event)

      expect(mockComponent1.hasUnsavedChanges).not.toHaveBeenCalled()
      expect(mockComponent2.hasUnsavedChanges).toHaveBeenCalled()
    })
  })
})
