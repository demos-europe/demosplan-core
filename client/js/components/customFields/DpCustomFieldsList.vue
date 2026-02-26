<template>
  <div v-if="hasFieldsToRender">
    <!-- Expandable version with dp-details -->
    <dp-details
      v-if="expandable"
      :summary="listTitle"
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
            :is-active-edit="enableToggle ? (activeEditFieldId === null || activeEditFieldId === field.id) : null"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
            @edit:cancel="() => handleEditEnd(field.id)"
            @edit:save="() => handleEditEnd(field.id)"
            @edit:start="() => handleEditStart(field.id)"
            @save:error="handleSaveError"
            @save:success="handleSaveSuccess"
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

    <!-- Editable (non-expandable): fieldset with legend for accessibility -->
    <fieldset
      v-else-if="mode === 'editable'"
      :class="prefixClass('pb-0')"
    >
      <legend
        v-if="!noTitle"
        :class="prefixClass('mb-2 text-[1em] font-[500]')"
      >
        {{ listTitle }}
      </legend>
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
            :is-active-edit="enableToggle ? (activeEditFieldId === null || activeEditFieldId === field.id) : null"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
            @edit:cancel="() => handleEditEnd(field.id)"
            @edit:save="() => handleEditEnd(field.id)"
            @edit:start="() => handleEditStart(field.id)"
            @save:error="handleSaveError"
            @save:success="handleSaveSuccess"
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
    </fieldset>

    <!-- Readonly non-expandable: optional title (tag configurable via titleTag prop) -->
    <div v-else>
      <component
        :is="effectiveTitleTag"
        v-if="!noTitle"
        :class="effectiveTitleClass"
      >
        {{ listTitle }}
      </component>
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
            :is-active-edit="enableToggle ? (activeEditFieldId === null || activeEditFieldId === field.id) : null"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
            @edit:cancel="() => handleEditEnd(field.id)"
            @edit:save="() => handleEditEnd(field.id)"
            @edit:start="() => handleEditStart(field.id)"
            @save:error="handleSaveError"
            @save:success="handleSaveSuccess"
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
      default: () => Translator.trans('statement.data'),
    },
    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: val => ['readonly', 'editable'].includes(val),
    },
    noTitle: {
      type: Boolean,
      required: false,
      default: false,
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
    titleClass: {
      type: [String, Array, Object],
      required: false,
      default: null,
    },
    titleTag: {
      type: String,
      required: false,
      default: 'p',
      validator: val => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'dt'].includes(val),
    },
  },

  emits: ['save:success', 'save:error', 'update:value', 'loaded'],

  data () {
    return {
      activeEditFieldId: null,
      definitions: [],
      values: [],
      isLoading: false,
      error: null,
    }
  },

  computed: {
    effectiveTitleClass () {
      return this.titleClass !== null ? this.titleClass : this.prefixClass('font-[700] mb-2')
    },

    effectiveTitleTag () {
      const allowed = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'dt']
      return allowed.includes(this.titleTag) ? this.titleTag : 'p'
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
      return !this.isLoading && !this.error && this.fieldsToRender.length > 0
    },

    layoutClasses () {
      const baseClasses = {
        vertical: this.prefixClass('flex flex-col space-y-2'),
        horizontal: this.prefixClass('flex flex-row flex-wrap gap-4'),
        grid: this.prefixClass('grid grid-cols-[repeat(auto-fit,minmax(300px,1fr))] gap-4'),
      }
      return baseClasses[this.layout] || baseClasses.vertical
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

    handleEditEnd (fieldId) {
      if (this.activeEditFieldId === fieldId) {
        this.activeEditFieldId = null
      }
    },

    handleEditStart (fieldId) {
      this.activeEditFieldId = fieldId
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
