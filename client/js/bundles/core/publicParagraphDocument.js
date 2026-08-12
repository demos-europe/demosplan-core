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
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal'
import TocStateMemorizer from '@DpJs/lib/statement/TocStateMemorizer'

/*
 * DpPublicDetailNoMap renders the Twig markup as scoped slot content, which is compiled in the
 * scope of this app - so everything the template uses has to be registered here, not on the component
 */
const components = {
  DpPublicDetailNoMap,
  DpUploadFiles,
  StatementModal,
}

const stores = {
  publicStatement,
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
