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
import VueSlider from 'vue-slider-component'

export default {
  name: 'DpMapAdmin',

  components: {
    DpOlMap,
    DpMapView,
    DpMultiselect,
    DpTooltipIcon,
    VueSlider
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
      scales: this.selectedScales,
      scaleValue: ['250', '35000']
    }
  },

  computed: {
    maxScale () {
      return 10
    }
  },

  methods: {
    sortSelected (type) {
      this[type].sort((a, b) => (parseInt(a.value) > parseInt(b.value)) ? 1 : ((parseInt(b.value) > parseInt(a.value)) ? -1 : 0))
    },
    show (e) {
      console.log(e)
    },
    marks (val) {
      return {
        label: this.availableScales.find(scale => scale.value === val).label,
        labelStyle: {
          transform: 'rotate(315deg)',
          width: '100px',
          display: 'block',
          marginTop: '20px',
          marginLeft: '-50px'
        }
      }
    },
    tooltipFormatter (value) {
      return `Maßstab: ${this.availableScales.find(scale => scale.value === value).label}`
    }
  },

  mounted () {
    this.sortSelected('scales')
  }
}
</script>
