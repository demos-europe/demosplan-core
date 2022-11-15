<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--This component contains a multiselect to insert a boilerplate into a textarea
      It receives the boilerplates as a prop.
      -->
</documentation>

<template>
  <div>
    <!-- Select boilerplate -->
    <div class="u-pb-0_25">
      <label class="u-mb-0_5">
        <i
          class="fa fa-question-circle float--right u-mt-0_125"
          :aria-label="Translator.trans('contextual.help')"
          v-tooltip="tooltipContent" />
        {{ title }}
      </label>
      <dp-multiselect
        class="display--inline-block"
        :options="boilerPlates"
        @input="addToTextArea"
        v-model="selectedBoilerPlate"
        label="title"
        track-by="id"
        :group-values="groupValues"
        :group-label="groupLabel"
        :group-select="groupSelect">
        <template v-slot:option="{ option }">
          {{ option.title }}
          <span v-if="option.$isLabel">
            {{ option.$groupLabel }}
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
        class="u-p-0_5 border border-radius-large min-height-250"
        id="previewField"
        v-cleanhtml="previewValue" />
    </div>
  </div>
</template>

<script>
import { CleanHtml } from 'demosplan-ui/directives'
import DpMultiselect from '../form/DpMultiselect'

export default {
  name: 'DpBoilerPlate',
  components: {
    DpMultiselect
  },

  directives: {
    cleanhtml: CleanHtml
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
      default: Translator.trans('boilerplate')
    }
  },

  data () {
    return {
      selectedBoilerPlate: '',
      previewValue: ''
    }
  },

  computed: {
    tooltipContent () {
      return {
        content: Translator.trans('boilerplates.categories.explanation'),
        classes: 'u-z-modal-window'
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
