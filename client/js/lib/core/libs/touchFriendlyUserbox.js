//  Tweak touch behavior for userbox

function toggleFlyout (e) {
  e.preventDefault()
  e.stopPropagation()
  const flyoutLink = e.currentTarget
  const flyoutBox = flyoutLink.parentElement

  if (flyoutBox.classList.contains('is-expanded')) {
    flyoutBox.classList.remove('is-expanded')
    document.querySelector('body').classList.remove('has-open-flyout')
    window.location.href = flyoutLink.getAttribute('href')
  } else {
    flyoutBox.classList.add('is-expanded')
    flyoutLink.classList.add('is-current')
    document.querySelector('body').classList.add('has-open-flyout')
  }
}

function closeFlyout (e) {
  const el = e.currentTarget

  if (el.classList.contains('has-open-flyout')) {
    el.classList.remove('has-open-flyout')
    const flyout = document.querySelector('[data-touch-flyout]')
    const flyoutBox = flyout.parentElement
    flyoutBox.classList.remove('is-expanded')
    flyout.classList.remove('is-current')
  }
}

function initUserbox () {
  const flyout = document.querySelector('[data-touch-flyout]')

  if (flyout) {
    flyout.addEventListener('touchstart', toggleFlyout)
    document.querySelector('body').addEventListener('touchstart', closeFlyout)
  }
}

export default initUserbox
