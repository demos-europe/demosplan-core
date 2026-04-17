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
        <span v-if="currentValue">
          {{ currentValue.label }}
        </span>
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
      @input="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpMultiselect, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'SingleselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpMultiselect,
  },

  mixins: [prefixClassMixin],

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

    showLabel: {
      type: Boolean,
      required: false,
      default: true,
    },
  },

  emits: ['update:value'],

  computed: {
    /**
     * Current selected option (full object for dp-multiselect)
     * Already matched in CustomField.transformValueForRenderer()
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
        value: newValue?.id ?? null,
      })
    },
  },
}
</script>
