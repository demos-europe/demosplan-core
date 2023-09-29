<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpMultiselect, DpTooltipIcon } from '@demos-europe/demosplan-ui'
import DpMapView from '@DpJs/components/map/map/DpMapView'
import DpOlMap from '@DpJs/components/map/map/DpOlMap'

export default {
  name: 'DpMapAdmin',

  components: {
    DpOlMap,
    DpMapView,
    DpMultiselect,
    DpTooltipIcon
  },

  props: {
    selectedScales: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      scales: this.selectedScales
    }
  },

  methods: {
    sortSelected (type) {
      this[type].sort((a, b) => (parseInt(a.value) > parseInt(b.value)) ? 1 : ((parseInt(b.value) > parseInt(a.value)) ? -1 : 0))
    }
  },

  mounted () {
    this.sortSelected('scales')
  }
}
</script>
