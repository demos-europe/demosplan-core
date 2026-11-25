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
import CustomLayer from '@DpJs/components/map/publicdetail/controls/CustomLayer.vue'
import { defineAsyncComponent } from 'vue'
import DpLayerLegend from '@DpJs/components/map/publicdetail/controls/legendList/DpLayerLegend.vue'
import DpPublicDetail from '@DpJs/components/map/publicdetail/DpPublicDetail.vue'
import DpPublicLayerListWrapper from '@DpJs/components/map/publicdetail/controls/layerlist/DpPublicLayerListWrapper.vue'
import DpUnfoldToolbarControl from '@DpJs/components/map/publicdetail/controls/DpUnfoldToolbarControl.vue'
import { initialize } from '@DpJs/InitVue'
import layers from '@DpJs/store/map/Layers'
import Map from '@DpJs/components/map/publicdetail/Map.vue'
import MapTools from '@DpJs/components/map/publicdetail/controls/MapTools.vue'
import publicStatement from '@DpJs/store/statement/PublicStatement'
import RegisterFlyout from '@DpJs/components/user/RegisterFlyout.vue'
import StatementForm from '@DpJs/lib/statement/StatementForm'
import StatementModal from '@DpJs/components/statement/publicStatementModal/StatementModal.vue'

//  Vuex store modules (to be registered on core bundle vuex store)
const stores = {
  layers,
  publicStatement,
}

/*
 * DpPublicDetail renders the Twig markup as scoped slot content, which is compiled in the scope
 * of this app - so everything the template uses has to be registered here, not on the component
 */
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
  // Only rendered by the projects that override public_elements_list.html.twig
  ElementsList: defineAsyncComponent(() => import('@DpJs/components/document/ElementsList.vue')),
  RegisterFlyout,
  StatementModal,
}

initialize(components, stores).then(() => {
  //  Code to be run after mount of vue instance
  StatementForm()
  TableWrapper()
})
