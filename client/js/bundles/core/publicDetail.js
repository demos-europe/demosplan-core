/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for public_detail.html.twig  !! only loaded when no Map is enabled !!
 * See map-publicdetail.js for the entrypoint loaded when there is a Map
 */

import { DpUploadFiles, DpVideoPlayer, TableWrapper } from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'
import StatementForm from '@DpJs/lib/statement/StatementForm'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal'

/*
 * DpPublicDetailNoMap renders the Twig markup as scoped slot content, which is compiled in the
 * scope of this app - so everything the template uses has to be registered here, not on the component
 */
const components = {
  DpPublicDetailNoMap,
  DpUploadFiles,
  DpVideoPlayer,
  // Only rendered by the projects that override public_elements_list.html.twig
  ElementsList: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList')),
  RegisterFlyout,
  StatementModal,
}

const stores = {
  publicStatement,
}

const apiStores = ['Elements']

//  Code to be run after mount of vue instance
initialize(components, stores, apiStores).then(() => {
  StatementForm()
  TableWrapper()
})
