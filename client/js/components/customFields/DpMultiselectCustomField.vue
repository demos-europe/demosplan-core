<template>
  <div class="dp-multiselect-custom-field">
    <!-- Readonly: With heading for accessibility -->
    <div
      v-if="mode === 'readonly'"
      class="flex items-start gap-1"
    >
      <div
        class="weight--bold"
        role="heading"
        aria-level="4"
      >
        {{ field.attributes.name }}<span v-if="field.attributes.isRequired"> *</span>
      </div>
      <dp-contextual-help
        v-if="field.attributes.description"
        :text="field.attributes.description"
        icon="info"
        size="medium"
        class="u-mt-0_125"
      />
    </div>

    <!-- Editable: With label for form field -->
    <div
      v-else
      class="flex items-start gap-1"
    >
      <dp-label
        :text="field.attributes.name"
        :required="field.attributes.isRequired"
        :for="`custom-field-${field.id}`"
      />
      <dp-contextual-help
        v-if="field.attributes.description"
        :text="field.attributes.description"
        icon="info"
        size="medium"
        class="u-mt-0_125"
      />
    </div>

    <!-- Read-only display -->
    <div
      v-if="mode === 'readonly'"
      class="u-mt-0_125"
    >
      <slot
        name="readonly-display"
        :field="field"
      >
        <div
          v-if="currentValue.length > 0"
          class="space-stack-xs"
        >
          <div
            v-for="option in currentValue"
            :key="option.id"
          >
            {{ option.label }}
          </div>
        </div>
        <span
          v-else
          class="font-size-small color--grey"
        >
          {{ Translator.trans('customfield.no.value') }}
        </span>
      </slot>
    </div>

    <!-- Editable mode -->
    <dp-multiselect
      v-else
      :id="`custom-field-${field.id}`"
      class="u-mt-0_25"
      :value="currentValue"
      :options="(field.attributes?.options || []).filter(opt => opt != null)"
      :placeholder="Translator.trans('choose')"
      label="label"
      track-by="id"
      multiple
      @input="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpMultiselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpMultiselect,
  },

  emits: ['update:value'],

  props: {
    // Complete field object: { id, attributes: {...}, value: {...} }
    field: {
      type: Object,
      required: true,
    },

    // Display mode: 'readonly' or 'editable'
    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: (value) => ['readonly', 'editable'].includes(value),
    },
  },

  computed: {
    /**
     * Current selected options (full objects for dp-multiselect)
     * Already matched in DpCustomField.transformValueForRenderer()
     */
    currentValue () {
      return this.field.value?.selectedOptions || []
    },
  },

  methods: {
    /**
     * Handle value updates
     * Emits unified structure: { id, value }
     * value = Array of option IDs (backend format)
     */
    handleUpdate (newValue) {
      this.$emit('update:value', {
        id: this.field.id,
        value: newValue.map(opt => opt.id),
      })
    },
  },
}
</script>
