<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <!-- Select boilerplate -->
    <div class="u-pb-0_25">
      <label class="u-mb-0_5">
        <dp-contextual-help
          class="float-right u-mt-0_125"
          :tooltip-options="tooltipOptions" />
        {{ title }}
      </label>
      <dp-multiselect
        v-model="selectedBoilerPlate"
        class="inline-block"
        :group-label="groupLabel"
        :group-select="groupSelect"
        :group-values="groupValues"
        label="title"
        :options="boilerPlates"
        track-by="id"
        @input="addToTextArea">
        <template v-slot:option="{ props }">
          {{ props.option.title }}
          <span v-if="props.option.$isLabel">
            {{ props.option.$groupLabel }}
          </span>
        </template>
      </dp-multiselect>
    </div>
    <!-- Preview of boilerplate text -->
    <div>
      <label
        for="previewField"
        class="u-mb-0_25 u-mt-0_5">
        Vorschau:
      </label>
      <div
        class="u-p-0_5 border rounded-lg min-h-11 c-styled-html"
        id="previewField"
        v-cleanhtml="previewValue" />
    </div>
  </div>
</template>

<script>
import { CleanHtml, DpContextualHelp, DpMultiselect, Tooltip } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpBoilerPlate',
  components: {
    DpContextualHelp,
    DpMultiselect
  },

  directives: {
    cleanhtml: CleanHtml,
    tooltip: Tooltip
  },

  props: {
    boilerPlates: {
      required: false,
      type: Array,
      default: () => []
    },

    groupValues: {
      required: false,
      type: String,
      default: ''
    },

    groupLabel: {
      required: false,
      type: String,
      default: ''
    },

    groupSelect: {
      required: false,
      type: Boolean,
      default: false
    },

    isGroupSelect: {
      required: false,
      type: Boolean,
      default: false
    },

    title: {
      required: false,
      type: String,
      default: 'boilerplate'
    }
  },

  data () {
    return {
      selectedBoilerPlate: '',
      previewValue: ''
    }
  },

  computed: {
    tooltipOptions () {
      return {
        classes: 'z-modal',
        content: Translator.trans('boilerplates.categories.explanation')
      }
    }
  },

  methods: {
    addToTextArea (data) {
      this.previewValue = data.text
      this.$emit('boilerplate-text-added', this.previewValue)
    },

    resetBoilerPlateMultiSelect () {
      this.selectedBoilerPlate = ''
      this.previewValue = ''
    }
  }
}
</script>
