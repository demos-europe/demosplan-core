<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset ref="customFieldsForm">
    <legend
      id="customFields"
      class="mb-3 color-text-muted font-normal"
    >
      {{ Translator.trans('fields.configurable') }}
    </legend>

    <custom-fields-list
      v-slot:default="{ fieldsWithDefinitions }"
      :definition-source-id="procedureId"
      :resource-id="statementId"
      :show-empty="true"
      :show-title="false"
      mode="editable"
      resource-type="Statement"
      source-entity="PROCEDURE"
      target-entity="STATEMENT"
      @loaded="onLoaded"
    >
      <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <custom-field
          v-for="{ field, definition } in fieldsWithDefinitions"
          :key="field.id"
          :definition="withoutDescription(definition)"
          :disabled="!editable"
          :field-data="{ id: field.id, value: getValue(field.id) }"
          :resource-id="statementId"
          mode="editable"
          resource-type="Statement"
          @update:value="newValue => onValueUpdate(field.id, newValue)"
        />
      </div>
    </custom-fields-list>

    <dp-button-row
      v-if="editable"
      :busy="isSaving"
      :disabled="!hasChanges || isSaving"
      class="mt-2 w-full"
      data-cy="statementMetaCustomFields:buttonRow"
      primary
      secondary
      @primary-action="save"
      @secondary-action="reset"
    />
  </fieldset>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DpButtonRow, useDpValidate } from '@demos-europe/demosplan-ui'
import CustomField from '@DpJs/components/customFields/CustomField.vue'
import CustomFieldsList from '@DpJs/components/customFields/CustomFieldsList.vue'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

type CustomFieldValueType = string | string[] | null

interface CustomFieldValue {
  id: string
  value: CustomFieldValueType
}

const props = defineProps({
  editable: {
    type: Boolean,
    default: false,
  },

  procedureId: {
    type: String,
    required: true,
  },

  statementId: {
    type: String,
    required: true,
  },
})

const customFieldsForm = ref<HTMLFieldSetElement | null>(null)
const { validate } = useDpValidate(customFieldsForm)

// Values are never mutated in place, so initial and current values can share entries
const currentValues = ref<CustomFieldValue[]>([])
const initialValues = ref<CustomFieldValue[]>([])
const isSaving = ref(false)

const isEmptyValue = (value: CustomFieldValueType | undefined) => value === null || value === undefined || value === '' ||
  (Array.isArray(value) && value.length === 0)

// A field that was never set has no entry, so empty values and absent entries count as the same
const normalize = (values: CustomFieldValue[]) => values
  .filter(entry => !isEmptyValue(entry.value))
  .sort((a, b) => a.id.localeCompare(b.id))

const hasChanges = computed(() => JSON.stringify(normalize(currentValues.value)) !== JSON.stringify(normalize(initialValues.value)))

const getValue = (fieldId: string) => currentValues.value.find(entry => entry.id === fieldId)?.value ?? null

// The description is shown as tooltip on the label; this view omits it
const withoutDescription = (definition: any) => definition ?
  { ...definition, attributes: { ...definition.attributes, description: '' } } :
  definition

const onLoaded = (values: CustomFieldValue[]) => {
  initialValues.value = values
  currentValues.value = values
}

const onValueUpdate = (fieldId: string, value: CustomFieldValueType) => {
  const exists = currentValues.value.some(entry => entry.id === fieldId)

  currentValues.value = exists ?
    currentValues.value.map(entry => entry.id === fieldId ? { ...entry, value } : entry) :
    [...currentValues.value, { id: fieldId, value }]
}

const reset = () => {
  currentValues.value = initialValues.value
}

const save = () => {
  if (validate() === false) {
    return
  }

  isSaving.value = true

  // Keep the submitted values: edits made while the request is pending must stay unsaved
  const submittedValues = currentValues.value
  const { updateCustomFields } = useCustomFields()

  return updateCustomFields('Statement', props.statementId, submittedValues)
    .then(() => {
      initialValues.value = submittedValues
      dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
    })
    .catch(() => {
      dplan.notify.error(Translator.trans('error.api.generic'))
    })
    .finally(() => {
      isSaving.value = false
    })
}

// Rejects if edits are still unsaved afterwards, so the guard of StatementMeta cancels the navigation
const saveForGuard = () => {
  // A second request could finish first and let the navigation happen before the older one overwrites it
  if (isSaving.value) {
    return Promise.reject(new Error('Custom fields are being saved'))
  }

  return Promise.resolve(save()).then(() => {
    if (hasChanges.value) {
      throw new Error('Custom fields still have unsaved changes')
    }
  })
}

// Used by the unsaved changes guard of StatementMeta
defineExpose({ hasUnsavedChanges: hasChanges, reset, save: saveForGuard })
</script>
