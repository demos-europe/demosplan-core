/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for list_final_group.html.twig
 */

import DpMapModal from '@DpJs/components/statement/assessmentTable/DpMapModal'
import DpPublicStatementList from '@DpJs/components/statement/publicStatementLists/DpPublicStatementList'
import { initialize } from '@DpJs/InitVue'
import { prefixClass } from '@demos-europe/demosplan-ui'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import Tabs from '@DpJs/lib/statement/Tabs'

const components = {
  DpMapModal,
  DpPublicStatementList
}

const stores = {
  publicStatement
}

const setTabFromHash = () => {
  const titles = document.querySelectorAll('.c-tabs__titles > li')

  if (location.hash === '#votedStatementsList') {
    document.querySelector('#myStatementsList').classList.remove(prefixClass('is-active-tab'))
    document.querySelector('#votedStatementsList').classList.add(prefixClass('is-active-tab'))
    titles[0].classList.remove(prefixClass('is-active-tab'))
    titles[1].classList.add(prefixClass('is-active-tab'))
  } else {
    document.querySelector('#myStatementsList').classList.add(prefixClass('is-active-tab'))
    document.querySelector('#votedStatementsList').classList.remove(prefixClass('is-active-tab'))
    titles[0].classList.add(prefixClass('is-active-tab'))
    titles[1].classList.remove(prefixClass('is-active-tab'))
  }
}
initialize(components, stores).then(() => {
  Tabs()

  setTabFromHash()

  addEventListener('hashchange', event => {
    setTabFromHash()
  })
})
