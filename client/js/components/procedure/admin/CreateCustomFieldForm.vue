<template>
  <div data-dp-validate="createCustomFieldForm">
    <div
      v-if="!isOpen"
      class="text-right mb-4">
      <dp-button
        data-cy="customFields:addField"
        @click="open"
        :text="Translator.trans('add')" />
    </div>

    <div
      v-if="isOpen"
      class="relative mb-4">
      <dp-loading
        v-if="isLoading"
        overlay />
      <div
        class="border rounded space-stack-m space-inset-m">
        <dp-input
          id="newFieldName"
          class="w-[calc(100%-26px)]"
          data-cy="customFields:newFieldName"
          v-model="customField.name"
          :label="{
            text: Translator.trans('name')
          }"
          maxlength="250"
          required />
        <dp-input
          id="newFieldDescription"
          class="w-[calc(100%-26px)]"
          data-cy="customFields:newFieldDescription"
          v-model="customField.description"
          :label="{
            text: Translator.trans('description')
          }"
          maxlength="250" />

        <slot />

        <dp-button-row
          :busy="isLoading"
          data-cy="customFields:addNewField"
          primary
          secondary
          @primary-action="dpValidateAction('createCustomFieldForm', () => handleSave(), false)"
          @secondary-action="handleAbort" />
      </div>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpInput,
  DpLoading,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'

export default {
  name: 'CreateCustomFieldForm',

  components: {
    DpButton,
    DpButtonRow,
    DpInput,
    DpLoading
  },

  mixins: [dpValidateMixin],

  props: {
    handleSuccess: {
      type: Boolean,
      default: false
    },

    isLoading: {
      type: Boolean,
      default: false
    }
  },

  emits: [
    'abort',
    'open',
    'save'
  ],

  data () {
    return {
      customField: {
        name: '',
        description: ''
      },
      isOpen: false
    }
  },

  watch: {
    handleSuccess: {
      handler (newVal) {
        if (newVal) {
          this.onSuccess()
        }
      },
      immediate: true
    }
  },

  methods: {
    close () {
      this.isOpen = false
    },

    handleAbort () {
      this.$emit('abort')
      this.close()
      this.reset()
    },

    handleSave () {
      this.$emit('save', this.customField)
    },

    onSuccess () {
      this.reset()
      this.close()
    },

    open () {
      this.isOpen = true
    },

    reset () {
      this.customField.name = ''
      this.customField.description = ''
    }
  }
}
</script>
