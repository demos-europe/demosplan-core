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
import DpUploadFiles from '@DpJs/components/core/DpUpload/DpUploadFiles'
import DpVideoPlayer from '@DpJs/components/core/DpVideoPlayer'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import publicStatement from '@DemosPlanStatementBundle/store/PublicStatement'
import RegisterFlyout from '@DpJs/components/core/RegisterFlyout'
import StatementForm from '@DpJs/lib/statement/StatementForm'
import TableWrapper from '@DpJs/lib/TableWrapper'

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
