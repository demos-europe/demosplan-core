<template>
  <div v-if="hasFieldsToRender">
    <!-- Expandable version with dp-details -->
    <dp-details
      v-if="expandable"
      :summary="effectiveTitle"
    >
      <slot
        :enable-toggle="enableToggle"
        :fields="fieldsToRender"
        :mode="mode"
        :source-id="definitionSourceId"
        :target-id="resourceId"
        :target-type="resourceType"
      >
        <div :class="layoutClasses">
          <dp-custom-field
            v-for="field in fieldsToRender"
            :key="field.id"
            :definition="definitions.find(d => d.id === field.id)"
            :enable-toggle="enableToggle"
            :field-data="{ id: field.id, value: field.value }"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
            @save:success="handleSaveSuccess"
            @save:error="handleSaveError"
            @update:value="newValue => handleValueUpdate(field.id, newValue)"
          >
            <!-- Forward readonly-display slot if parent provided it -->
            <template
              v-if="$slots['readonly-display']"
              v-slot:readonly-display="slotProps"
            >
              <slot
                name="readonly-display"
                v-bind="slotProps"
              />
            </template>
          </dp-custom-field>
        </div>
      </slot>
    </dp-details>

    <!-- Non-expandable version -->
    <div v-else>
      <h3
        v-if="listTitle"
        :class="prefixClass('font-bold mb-2')"
      >
        {{ listTitle }}
      </h3>
      <slot
        :enable-toggle="enableToggle"
        :fields="fieldsToRender"
        :mode="mode"
        :source-id="definitionSourceId"
        :target-id="resourceId"
        :target-type="resourceType"
      >
        <div :class="layoutClasses">
          <dp-custom-field
            v-for="field in fieldsToRender"
            :key="field.id"
            :definition="definitions.find(d => d.id === field.id)"
            :enable-toggle="enableToggle"
            :field-data="{ id: field.id, value: field.value }"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
            @save:success="handleSaveSuccess"
            @save:error="handleSaveError"
            @update:value="newValue => handleValueUpdate(field.id, newValue)"
          >
            <!-- Forward readonly-display slot if parent provided it -->
            <template
              v-if="$slots['readonly-display']"
              v-slot:readonly-display="slotProps"
            >
              <slot
                name="readonly-display"
                v-bind="slotProps"
              />
            </template>
          </dp-custom-field>
        </div>
      </slot>
    </div>
  </div>
</template>

<script>
import { dpApi, DpDetails, prefixClassMixin } from '@demos-europe/demosplan-ui'
import DpCustomField from './DpCustomField'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'DpCustomFieldsList',

  components: {
    DpCustomField,
    DpDetails,
  },

  mixins: [prefixClassMixin],

  props: {
    definitionSourceId: {
      type: String,
      required: true,
    },
    enableToggle: {
      type: Boolean,
      required: false,
      default: false,
    },
    expandable: {
      type: Boolean,
      required: false,
      default: false,
    },
    layout: {
      type: String,
      required: false,
      default: 'vertical',
      validator: val => ['vertical', 'horizontal', 'grid'].includes(val),
    },
    listTitle: {
      type: String,
      required: false,
      default: '',
    },
    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: val => ['readonly', 'editable'].includes(val),
    },
    resourceId: {
      type: String,
      required: true,
    },
    resourceType: {
      type: String,
      required: true,
    },
    showEmpty: {
      type: Boolean,
      required: false,
      default: false,
    },
  },

  emits: ['save:success', 'save:error', 'update:value', 'loaded'],

  data () {
    return {
      definitions: [],
      values: [],
      isLoading: false,
      error: null,
    }
  },

  computed: {
    effectiveTitle () {
      return this.listTitle || Translator.trans('more.data')
    },

    /*
     * Create field-data objects by matching definitions with values.
     * The matching with the definition is important to also show empty custom fields in edit or toggle mode.
     */
    fieldDataObjects () {
      return this.definitions.map(def => {
        const valueObj = this.values.find(v => v.id === def.id)
        return {
          id: def.id,
          value: valueObj?.value ?? null,
        }
      })
    },

    fieldsToRender () {
      if (this.showEmpty) {
        return this.fieldDataObjects
      } else {
        return this.fieldDataObjects.filter(field =>
          field.value !== null &&
          field.value !== undefined &&
          field.value !== '' &&
          !(Array.isArray(field.value) && field.value.length === 0),
        )
      }
    },

    hasFieldsToRender () {
      return !this.isLoading && !this.error && this.hasRequiredPermission && this.fieldsToRender.length > 0
    },

    hasRequiredPermission () {
      return hasPermission(this.requiredPermission)
    },

    layoutClasses () {
      const baseClasses = {
        vertical: this.prefixClass('flex flex-col space-y-2'),
        horizontal: this.prefixClass('flex flex-row flex-wrap gap-4'),
        grid: this.prefixClass('grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-4'),
      }
      return baseClasses[this.layout] || baseClasses.vertical
    },

    requiredPermission () {
      if (this.resourceType === 'DraftStatement') {
        return 'feature_statements_custom_fields'
      }
      return 'field_statements_custom_fields'
    },
  },

  watch: {
    resourceType () {
      this.fetchCustomFieldsData()
    },
    resourceId () {
      this.fetchCustomFieldsData()
    },
    definitionSourceId () {
      this.fetchCustomFieldsData()
    },
  },

  methods: {
    fetchCustomFieldsData () {
      // Check permission
      if (!this.hasRequiredPermission) {
        console.warn('Missing permission for custom fields')
        return
      }

      this.isLoading = true
      this.error = null

      // 1. Fetch definitions (gets array of field definitions with IDs)
      const { fetchCustomFields } = useCustomFields()

      fetchCustomFields(this.definitionSourceId)
        .then(defs => {
          this.definitions = defs

          // 2. Fetch values (gets array of { id, value } for this entity)
          const baseUrl = Routing.generate('api_resource_get', {
            resourceType: this.resourceType,
            resourceId: this.resourceId,
          })
          const url = `${baseUrl}?fields[${this.resourceType}]=customFields`

          return dpApi.get(url)
        })
        .then(response => {
          this.values = response.data.data.attributes.customFields || []
          this.$emit('loaded', this.values)
        })
        .catch(err => {
          console.error('Failed to fetch custom fields data:', err)
          this.error = err
        })
        .finally(() => {
          this.isLoading = false
        })
    },

    handleSaveError (payload) {
      this.$emit('save:error', payload)
    },

    handleSaveSuccess (payload) {
      this.$emit('save:success', payload)
      // Refetch data after save
      this.fetchCustomFieldsData()
    },

    handleValueUpdate (fieldId, newValue) {
      const valueIndex = this.values.findIndex(v => v.id === fieldId)
      if (valueIndex === -1) {
        this.values = [...this.values, { id: fieldId, value: newValue }]
      } else {
        this.values = this.values.map((v, i) =>
          i === valueIndex ? { ...v, value: newValue } : v,
        )
      }
      this.$emit('update:value', { fieldId, value: newValue })
    },
  },

  mounted () {
    this.fetchCustomFieldsData()
  },
}
</script>
