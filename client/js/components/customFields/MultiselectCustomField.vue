<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <dl
    v-if="mode === 'readonly'"
    :class="prefixClass('mb-0')"
  >
    <dt :class="prefixClass('flex items-start gap-1 font-[500] mb-1')">
      {{ field.attributes.name }}<span v-if="field.attributes.isRequired"> *</span>
      <dp-contextual-help
        v-if="field.attributes.description"
        :text="field.attributes.description"
      />
    </dt>
    <dd :class="prefixClass('ml-1')">
      <slot
        :field="field"
        name="readonly-display"
      >
        <div
          v-if="currentValue.length > 0"
          :class="prefixClass('space-stack-xs')"
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
          :class="prefixClass('text-sm text-muted')"
        >
          -
        </span>
      </slot>
    </dd>
  </dl>
  <div
    v-else
    :class="showLabel ? prefixClass('mb-3') : null"
  >
    <dp-label
      v-if="showLabel"
      :class="prefixClass('mb-2')"
      :for="`custom-field-${field.id}`"
      :required="field.attributes.isRequired"
      :text="field.attributes.name"
      :tooltip="field.attributes.description || ''"
    />
    <dp-multiselect
      :id="`custom-field-${field.id}`"
      :data-dp-validate-error-fieldname="field.attributes.name"
      :options="(field.attributes?.options || []).filter(opt => opt != null)"
      :placeholder="Translator.trans('choose')"
      :required="field.attributes.isRequired"
      :value="currentValue"
      label="label"
      track-by="id"
      multiple
      @input="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpMultiselect, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'MultiselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpMultiselect,
  },

  mixins: [prefixClassMixin],

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

    showLabel: {
      type: Boolean,
      required: false,
      default: true,
    },
  },

  emits: ['update:value'],

  computed: {
    /**
     * Current selected options (full objects for dp-multiselect)
     * Already matched in CustomField.transformValueForRenderer()
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
        value: newValue.length > 0 ? newValue.map(opt => opt.id) : null,
      })
    },
  },

  mounted () {
    /*
     * The dpValidateMultiselectDirective sets data-dp-validate-is-valid=false on mount
     * when options exist, regardless of initial value. Correct this for pre-populated
     * required fields so validation passes without requiring a user interaction first.
     */
    if (this.field?.attributes?.isRequired && this.currentValue.length > 0) {
      this.$nextTick(() => {
        const el = this.$el.querySelector('[data-dp-validate-is-valid]')
        if (el) {
          el.dataset.dpValidateIsValid = 'true'
        }
      })
    }
  },
}
</script>
