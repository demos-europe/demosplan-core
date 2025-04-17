<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="newOrgaModal"
    content-classes="u-2-of-3 u-pb--0_75 text-left">
    <h2>{{ Translator.trans('organisation.add') }}</h2>
    <div
      v-for="field in fields"
      :key="field.field"
      class="layout">
      <label
        :for="`new_${field.field}`"
        class="layout__item u-1-of-2 u-mb-0_5">
        {{ field.value }}
      </label><!--
      --><input
        v-model="formFields[field.field]"
        type="text"
        :name="`new_${field.field}`"
        class="layout__item u-1-of-2">
    </div>
    <div class="text-right">
      <button
        type="button"
        @click="saveAndReturn"
        class="btn btn--primary">
        {{ Translator.trans('add') }}
      </button>
    </div>
  </dp-modal>
</template>

<script>
import { DpModal } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpNewOrgaModal',

  components: {
    DpModal
  },

  props: {
    fields: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  emits: [
    'save'
  ],

  data () {
    return {
      formFields: {}
    }
  },

  methods: {
    toggleModal () {
      this.$refs.newOrgaModal.toggle()
    },

    saveAndReturn () {
      // Emit data to the parent
      this.$emit('save', this.formFields)
      // Reset form
      this.formFields = {}
      // Close modal
      this.toggleModal()
    }
  }
}
</script>
