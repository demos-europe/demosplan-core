/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for public_paragraph_document.html.twig
 */

import { DpUploadFiles, prefixClass, TableWrapper } from '@demos-europe/demosplan-ui'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import TocStateMemorizer from '@DpJs/lib/statement/TocStateMemorizer'

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
