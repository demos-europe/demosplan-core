<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpCheckbox, DpMultiselect, DpTooltipIcon } from '@demos-europe/demosplan-ui'
import DpMapAdminScales from './DpMapAdminScales'
import DpMapView from '@DpJs/components/map/map/DpMapView'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'

export default {
  name: 'DpMapAdmin',

  components: {
    DpCheckbox,
    DpOlMap,
    DpMapView,
    DpMapAdminScales,
    DpMultiselect,
    DpTooltipIcon
  },

  props: {
    availableScales: {
      type: Array,
      required: false,
      default: () => []
    },

    selectedScales: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      areScalesSuitable: true
    }
  }
}
</script>
