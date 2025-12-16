/**
 * (c) 2010-present DEMOS plan GmbH.
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

  const elementsHiddenForBaseMap = [
    {
      node: document.querySelector('input[name="r_user_toggle_visibility"]'),
      defaultValue: true
    },
    {
      node: document.querySelector('input[name="r_opacity"]'),
      defaultValue: '100'
    }
  ]

  // Get references to default visibility elements
  const defaultVisibilityCheckbox = document.querySelector('input[name="r_default_visibility"]')
  const defaultVisibilityHint = document.getElementById('defaultVisibilityHint')

  // Detect if we're in edit mode
  const isEditMode = defaultVisibilityCheckbox?.dataset.cy === 'editMapLayerDefaultVisibility'

  // If base layer, hide toggleVisibility checkbox and its parent (the label)
  const isBaseLayerSelected = document.querySelector('input[name="r_type"][value="base"]')?.checked

  if (isBaseLayerSelected) {
    elementsHiddenForBaseMap.forEach((element) => {
      disableNode(element.node, element.defaultValue)
    })
    if (isEditMode) {
      // Show hint, don't change checkbox status
      if (defaultVisibilityCheckbox && defaultVisibilityHint && !defaultVisibilityCheckbox.disabled) {
        defaultVisibilityHint.classList.remove('hidden')
      }
    }

    // If 'create' view, show hint + uncheck checkbox
    if (!isEditMode) {
      updateDefaultVisibility(true)
    }
  }

  // If overlay selected and 'create' view, hide hint + check checkbox
  if (!isBaseLayerSelected && !isEditMode) {
    updateDefaultVisibility(false)
  }

  // Add event listener to handle checkbox change
  Array.from(document.querySelectorAll('input[name="r_type"]')).forEach(el => el.addEventListener('change', handleUserToggle))

  function createHiddenNode (node) {
    const hiddenElement = node.cloneNode()
    hiddenElement.setAttribute('hidden', true)
    hiddenElement.setAttribute('id', `hidden_${node.getAttribute('id')}`)

    return hiddenElement
  }

  function disableNode (node, defaultValue) {
    if (node.type === 'checkbox') {
      node.checked = defaultValue
    } else {
      node.value = defaultValue
    }
    document.getElementById('form').appendChild(createHiddenNode(node))
    node.setAttribute('disabled', 'true')
  }

  function enableNode (node) {
    node.removeAttribute('disabled')
    const hidden = document.getElementById(`hidden_${node.getAttribute('id')}`)
    if (hidden) {
      document.getElementById('form').removeChild(hidden)
    }
  }

  function updateDefaultVisibility (isBaseLayer) {
    if (defaultVisibilityCheckbox && defaultVisibilityHint) {
      // Don't show hint or change checkbox if it's disabled (e.g., in visibility group)
      if (defaultVisibilityCheckbox.disabled) {
        defaultVisibilityHint.classList.add('hidden')
        return
      }

      if (isBaseLayer) {
        defaultVisibilityCheckbox.checked = false
        defaultVisibilityHint.classList.remove('hidden')
      } else {
        defaultVisibilityCheckbox.checked = true
        defaultVisibilityHint.classList.add('hidden')
      }
    }
  }

  function handleUserToggle (e) {
    const radioVal = e.target.value

    const isBaseLayer = radioVal === 'base'

    if (isBaseLayer) {
      elementsHiddenForBaseMap.forEach((element) => {
        disableNode(element.node, element.defaultValue)
      })
    } else {
      elementsHiddenForBaseMap.forEach((element) => {
        enableNode(element.node)
      })
    }

    // Update default visibility based on layer type
    updateDefaultVisibility(isBaseLayer)
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
