/**
 *  ActionMenu
 *  Supplements .c-actionmenu
 *
 *  // @improve T13061
 *
 *  This is currently interim vue/js. Buttons (Menu Items) are Vue Components that emit events, while menu behavior is
 *  defined here. @TODO this should probably once be a vue component.
 *
 *   *  Markup Examples
 *  --------------------------------------------------------------------------------
 *
 *  <div class="c-actionmenu" data-actionmenu>
 *      <button class="c-actionmenu__trigger" aria-haspopup="true" aria-expanded="false">
 *          <i class="fa fa-th-large" aria-hidden="true"></i>
 *          Ansicht
 *      </button>
 *      <div class="c-actionmenu__menu" role="menu" hidden>
 *
 *          <button data-actionmenu-current>
 *              {{ Translator.trans('statements') }}
 *          </button>
 *
 *          <button>
 *              {{ Translator.trans('fragments') }}
 *          </button>
 *
 *      </div>
 *  </div>
 *
 *  To enable highlighting of current item, drop data-actionmenu-current on an item
 *
 */
import { prefixClass } from 'demosplan-ui/lib'

class ActionMenu {
  constructor (actionMenuElement) {
    this.el = actionMenuElement
    this.trigger = this.el.querySelector('[aria-haspopup]')
    this.menu = this.el.querySelector('[role="menu"]')
    this.menuItems = this.menu.querySelectorAll('[data-actionmenu-menuitem]')
    this.hasCurrent = (this.menu.querySelectorAll('[data-actionmenu-current]').length > 0)
    this.currentlySelectedIndex = 0

    this.openMenu = this.openMenu.bind(this)
    this.closeMenu = this.closeMenu.bind(this)
    this.highlightCurrent = this.highlightCurrent.bind(this)
    this.handleTriggerKeydown = this.handleTriggerKeydown.bind(this)
    this.handleTriggerFocus = this.handleTriggerFocus.bind(this)
    this.handleMenuItemKeydown = this.handleMenuItemKeydown.bind(this)

    // Open menu on hover
    this.el.addEventListener('mouseover', this.openMenu)

    //  Close menu on mouseleave
    this.el.addEventListener('mouseleave', this.closeMenu)

    //  Open menu on focus of trigger button + on hover of element
    this.trigger.addEventListener('focus', this.handleTriggerFocus)

    // Attach event listener for trigger button
    this.trigger.addEventListener('keydown', this.handleTriggerKeydown)

    // Attach event listeners for menu items
    for (let i = 0; i < this.menuItems.length; i++) {
      const menuItem = this.menuItems[i]

      //  Highlight currently selected menu item
      menuItem.addEventListener('click', this.highlightCurrent)

      //  Keyboard behavior
      menuItem.addEventListener('keydown', this.handleMenuItemKeydown)
    }
  }

  handleTriggerKeydown (event) {
    switch (event.keyCode) {
      // Down
      case 40:
        // Prevent page scrolling
        event.preventDefault()
        this.openMenu()
        this.menu.classList.remove(prefixClass('has-focused-trigger'))
        this.menuItems[this.currentlySelectedIndex].focus()
        break

      // Up, tab
      case 38:
      case 9:
        this.closeMenu()
        break

      // Esc
      case 27:
        this.closeMenu()
        this.trigger.focus()
        break

      default:
        break
    }
  }

  handleTriggerFocus () {
    this.openMenu()
  }

  handleMenuItemKeydown (event) {
    switch (event.keyCode) {
      // Down
      case 40:
        // Prevent page scrolling
        event.preventDefault()
        if (this.currentlySelectedIndex < this.menuItems.length - 1) {
          this.currentlySelectedIndex++
        } else {
          this.currentlySelectedIndex = 0
        }

        this.menuItems[this.currentlySelectedIndex].focus()
        break

      // Up
      case 38:
        // Prevent page scrolling
        event.preventDefault()
        if (this.currentlySelectedIndex === 0) {
          this.currentlySelectedIndex = this.menuItems.length - 1
        } else {
          this.currentlySelectedIndex--
        }
        this.menuItems[this.currentlySelectedIndex].focus()
        break

      // Tab, esc
      case 9:
      case 27:
        this.trigger.focus()
        this.closeMenu()
        break

      default:
        break
    }
  }

  openMenu () {
    this.trigger.setAttribute('aria-expanded', 'true')
    this.menu.classList.add('has-focused-trigger')
    this.el.classList.add('is-expanded')
  }

  closeMenu () {
    this.trigger.setAttribute('aria-expanded', 'false')
    this.menu.classList.remove('has-focused-trigger')
    this.el.classList.remove('is-expanded')
    this.currentlySelectedIndex = 0
  }

  highlightCurrent (event) {
    if (this.hasCurrent) {
      this.menu.querySelector('[data-actionmenu-current]').removeAttribute('data-actionmenu-current')
      event.target.setAttribute('data-actionmenu-current', true)
    }
  }
}

export default function () {
  const actionMenuElements = document.querySelectorAll('[data-actionmenu]')
  const actionMenus = []

  for (let i = 0; i < actionMenuElements.length; i++) {
    actionMenus.push(new ActionMenu(actionMenuElements[i]))
  }
}
