/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { dpApi } from '@demos-europe/demosplan-ui'

export default function DeleteFragmentButton () {
  const deleteFragmentButton = document.querySelectorAll('[data-post-delete]')

  if (deleteFragmentButton.length > 0) {
    Array.from(deleteFragmentButton).forEach(el => el.addEventListener('click', event => {
      /*
       * Bind current element to the window, so we can access it from within the "then"
       * there should be a way to use it straight away, but i don't know how
       */
      const elem = event.target
      event.preventDefault()

      // Dpconfirm() is set in window...
      if (dpconfirm(Translator.trans('check.fragment.delete'))) {
        // Prepare the form-data
        const formData = new FormData()
        formData.append('delete', true)

        //  Post to delete route
        dpApi({
          method: 'POST',
          url: elem.getAttribute('data-post-delete'),
          data: formData
        })
          .then((data) => {
            if (data.data.code === 200 && data.data.success === true) {
              dplan.notify.notify('confirm', Translator.trans('confirm.fragment.deleted'))
              // Remove Item from DOM
              const target = document.querySelector('[data-post-delete-target="' + elem.getAttribute('data-target-id') + '"]')
              target.parentNode.removeChild(target)
            } else {
              dplan.notify.notify('error', Translator.trans('error.delete'))
            }
          })
          .catch(() => {
            dplan.notify.notify('error', Translator.trans('error.delete'))
          })
      }
    }))
  }
}
