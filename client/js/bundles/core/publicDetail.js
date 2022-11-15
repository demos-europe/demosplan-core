/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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

import DpPublicDetailNoMap from '@DpJs/components/statement/DpPublicDetailNoMap'
import { DpRegisterFlyout, DpUploadFiles, DpVideoPlayer } from 'demosplan-ui/components/core'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import StatementForm from '@DpJs/lib/statement/StatementForm'
import TableWrapper from '@DpJs/lib/core/TableWrapper'

const components = {
  DpPublicDetailNoMap,
  DpUploadFiles,
  DpVideoPlayer,
  RegisterFlyout
}

const stores = {
  publicStatement
}

const apiStores = ['elements']

//  Code to be run after mount of vue instance
initialize(components, stores, apiStores).then(() => {
  StatementForm()
  TableWrapper()
})
