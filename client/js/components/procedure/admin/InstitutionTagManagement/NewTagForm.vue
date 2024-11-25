<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div data-dp-validate="addNewTagForm">
    <dp-loading
      v-if="isLoading"
      overlay />
    <div class="border rounded space-stack-m space-inset-m">
      <div class="relative u-pb-0_5 font-size-large">
        {{ Translator.trans('tag.new') }}
        <button
          class="btn--blank o-link--default float-right"
          @click="handleCloseForm()">
          <dp-icon icon="close" />
        </button>
      </div>
      <dp-input
        id="createTag"
        v-model="newTag.label"
        :label="{
          text: Translator.trans('name')
        }"
        required />
      <dp-select
        v-model="newTag.category"
        :label="{
          text: Translator.trans('category')
        }"
        :options="[{value: 'Kategorie1', label: 'Kategorie1'}]"
        required />
      <dp-button-row
        :busy="isLoading"
        align="left"
        primary
        secondary
        @primary-action="dpValidateAction('addNewTagForm', () => handleSaveForm(), false)"
        @secondary-action="handleCloseForm()" />
    </div>
  </div>
</template>
<script>
import {
  DpButton,
  DpButtonRow,
  DpIcon,
  DpInput,
  DpLoading,
  DpSelect,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
export default {
  name: 'NewTagForm',

  components: {
    DpButton,
    DpButtonRow,
    DpIcon,
    DpInput,
    DpLoading,
    DpSelect
  },

  props: {
    isLoading: {
      type: Boolean,
      default: false
    }
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      newTag: {}
    }
  },

  methods: {
    handleCloseForm () {
      this.$emit('closeNewTagForm')
      this.newTag.label = null
    },

    handleSaveForm () {
      this.$emit('saveNewTagForm', this.newTag)
      this.newTag.label = null
    }
  }
}
</script>
