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
    <dp-multiselect
      :id="`custom-field-${field.id}`"
      :data-dp-validate-error-fieldname="field.attributes.name"
      :value="currentValue"
      :options="(field.attributes?.options || []).filter(opt => opt != null)"
      :placeholder="Translator.trans('choose')"
      :required="field.attributes.isRequired"
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
  name: 'DpMultiselectCustomField',

  components: {
    DpContextualHelp,
    DpLabel,
    DpMultiselect,
  },

  mixins: [prefixClassMixin],

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
