<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div v-if="isLoading || resolvedDefinition">
    <dp-loading
      v-if="isLoading"
      :overlay="false"
    />
    <!-- Toggle Mode: Readonly with Edit Button -->
    <div v-else-if="enableToggle && !isEditing">
      <div :class="prefixClass('flex items-start gap-1')">
        <div :class="prefixClass('flex-1')">
          <component
            :is="getComponentForType(resolvedDefinition.attributes.fieldType)"
            :field="mergedField"
            mode="readonly"
          >
            <template
              v-if="$slots['readonly-display']"
              v-slot:readonly-display="slotProps"
            >
              <slot
                v-bind="slotProps"
                name="readonly-display"
              />
            </template>
          </component>
        </div>
        <dp-button
          :aria-label="Translator.trans('edit')"
          :text="Translator.trans('edit')"
          icon="edit"
          variant="subtle"
          hide-text
          @click="startEditing"
        />
      </div>
    </div>

    <!-- Toggle Mode: Editing with Save/Cancel -->
    <div v-else-if="enableToggle && isEditing">
      <div :class="prefixClass('flex items-start gap-1')">
        <div :class="prefixClass('flex-1')">
          <component
            :is="getComponentForType(resolvedDefinition.attributes.fieldType)"
            :field="editingField"
            mode="editable"
            @update:value="handleEditingValueUpdate"
          />
        </div>
        <div>
          <dp-button
            :aria-label="Translator.trans('save')"
            :class="prefixClass('mr-1')"
            :disabled="isSaving"
            :text="Translator.trans('save')"
            icon="check"
            variant="subtle"
            hide-text
            @click="saveEdit"
          />
          <dp-button
            :aria-label="Translator.trans('abort')"
            :disabled="isSaving"
            :text="Translator.trans('abort')"
            icon="x"
            variant="subtle"
            hide-text
            @click="cancelEdit"
          />
        </div>
      </div>
    </div>

    <!-- Direct Mode: Always editable or readonly -->
    <component
      :is="getComponentForType(resolvedDefinition.attributes.fieldType)"
      v-else-if="mergedField"
      :field="mergedField"
      :mode="mode"
      @update:value="handleValueUpdate"
    >
      <template
        v-if="$slots['readonly-display']"
        v-slot:readonly-display="slotProps"
      >
        <slot
          v-bind="slotProps"
          name="readonly-display"
        />
      </template>
    </component>
  </div>
</template>

