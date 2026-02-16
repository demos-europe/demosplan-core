<template>
  <div class="dp-singleselect-custom-field">
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
        <!-- Default: Inline span (Backward Compatible) -->
        <span v-if="currentValue">
          {{ currentValue.label }}
        </span>
        <span
          v-else
          class="font-size-small color--grey"
        >
          {{ Translator.trans('customfield.no.value') }}
        </span>
      </slot>
    </div>

    <!-- Editable mode -->
    <dp-select
      v-else
      :id="`custom-field-${field.id}`"
      class="u-mt-0_25"
      :model-value="currentValue"
      :options="field.attributes.options || []"
      :placeholder="Translator.trans('choose')"
      label="label"
      track-by="id"
      @update:model-value="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpSelect } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSingleselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpSelect,
  },

  emits: ['update:value'],

  props: {
    field: {
      type: Object,
      required: true,
    },

    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: (value) => ['readonly', 'editable'].includes(value),
    },
  },

  computed: {
    /**
     * Current selected option (full object for dp-select)
     * Already matched in DpCustomField.transformValueForRenderer()
     */
    currentValue () {
      const selectedOptions = this.field.value?.selectedOptions || []
      return selectedOptions[0] || null
    },
  },

  methods: {
    /**
     * Handle value updates
     * Emits unified structure: { id, value }
     * value = single option ID or null (backend format)
     */
    handleUpdate (newValue) {
      this.$emit('update:value', {
        id: this.field.id,
        value: newValue ? newValue.id : null,
      })
    },
  },
}
</script>
