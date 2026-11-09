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
    <span
      v-for="(chip, index) in chips"
      :key="chip.key"
      :class="{ 'hidden [.is-expanded-row_&]:block': index >= MAX_VISIBLE_CHIPS }"
    >
      <dp-tooltip
        :text="Translator.trans('custom.fields.chip.tooltip', { name: escapeHtml(chip.fieldName) })"
        node-type="span"
      >
        <dp-badge
          :text="chip.label"
          class="flex items-center border border-transparent"
          color="default"
          size="small"
        />
      </dp-tooltip>
    </span>
    <button
      v-if="hiddenCount > 0"
      class="inline-flex cursor-pointer items-center rounded-md border border-interactive mt-0.5 px-1.5 py-0.5 text-xs text-interactive [.is-expanded-row_&]:hidden"
    >
      +{{ hiddenCount }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { DpBadge, DpTooltip } from '@demos-europe/demosplan-ui'
import { resolveCustomFieldLabels } from '@DpJs/lib/shared/resolveCustomFieldLabels'
import { useCustomFields } from '@DpJs/composables/useCustomFields'

interface CustomFieldDefinition {
  id: string
  attributes: {
    name: string
    fieldType: string
    isSeparateColumn?: boolean
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
const MAX_VISIBLE_CHIPS = 4

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

// Fields shown in their own column are not part of the collective column
const entries = computed<FieldEntry[]>(() => definitions.value
  .filter(definition => !definition.attributes.isSeparateColumn)
  .map(definition => ({
    name: definition.attributes.name,
    labels: resolveCustomFieldLabels(definition, values.value.find(value => value.id === definition.id)?.value ?? null),
  }))
  .filter(entry => entry.labels.length > 0))

const chips = computed<Chip[]>(() => entries.value.flatMap((entry, entryIndex) =>
  entry.labels.map((label, labelIndex) => ({ fieldName: entry.name, key: `${entryIndex}_${labelIndex}`, label })),
))

// The chips beyond the limit are only shown while the table row is expanded
const hiddenCount = computed<number>(() => Math.max(chips.value.length - MAX_VISIBLE_CHIPS, 0))

const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }

// DpTooltip inserts its text as HTML, field names and labels are user input and must be escaped.
const escapeHtml = (text: string): string => text.replace(/[&<>"']/g, character => HTML_ESCAPES[character])

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
