<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Card summarising a multi-selection in a list: count, expandable list of the selected items
      with a remove button per row, and a button row with "unselect" plus whatever actions the
      list offers via the `actions` slot. The parent owns the selection and the expanded state. -->
</documentation>

<template>
  <div
    class="shadow-sm rounded-lg mt-2 py-4 px-6"
    :data-cy="dataCy"
  >
    <div class="flex items-center justify-between border-b border-neutral py-2">
      <span :data-cy="`${dataCy}:count`">
        {{ countText }}
      </span>
      <dp-button
        :disabled="toggleDisabled"
        :icon="showItems ? 'caret-up' : 'caret-down'"
        :text="Translator.trans(showItems ? 'selection.hide' : 'selection.show')"
        :data-cy="`${dataCy}:toggleItems`"
        variant="subtle"
        @click="emit('toggle:items')"
      />
    </div>
    <dp-transition-expand>
      <div v-show="showItems">
        <dp-loading
          v-if="isLoadingItems"
          class="mt-1"
        />
        <template v-else>
          <div
            v-for="item in items"
            :key="item.id"
            class="flex items-center justify-between border-b border-neutral py-2 my-2"
          >
            <div class="grow">
              <slot
                name="item"
                :item="item"
              >
                <span class="font-semibold">
                  {{ item.name }}
                </span>
              </slot>
            </div>
            <dp-button
              :text="Translator.trans('remove')"
              :data-cy="`${dataCy}:removeItem`"
              hide-text
              icon="x"
              icon-weight="bold"
              variant="subtle"
              @click="emit('remove:item', item.id)"
            />
          </div>
        </template>
      </div>
    </dp-transition-expand>
    <div class="text-right mt-4 space-x-2">
      <dp-button
        :text="Translator.trans('unselect')"
        class="p-1"
        color="secondary"
        data-cy="resetSelectedItems"
        variant="outline"
        @click="emit('reset')"
      />
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { DpButton, DpLoading, DpTransitionExpand } from '@demos-europe/demosplan-ui'
import type { PropType } from 'vue'

interface SelectionItem {
  id: string
  name?: string
}

defineProps({
  /**
   * Already translated, e.g. "2 Organisationen sind ausgewählt"
   */
  countText: {
    type: String,
    required: true,
  },

  dataCy: {
    type: String,
    required: false,
    default: 'selectionCard',
  },

  isLoadingItems: {
    type: Boolean,
    required: false,
    default: false,
  },

  items: {
    type: Array as PropType<SelectionItem[]>,
    required: false,
    default: () => [],
  },

  showItems: {
    type: Boolean,
    required: false,
    default: false,
  },

  toggleDisabled: {
    type: Boolean,
    required: false,
    default: false,
  },
})

const emit = defineEmits<{ 'remove:item': [id: string], reset: [], 'toggle:items': [] }>()
</script>
