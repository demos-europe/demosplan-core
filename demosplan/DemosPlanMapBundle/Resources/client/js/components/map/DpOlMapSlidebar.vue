<license>
(c) 2010-present DEMOS E-Partizipation GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<documentation>
<!--
 -->
</documentation>

<template>
  <div>
    <template>
      <h2 class="u-mb-0_75"
        v-text="Translator.trans('map.relation.set')">
      </h2>

      <div
        class="c-slidebar__content overflow-y-auto u-mr">
        <dp-ol-map
          :procedureId="procedureId"
          :options="{
          autoSuggest: false,
        }"/>
      </div>
    </template>
  </div>
</template>

<script>
import DpOlMap from '@DemosPlanMapBundle/components/map/DpOlMap'

export default {
  name: 'DpOlMapSlidebar',

  components: {
    DpOlMap
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  }
}
</script>
