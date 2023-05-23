/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default () => {
  const checkLayerDependencies = (checked, requiredElements, toDisable) => {
    if (checked) {
      // Disable all radio-options
      const allRadioOptionsElements = Array.from(document.querySelectorAll('[name=r_type]'))
      allRadioOptionsElements.forEach((el) => {
        el.disabled = true
      })
      document.querySelector(requiredElements).checked = true
      // We have to enable the selected element, otherwise it won't show up in the post-request
      document.querySelector(requiredElements).disabled = false
      // Uncheck correlating options
      const allCheckedBoxElements = Array.from(document.querySelectorAll(toDisable))
      allCheckedBoxElements.forEach((el) => {
        el.checked = false
      })
    } else {
      const allDisabledElements = Array.from(document.querySelectorAll('[name=r_type]'))
      allDisabledElements.forEach((el) => {
        el.disabled = false
      })
    }
  }

  const allDataRequiresElements = Array.from(document.querySelectorAll('[data-requires]'))
  allDataRequiresElements.forEach((el) => {
    // Register eventListener for scope and bPlan
    el.addEventListener('change', () => {
      checkLayerDependencies(el.checked, el.getAttribute('data-requires'), el.getAttribute('data-to-disable'))
    })

    // Check if scope or bPlan is set on load so we have to disable the layer-type-options
    if (el.checked) {
      checkLayerDependencies(el.checked, el.getAttribute('data-requires'), el.getAttribute('data-to-disable'))
    }
  })

  // Check if base layer and disable user toggling on load
  if (document.querySelector('input[name="r_type"][value="base"]').checked) {
    const checkbox = document.querySelector('input[name="r_user_toggle_visibility"]')
    checkbox.checked = true
    checkbox.setAttribute('disabled', true)
    // Create hidden input with all necessary attributes
    document.getElementById('form').appendChild(createHidden())
  }

  // Add event listener to handle checkbox change
  Array.from(document.querySelectorAll('input[name="r_type"]')).forEach(el => el.addEventListener('change', handleUserToggle))

  function handleUserToggle (e) {
    const radioVal = e.target.value
    const checkbox = document.querySelector('input[name="r_user_toggle_visibility"]')
    if (radioVal === 'base') {
      checkbox.checked = true
      checkbox.setAttribute('disabled', true)
      document.getElementById('form').appendChild(createHidden())
    } else {
      checkbox.removeAttribute('disabled')
      const hidden = document.getElementById('hiddenUserToggle')
      if (hidden) {
        document.getElementById('form').removeChild(hidden)
      }
    }
  }

  function createHidden () {
    const hiddenCheckbox = document.createElement('input')
    hiddenCheckbox.setAttribute('type', 'checkbox')
    hiddenCheckbox.setAttribute('hidden', true)
    hiddenCheckbox.checked = true
    hiddenCheckbox.setAttribute('value', 1)
    hiddenCheckbox.setAttribute('name', 'r_user_toggle_visibility')
    hiddenCheckbox.setAttribute('id', 'hiddenUserToggle')
    return hiddenCheckbox
  }

  // Xplan default layer checkbox handling
  const inputXplanDefaultLayers = document.querySelector('[name=r_xplanDefaultlayers]')
  const xplanTrigger = document.querySelector('input[name="r_xplan"]')

  /*
   * If the procedure type is not bplan, we won't find the checkbox element,
   * so we can skip this
   */
  if (inputXplanDefaultLayers) {
    // Toggle defaultLayer checkbox
    const xPlanDefaultLayersVisibility = function (show) {
      const inputXplanDefaultLayers = document.querySelector('input[name="r_xplanDefaultlayers"]')
      if (show === false) {
        inputXplanDefaultLayers.parentNode.setAttribute('style', 'display: none')
        inputXplanDefaultLayers.checked = false
      } else {
        inputXplanDefaultLayers.parentNode.removeAttribute('style')
      }
    }

    // If r_xplan is checked, we want to show the defaultLayer checkbox
    if (xplanTrigger) {
      xPlanDefaultLayersVisibility(xplanTrigger.checked)
      xplanTrigger.addEventListener('change', (ev) => {
        xPlanDefaultLayersVisibility(ev.target.checked)
      })
    }
  }
}
