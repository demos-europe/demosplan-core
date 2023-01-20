export default function CheckableItem () {
  /**
   *
   *  Markup Example [data-form-actions-check-all]:
   *
   *  <label class="btn-icns display--inline" for="select_all">
   *      <input id="select_all" type="checkbox" data-form-actions-check-all>
   *      {{ Translator.trans('markall') }}
   *  </label>
   *
   *  Toggle all checkboxes inside a form
   */
  const toggleCheckboxes = function (checkbox, form, checkboxSelector) {
    checkboxSelector = checkboxSelector || ''
    const selectAllBTN = Array.from(checkbox)
    const checkboxes = document.querySelectorAll('input[type="checkbox"]' + checkboxSelector)

    selectAllBTN.forEach((parent) => {
      parent.addEventListener('change', () => {
        Array.from(checkboxes).forEach((child) => {
          child.checked = parent.checked
        })
      })
    })
  }

  /**
   * Toggle all checkboxes inside the form nearest to self
   * with the Property '[name="item_check[]"]'
   */
  const formActionsCheckAll = document.querySelectorAll('[data-form-actions-check-all]')
  const allFormActionsCheckAll = Array.from(formActionsCheckAll)
  let checkAllForm = ''

  allFormActionsCheckAll.forEach((parent) => {
    checkAllForm = parent.closest('form')
  })

  //  Perform initial toggle
  toggleCheckboxes(formActionsCheckAll, checkAllForm, '[name="item_check[]"], [data-checkable-item]')

  /**
   * Group & Children checkbox
   * parent checkboxes must have: 'data-checkable-parent-id', we need id of parent
   * child checkboxes have same id like parent id, in data attribute: 'data-checkable-item'
   */
  const checkableParent = document.querySelectorAll('[data-checkable-parent-id]')

  Array.from(checkableParent).forEach((parentEl) => {
    const checkableParentId = parentEl.dataset.checkableParentId
    const children = document.querySelectorAll(`[data-checkable-item="${checkableParentId}"]`)

    // Toggle all children, when parent checked
    parentEl.addEventListener('change', () => {
      Array.from(children).forEach((child) => {
        child.checked = parentEl.checked
      })
    })

    // Unchecked child is unchecked parent
    Array.from(children).forEach((child) => {
      child.addEventListener('change', () => {
        if (child.checked === false) {
          parentEl.checked = false
        }
      })
    })
  })
}
