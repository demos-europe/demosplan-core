/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entry point for administration_list_masters.html.twig
 */

import { initialize } from '@DpJs/InitVue'

initialize().then(() => {
  if (document.querySelector('[data-delete-master-procedure]')) {
    document.querySelector('[data-delete-master-procedure]').addEventListener('click', function (event) {
      event.preventDefault()
      if (dpconfirm(Translator.trans('check.entries.marked.delete'))) {
        event.currentTarget.form.method = 'post'
        event.currentTarget.form.action = Routing.generate('DemosPlan_procedure_templates_delete')
        event.currentTarget.form.submit()
      } else {
        return false
      }
    })
  }
})
