<template>
  <div v-if="isLoading || resolvedDefinition">
    <dp-loading
      v-if="isLoading"
      :overlay="false"
    />
    <dp-inline-notification
      v-if="error"
      :message="Translator.trans('error.loading.custom.field')"
      type="error"
    />

    <!-- Toggle Mode: Readonly with Edit Button -->
    <div
      v-else-if="enableToggle && !isEditing"
      :class="prefixClass('dp-custom-field__readonly')"
    >
      <div :class="prefixClass('flex items-start gap-1')">
        <div :class="prefixClass('dp-custom-field__content flex-1')">
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
                name="readonly-display"
                v-bind="slotProps"
              />
            </template>
          </component>
        </div>
        <div :class="prefixClass('dp-custom-field__trigger')">
          <button
            type="button"
            :title="Translator.trans('edit')"
            class="btn--blank o-link--default"
            @click="startEditing"
          >
            <i
              aria-hidden="true"
              class="fa fa-pencil"
            />
          </button>
        </div>
      </div>
    </div>

    <!-- Toggle Mode: Editing with Save/Cancel -->
    <div
      v-else-if="enableToggle && isEditing"
      :class="prefixClass('dp-custom-field__editing')"
    >
      <div :class="prefixClass('flex items-start gap-1')">
        <div :class="prefixClass('dp-custom-field__content flex-1')">
          <component
            :is="getComponentForType(resolvedDefinition.attributes.fieldType)"
            :field="editingField"
            mode="editable"
            @update:value="handleEditingValueUpdate"
          />
        </div>
        <div :class="prefixClass('dp-custom-field__trigger')">
          <button
            type="button"
            :title="Translator.trans('save')"
            :disabled="isSaving"
            class="btn--blank o-link--default"
            :class="prefixClass('mr-1')"
            @click="saveEdit"
          >
            <i
              aria-hidden="true"
              class="fa fa-check"
            />
          </button>
          <button
            type="button"
            :title="Translator.trans('abort')"
            :disabled="isSaving"
            class="btn--blank o-link--default"
            @click="cancelEdit"
          >
            <i
              aria-hidden="true"
              class="fa fa-times"
            />
          </button>
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
          name="readonly-display"
          v-bind="slotProps"
        />
      </template>
    </component>
  </div>
</template>

<script>
import { DpInlineNotification, DpLoading, prefixClassMixin } from '@demos-europe/demosplan-ui'
import DpMultiselectCustomField from './DpMultiselectCustomField'
import DpSingleselectCustomField from './DpSingleselectCustomField'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'DpCustomField',

  components: {
    DpInlineNotification,
    DpLoading,
    DpMultiselectCustomField,
    DpSingleselectCustomField,
  },

  mixins: [prefixClassMixin],

  emits: [
    'edit:cancel',
    'edit:save',
    'edit:start',
    'save:error',
    'save:success',
    'update:value',
  ],

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
      type: String,
      required: false,
      default: null,
    },

    resourceType: {
      type: String,
      required: false,
      default: null,
    },
  },

  data () {
    return {
      componentMap: {
        multiSelect: 'dp-multiselect-custom-field',
        singleSelect: 'dp-singleselect-custom-field',
      },
      resolvedDefinition: null,
      editingValue: null,
      error: null,
      isEditing: false,
      isLoading: false,
      isSaving: false,
      saveError: null,
    }
  },

  computed: {
    /**
     * Field object for editing state (used in toggle mode)
     * Uses editingValue instead of prop value
     */
    editingField () {
      if (!this.resolvedDefinition) return null

      const transformedValue = this.transformValueForRenderer(this.editingValue)

      return {
        ...this.resolvedDefinition,
        value: transformedValue,
      }
    },

    /**
     * Merged field: definition + value
     * Returns complete field object for renderer
     * Transforms raw backend value to format expected by renderers
     */
    mergedField () {
      if (!this.resolvedDefinition) {
        return null
      }

      const transformedValue = this.transformValueForRenderer(this.fieldData.value)

      return {
        ...this.resolvedDefinition,
        value: transformedValue,
      }
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
        // Kein emit – der Parent hat die Schließung initiiert
      }
    },
  },

  created () {
    if (!this.definition && !this.definitionSourceId) {
      console.warn('DpCustomField: either "definition" or "definitionSourceId" must be provided.')
    }
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
      this.error = null

      fetchCustomFields(this.definitionSourceId)
        .then(definitions => {
          this.resolvedDefinition = definitions.find(d => d.id === this.fieldData.id)

          if (!this.resolvedDefinition) {
            console.warn(`Custom field definition not found for ID: ${this.fieldData.id}`)
          }

          this.isLoading = false
        })
        .catch(err => {
          console.error('Failed to fetch custom field definition:', err)
          this.error = err
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
     * Save custom field value via JSON:API
     * Can be called by parent (e.g., form submit) or internally (toggle mode save)
     * Returns a Promise for async handling
     *
     * If resourceType/resourceId not provided, returns resolved promise (backward compatibility)
     *
     * @returns {Promise} Promise resolving on successful save
     *
     * @example Called by parent
     * this.$refs.customFieldRef.saveCustomField()
     *   .then(() => console.log('Saved'))
     *   .catch(err => console.error(err))
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
          throw error  // Re-throw so parent can handle
        })
    },

    /**
     * Save edit (toggle mode)
     * If resourceType/resourceId provided: saves via API first, then emits
     * If not provided: just emits (backward compatibility)
     */
    saveEdit () {
      if (this.resourceType && this.resourceId) {
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
}
</script>
