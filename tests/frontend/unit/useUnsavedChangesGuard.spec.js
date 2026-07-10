/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { _resetUnsavedChangesGuard, useUnsavedChangesGuard } from '@DpJs/composables/useUnsavedChangesGuard'

// Helper to flush all pending promises and timers
const flushPromises = () => new Promise(resolve => setTimeout(resolve, 0))

const createLink = (href) => {
  const link = document.createElement('a')

  link.href = href
  document.body.appendChild(link)

  return link
}

const clickLink = (link) => {
  const event = new MouseEvent('click', { bubbles: true, cancelable: true })
  const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

  link.dispatchEvent(event)

  return { event, preventDefaultSpy }
}

const dispatchDialogResult = (action) => {
  document.dispatchEvent(new CustomEvent('unsaved-changes-dialog:result', {
    detail: { action },
  }))
}

const dispatchBeforeUnload = () => {
  const event = new Event('beforeunload', { cancelable: true })
  const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

  globalThis.dispatchEvent(event)

  return { event, preventDefaultSpy }
}

const initGuardedComponent = (componentOptions) => {
  const { init } = useUnsavedChangesGuard()

  init(componentOptions)

  return init
}

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

    Object.defineProperty(globalThis, 'location', {
      configurable: true,
      writable: true,
      value: {
        href: '',
      },
    })
  })

  afterEach(() => {
    dialogElement?.remove()
    jest.clearAllMocks()
  })

  describe('initialization', () => {
    it('should register global listeners only once', () => {
      const addEventListenerSpy = jest.spyOn(globalThis, 'addEventListener')

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
      mockComponent1.hasUnsavedChanges.mockReturnValue(false)

      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const { preventDefaultSpy } = dispatchBeforeUnload()

      expect(preventDefaultSpy).not.toHaveBeenCalled()
    })

    it('should prevent navigation when component has unsaved changes', () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const { preventDefaultSpy } = dispatchBeforeUnload()

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

      globalThis.dispatchEvent(event)

      expect(mockComponent1.hasUnsavedChanges).toHaveBeenCalled()
      expect(mockComponent2.hasUnsavedChanges).toHaveBeenCalled()
      expect(preventDefaultSpy).toHaveBeenCalled()
    })
  })

  describe('link click handler', () => {
    it('should not prevent navigation when no unsaved changes', () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(false)

      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com')
      const { preventDefaultSpy } = clickLink(link)

      expect(preventDefaultSpy).not.toHaveBeenCalled()

      link.remove()
    })

    it('should prevent navigation and show dialog when link clicked with unsaved changes', async () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com')
      const dialogShowListener = jest.fn()

      document.addEventListener('unsaved-changes-dialog:show', dialogShowListener)

      const { preventDefaultSpy } = clickLink(link)

      expect(preventDefaultSpy).toHaveBeenCalled()

      await flushPromises()

      expect(dialogShowListener).toHaveBeenCalled()
      link.remove()
      document.removeEventListener('unsaved-changes-dialog:show', dialogShowListener)
    })

    it('should ignore clicks on non-link elements', () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        componentId: 'test-component-1',
      })

      const button = document.createElement('button')

      document.body.appendChild(button)

      const event = new MouseEvent('click', { bubbles: true, cancelable: true })
      const preventDefaultSpy = jest.spyOn(event, 'preventDefault')

      button.dispatchEvent(event)

      expect(preventDefaultSpy).not.toHaveBeenCalled()

      button.remove()
    })
  })

  describe('dialog result handling', () => {
    beforeEach(() => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
    })

    it('should call saveUnsavedChanges when save action is chosen', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent1.saveUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com')

      clickLink(link)
      dispatchDialogResult('save')

      await flushPromises()

      expect(mockComponent1.saveUnsavedChanges).toHaveBeenCalled()
      link.remove()
    })

    it('should call onDiscardChanges when discard action is chosen', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onDiscardChanges: mockComponent1.onDiscardChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com')

      clickLink(link)
      dispatchDialogResult('discard')

      await flushPromises()

      expect(mockComponent1.onDiscardChanges).toHaveBeenCalled()
      link.remove()
    })

    it('should navigate after discarding changes', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onDiscardChanges: mockComponent1.onDiscardChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com/discard-test')

      clickLink(link)
      dispatchDialogResult('discard')

      await flushPromises()

      expect(globalThis.location.href).toBe('https://example.com/discard-test')
      link.remove()
    })

    it('should call onCancelNavigation when cancel action is chosen', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onCancelNavigation: mockComponent1.onCancelNavigation,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com')

      clickLink(link)
      dispatchDialogResult('cancel')

      await flushPromises()

      expect(mockComponent1.onCancelNavigation).toHaveBeenCalled()
      link.remove()
    })

    it('should NOT navigate after cancel action', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        onCancelNavigation: mockComponent1.onCancelNavigation,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com/cancel-test')

      clickLink(link)
      dispatchDialogResult('cancel')

      await flushPromises()

      // Location should remain empty (not navigate)
      expect(globalThis.location.href).toBe('')
      link.remove()
    })

    it('should save all components with unsaved changes', async () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
      mockComponent2.hasUnsavedChanges.mockReturnValue(true)

      const { init: init1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

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

      const link = createLink('https://example.com')

      clickLink(link)
      dispatchDialogResult('save')

      await flushPromises()

      expect(mockComponent1.saveUnsavedChanges).toHaveBeenCalled()
      expect(mockComponent2.saveUnsavedChanges).toHaveBeenCalled()
      link.remove()
    })

    it('should navigate after successful save', async () => {
      initGuardedComponent({
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
        saveUnsavedChanges: mockComponent1.saveUnsavedChanges,
        componentId: 'test-component-1',
      })

      const link = createLink('https://example.com/test')

      clickLink(link)
      dispatchDialogResult('save')

      await flushPromises()

      expect(globalThis.location.href).toBe('https://example.com/test')
      link.remove()
    })
  })

  describe('cleanup', () => {
    it('should stop checking component after cleanup', () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)

      const { init, cleanup } = useUnsavedChangesGuard()

      init({
        componentId: 'test',
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
      })

      cleanup()

      dispatchBeforeUnload()

      expect(mockComponent1.hasUnsavedChanges).not.toHaveBeenCalled()
    })

    it('should only cleanup the specific component, not others', () => {
      mockComponent1.hasUnsavedChanges.mockReturnValue(true)
      mockComponent2.hasUnsavedChanges.mockReturnValue(true)

      const { init: init1, cleanup: cleanup1 } = useUnsavedChangesGuard()
      const { init: init2 } = useUnsavedChangesGuard()

      init1({
        componentId: 'component-1',
        hasUnsavedChanges: mockComponent1.hasUnsavedChanges,
      })

      init2({
        componentId: 'component-2',
        hasUnsavedChanges: mockComponent2.hasUnsavedChanges,
      })

      cleanup1()

      dispatchBeforeUnload()

      expect(mockComponent1.hasUnsavedChanges).not.toHaveBeenCalled()
      expect(mockComponent2.hasUnsavedChanges).toHaveBeenCalled()
    })
  })
})
