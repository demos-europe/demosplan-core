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

interface CustomFieldValue {
  id: string
  value: unknown
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

const hasChanges = computed(() => JSON.stringify(currentValues.value) !== JSON.stringify(initialValues.value))

const getValue = (fieldId: string) => currentValues.value.find(entry => entry.id === fieldId)?.value ?? null

// The description is shown as tooltip on the label; this view omits it
const withoutDescription = (definition: any) => definition ?
  { ...definition, attributes: { ...definition.attributes, description: '' } } :
  definition

const onLoaded = (values: CustomFieldValue[]) => {
  initialValues.value = values
  currentValues.value = values
}

const onValueUpdate = (fieldId: string, value: unknown) => {
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

// Used by the unsaved changes guard of StatementMeta
defineExpose({ hasUnsavedChanges: hasChanges, reset, save })
</script>
