<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset>
    <legend
      id="customFields"
      class="mb-3 color-text-muted font-normal"
    >
      {{ Translator.trans('fields.configurable') }}
    </legend>

    <custom-fields-list
      v-slot:default="{ fieldsWithDefinitions }"
      :definition-source-id="procedureId"
      :mode="editable ? 'editable' : 'readonly'"
      :resource-id="statementId"
      :show-empty="true"
      :show-title="false"
      resource-type="Statement"
      source-entity="PROCEDURE"
      target-entity="STATEMENT"
      @loaded="onLoaded"
    >
      <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <custom-field
          v-for="{ field, definition } in fieldsWithDefinitions"
          :key="field.id"
          :definition="definition"
          :field-data="{ id: field.id, value: getValue(field.id) }"
          :mode="editable ? 'editable' : 'readonly'"
          :resource-id="statementId"
          resource-type="Statement"
          @update:value="newValue => onValueUpdate(field.id, newValue)"
        />
      </div>
    </custom-fields-list>

    <dp-button-row
      v-if="editable"
      :busy="isSaving"
      class="mt-2 w-full"
      :disabled="!hasChanges || isSaving"
      primary
      secondary
      @primary-action="save"
      @secondary-action="reset"
    />
  </fieldset>
</template>

<script setup lang="ts">
import { computed, ref, toRaw } from 'vue'
import CustomField from '@DpJs/components/customFields/CustomField.vue'
import CustomFieldsList from '@DpJs/components/customFields/CustomFieldsList.vue'
import { DpButtonRow } from '@demos-europe/demosplan-ui'
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

const currentValues = ref<CustomFieldValue[]>([])
const initialValues = ref<CustomFieldValue[]>([])
const isSaving = ref(false)

const hasChanges = computed(() => JSON.stringify(currentValues.value) !== JSON.stringify(initialValues.value))

const clone = (values: CustomFieldValue[]): CustomFieldValue[] => structuredClone(toRaw(values))

const getValue = (fieldId: string) => currentValues.value.find(entry => entry.id === fieldId)?.value ?? null

const onLoaded = (values: CustomFieldValue[]) => {
  initialValues.value = clone(values)
  currentValues.value = clone(values)
}

const onValueUpdate = (fieldId: string, value: unknown) => {
  const exists = currentValues.value.some(entry => entry.id === fieldId)

  currentValues.value = exists ?
    currentValues.value.map(entry => entry.id === fieldId ? { ...entry, value } : entry) :
    [...currentValues.value, { id: fieldId, value }]
}

const reset = () => {
  currentValues.value = clone(initialValues.value)
}

const save = () => {
  isSaving.value = true

  const { updateCustomFields } = useCustomFields()

  return updateCustomFields('Statement', props.statementId, currentValues.value)
    .then(() => {
      initialValues.value = clone(currentValues.value)
      dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
    })
    .catch(() => {
      dplan.notify.error(Translator.trans('error.api.generic'))
    })
    .finally(() => {
      isSaving.value = false
    })
}
</script>
