<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentaion>
  <usage>
    <!--
      Button that returns the the current extent of an ol-Map to its parent component
      requires an provided olMapState-object from parent/root
    -->
    <dp-ol-map-extent translation-key="ButtonText (untranslated)" />
  </usage>
</documentaion>

<template>
  <button
    type="button"
    @click="setExtent"
    class="btn--blank u-ml-0_5 o-link--default weight--bold">
    {{ Translator.trans(translationKey) }}
  </button>
</template>

<script>
export default {
  name: 'DpOlMapSetExtent',

  inject: ['olMapState'],

  props: {
    translationKey: {
      required: true,
      type: String
    }
  },

  computed: {
    map () {
      return this.olMapState.map
    }
  },

  methods: {
    setExtent () {
      const extent = this.map.getView().calculateExtent(this.map.getSize())
      this.$emit('extentSet', extent)
    }
  }
}
</script>
