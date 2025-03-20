/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { addFormHiddenField } from '../lib/core/libs/FormActions'

const saveAndReturn = {
  mounted: function (el) {
    // Listen to click on 'save and return' button to show confirm animation on statement item
    el.addEventListener('click', ev => {
      addFormHiddenField(ev.target.parentNode, 'submit_item_return_button', true)
      window.sessionStorage.setItem('saveAndReturn', true)
    })
  }
}

export default saveAndReturn
