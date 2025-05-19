/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

/**
 * This is the entrypoint for public_detail.html.twig  !! only loaded when a Map is enabled !!
 * See core-publicdetail.js for the entrypoint loaded when there is no Map
 */
import { DpUploadFiles, DpVideoPlayer, TableWrapper } from '@demos-europe/demosplan-ui'
import DiplanKarteWrapper from '@DpJs/components/map/publicdetail/DiplanKarteWrapper'
import DpPublicDetail from '@DpJs/components/map/publicdetail/DpPublicDetail'
import DpPublicSurvey from '@DpJs/components/procedure/survey/DpPublicSurvey'
import { initialize } from '@DpJs/InitVue'
import layers from '@DpJs/store/map/Layers'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout'
import StatementForm from '@DpJs/lib/statement/StatementForm'

//  Vuex store modules (to be registered on core bundle vuex store)
const stores = {
  layers,
  publicStatement
}

//  (Unmounted) vue parent components
const components = {
  DiplanKarteWrapper,
  DpPublicDetail,
  DpPublicSurvey,
  DpUploadFiles,
  DpVideoPlayer,
  RegisterFlyout
}

initialize(components, stores).then(() => {
  //  Code to be run after mount of vue instance
  StatementForm()
  TableWrapper()
})
