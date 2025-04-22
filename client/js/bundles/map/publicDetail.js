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
import CustomLayer from '@DpJs/components/map/publicdetail/controls/CustomLayer'
import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend'
import DpPublicDetail from '@DpJs/components/map/publicdetail/DpPublicDetail'
import DpPublicLayerListWrapper from '@DpJs/components/map/publicdetail/controls/layerlist/DpPublicLayerListWrapper'
import DpUnfoldToolbarControl from '@DpJs/components/map/publicdetail/controls/DpUnfoldToolbarControl'
import { initialize } from '@DpJs/InitVue'
import layers from '@DpJs/store/map/Layers'
import Map from '@DpJs/components/map/publicdetail/Map'
import MapTools from '@DpJs/components/map/publicdetail/controls/MapTools'
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
  'dp-custom-layer': CustomLayer,
  DpLayerLegend,
  'dp-map': Map,
  'dp-map-tools': MapTools,
  DpPublicDetail,
  DpPublicLayerListWrapper,
  DpUnfoldToolbarControl,
  DpUploadFiles,
  DpVideoPlayer,
  RegisterFlyout
}

initialize(components, stores).then(() => {
  //  Code to be run after mount of vue instance
  StatementForm()
  TableWrapper()
})
