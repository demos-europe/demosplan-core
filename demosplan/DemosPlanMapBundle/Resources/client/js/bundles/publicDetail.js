/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
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
import CustomLayer from '@DemosPlanMapBundle/components/publicdetail/controls/CustomLayer'
import DpLayerLegend from '@DemosPlanMapBundle/components/publicdetail/controls/legendList/DpLayerLegend'
import DpPublicDetail from '@DemosPlanMapBundle/components/publicdetail/DpPublicDetail'
import DpPublicLayerListWrapper from '@DemosPlanMapBundle/components/publicdetail/controls/layerlist/DpPublicLayerListWrapper'
import DpPublicSurvey from '@DemosPlanProcedureBundle/components/survey/DpPublicSurvey'
import DpUnfoldToolbarControl from '@DemosPlanMapBundle/components/publicdetail/controls/DpUnfoldToolbarControl'
import DpUploadFiles from '@DemosPlanCoreBundle/components/DpUpload/DpUploadFiles'
import DpVideoPlayer from '@DemosPlanCoreBundle/components/DpVideoPlayer'
import { initialize } from '@DemosPlanCoreBundle/InitVue'
import layers from '@DemosPlanMapBundle/store/Layers'
import Map from '@DemosPlanMapBundle/components/publicdetail/Map'
import MapTools from '@DemosPlanMapBundle/components/publicdetail/controls/MapTools'
import publicStatement from '@DemosPlanStatementBundle/store/PublicStatement'
import RegisterFlyout from '@DemosPlanCoreBundle/components/RegisterFlyout'
import StatementForm from '@DemosPlanStatementBundle/lib/StatementForm'
import TableWrapper from '@DpJs/lib/TableWrapper'

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
  DpPublicSurvey,
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
