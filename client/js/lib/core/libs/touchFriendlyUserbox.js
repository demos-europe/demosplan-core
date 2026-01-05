/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

//  Tweak touch behavior for userbox

function toggleFlyout (e) {
  e.preventDefault()
  e.stopPropagation()
  const flyoutLink = e.currentTarget
  const flyoutBox = flyoutLink.parentElement

  // Check the state BEFORE closing all flyouts
  const isCurrentlyExpanded = flyoutBox.classList.contains('is-expanded')

  if (isCurrentlyExpanded) {
    flyoutBox.classList.remove('is-expanded')
    flyoutLink.classList.remove('is-current')
    document.querySelector('body').classList.remove('has-open-flyout')

    // Only redirect if there's a valid href and it's not a button
    const href = flyoutLink.getAttribute('href')
    if (href && href !== '#' && href !== window.location.href) {
      window.location.href = href
    }
  } else {
    // Close any other open flyouts first, then open this one
    closeAllFlyouts()

    flyoutBox.classList.add('is-expanded')
    flyoutLink.classList.add('is-current')
    document.querySelector('body').classList.add('has-open-flyout')
  }
}

function closeFlyout (e) {
  const clickTarget = e.target
  const body = document.querySelector('body')

  if (clickTarget.closest('[data-touch-flyout]') || clickTarget.closest('.c-flyout__content')) {
    return
  }

  if (body.classList.contains('has-open-flyout')) {
    closeAllFlyouts()
  }
}

function closeAllFlyouts () {
  const flyouts = document.querySelectorAll('[data-touch-flyout]')
  flyouts.forEach(flyout => {
    const flyoutBox = flyout.parentElement
    flyoutBox.classList.remove('is-expanded')
    flyout.classList.remove('is-current')
  })
  document.querySelector('body').classList.remove('has-open-flyout')
}

function initUserbox () {
  const flyouts = document.querySelectorAll('[data-touch-flyout]')

  if (flyouts.length > 0) {
    flyouts.forEach(flyout => {
      flyout.addEventListener('touchstart', toggleFlyout, { passive: false })
    })

    // Use click instead of touchstart for body to prevent race condition
    document.querySelector('body').addEventListener('click', closeFlyout)
  }
}

export default initUserbox
