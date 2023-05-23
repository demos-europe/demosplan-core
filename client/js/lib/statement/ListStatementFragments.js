/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default function ListStatementFragments () {
  /**
   * Toggle all checkboxes
   */
  const checkbox = $('[data-export-actions-check-all]')
  if (checkbox.length > 0) {
    //  Toggle onChange
    checkbox.on('change', function (e) {
      const checkboxes = $('[data-selection-checkbox]')

      if (checkbox.is(':checked')) {
        checkboxes.prop('checked', true)
      } else {
        checkboxes.prop('checked', false)
      }
    })
  }

  /**
   * Export to pdf
   */
  const exportBtn = $('[data-export-actions-export]')
  exportBtn.on('click', function (event) {
    event.preventDefault()
    const ids = []
    $('[data-selection-checkbox]').each(function (index, item) {
      if (item.checked === true) {
        ids.push(item.id.replace(':item_check[]', ''))
      }
    })
    const form = document.createElement('form')
    form.setAttribute('method', 'POST')
    form.setAttribute('action', Routing.generate('DemosPlan_fragment_list_export'))
    for (const index in ids) {
      const field = document.createElement('input')
      field.setAttribute('type', 'hidden')
      field.setAttribute('name', 'fragmentIds[]')
      field.setAttribute('value', ids[index])
      form.appendChild(field)
    }
    const isArchive = $('[data-export-is-archive]')
    if (isArchive.length > 0 && isArchive[0].value === 'true') {
      const field = document.createElement('input')
      field.setAttribute('type', 'hidden')
      field.setAttribute('name', 'isArchive')
      field.setAttribute('value', true)
      form.appendChild(field)
    }
    $('[name^="filter_"]').each(function (index, item) {
      const c = item.cloneNode(true)
      form.appendChild(c)
    })
    document.body.appendChild(form)
    form.submit()
  })
}
