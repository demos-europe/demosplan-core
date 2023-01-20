/**
 * GlobalEventListener - Add Event listeners, that have been added & initialized in mounted.
 */

export default function initGlobalEventListener () {
  // Used for Scroll to Top
  const scrollToTop = document.querySelector('[data-scroll-to-top]')
  if (scrollToTop) {
    scrollToTop.addEventListener('click', function (event) {
      event.preventDefault()
      document.body.scrollTop = 0
      document.documentElement.scrollTop = 0
    })
  }

  // Used for responsively compressed menu
  const responsiveMenuHelper = document.querySelector('[data-responsive-menu-helper]')
  if (responsiveMenuHelper) {
    responsiveMenuHelper.addEventListener('click', function (event) {
      event.preventDefault()
      document.getElementById('responsive-menu-helper-checkbox').toggleAttribute('checked')
      responsiveMenuHelper.setAttribute('aria-expanded', document.getElementById('responsive-menu-helper-checkbox').checked)
    })
  }
}
