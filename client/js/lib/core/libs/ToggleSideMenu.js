/**
 * Toggle the side menu in/out. Saves state in sessionStorage.
 *
 * In a perfect world, this would have been a Vue component, but since
 * multiple elements have to be changed, that would need every view to
 * mount a Vue instance on #app. Which would most certainly break all
 * pages that add interaction via <script>.
 *
 * Some of the implementation details are not quite good practice.
 * This may be seen as a WIP state because both sideMenu and <script>
 * status may change soon.
 */
class ToggleSideMenu {
  constructor () {
    // Css classes to throw on the elements
    this.css = {
      iconMenuOpen: 'fa-chevron-right',
      iconMenuClosed: 'fa-chevron-left',
      hide: 'display--none',
      isExpanded: 'is-expanded'
    }

    this.trigger = document.querySelector('[data-toggle-sidebar-menu-trigger]')

    if (this.trigger) {
      this.triggerIcon = this.trigger.querySelector('i')
      this.mainContent = document.getElementById('jumpContent')
      this.sideMenu = document.getElementById('sideMenu')
      this.sideMenuContainer = document.querySelector('[data-toggle-sidebar-menu-container]')

      // Get saved value from sessionStorage, toggle the respective state and bind event to trigger button
      const stored = sessionStorage.getItem('sideMenuOpen')
      this.open = stored === null ? true : JSON.parse(stored)
      this.open ? this.toggleIn() : this.toggleOut()
      this.trigger.addEventListener('click', this.toggle.bind(this))
    }
  }

  toggle () {
    this.open = (this.open === false)
    this.open ? this.toggleIn() : this.toggleOut()
    sessionStorage.setItem('sideMenuOpen', this.open)
  }

  toggleIn () {
    this.sideMenu.classList.remove(this.css.hide)
    this.mainContent.classList.remove(this.css.isExpanded)
    this.sideMenuContainer.classList.add(this.css.isExpanded)
    this.triggerIcon.classList.add(this.css.iconMenuOpen)
    this.triggerIcon.classList.remove(this.css.iconMenuClosed)
  }

  toggleOut () {
    this.sideMenu.classList.add(this.css.hide)
    this.mainContent.classList.add(this.css.isExpanded)
    this.sideMenuContainer.classList.remove(this.css.isExpanded)
    this.triggerIcon.classList.add(this.css.iconMenuClosed)
    this.triggerIcon.classList.remove(this.css.iconMenuOpen)
  }
}

const initToggleSideMenu = () => {
  return new ToggleSideMenu()
}

export default initToggleSideMenu
