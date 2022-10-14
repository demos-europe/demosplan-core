/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getScrollTop } from 'demosplan-utils'
import MatchMedia from '@DpJs/lib/MatchMedia'

/**
 * This is a minimal own implementation of sticky behavior, since our "beloved" semantic-ui Sticky
 * could not handle sticky-bottom.
 */
class Stickier {
  /**
   * The constructor sets defaults and initializes the sticky behavior of the given element.
   *
   * @param {DOMElement} stickyElement
   *    The element the sticky behavior will be applied to.
   * @param {DOMElement} contextElement
   *    The element which is used as context for calculating stickyness
   * @param {Integer} elementOffset
   *    A pixel value that is additionally added to the elements position when determining stickyness.
   * @param {String} stickToDirection ('top'|'bottom')
   *    Should te element stick to the top or bottom of its container
   * @param {String} stickFromBreakpoint ('palm'|'lap-up'|'desk-up'|'wide')
   *    Breakpoint, at which the sticky behavior should be applied.
   * @param {DOMElement} containerElement
   *    In some circumstances (if several sticky elements need to work together  dynamically) it may be needed
   *    to specify the container manually to prevent the instances from interfering).
   * @param {Boolean} observeContext
   *    Whether context changes should trigger a refresh of stickier positions.
   */
  constructor (
    stickyElement,
    contextElement,
    elementOffset = 0,
    stickToDirection = 'top',
    stickFromBreakpoint = 'palm',
    containerElement = null,
    observeContext = true
  ) {
    // Set up variables from options
    this.element = stickyElement
    this.context = contextElement || this.element.offsetParent
    this.container = containerElement || this.element.offsetParent
    this.elementOffset = elementOffset
    this.stickToDirection = stickToDirection
    this.stickFromBreakpoint = stickFromBreakpoint
    this.observeContext = observeContext

    // Class names are kept here as constant for better maintainability
    this.className = {
      top: 'is-top',
      bottom: 'is-bottom',
      fixed: 'is-fixed',
      bound: 'is-bound'
    }

    // Initialize MatchMedia() for later usage when determining  if the element should be initialized.
    this.currentBreakpoint = new MatchMedia()

    this.isParentContext = this._isParentContext()

    // The positions and offset values of viewport, element and context are initially stored.
    this._savePositions()

    // Wrap element with a div to maintain layout.
    this._wrapElement()

    if (this.observeContext) {
      // Observe context to check if sticky state has to be updated.
      this.contextResizeObserver = new ResizeObserver(this._handleContextChange.bind(this))
      this.contextResizeObserver.observe(this.context)

      /*
       * Also observe mutations because when min-height is set to container,
       * ResizeObserver does not trigger anymore even if its content changes.
       */
      this.contextMutationObserver = new MutationObserver(this._handleContextChange.bind(this))
      this.contextMutationObserver.observe(this.context, {
        childList: true,
        subtree: true
      })
    }

    // Check if element changes dimensions, hence wrapper height has to be updated.
    this.elementResizeObserver = new ResizeObserver(this._handleElementChange.bind(this))
    this.elementResizeObserver.observe(this.element)

    // Bind scroll handler to check if sticky state has to be updated.
    document.addEventListener('scroll', this._handleScroll.bind(this), {
      passive: true
    })
  }

  destroy () {
    if (this.observeContext) {
      this.contextResizeObserver.disconnect()
      this.contextMutationObserver.disconnect()
    }
    this.elementResizeObserver.disconnect()
    document.removeEventListener('scroll', this._handleScroll)
    this.wrapped.remove()
  }

  /**
   * Wrap the sticky element with a div to prevent content jitter when element leaves "Normal flow".
   * @see https://developer.mozilla.org/en-US/docs/Learn/CSS/CSS_layout/Normal_Flow
   * @return {HTMLDivElement}
   */
  _wrapElement () {
    const wrapper = document.createElement('div')
    this.element.parentNode.insertBefore(wrapper, this.element)
    wrapper.appendChild(this.element)
    this.wrapped = wrapper
    this._setMinHeight(this.wrapped)
  }

  /**
   * On context change, all positioning values have to be recalculated.
   * @private
   */
  _handleContextChange () {
    this._unsetContainerHeight()
    this._unsetPosition()
    if (this._StickFromBreakpoint() === true) {
      this._savePositions()
    }
    this._stickier()
  }

  /**
   * If the element itself changes dimensions, all values have to be recalculated.
   * @private
   */
  _handleElementChange () {
    window.requestAnimationFrame(() => this._updateElementWrapper())
  }

  _updateElementWrapper () {
    // Update all stored values but preserve element.top which is the initial top position of the element
    const elementTop = this.stored.element.top
    this._savePositions()
    this.stored.element.top = elementTop
    this.stored.element.bottom = elementTop + this.stored.element.height
    this._setMinHeight(this.wrapped)
  }

