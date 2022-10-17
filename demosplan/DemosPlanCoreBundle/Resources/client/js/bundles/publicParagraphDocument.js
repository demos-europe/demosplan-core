/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for public_paragraph_document.html.twig
 */

import DpPublicDetailNoMap from '@DemosPlanStatementBundle/components/DpPublicDetailNoMap'
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { prefixClass } from 'demosplan-ui/lib'
import publicStatement from '@DemosPlanStatementBundle/store/PublicStatement'
import TableWrapper from '@DpJs/lib/TableWrapper'
import TocStateMemorizer from '@DemosPlanCoreBundle/lib/TocStateMemorizer'

const components = {
  DpPublicDetailNoMap,
  DpUploadFiles
}

const stores = {
  publicStatement
}

initialize(components, stores).then(() => {
  // StatementForm()
  TableWrapper()
  if (window.sessionStorage) {
    Array.from(document.getElementsByClassName(prefixClass('c-toc--level-0'))).forEach(toc => new TocStateMemorizer(toc))
  }

  if (document.querySelector('[data-jump-to-statement]')) {
    document.querySelector('[data-jump-to-statement]').addEventListener('click', function () {
      if (document.getElementById('statementModalButton')) {
        document.getElementById('statementModalButton').focus()
      }
    })
  }
})
