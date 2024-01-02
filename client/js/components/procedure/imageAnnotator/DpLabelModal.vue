<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="labelModal"
    content-classes="w-14">
    <template>
      <h3>
        {{ Translator.trans('format') }}
      </h3>
      <div class="flex space-inline-s">
        <dp-select
          v-model="selectedLabel"
          classes="w-12"
          name="labelSelect"
          placeholder="-"
          :options="labels" />
        <button
          @click="setLabel"
          class="btn btn--primary">
          {{ Translator.trans('accept') }}
        </button>
      </div>
    </template>
  </dp-modal>
</template>

<script>
import { DpModal, DpSelect } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpLabelModal',

  components: {
    DpModal,
    DpSelect
  },

  props: {
    labels: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      selectedLabel: ''
    }
  },

  methods: {
    setLabel () {
      this.$emit('set-label', this.selectedLabel)
      this.toggleModal()
    },

    toggleModal (preselectedLabel = null) {
      if (preselectedLabel) {
        this.selectedLabel = preselectedLabel
      }
      this.$refs.labelModal.toggle()
    }
  }
}
</script>
