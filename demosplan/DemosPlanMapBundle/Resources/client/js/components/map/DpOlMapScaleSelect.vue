<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="display--inline-block">
    <label
      class="display--inline-block u-m-0"
      for="customScaleControl">
      {{ Translator.trans('map.scale') }}
    </label>
    <select
      name="customScaleControl"
      id="customScaleControl"
      @change="setScale"
      class="c-ol-map__select">
      <option
        :value="scaleObj.scale"
        v-for="(scaleObj, key) in scales"
        :selected="currentScale === scaleObj.scale"
        :key="key">
        1:{{ scaleObj.scale.toLocaleString() }}
      </option>
    </select>
  </div>
</template>

<script>
import { getScaleFromResolution, getScalesAndResolutions } from './utils/utils'
import { easeOut } from 'ol/easing'

export default {
  name: 'DpOlMapScaleSelect',

  inject: ['olMapState'],

  data () {
    return {
      currentScale: null,
      scales: []
    }
  },

  /*
   * Refactor watcher
   * should not be necessary
   */
  watch: {
    olMapState () {
      this.init()
    }
  },

  methods: {
    init () {
      const map = this.olMapState.map
      const view = this.view = map.getView()
      const resolutions = view.getResolutions()
      const currentResolution = this.view.getResolution()
      const units = this.units = view.getProjection().getUnits()

      //  Translate map resolutions to scales to display in select
      this.scales = getScalesAndResolutions(resolutions, units)

      //  Get initial scale
      this.currentScale = getScaleFromResolution(currentResolution, this.units)

      //  Attach event listeners to OpenLayers events to determine if resolution has changed and update selected option
      map.on('movestart', () => {
        let newResolution
        const resolution = newResolution = this.view.getResolution()

        map.once('moveend', () => {
          newResolution = this.view.getResolution()

          //  Fire only if resolution changed (movestart & moveend is triggered by several other interactions as well)
          if (resolution !== newResolution) {
            this.currentScale = getScaleFromResolution(newResolution, this.units)
          }
        })
      })
    },

    setScale (evt) {
      //  Get current scale from event target value
      const currentScale = evt.target.value
      const currentIndex = this.scales.findIndex((scalesObjItem) => scalesObjItem.scale === parseInt(currentScale))
      const resolution = this.scales[currentIndex].resolution

      //  Cancel running animations
      if (this.view.getAnimating()) {
        this.view.cancelAnimations()
      }

      //  Animate view to new resolution
      this.view.animate({
        resolution: resolution,
        duration: 250,
        easing: easeOut
      })
    }

  },

  async mounted () {
    await this.olMapState.map
    //  Setup state + behavior
    this.init()
  }
}
</script>
