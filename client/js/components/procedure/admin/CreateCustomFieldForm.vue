<template>
  <div data-dp-validate="createCustomFieldForm">
    <div
      v-if="!isOpen"
      class="text-right mb-4"
    >
      <dp-button
        data-cy="customFields:addField"
        :text="Translator.trans('add')"
        @click="open"
      />
    </div>

    <div
      v-if="isOpen"
      class="relative mb-4"
    >
      <dp-loading
        v-if="isLoading"
        overlay
      />
      <div
        class="border rounded-sm space-stack-m space-inset-m"
      >
        <dp-input
          id="newFieldName"
          v-model="customField.name"
          class="w-[calc(100%-26px)]"
          data-cy="customFields:newFieldName"
          :label="{
            text: Translator.trans('name')
          }"
          maxlength="250"
          required
        />
        <dp-input
          id="newFieldDescription"
          v-model="customField.description"
          class="w-[calc(100%-26px)]"
          data-cy="customFields:newFieldDescription"
          :label="{
            text: Translator.trans('description')
          }"
          maxlength="250"
        />
        <dp-select
          id="newFieldType"
          v-model="customField.fieldType"
          class="w-[calc(100%-26px)]"
          data-cy="customFields:newFieldType"
          :label="{
            text: Translator.trans('type'),
            tooltip: Translator.trans('explanation.field.type')
          }"
          :options="typeOptions"
          :disabled="disableTypeSelection"
          :required="preselectedType === ''" />

        <slot />

        <dp-button-row
          :busy="isLoading"
          data-cy="customFields:addNewField"
          primary
          secondary
          @primary-action="dpValidateAction('createCustomFieldForm', () => handleSave(), false)"
          @secondary-action="handleAbort"
        />
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
  DpSelect,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'CreateCustomFieldForm',

  components: {
    DpButton,
    DpButtonRow,
    DpInput,
    DpLoading,
    DpSelect,
  },

  mixins: [dpValidateMixin],

  props: {
    handleSuccess: {
      type: Boolean,
      default: false,
    },

    isLoading: {
      type: Boolean,
      default: false,
    },

    preselectedType: {
      type: String,
      default: '',
    },

    disableTypeSelection: {
      type: Boolean,
      default: false,
    },
  },

  emits: [
    'abort',
    'open',
    'save',
  ],

  data () {
    return {
      customField: {
        name: '',
        description: '',
        fieldType: this.preselectedType,
      },
      isOpen: false,
      typeOptions: [
        {
          value: 'multiSelect',
          label: 'Mehrfachauswahl',
        },
        {
          value: 'singleSelect',
          label: 'Einzelauswahl',
        },
      ],
    }
  },

  watch: {
    handleSuccess: {
      handler (newVal) {
        if (newVal) {
          this.onSuccess()
        }
      },
      immediate: true,
    },
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
      this.customField.fieldType = this.preselectedType
    },
  },
}
</script>
