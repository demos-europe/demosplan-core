<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot name="beforeElement">
      <label
        class="u-mb-0_25"
        :for="elementsInputName">
        {{ Translator.trans('document') }}
      </label>
    </slot>
    <div :class="selectboxWrapperClasses">
      <select
        v-model="currentElementId"
        class="o-form__control-select"
        :name="elementsInputName"
        :id="elementsInputName">
        <option value="">
          -
        </option>
        <option
          v-for="elem in elements"
          :key="elem.id"
          :value="elem.id">
          {{ elem.title }}
        </option>
      </select>
    </div>
    <template v-if="elementsHasParagraph">
      <slot name="beforeParagraph">
        <label
          class="u-mb-0_25"
          :for="paragraphsInputName">
          {{ Translator.trans('paragraph') }}
        </label>
      </slot>
      <div :class="selectboxWrapperClasses">
        <select
          v-model="currentParagraphId"
          class="o-form__control-select"
          :name="paragraphsInputName"
          :id="paragraphsInputName">
          <option value="">
            -
          </option>
          <option
            v-for="paragraph in selectedElementParagraph"
            :key="paragraph.id"
            :value="paragraph.id">
            {{ paragraph.title }}
          </option>
        </select>
      </div>
    </template>
    <template v-if="elementsHasFiles">
      <slot name="beforeParagraph">
        <label
          class="u-mb-0_25"
          :for="fileInputName">
          {{ Translator.trans('file') }}
        </label>
      </slot>
      <div :class="selectboxWrapperClasses">
        <select
          v-model="currentFileId"
          class="layout__item"
          :name="fileInputName"
          :id="fileInputName">
          <option value="">
            -
          </option>
          <option
            v-for="document in selectedElementFile"
            :key="document.id"
            :value="document.id">
            {{ document.title }}
          </option>
        </select>
      </div>
    </template>
  </div>
</template>

<script>
export default {
  name: 'DpSelectDocument',

  props: {
    elements: {
      required: true,
      type: Array,
      default: () => []
    },

    paragraphs: {
      type: Object,
      required: true,
      default: () => ({})
    },

    documents: {
      type: Object,
      required: true,
      default: () => ({})
    },

    selectedElementId: {
      required: false,
      type: String,
      default: ''
    },

    selectedElementTitle: {
      required: false,
      type: String,
      default: ''
    },

    selectedParagraphId: {
      required: false,
      type: String,
      default: ''
    },

    selectedParagraphTitle: {
      required: false,
      type: String,
      default: ''
    },

    selectedFileId: {
      required: false,
      type: String,
      default: ''
    },

    selectedFileTitle: {
      required: false,
      type: String,
      default: ''
    },

    elementsInputName: {
      required: false,
      type: String,
      default: 'r_element'
    },

    paragraphsInputName: {
      required: false,
      type: String,
      default: 'r_paragraph'
    },

    fileInputName: {
      required: false,
      type: String,
      default: 'r_document'
    },

    selectboxWrapperClasses: {
      required: false,
      type: String,
      default: ''
    }
  },

  data () {
    return {
      currentElementId: this.selectedElementId,
      currentElementTitle: this.selectedElementTitle,
      currentParagraphId: this.selectedParagraphId,
      currentParagraphTitle: this.selectedParagraphTitle,
      currentFileId: this.selectedFileId,
      currentFileTitle: this.selectedFileTitle
    }
  },

  computed: {
    /**
     * Checks if files exists for this paragraph
     *
     * @return {boolean}
     */
    elementsHasFiles () {
      return Array.isArray(this.documents[this.currentElementId])
    },

    /**
     * Checks if paragraphs exists for this element
     *
     * @return {boolean}
     */
    elementsHasParagraph () {
      return Array.isArray(this.paragraphs[this.currentElementId])
    },

    /**
     * Returns an Array of Files for the selected Paragraph
     *
     * @return {Array}
     */
    selectedElementFile () {
      return this.documents[this.currentElementId]
    },

    /**
     * Returns an Array of paragraphs for the selected Document
     *
     * @return {Array}
     */
    selectedElementParagraph () {
      return this.paragraphs[this.currentElementId]
    }
  },

  watch: {
    elementsHasParagraph: {
      handler (hasParagraph) {
        if (hasParagraph === false) {
          this.currentParagraphTitle = ''
          this.currentParagraphId = ''
        }
      },
      deep: true
    },

    elementsHasFiles: {
      handler (hasFiles) {
        if (hasFiles === false) {
          this.currentFileTitle = ''
          this.currentFileId = ''
        }
      },
      deep: true
    }
  }
}
</script>
