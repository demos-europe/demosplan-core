/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_draft.html.twig
 */

import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpModal from '@DpJs/components/core/DpModal'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { prefixClass } from 'demosplan-ui/lib'
import publicStatement from '@DemosPlanStatementBundle/store/PublicStatement'
import StatementForm from '@DemosPlanStatementBundle/lib/StatementForm'

const components = {
  DpMapModal,
  DpModal,
  DpPublicDetailNoMap,
  DpPublicStatementList,
  DpUploadFiles
}

const stores = {
  publicStatement
}

initialize(components, stores).then(() => {
  if (window.location.hash) {
    const elem = document.getElementById(window.location.hash.slice(1))
    if (elem) {
      elem.classList.add(prefixClass('target-element'))
    }
  }
  StatementForm()
})
