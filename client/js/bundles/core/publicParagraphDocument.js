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

import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { DpUploadFiles } from '@demos-europe/demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import { prefixClass } from '@demos-europe/demosplan-ui/lib'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import { TableWrapper } from '@demos-europe/demosplan-utils'
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
