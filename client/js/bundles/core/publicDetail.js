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
import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { initialize } from '@DpJs/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'
import StatementForm from '@DpJs/lib/statement/StatementForm'

const components = {
  DpPublicDetailNoMap,
  DpUploadFiles,
  DpVideoPlayer,
  RegisterFlyout
}

const stores = {
  publicStatement
}

const apiStores = ['Elements']

//  Code to be run after mount of vue instance
initialize(components, stores, apiStores).then(() => {
  StatementForm()
  TableWrapper()
})
