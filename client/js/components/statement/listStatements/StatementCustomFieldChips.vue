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
    <!-- Chips beyond the limit are only shown while the table row is expanded. The flex wrapper avoids a text line box that would be taller than the badge. -->
    <span
      v-for="(chip, index) in chips"
      :key="chip.key"
      :class="index < MAX_VISIBLE_CHIPS ? 'flex' : 'hidden [.is-expanded-row_&]:flex'"
    >
      <dp-tooltip
        :text="Translator.trans('custom.fields.chip.tooltip', { name: escapeHtml(chip.fieldName) })"
        node-type="span"
      >
        <!-- Persistent field name for assistive technology, the tooltip is only available on hover -->
        <span class="sr-only">{{ chip.fieldName }}:</span>
        <dp-badge
          :text="chip.label"
          class="flex items-center border border-transparent"
          color="default"
          size="small"
        />
      </dp-tooltip>
    </span>
    <dp-popover
      v-if="hiddenCount > 0"
      :aria-label="Translator.trans('custom.fields.chips.show.more', { count: hiddenCount })"
      :panel-label="Translator.trans('custom.fields.collection')"
      class="[.is-expanded-row_&]:hidden"
    >
      <template v-slot:trigger>
        +{{ hiddenCount }}
      </template>
      <div class="w-max max-w-[400px] p-3 text-sm text-default">
        <p class="font-semibold">
          {{ '#'+ externId }} | {{ Translator.trans('custom.fields.collection') }}
        </p>
        <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3">
          <template
            v-for="entry in entries"
            :key="entry.name"
          >
            <dt class="font-semibold">
              {{ entry.name }}:
            </dt>
            <dd>
              {{ entry.labels.join(', ') }}
            </dd>
          </template>
        </dl>
      </div>
    </dp-popover>
  </div>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import type { CustomFieldDefinition, CustomFieldValue } from '@DpJs/types/customFields'
import { DpBadge, DpPopover, DpTooltip } from '@demos-europe/demosplan-ui'
import { resolveCustomFieldLabels } from '@DpJs/lib/shared/resolveCustomFieldLabels'

interface Chip {
  fieldName: string
  key: string
  label: string
}

interface FieldEntry {
  name: string
  labels: string[]
}

const MAX_VISIBLE_CHIPS = 4

const props = defineProps({
  // The values of the statement, as already requested for the row
  customFields: {
    type: Array as PropType<CustomFieldValue[] | null>,
    required: false,
    default: null,
  },

  definitions: {
    type: Array as PropType<CustomFieldDefinition[]>,
    required: true,
  },

  externId: {
    type: String,
    required: true,
  },
})

// Fields shown in their own column are not part of the collective column
const entries = computed<FieldEntry[]>(() => props.definitions
  .filter(definition => !definition.attributes.isSeparateColumn)
  .map(definition => ({
    name: definition.attributes.name,
    labels: resolveCustomFieldLabels(definition, props.customFields?.find(value => value.id === definition.id)?.value ?? null),
  }))
  .filter(entry => entry.labels.length > 0))

const chips = computed<Chip[]>(() => entries.value.flatMap((entry, entryIndex) =>
  entry.labels.map((label, labelIndex) => ({ fieldName: entry.name, key: `${entryIndex}_${labelIndex}`, label })),
))

const hiddenCount = computed<number>(() => Math.max(chips.value.length - MAX_VISIBLE_CHIPS, 0))

const HTML_ESCAPES: Record<string, string> = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }

// DpTooltip inserts its text as HTML, field names and labels are user input and must be escaped.
const escapeHtml = (text: string): string => text.replace(/[&<>"']/g, character => HTML_ESCAPES[character])
</script>