  /**
   * On scroll, invoke the main function that calculates positioning of the element.
   * @private
   */
  _handleScroll () {
    window.requestAnimationFrame(this._stickier.bind(this))
  }

  /**
   * Depending on current position of element relative to the viewport & its context, toggle sticky state of element.
   */
  _stickier () {
    if (this._StickFromBreakpoint() === false) {
      return
    }

    const stored = this.stored
    const doesNotFit = stored.fitsViewport === false
    const context = stored.context
    const element = stored.element
    const viewport = stored.viewport
    const scroll = {
      top: getScrollTop() + this.elementOffset,
      bottom: getScrollTop() + this.elementOffset + viewport.height
    }
    const elementScroll = stored.fitsViewport ? 0 : this._getElementScroll(scroll.top)

    this._log(() => this._isInitial(), this._isInitial(), this._is('top'), this._is('bottom'))

    // After page load, and before other conditions trigger, the element will be in its initial state
    if (this._isInitial()) {
      if (scroll.top >= context.bottom) {
        this._log('element context above viewport - initial position is bottom of context')
        this._bindBottom()
      } else if (scroll.top > element.top) {
        if ((element.height + scroll.top - elementScroll) >= context.bottom) {
          this._log('element ends below context - initial position is bottom of context')
          this._bindBottom()
        } else {
          this._log('element ends above context - initial position is fixed to top')
          this._fixTop()
        }
      }
    } else if (this._is('fixed')) {
      if (this._is('top')) {
        if (scroll.top <= element.top) {
          this._log('fixed element reached top of container')
          this._unsetPosition()
        } else if ((element.height + scroll.top - elementScroll) >= context.bottom) {
          this._log('fixed element reached bottom of container')
          this._bindBottom()
        } else if (doesNotFit) {
          this._log('syncing large element wit scrollPosition')
          this._setElementScroll(elementScroll)
          this.stored.scroll = scroll.top
          this.stored.elementScroll = elementScroll
        }
      } else if (this._is('bottom')) {
        if ((scroll.bottom - element.height) <= element.top) {
          this._log('bottom fixed rail has reached top of container')
          this._unsetPosition()
        } else if (scroll.bottom >= context.bottom) {
          this._log('bottom fixed rail has reached bottom of container')
          this._bindBottom()
        } else if (doesNotFit) {
          this._log('syncing large element wit scrollPosition')
          this._setElementScroll(elementScroll)
          this.stored.scroll = scroll.top
          this.stored.elementScroll = elementScroll
        }
      }
    } else if (this._is('bottom')) {
      // Element is bound bottom, scrolling up...
      if (scroll.top <= element.top) {
        this._log('Jumped from bottom fixed to top fixed')
        this._unsetPosition()
      } else if (this._is('bound') && (scroll.top <= context.bottom - element.height)) {
        this._log('Fixing bottom attached element to top of browser.')
        this._fixTop()
      }
    }
  }

  _fixTop () {
    this._setElementWidth()
    this._setElementOffset()
    this.element.classList.remove(this.className.bottom)
    this.element.classList.remove(this.className.bound)
    this.element.classList.add(this.className.top)
    this.element.classList.add(this.className.fixed)
  }

  _bindBottom () {
    this.element.style.top = null
    this._unsetElementOffset()
    this._setElementWidth()
    this._setRail()

    this.element.classList.remove(this.className.top)
    this.element.classList.remove(this.className.fixed)
    this.element.classList.add(this.className.bottom)
    this.element.classList.add(this.className.bound)
  }

  _unFix () {
    this._unsetElementStyles()
    this.element.classList.remove(this.className.top)
    this.element.classList.remove(this.className.bottom)
    this.element.classList.remove(this.className.fixed)
  }

  _unBind () {
    this._unsetElementStyles()
    this.element.classList.remove(this.className.top)
    this.element.classList.remove(this.className.bottom)
    this.element.classList.remove(this.className.bound)
  }

  _unsetPosition () {
    this._unFix()
    this._unBind()
  }

  _unsetElementStyles () {
    this._unsetElementOffset()
    this._unsetRail()
    this.element.style[this.stickToDirection] = null
    this.element.style.width = null
  }

  /**
   * Set the context min-height to that of the container to make bottom bound state work.
   * @private
   */
  _setContainerHeight () {
    if (this.isParentContext || !this.container || this._isHidden(this.container)) {
      return
    }
    this.container.style['min-height'] = Math.ceil(this.stored.context.height) + 'px'
  }

  _unsetContainerHeight () {
    if (!this.container || this._isHidden(this.container)) {
      return
    }
    this.container.style['min-height'] = null
  }

  /**
   * Apply with of wrapper to element to sync it with normal flow.
   * @private
   */
  _setElementWidth () {
    this.element.style.width = this.wrapped.offsetWidth + 'px'
  }

