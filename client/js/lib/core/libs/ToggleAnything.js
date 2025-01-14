/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { getAnimationEventName, prefixClass } from '@demos-europe/demosplan-ui'

/**
 *  ToggleAnything
 *  Adds toggle behavior to elements
 *
 *  Markup Examples
 *  --------------------------------------------------------------------------------
 *  tbd
 *
 *  @deprecated Use a more dedicated UI pattern.
 *  @improve T12068
 */
export default function ToggleAnything () {
  const taPrefixed = $(prefixClass('.js__toggleAnything'))
  const taNotPrefixed = $('.js__toggleAnything')
  const omitPrefix = taNotPrefixed.length > 0
  const ta = omitPrefix ? taNotPrefixed : taPrefixed.length > 0 ? taPrefixed : []

  if (ta.length > 0) {
    console.warn('Found usage of deprecated ToggleAnything, please use a dedicated reveal pattern instead.')

    //  Get config from data attributes of element
    const getConfig = function (el) {
      const config = {}
      const defaultProperty = prefixClass('.is-active', omitPrefix)

      /**
       * Which element to target?
       *
       * 'next' to toggle next element in dom (default)
       * 'child' to toggle first child element
       * 'data' to toggle next element with 'data-toggle-target' set
       *  id (with leading #) to toggle elements with this data-toggle-id
       *  class (with leading dot) to toggle all elements with class
       *
       * @type string (default: 'next')
       */
      config.target = el.data('toggle') !== undefined ? el.data('toggle') : 'next'

      /**
       * Which class to toggle on the target?
       *
       * @type string (default: defaultProperty '.is-active')
       */
      config.property = el.data('toggle-property') !== undefined ? el.data('toggle-property') : defaultProperty

      /**
       * Should it listen to animationEnd & toggle a class 'is-visible' afterwards?
       * this assumes that, if no toggle-property is set, the instance is used with .o-toggle
       *
       * @type boolean
       */
      config.isVisibleOnAnimationEnd = el.data('toggle-property') === undefined || el.data('toggle-property') === defaultProperty

      /**
       * Define scope in which toggles are closed
       *
       * 'none' to not clear other toggle states (default)
       * any css selector whose children shall be cleared of css selector .property
       *
       * @type string (default: 'none')
       */
      config.container = el.data('toggle-container') !== undefined ? el.data('toggle-container') : 'none'

      /**
       * Define if toggle toggles only in, not out on second click
       * @TODO rename to data-toggle-in
       *
       * @type boolean (default: false)
       */
      config.toggleExclusive = el.data('toggle-exclusive') !== undefined

      /**
       * Define if toggle toggles only out, not in on second click
       *
       * @type boolean (default: false)
       */
      config.toggleOut = el.data('toggle-out') !== undefined

      /**
       * Define if toggle should be activated for a certain screen width only
       *
       * @type boolean (default: false)
       */
      config.responsive = el.data('toggle-responsive') !== undefined ? el.data('toggle-responsive') : false

      /**
       * Element to toggle
       *
       * @type object
       */
      config.targetObj = getTargetObj(el, config.target)

      /**
       * Define if default behavior of element should be suppressed
       *
       * @type boolean (default: false)
       */
      config.preventDefault = el.data('toggle-prevent-default') !== undefined

      //  Remove class dot if present
      if (config.property.indexOf('.') === 0) {
        config.propertyClass = config.property.slice(1)
      }

      return config
    }

    //  Returns target obj based on which type of target was specified
    const getTargetObj = function ($this, target) {
      if (target.indexOf('#') === 0) {
        return $('[data-toggle-id="' + target.slice(1) + '"]')
      } else if (target.indexOf('.') === 0) {
        return $(target)
      } else if (target === 'data') {
        return $this.nextAll('[data-toggle-target]').first() // Wat?
      } else if (target === 'child') {
        return $this.children().first()
      } else {
        return $this.next()
      }
    }

    /**
     * Toggle aria-attributes of toggle trigger and its target(s) to current state
     *
     * This behavior is only executed when the respective attributes initially are
     * found in the element. This way the omission of any of the attributes disables
     * described behavior.
     *
     * @param {Object} $el - The jQuery Object containing the trigger element
     * @param {boolean} isToggled - The current state of the
     */
    const toggleAriaAttrs = function ($el, isToggled) {
      /*
       *  Set `aria-expanded` of toggle trigger
       *  See https://www.w3.org/TR/wai-aria-1.1/#aria-expanded
       */
      $el.each(function () {
        if (this.hasAttribute('aria-expanded')) {
          $(this).attr('aria-expanded', isToggled)
        }
      })

      //  Set `aria-hidden` of toggle targets
      $el.config.targetObj.each(function () {
        if (this.hasAttribute('aria-hidden')) {
          $(this).attr('aria-hidden', !isToggled)
        }
      })
    }

    //  Performs toggle actions in onOff mode
    const toggleOnOff = function ($this) {
      //  Remove active toggle class from every toggle which references target
      $('[data-toggle="' + $this.config.target + '"]').removeClass($this.config.propertyClass + '-toggle')
      $this.addClass($this.config.propertyClass + '-toggle')
    }

    //  Listens to animation end + toggles class to target element afterwards
    const toggleClassAfterAnimationEnd = function (el, cssClass) {
      const animationEvent = getAnimationEventName()

      if (el.config.isVisibleOnAnimationEnd && animationEvent) {
        el.config.targetObj.one(animationEvent, function () {
          el.config.targetObj.toggleClass(cssClass)
        })
      }
    }

    //  Toggle the current classes on toggle and target
    const toggleCurrent = function (el) {
      const target = el.config.targetObj

      if (el.config.toggleExclusive === true) {
        //  Toggle only in
        if (!target.hasClass(el.config.propertyClass)) {
          toggleOnOff(el)
          toggleAriaAttrs(el, true)
          target.addClass(el.config.propertyClass)
        }
      } else if (el.config.toggleOut === true) {
        //  Toggle only out
        if (target.hasClass(el.config.propertyClass)) {
          toggleOnOff(el)
          toggleAriaAttrs(el, false)
          target.removeClass(el.config.propertyClass)
        }
      } else {
        //  Toggle default
        el.toggleClass(el.config.propertyClass + '-toggle')
        target.toggleClass(el.config.propertyClass)

        const isToggled = el.hasClass(el.config.propertyClass + '-toggle')
        toggleAriaAttrs(el, isToggled)
      }
    }

    //  Initialize behavior
    const initToggleAnything = function (index, value) {
      const el = $(value)

      el.config = getConfig(el)

      //  Fire only if screen with matches
      if (!el.config.responsive || window.innerWidth < el.config.responsive) {
        //  Remove css to activate toggle visuals
        el.closest(prefixClass('.o-toggle', omitPrefix)).removeClass(prefixClass('is-disabled-toggle', omitPrefix))
        toggleAriaAttrs(el, false)

        /*
         *  Set class on target element which sets negative animation-delay for targets
         *  which have to be visible on load; remove class on first click
         */
        if (el.config.targetObj.hasClass(el.config.propertyClass)) {
          el.config.targetObj.addClass(prefixClass('is-run', omitPrefix))
          el.one('click', function () {
            el.config.targetObj.removeClass(prefixClass('is-run', omitPrefix))
          })
        }

        //  Activate toggle on page load when toggles are checked checkboxes / radios
        if (el.is(':checked')) {
          toggleOnOff(el)
          el.config.targetObj.addClass(el.config.propertyClass)
        }

        el.on('click statementform', { el }, function (e) {
          //  Prevent default if configured so
          if (el.config.preventDefault === true) {
            e.preventDefault()
          }

          //  Close all containers in scope
          if (el.config.container !== 'none') {
            el
              .closest(el.config.container)
              .find(el.config.property)
              .not(el.config.targetObj)
              .removeClass(el.config.propertyClass)

            el
              .closest(el.config.container)
              .find(el.config.property + '-toggle')
              .not(el)
              .removeClass(el.config.propertyClass + '-toggle')
          }

          toggleCurrent(el)
          if (el.config.container === 'none') {
            toggleClassAfterAnimationEnd(el, prefixClass('is-visible', omitPrefix))
          }

          //  Trigger custom event for other components to listen to
          if (!el.config.targetObj.data('toggle-touched')) {
            el.config.targetObj.trigger('isVisible').data('toggle-touched', 1)
          }

          //  Vue Components may listen to this
          const toggleAnythingClicked = new CustomEvent('toggleAnything:clicked', { data: el.data('toggle-id') })

          //  Vue Components may listen to this
          document.dispatchEvent(toggleAnythingClicked)
        })
      }
    }

    //  Attach events
    ta.each(function (index, value) {
      initToggleAnything(index, value)
    })
  }
}
