<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div :class="{ 'hidden': isMobile }">
    <div :class="prefixClass('c-map__group')">
      <div :class="prefixClass('relative')">
        <button
          :class="[unfolded ? prefixClass('is-active') : '', prefixClass('c-map__group-header c-map__group-item btn--blank o-link--default u-pv-0_25')]"
          data-cy="mapTools:mapToolsTap"
          @click="toggle">
          {{ Translator.trans('maptools') }}
        </button>
        <dp-contextual-help
          v-if="unfolded"
          class="c-map__layerhelp"
          :text="Translator.trans('maptools.explanation')" />
      </div>
    </div>

    <ul
      id="mapTools"
      :class="prefixClass('c-map__group')"
      v-show="unfolded">
      <li
        v-for="tool in toolList"
        :id="tool.id"
        :key="tool.id"
        :class="prefixClass('c-map__group-item c-map__layer c-map__measure-tool js__mapcontrol')"
        :title="tool.title">
        <button
          :class="prefixClass('btn--blank o-link--default')"
          :aria-label="tool.title + ' ' + Translator.trans('map.interactive.pointer.needed')"
          :data-cy="`mapTools:${tool.id}`">
          <!-- Active and inactive tool icons -->
          <svg
            v-if="tool.isActive"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 12 17"
            :class="prefixClass('c-map__group-item u-p-0 inline')"
            style="width: 16px; height: 16px; vertical-align: text-top;">
            <defs>
              <clipPath :id="'a' + tool.id">
                <path d="M0 0h12v17H0z" />
              </clipPath>
            </defs>
            <g :clip-path="`url(#a${tool.id})`">
              <path
                d="M0 0v17l4.849-4.973H12z"
                fill="#1e3884" />
            </g>
          </svg>

          <svg
            v-else
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 12 17"
            :class="prefixClass('c-map__group-item u-p-0 inline')"
            style="width: 16px; height: 16px; vertical-align: text-top;">
            <defs>
              <clipPath :id="'a' + tool.id">
                <path d="M0 0h12v17H0z" />
              </clipPath>
            </defs>
            <g
              fill="none"
              :clip-path="`url(#a${tool.id})`">
              <path d="M0 0v17l4.849-4.973H12z" />
              <path
                d="M1 14.542l3.132-3.212.295-.302h5.162L1 2.418v12.124M0 17V0l12 12.028H4.85L-.002 17z"
                fill="#707070" />
            </g>
          </svg>
          {{ Translator.trans(tool.transkey) }}
        </button>
      </li>
      <li
        id="measureRemoveButton"
        :class="prefixClass('c-map__group-item u-ph-0_5 u-pv-0_25 js__mapcontrol')"
        title="Messungen entfernen"
        :aria-label="Translator.trans('map.measure.remove') + ' ' + Translator.trans('map.interactive.pointer.needed')">
        <button
          :class="prefixClass('btn--blank o-link--default')"
          data-cy="mapTools:mapMeasureRemove">
          <i
            :class="prefixClass('fa fa-times')"
            aria-hidden="true" />
          {{ Translator.trans('map.measure.remove') }}
        </button>
      </li>

      <li
        id="resetZoomButton"
        :class="prefixClass('c-map__group-item u-ph-0_5 u-pv-0_25 js__mapcontrol zoom-reset')"
        title="Zoom der Karte zurücksetzen">
        <button
          :class="prefixClass('btn--blank o-link--default')"
          :aria-label="Translator.trans('map.zoom.reset') + ' ' + Translator.trans('map.interactive.pointer.needed')"
          data-cy="mapTools:mapZoomReset">
          {{ Translator.trans('map.zoom.reset') }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script>
import { DpContextualHelp, prefixClassMixin } from '@demos-europe/demosplan-ui'
import isMobile from 'ismobilejs'

export default {
  name: 'DpMapTools',
  components: { DpContextualHelp },

  mixins: [prefixClassMixin],

  emits: [
    'map-tools:unfolded'
  ],

  data () {
    return {
      isMobile: isMobile(window.navigator).any,
      unfolded: false,
      toolList: [
        {
          isActive: false,
          id: 'measureLineButton',
          title: 'Entfernung messen',
          transkey: 'distancemeasure'
        },
        {
          isActive: false,
          id: 'measurePolygonButton',
          title: 'Fläche messen',
          transkey: 'areameasure'
        },
        {
          isActive: false,
          id: 'measureRadiusButton',
          title: 'Radius messen',
          transkey: 'radiusmeasure'
        },
        {
          isActive: false,
          id: 'dragZoomButton',
          title: 'Ausschnitt wählen',
          transkey: 'section.zoom'
        }
      ]
    }
  },

  methods: {
    toggle () {
      const unfolded = this.unfolded = !this.unfolded
      if (unfolded) {
        this.$root.$emit('map-tools:unfolded')
      }
    },

    /**
     * Check if list item has class 'is-active' and if so, sets isActive to true so that active icon is displayed
     */
    checkIfActive () {
      for (let i = 0; i < this.toolList.length; i++) {
        this.toolList[i].isActive = document.getElementById(this.toolList[i].id).classList.contains(this.prefixClass('is-active'))
      }
    }
  },

  created () {
    this.$root.$on('custom-layer:unfolded layer-list:unfolded layer-legend:unfolded', () => { this.unfolded = false })

    this.$root.$on('changeActive', () => {
      this.checkIfActive()
    })
  }
}
</script>
