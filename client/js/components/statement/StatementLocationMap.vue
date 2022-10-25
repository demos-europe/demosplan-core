<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h2
      class="u-mb-0_75"
      v-text="heading">
    </h2>

    <div class="c-slidebar__content overflow-y-auto u-mr">
      <dp-ol-map
        :procedure-id="procedureId"
        :options="{
          autoSuggest: false
      }"/>
    </div>
  </div>
</template>

<script>
import DpOlMap from '@DemosPlanMapBundle/components/map/DpOlMap'

export default {
  name: 'StatementLocationMap',

  components: {
    DpOlMap
  },

  props: {
    statementExternId: {
      type: String,
      required: false,
      default: ''
    },

    procedureId: {
      type: String,
      required: true
    }
  },

  computed: {
    heading () {
      if (this.statementExternId) {
        return Translator.trans('statement.detail.location.heading', { externId: this.statementExternId })
      }

      return Translator.trans('public.participation.relation')
    }
  }
}
</script>
