<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="block u-mb-0_5 u-pv-0_5 border--top flow-root">
    <label
      class="inline-block u-m-0"
      for="customLatitude">
      {{ Translator.trans('coordinate.right.value') }}
    </label>
    <input
      type="text"
      pattern="[0-9]*[.|,]?[0-9]+"
      required
      id="customLatitude"
      v-model="latitudeValue"
      class="c-ol-map__select w-9 u-mr">

    <label
      class="inline-block u-m-0"
      for="customLongitude">
      {{ Translator.trans('coordinate.top.value') }}
    </label>
    <input
      type="text"
      pattern="[0-9]*[.|,]?[0-9]+"
      required
      id="customLongitude"
      v-model="longitudeValue"
      class="c-ol-map__select w-9 u-mr">

    <button
      @click.prevent="addMarker"
      :disabled="!isCoordinatesValid"
      ref="myBtnCoordinates"
      class="btn btn--primary float-right">
      {{ Translator.trans('coordinate.location.submite') }}
    </button>
  </div>
</template>

<script>

import proj4 from 'proj4'

export default {
  name: 'DpProcedureCoordinateInput',

  props: {
    coordinate: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  emits: [
    'input'
  ],

  data () {
    return {
      latitudeValue: '', // 568400.97
      longitudeValue: '' // 5923963.03
    }
  },

  computed: {
    isCoordinatesValid () {
      const lat = this.convertToFloat(this.latitudeValue)
      const lon = this.convertToFloat(this.longitudeValue)
      return Number(lat) === lat && Number(lon) === lon
    }
  },

  watch: {
    coordinate: {
      handler (coordinates) {
        this.updateCoordinates(coordinates)
      },
      deep: true
    }
  },

  methods: {
    addMarker () {
      // Coordinate input field is meant to be EPSG:25832, therefore it needs to be transformed
      proj4.defs([
        [
          'EPSG:25832',
          '+proj=utm +zone=32 +ellps=GRS80 +units=m +no_defs'
        ]
      ])

      this.$emit('input', proj4('EPSG:25832', window.dplan.defaultProjectionLabel, [this.convertToFloat(this.latitudeValue), this.convertToFloat(this.longitudeValue)]))
      this.$refs.myBtnCoordinates.blur()
    },

    updateCoordinates (coordinates) {
      if (coordinates.length === 2) {
        this.latitudeValue = coordinates[0]
        this.longitudeValue = coordinates[1]
      }
    },

    convertToFloat (val) {
      return parseFloat((val + '').replace(',', '.'))
    }
  },

  mounted () {
    //  Setup state + behavior
    this.updateCoordinates(this.coordinate)
  }
}
</script>
