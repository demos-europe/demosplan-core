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
          data: formData,
          options: {
            messages: {
              200: {
                text: Translator.trans('confirm.fragment.deleted'),
                type: 'confirm',
              },
              204: {
                text: Translator.trans('confirm.fragment.deleted'),
                type: 'confirm',
              },
              400: {
                text: Translator.trans('error.delete'),
                type: 'error',
              },
            },
          },
        })
          .then(({ data }) => {
            if (data.code === 200 && data.success === true) {
              // Remove Item from DOM
              const target = document.querySelector('[data-post-delete-target="' + elem.getAttribute('data-target-id') + '"]')
              target.parentNode.removeChild(target)
            }
          })
      }
    }))
  }
}
