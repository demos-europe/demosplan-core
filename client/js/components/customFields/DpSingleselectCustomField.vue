<template>
  <dl
    v-if="mode === 'readonly'"
  >
    <dt :class="prefixClass('flex items-start gap-1 font-[500] mb-2')">
      {{ field.attributes.name }}<span v-if="field.attributes.isRequired"> *</span>
      <dp-contextual-help
        v-if="field.attributes.description"
        :text="field.attributes.description"
        icon="info"
        size="medium"
      />
    </dt>
    <dd :class="prefixClass('ml-1')">
      <slot
        name="readonly-display"
        :field="field"
      >
        <span v-if="currentValue">
          {{ currentValue.label }}
        </span>
        <span
          v-else
          :class="prefixClass('font-size-small color--grey')"
        >
          {{ Translator.trans('customfield.no.value') }}
        </span>
      </slot>
    </dd>
  </dl>
  <div
    v-else
    :class="prefixClass('mb-3')"
  >
    <div :class="prefixClass('flex items-start gap-1')">
      <dp-label
        :text="field.attributes.name"
        :required="field.attributes.isRequired"
        :for="`custom-field-${field.id}`"
        :class="prefixClass('mb-2')"
      />
      <dp-contextual-help
        v-if="field.attributes.description"
        :text="field.attributes.description"
        icon="info"
        size="medium"
        :class="prefixClass('mb-2')"
      />
    </div>
    <dp-select
      :id="`custom-field-${field.id}`"
      :data-dp-validate-error-fieldname="field.attributes.name"
      :model-value="currentValue"
      :options="field.attributes.options || []"
      :placeholder="Translator.trans('choose')"
      :required="field.attributes.isRequired"
      label="label"
      track-by="id"
      @update:model-value="handleUpdate"
    />
  </div>
</template>

<script>
import { DpContextualHelp, DpLabel, DpSelect, prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSingleselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpSelect,
  },

  mixins: [prefixClassMixin],

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
