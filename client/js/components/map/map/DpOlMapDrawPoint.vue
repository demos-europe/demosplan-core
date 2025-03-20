<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    type="button"
    data-cy="map:mapDrawPoint"
    @click="activate"
    :class="[prefixClass('btn--blank o-link--default weight--bold'), active ? prefixClass('color-highlight') : '']">
    <i
      :class="prefixClass('fa fa-map-marker')"
      aria-hidden="true" />
    {{ Translator.trans('map.relation.set') }}
  </button>
</template>

<script>
import { Draw } from 'ol/interaction'
import drawStyle from './utils/drawStyle'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'
import { Vector } from 'ol/layer'

export default {
  name: 'DpOlMapDrawPoint',

  inject: ['olMapState'],

  mixins: [prefixClassMixin],

  props: {
    //  @TODO implement `required: false`, craft a new vector layer instead
    target: {
      required: true,
      type: String
    },

    active: {
      required: false,
      type: Boolean,
      default: true
    }
  },

  emits: [
    'tool:activated',
    'tool:setPoint'
  ],

  data () {
    return {
      // Active: true,
    }
  },

  computed: {
    map () {
      return this.olMapState.map
    }
  },

  methods: {
    init () {
      if (this.map === null) {
        return
      }

      let layerToDrawInto
      let drawInteraction

      //  Atm this does not work for layer groups. See https://gis.stackexchange.com/a/240405
      this.map.getLayers().forEach((layer) => {
        if (layer instanceof Vector && layer.get('name') === this.target) {
          layerToDrawInto = layer
        }
      })

      // eslint-disable-next-line prefer-const
      drawInteraction = new Draw({
        source: layerToDrawInto.getSource(),
        type: 'Point',
        style: drawStyle(this.olMapState.drawStyles)
      })

      drawInteraction.on('drawstart', function () {
        layerToDrawInto.getSource().clear()
      })

      drawInteraction.on('drawend', () => {
        this.$emit('tool:setPoint', true)
      })

      this.map.addInteraction(drawInteraction)
    },

    activate () {
      if (this.active === false) {
        this.$emit('tool:activated', true)
      }
    }
  },

  mounted () {
    this.$nextTick(() => {
      this.init()
    })
  }
}
</script>
