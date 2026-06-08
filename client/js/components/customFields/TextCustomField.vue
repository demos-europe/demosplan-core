<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <dl
    v-if="mode === 'readonly' && showLabel"
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
        <span
          v-if="currentValue"
          :class="prefixClass('whitespace-pre-wrap')"
        >
          {{ currentValue }}
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
  <slot
    v-else-if="mode === 'readonly'"
    :field="field"
    name="readonly-display"
  >
    <span
      v-if="currentValue"
      :class="prefixClass('whitespace-pre-wrap')"
    >
      {{ currentValue }}
    </span>
    <span
      v-else
      :class="prefixClass('text-sm text-muted')"
    >
      -
    </span>
  </slot>
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
    <dp-text-area
      :id="`custom-field-${field.id}`"
      :data-dp-validate-error-fieldname="field.attributes.name"
      :value="currentValue"
      maxlength="250"
      reduced-height
      @input="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpTextArea, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'TextCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpTextArea,
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
    currentValue () {
      return this.field.value?.value || ''
    },
  },

  methods: {
    // Null signals the backend to clear the stored value
    handleUpdate (newValue) {
      this.$emit('update:value', {
        id: this.field.id,
        value: newValue?.length > 0 ? newValue : null,
      })
    },
  },
}
</script>
