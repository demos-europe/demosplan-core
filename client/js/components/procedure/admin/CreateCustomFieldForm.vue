<template>
  <div data-dp-validate="createCustomFieldForm">
    <div
      v-if="!isOpen"
      class="text-right mb-4"
    >
      <dp-button
        :text="Translator.trans('add')"
        data-cy="customFields:addField"
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

        <fieldset class="border-0 m-0 p-0">
          <dp-label
            :text="Translator.trans('custom.field.target')"
            class="mb-1"
            required
          />
          <div
            v-for="[entityKey, entityLabel] in Object.entries(targetOptions)"
            :key="entityKey"
            class="mb-1"
          >
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="customField.targetEntity"
                :data-cy="`customFields:targetEntity:${entityKey}`"
                :value="entityKey"
                class="mr-1"
                name="customFieldTargetEntity"
                type="radio"
                required
              >
              {{ entityLabel }}
            </label>
          </div>
        </fieldset>

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
          required
        />

        <dp-checkbox
          v-if="customField.targetEntity === 'STATEMENT'"
          id="requiredCheckbox"
          v-model="customField.isRequired"
          :label="{
            text: Translator.trans('statements.fields.configurable.required')
          }"
          class="mb-2"
          data-cy="customFields:isRequired"
        />

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
  DpCheckbox,
  DpInput,
  DpLabel,
  DpLoading,
  DpSelect,
  dpValidateMixin,
} from '@demos-europe/demosplan-ui'

export default {
  name: 'CreateCustomFieldForm',

  components: {
    DpButton,
    DpButtonRow,
    DpCheckbox,
    DpInput,
    DpLabel,
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

    targetOptions: {
      type: Object,
      required: true,
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
        description: '',
        fieldType: '',
        isRequired: false,
        name: '',
        targetEntity: '',
      },
      isOpen: false,
      typeOptions: [
        {
          value: 'multiSelect',
          label: Translator.trans('custom.field.type.multiSelect'),
        },
        {
          value: 'singleSelect',
          label: Translator.trans('custom.field.type.singleSelect'),
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
      this.customField.description = ''
      this.customField.fieldType = ''
      this.customField.isRequired = false
      this.customField.name = ''
      this.customField.targetEntity = ''
    },
  },
}
</script>
