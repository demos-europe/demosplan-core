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

import { DpModal, DpUploadFiles, prefixClass } from '@demos-europe/demosplan-ui'
import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import StatementForm from '@DpJs/lib/statement/StatementForm'

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
