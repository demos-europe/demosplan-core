<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import DpMapView from '@DemosPlanMapBundle/components/map/DpMapView'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'
import DpOlMap from '@DemosPlanMapBundle/components/map/DpOlMap'

export default {
  name: 'DpMapAdmin',

  components: {
    DpOlMap,
    DpMapView,
    DpMultiselect
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
