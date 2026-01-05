<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="flex flex-col h-full">
    <!-- Select boilerplate -->
    <div class="pb-1 mb-2 flex-shrink-0">
      <dp-label
        class="mb-2"
        for="boilerplateSelect"
        :text="title"
        :tooltip="tooltipOptions.content"
      />
      <dp-multiselect
        id="boilerplateSelect"
        v-model="selectedBoilerPlate"
        class="inline-block"
        :group-label="groupLabel"
        :group-select="groupSelect"
        :group-values="groupValues"
        label="title"
        :options="boilerPlates"
        track-by="id"
        @input="addToTextArea"
      >
        <template v-slot:option="{ props }">
          {{ props.option.title }}
          <span v-if="props.option.$isLabel">
            {{ props.option.$groupLabel }}
          </span>
        </template>
      </dp-multiselect>
    </div>
    <!-- Preview of boilerplate text -->
    <div class="flex flex-col flex-1 min-h-0">
      <h4 class="mb-2 flex-shrink-0">
        {{ Translator.trans('preview') }}
      </h4>
      <div class="border rounded-lg flex-1 min-h-11 overflow-auto">
        <div
          id="previewField"
          v-cleanhtml="previewValue"
          class="p-2 c-styled-html"
        />
      </div>
    </div>
  </div>
</template>

<script>
import { CleanHtml, DpLabel, DpMultiselect, Tooltip } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpBoilerPlate',
  components: {
    DpLabel,
    DpMultiselect,
  },

  directives: {
    cleanhtml: CleanHtml,
    tooltip: Tooltip,
  },

  props: {
    boilerPlates: {
      required: false,
      type: Array,
      default: () => [],
    },

    groupValues: {
      required: false,
      type: String,
      default: '',
    },

    groupLabel: {
      required: false,
      type: String,
      default: '',
    },

    groupSelect: {
      required: false,
      type: Boolean,
      default: false,
    },

    isGroupSelect: {
      required: false,
      type: Boolean,
      default: false,
    },

    title: {
      required: false,
      type: String,
      default: 'boilerplate',
    },
  },

  emits: [
    'boilerplateText:added',
  ],

  data () {
    return {
      selectedBoilerPlate: '',
      previewValue: '',
    }
  },

  computed: {
    tooltipOptions () {
      return {
        classes: 'z-modal',
        content: Translator.trans('boilerplates.categories.explanation'),
      }
    },
  },

  methods: {
    addToTextArea (data) {
      this.previewValue = data.text
      this.$emit('boilerplateText:added', this.previewValue)
    },

    resetBoilerPlateMultiSelect () {
      this.selectedBoilerPlate = ''
      this.previewValue = ''
    },
  },
}
</script>