  /**
   * When bound bottom, the parent of the fixed element needs to occupy the same vertical space as the context.
   * @private
   */
  _setRail () {
    this.wrapped.style['min-height'] = Math.ceil(this.stored.context.height) + 'px'
    this.wrapped.style.position = 'relative'
  }

  _unsetRail () {
    this.wrapped.style['min-height'] = Math.ceil(this.stored.element.height) + 'px'
    this.wrapped.style.position = null
  }

  /**
   * Apply offset that is given via options.
   * @private
   */
  _setElementOffset () {
    this.element.style[`margin-${this.stickToDirection}`] = this.elementOffset + 'px'
  }

  _unsetElementOffset () {
    this.element.style[`margin-${this.stickToDirection}`] = null
  }

  /**
   * Sync an element min height with stored height of fixed element.
   * @param element
   * @private
   */
  _setMinHeight (element) {
    element.style['min-height'] = Math.ceil(this.stored.element.height) + 'px'
  }

  /**
   * Determine if the current breakpoint matches the value specified when creating instance
   * @return {boolean|*}
   * @private
   */
  _StickFromBreakpoint () {
    return this.currentBreakpoint.is(this.stickFromBreakpoint) || this.currentBreakpoint.greater(this.stickFromBreakpoint)
  }

  /**
   * Save all needed positions and dimensions for later calculations
   * @private
   */
  _savePositions () {
    const element = {
      height: this._getHeight(this.element),
      top: this._getOffsetTop(this.element)
    }
    const context = {
      height: this._getHeight(this.context),
      top: this._getOffsetTop(this.context)
    }
    const viewport = {
      height: Math.max(document.documentElement.clientHeight, window.innerHeight || 0)
    }

    this.stored = {
      fitsViewport: ((element.height + this.elementOffset) <= viewport.height),
      scroll: null,
      elementScroll: null,
      element: {
        top: element.top,
        height: element.height,
        bottom: element.top + element.height
      },
      context: {
        top: context.top,
        height: context.height,
        bottom: context.top + context.height
      },
      viewport: {
        height: viewport.height
      }
    }

    this._setContainerHeight()
  }

  _setElementScroll (scroll) {
    if (this.stored.scroll === scroll) {
      return
    }
    if (this._is('top')) {
      this.element.style.top = `-${scroll}px`
      this.element.style.bottom = null
    }
    if (this._is('bottom')) {
      this.element.style.bottom = `-${scroll}px`
      this.element.style.top = null
    }
  }

  _getElementScroll (scroll) {
    scroll = scroll || getScrollTop()
    const element = this.stored.element
    const viewportHeight = this.stored.viewport.height
    const scrollDelta = this.stored.scroll ? (scroll - this.stored.scroll) : 0
    const maxScroll = element.height - viewportHeight + this.elementOffset
    let elementScroll = this._getCurrentElementScroll()
    const mayScroll = elementScroll + scrollDelta

    if (this.stored.fitsViewport || mayScroll < 0) {
      elementScroll = 0
    } else if (mayScroll > maxScroll) {
      elementScroll = maxScroll
    } else {
      elementScroll = mayScroll
    }
    return elementScroll
  }

  _getOffsetTop (element) {
    let offsetTop = 0

    do {
      offsetTop += element.offsetTop || 0
      element = element.offsetParent
    } while (element)

    return offsetTop
  }

  _getHeight (element) {
    return Math.max(element.offsetHeight, element.clientHeight)
  }

  _getCurrentElementScroll () {
    if (this.stored.elementScroll) {
      return this.stored.elementScroll
    }
    return Math.abs(parseInt(this._is('top') ? this.element.style.top : this.element.style.bottom, 10)) || 0
  }

  /**
   * Checks if element is bound, fixed, top or bottom by looking up the respective classNames.
   *
   * @param {String} prop (bound|fixed|top|bottom)
   * @return {*}
   * @private
   */
  _is (prop) {
    return this.element.classList.contains(this.className[prop])
  }

  /**
   * Checks if element is in its initial position.
   *
   * @return {boolean|boolean}
   * @private
   */
  _isInitial () {
    return (this._is('fixed') === false && this._is('bound') === false)
  }

  _isParentContext () {
    let element = this.element

    do {
      element = element.parentNode
      if (this.context === element) {
        return true
      }
    } while (element)

    return false
  }

  _isHidden (el) {
    const style = window.getComputedStyle(el)
    return (style.display === 'none')
  }

  /**
   * Log fn that can specify a callback to trigger selective console.log()
   * @private
   */
  _log () {
    let conditionFunction = () => true
    let log = ''
    let i = 0

    // Use callback fn to control output
    if (typeof arguments[0] === 'function') {
      conditionFunction = arguments[0]
      i = 1
    }

    if (conditionFunction()) {
      for (i; i < arguments.length; i++) {
        log += arguments[i] + ' '
      }
      return log
    }
  }
}

export default Stickier
