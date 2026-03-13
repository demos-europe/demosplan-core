<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div v-if="isLoading || hasFieldsToRender">
    <dp-loading
      v-if="isLoading"
      :overlay="false"
    />
    <template v-else>
      <!-- Expandable version with dp-details -->
      <div
        v-if="expandable"
        :class="prefixClass('flex items-center gap-1')"
      >
        <dp-details :summary="listTitle">
          <slot
            :definition-source-id="definitionSourceId"
            :enable-toggle="enableToggle"
            :fields="fieldsToRender"
            :mode="mode"
            :resource-id="resourceId"
            :resource-type="resourceType"
          >
            <div :class="layoutClasses">
              <custom-field
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
                    v-bind="slotProps"
                    name="readonly-display"
                  />
                </template>
              </custom-field>
            </div>
          </slot>
        </dp-details>
        <dp-contextual-help
          v-if="titleInfoText"
          :class="prefixClass('self-start')"
          :text="titleInfoText"
          icon="info"
          size="medium"
        />
      </div>

      <!-- Editable (non-expandable): fieldset with legend for accessibility -->
      <fieldset
        v-else-if="mode === 'editable'"
        :class="prefixClass('pb-0')"
      >
        <legend
          v-if="showTitle"
          :class="prefixClass('mb-2 text-[1em] font-[500]')"
        >
          {{ listTitle }}
        </legend>
        <dp-contextual-help
          v-if="titleInfoText"
          :text="titleInfoText"
          icon="info"
          size="medium"
        />
        <slot
          :definition-source-id="definitionSourceId"
          :enable-toggle="enableToggle"
          :fields="fieldsToRender"
          :mode="mode"
          :resource-id="resourceId"
          :resource-type="resourceType"
        >
          <div :class="layoutClasses">
            <custom-field
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
                  v-bind="slotProps"
                  name="readonly-display"
                />
              </template>
            </custom-field>
          </div>
        </slot>
      </fieldset>

      <!-- Readonly non-expandable: optional title (tag configurable via titleTag prop) -->
      <div v-else>
        <div :class="[prefixClass('flex items-center gap-1'), effectiveTitleClass]">
          <component
            :is="effectiveTitleTag"
            v-if="showTitle"
            class="m-0"
          >
            {{ listTitle }}
          </component>
          <dp-contextual-help
            v-if="titleInfoText"
            :text="titleInfoText"
            icon="info"
            size="medium"
          />
        </div>
        <slot
          :definition-source-id="definitionSourceId"
          :enable-toggle="enableToggle"
          :fields="fieldsToRender"
          :mode="mode"
          :resource-id="resourceId"
          :resource-type="resourceType"
        >
          <div :class="layoutClasses">
            <custom-field
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
                  v-bind="slotProps"
                  name="readonly-display"
                />
              </template>
            </custom-field>
          </div>
        </slot>
      </div>
    </template>
  </div>
</template>

<script>
import { DpContextualHelp, DpDetails, DpLoading, prefixClassMixin } from '@demos-europe/demosplan-ui'
import CustomField from './CustomField'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

export default {
  name: 'CustomFieldsList',

  components: {
    CustomField,
    DpContextualHelp,
    DpDetails,
    DpLoading,
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
      default: () => Translator.trans('more.data'),
    },

    mode: {
      type: String,
      required: false,
      default: 'readonly',
      validator: val => ['readonly', 'editable'].includes(val),
    },

    showTitle: {
      type: Boolean,
      required: false,
      default: true,
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

    titleInfoText: {
      type: String,
      required: false,
      default: '',
    },

    titleTag: {
      type: String,
      required: false,
      default: 'p',
      validator: val => ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'dt'].includes(val),
    },
  },

  emits: [
    'hasContent',
    'loaded',
    'save:error',
    'save:success',
    'update:value',
  ],

  data () {
    return {
      activeEditFieldId: null,
      definitions: [],
      error: null,
      isLoading: false,
      values: [],
    }
  },

  computed: {
    effectiveTitleClass () {
      return this.titleClass === null ? this.prefixClass('font-[700] mb-2') : this.titleClass
    },

    effectiveTitleTag () {
      const allowed = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'dt']
      return allowed.includes(this.titleTag) ? this.titleTag : 'p'
    },

    /*
     * Match definitions with values and filter based on showEmpty prop.
     * Definitions without a matching value are included with value: null
     * to also show empty custom fields in edit or toggle mode.
     */
    fieldsToRender () {
      const allFields = this.definitions.map(def => {
        const valueObj = this.values.find(v => v.id === def.id)
        return {
          id: def.id,
          value: valueObj?.value ?? null,
        }
      })

      if (this.showEmpty) {
        return allFields
      }

      return allFields.filter(field =>
        field.value !== null &&
        field.value !== undefined &&
        field.value !== '' &&
        !(Array.isArray(field.value) && field.value.length === 0),
      )
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
    definitionSourceId () {
      this.fetchCustomFieldsData()
    },

    resourceId () {
      this.fetchCustomFieldsData()
    },

    resourceType () {
      this.fetchCustomFieldsData()
    },
  },

  methods: {
    fetchCustomFieldsData () {
      this.isLoading = true
      this.error = null

      const { fetchCustomFields, fetchCustomFieldValues } = useCustomFields()

      // 1. Fetch definitions (gets array of field definitions with IDs)
      fetchCustomFields(this.definitionSourceId)
        .then(defs => {
          this.definitions = defs

          // 2. Fetch values (gets array of { id, value } for this entity)
          return fetchCustomFieldValues(this.resourceType, this.resourceId)
        })
        .then(values => {
          this.values = values
          this.$emit('loaded', this.values)
        })
        .catch(err => {
          console.error('Failed to fetch custom fields data:', err)
          this.error = err
        })
        .finally(() => {
          this.isLoading = false
          this.$nextTick(() => {
            this.$emit('hasContent', this.hasFieldsToRender)
          })
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
