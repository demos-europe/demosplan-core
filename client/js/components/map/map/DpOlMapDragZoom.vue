<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <button
    type="button"
    data-cy="map:zoomWindow"
    @click="toggle"
    :class="[active ? prefixClass('color-highlight') : '', prefixClass('btn--blank u-ml-0_5 o-link--default weight--bold')]">
    {{ Translator.trans('zoomwindow') }}
  </button>
</template>

<script>
import { always } from 'ol/events/condition'
import { DragZoom } from 'ol/interaction'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpOlMapDragZoom',

  inject: ['olMapState'],

  mixins: [prefixClassMixin],

  emits: [
    'tool:activated'
  ],

  data () {
    return {
      active: false,
      name: 'dragzoom'
    }
  },

  computed: {
    map () {
      return this.olMapState.map
    }
  },

  methods: {
    activateTool () {
      this.map.addInteraction(this.dragZoom)
      this.$emit('tool:activated', true)
      this.active = true
    },

    deactivateTool () {
      this.map.removeInteraction(this.dragZoom)
      this.$emit('tool:activated', false)
      this.active = false
    },

    toggle () {
      this.active ? this.deactivateTool() : this.activateTool()
    }
  },

  mounted () {
    this.dragZoom = new DragZoom({ condition: always, className: this.prefixClass('border--normal') })
  }
}
</script>