<script>
import { DpButton, DpLoading, prefixClassMixin } from '@demos-europe/demosplan-ui'
import MultiselectCustomField from './MultiselectCustomField'
import SingleselectCustomField from './SingleselectCustomField'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'CustomField',

  components: {
    DpButton,
    DpLoading,
    MultiselectCustomField,
    SingleselectCustomField,
  },

  mixins: [prefixClassMixin],

  props: {
    definition: {
      type: Object,
      required: false,
      default: null,
    },

    definitionSourceId: {
      type: String,
      required: false,
      default: null,
    },

    enableToggle: {
      type: Boolean,
      required: false,
      default: false,
    },

    fieldData: {
      type: Object,
      required: true,
      validator: (val) => {
        // The id must exist and be a non-empty string
        return val?.id && typeof val.id === 'string' && val.id.length > 0
      },
    },

    isActiveEdit: {
      type: Boolean,
      required: false,
      default: null,
    },

    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: (value) => ['readonly', 'editable'].includes(value),
    },

    resourceId: {
      type: [String, null],
      required: false,
      default: null,
    },

    resourceType: {
      type: [String, null],
      required: false,
      default: null,
    },
  },

  emits: [
    'edit:cancel',
    'edit:save',
    'edit:start',
    'save:error',
    'save:success',
    'update:value',
  ],

  data () {
    return {
      componentMap: {
        multiSelect: 'multiselect-custom-field',
        singleSelect: 'singleselect-custom-field',
      },
      resolvedDefinition: null,
      editingValue: null,
      isEditing: false,
      isLoading: false,
      isSaving: false,
      saveError: null,
    }
  },

  computed: {
    editingField () {
      return this.buildFieldObject(this.editingValue)
    },

    mergedField () {
      return this.buildFieldObject(this.fieldData.value)
    },
  },

  watch: {
    definition: {
      handler (newVal) {
        if (newVal) {
          this.resolvedDefinition = newVal
        }
      },
      immediate: true,
    },

    definitionSourceId: {
      handler () {
        this.fetchDefinition()
      },
      immediate: true,
    },

    'fieldData.id': {
      handler () {
        this.fetchDefinition()
      },
    },

    isActiveEdit (newVal) {
      if (newVal === false && this.isEditing) {
        this.isEditing = false
        this.editingValue = null
        // No emit – the Parent initialized closing
      }
    },
  },

  methods: {
    cancelEdit () {
      this.isEditing = false
      this.editingValue = null
      this.$emit('edit:cancel')
    },

    /**
     * Fetch definition for THIS field using composable.
     * Composable caching ensures only ONE API call for all instances!
     */
    fetchDefinition () {
      if (this.definition) {
        this.resolvedDefinition = this.definition

        return
      }

      if (!this.definitionSourceId) {
        return
      }

      const { fetchCustomFields } = useCustomFields()

      this.isLoading = true

      fetchCustomFields(this.definitionSourceId)
        .then(definitions => {
          this.resolvedDefinition = definitions.find(d => d.id === this.fieldData.id)

          if (!this.resolvedDefinition) {
            console.warn(`Custom field definition not found for ID: ${this.fieldData.id}`)
          }

          this.isLoading = false
        })
        .catch(() => {
          this.isLoading = false
        })
    },

    /**
     * Get the component name for a given field type
     */
    getComponentForType (fieldType) {
      return this.componentMap[fieldType] || 'dp-singleselect-custom-field'
    },

    /**
     * Handle value updates during editing (toggle mode)
     * Updates local editingValue without emitting
     * Renderers emit unified structure: { id, value }
     */
    handleEditingValueUpdate (fieldValue) {
      this.editingValue = fieldValue.value
    },

    /**
     * Handle value updates from child component
     * Renderers emit unified structure: { id, value }
     * This method simply forwards the value to the parent (generic, no field-type logic)
     */
    handleValueUpdate (fieldValue) {
      this.$emit('update:value', fieldValue.value)
    },

    /**
     * Save custom field value via JSON:API (called internally by saveEdit)
     * If resourceType/resourceId not provided, returns resolved promise
     *
     * @returns {Promise} Promise resolving on successful save
     */
    saveCustomField () {
      if (!this.resourceType || !this.resourceId) {
        return Promise.resolve()
      }

      const currentValue = this.enableToggle && this.isEditing ?
        this.editingValue :
        this.fieldData.value

      this.isSaving = true
      this.saveError = null

      const { updateCustomFields } = useCustomFields()

      return updateCustomFields(
        this.resourceType,
        this.resourceId,
        [{ id: this.fieldData.id, value: currentValue }],
      )
        .then(() => {
          this.isSaving = false
          this.$emit('save:success', { fieldId: this.fieldData.id, value: currentValue })
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
        .catch((error) => {
          this.isSaving = false
          this.saveError = error
          this.$emit('save:error', { fieldId: this.fieldData.id, value: currentValue, error })
          dplan.notify.notify('error', Translator.trans('error.changes.not.saved'))
        })
    },

    /**
     * Save edit (toggle mode)
     * If resourceType/resourceId provided: saves via API first, then emits
     * If not provided: just emits (backward compatibility)
     */
    saveEdit () {
      if (this.resourceType && this.resourceId) {
        const isRequired = this.resolvedDefinition?.attributes?.isRequired
        const isEmpty = !this.editingValue ||
          (Array.isArray(this.editingValue) && this.editingValue.length === 0)

        if (isRequired && isEmpty) {
          const fieldName = this.resolvedDefinition?.attributes?.name
          dplan.notify.notify('error', fieldName ?
            Translator.trans('error.mandatoryfield', { name: fieldName }) :
            Translator.trans('error.mandatoryfields'),
          )
          return
        }

        this.saveCustomField()
          .then(() => {
            this.$emit('update:value', this.editingValue)
            this.$emit('edit:save', this.editingValue)
            this.isEditing = false
            this.editingValue = null
          })
      } else {
        // Traditional mode: just emit without API call
        this.$emit('update:value', this.editingValue)
        this.$emit('edit:save', this.editingValue)
        this.isEditing = false
        this.editingValue = null
      }
    },

    /**
     * Start editing (toggle mode)
     * Clones current value to editingValue
     */
    startEditing () {
      this.isEditing = true
      this.editingValue = this.fieldData.value
      this.$emit('edit:start')
    },

    buildFieldObject (rawValue) {
      if (!this.resolvedDefinition) {
        return null
      }

      return {
        ...this.resolvedDefinition,
        value: this.transformValueForRenderer(rawValue),
      }
    },

    /**
     * Transform raw backend value to renderer format
     * Generic transformation with type-specific enrichments
     * Backend format: depends on field type (array, string, number, etc.)
     * Renderer format: { id, value, ...type-specific properties }
     */
    transformValueForRenderer (rawValue) {
      const fieldType = this.resolvedDefinition?.attributes?.fieldType

      // Base structure (generic for all types)
      const transformed = {
        id: this.fieldData.id,
        value: rawValue || null,  // The raw backend value
      }

      if (fieldType === 'multiSelect' || fieldType === 'singleSelect') {
        // For select types: match IDs to full option objects
        let optionIds = []
        if (rawValue) {
          optionIds = Array.isArray(rawValue) ? rawValue : [rawValue]
        }

        const allOptions = this.resolvedDefinition?.attributes?.options || []
        const selectedOptions = optionIds
          .map(optionId => allOptions.find(opt => opt?.id === optionId))
          .filter(opt => opt != null)

        transformed.selectedOptions = selectedOptions
      }
      /*
       * Future types can be added here:
       * else if (fieldType === 'text') { ... }
       * else if (fieldType === 'number') { ... }
       */

      return transformed
    },
  },

  created () {
    if (!this.definition && !this.definitionSourceId) {
      console.warn('CustomField: either "definition" or "definitionSourceId" must be provided.')
    }
  },
}
</script>
