<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="chips.length > 0"
    class="flex flex-wrap items-center gap-1"
  >
    <dp-tooltip
      v-for="chip in visibleChips"
      :key="chip.key"
      :text="Translator.trans('custom.fields.chip.tooltip', { name: escapeHtml(chip.fieldName) })"
      node-type="span"
    >
      <dp-badge
        :text="chip.label"
        color="default"
        size="small"
      />
    </dp-tooltip>
    <dp-tooltip
      v-if="hiddenCount > 0"
      :text="tooltipHtml"
      node-type="span"
    >
      <span class="text-sm text-interactive">
        {{ Translator.trans('custom.fields.chips.more', { count: hiddenCount }) }}
      </span>
    </dp-tooltip>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { DpBadge, DpTooltip } from '@demos-europe/demosplan-ui'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

interface CustomFieldDefinition {
  id: string
  attributes: {
    name: string
    fieldType: string
    options: { id: string, label: string }[]
  }
}

interface CustomFieldValue {
  id: string
  value: string | string[] | null
}

interface Chip {
  fieldName: string
  key: string
  label: string
}

interface FieldEntry {
  name: string
  labels: string[]
}

// The visible chip count is not defined in the design yet, adjust once it is.
const MAX_VISIBLE_CHIPS = 2

const props = defineProps({
  definitionSourceId: {
    type: String,
    required: true,
  },

  resourceId: {
    type: String,
    required: true,
  },
})

const { fetchCustomFields, fetchCustomFieldValues } = useCustomFields()

const definitions = ref<CustomFieldDefinition[]>([])
const values = ref<CustomFieldValue[]>([])

/*
 * Select fields store option IDs (a string or a list of strings), text fields store the text itself.
 * Option IDs that no longer exist in the definition are dropped.
 */
const resolveLabels = (definition: CustomFieldDefinition, value: CustomFieldValue['value']): string[] => {
  if (value === null || value === '') {
    return []
  }

  if (definition.attributes.fieldType === 'text') {
    return [String(value)]
  }

  const optionIds = Array.isArray(value) ? value : [value]

  return optionIds
    .map(optionId => definition.attributes.options.find(option => option.id === optionId)?.label)
    .filter((label): label is string => Boolean(label))
}

const entries = computed<FieldEntry[]>(() => definitions.value
  .map(definition => ({
    name: definition.attributes.name,
    labels: resolveLabels(definition, values.value.find(value => value.id === definition.id)?.value ?? null),
  }))
  .filter(entry => entry.labels.length > 0))

const chips = computed<Chip[]>(() => entries.value.flatMap((entry, entryIndex) =>
  entry.labels.map((label, labelIndex) => ({ fieldName: entry.name, key: `${entryIndex}_${labelIndex}`, label })),
))

const visibleChips = computed<Chip[]>(() => chips.value.slice(0, MAX_VISIBLE_CHIPS))

const hiddenCount = computed<number>(() => chips.value.length - visibleChips.value.length)

const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }

// DpTooltip inserts its text as HTML, field names and labels are user input and must be escaped.
const escapeHtml = (text: string): string => text.replace(/[&<>"']/g, character => HTML_ESCAPES[character])

const tooltipHtml = computed<string>(() => entries.value
  .map(entry => `${escapeHtml(entry.name)}: ${escapeHtml(entry.labels.join(', '))}`)
  .join('<br>'))

onMounted(async () => {
  try {
    definitions.value = await fetchCustomFields(props.definitionSourceId, {
      sourceEntity: 'PROCEDURE',
      targetEntity: 'STATEMENT',
    })
    values.value = await fetchCustomFieldValues('Statement', props.resourceId, props.definitionSourceId, 'procedure.id')
  } catch (error) {
    console.error('Failed to load custom fields of statement', props.resourceId, error)
  }
})
</script>
