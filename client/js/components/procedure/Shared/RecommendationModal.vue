<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <dp-modal
    ref="recommendationModal"
    content-classes="w-2/3"
  >
    <div class="flex w-full">
      <h3 class="mb-4">
        {{ Translator.trans('segment.recommendation.insert.similar') }}
      </h3>
      <dp-contextual-help
        v-if="activeId === 'oracleRec'"
        class="ml-1"
        icon="ai"
        size="large"
        :text="Translator.trans('segment.oracle.tooltip')"
      />
      <dp-tooltip
        v-if="activeId === 'oracleRec'"
        :text="Translator.trans('segment.oracle.beta.tooltip')"
      >
        <dp-badge
          class="absolute right-4"
          size="smaller"
          :text="Translator.trans('segment.oracle.beta')"
        />
      </dp-tooltip>
    </div>
    <dp-tabs
      v-if="tabAddonsLoaded && segmentDataLoaded"
      :active-id="activeId"
      @change="handleTabChange"
    >
      <dp-tab
        v-for="addon in modalAddons"
        :id="addon.options.id"
        :key="addon.options.id"
        :is-active="activeId === addon.options.id"
        :label="Translator.trans(addon.options.title)"
      >
        <component
          :is="addon.component"
          class="mt-4"
          :data-cy="`addon:${addon.name}`"
          :demosplan-ui="demosplanUi"
          :procedure-id="procedureId"
          :segment-id="segmentId"
          @recommendation:insert="closeRecommendationModalAfterInsert"
        />
      </dp-tab>
    </dp-tabs>
  </dp-modal>
</template>
<script>
import * as demosplanUi from '@demos-europe/demosplan-ui'
import { DpBadge, DpContextualHelp, DpModal, DpTab, DpTabs, DpTooltip } from '@demos-europe/demosplan-ui'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'
import { shallowRef } from 'vue'

export default {
  name: 'RecommendationModal',
  components: {
    DpBadge,
    DpContextualHelp,
    DpModal,
    DpTab,
    DpTabs,
    DpTooltip,
  },
  props: {
    procedureId: {
      type: String,
      required: true,
    },

    segmentDataLoaded: {
      type: Boolean,
      required: true,
    },

    segmentId: {
      type: String,
      required: true,
    },
  },

  emits: [
    'addons:loaded',
    'recommendation:insert',
  ],

  data () {
    return {
      activeId: '',
      demosplanUi: shallowRef(demosplanUi),
      modalAddons: [],
      tabAddonsLoaded: false,
    }
  },

  methods: {
    closeRecommendationModalAfterInsert (recommendation) {
      this.$emit('recommendation:insert', recommendation)
      this.toggle()
    },

    handleTabChange (id) {
      this.activeId = id
    },

    toggle () {
      this.$refs.recommendationModal.toggle()
    },
  },

  mounted () {
    loadAddonComponents('segment.recommendationModal.tab')
      .then(addons => {
        if (!addons.length) {
          return
        }

        this.activeId = (addons[0].options && addons[0].options.id) || ''
        this.tabAddonsLoaded = true
        this.$emit('addons:loaded')

        this.modalAddons = addons.map(addon => {
          const { name, options } = addon

          return {
            component: shallowRef(window[name].default),
            name,
            options,
          }
        })
      })
  },

}
</script>

