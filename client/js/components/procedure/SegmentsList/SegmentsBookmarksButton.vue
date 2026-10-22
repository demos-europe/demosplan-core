<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="relative float-right">
    <dp-tooltip
      :key="tooltipKey"
      data-cy="segmentsBookmarksButton:bookmarkTitle"
      :text="tooltipText"
    >
      <dp-button
        class="max-w-11 mx-0.5"
        color="secondary"
        data-cy="segmentsBookmarksButton:open"
        icon="bookmark"
        :text="buttonText"
        text-truncate
        variant="outline"
        @click="$emit('click')"
      />
    </dp-tooltip>
    <span
      v-if="hasUnsavedChanges"
      class="absolute top-1 left-4 w-1 h-1 bg-interactive rounded-full"
      data-cy="segmentsBookmarksButton:unsavedIndicator"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, type ComputedRef, ref } from 'vue'
import { DpButton, DpTooltip } from '@demos-europe/demosplan-ui'

const defaultViewsLabel = Translator.trans('views')
const buttonText = ref<string>(defaultViewsLabel)
const hasUnsavedChanges = ref<boolean>(false)
const tooltipKey = ref<number>(0)

const tooltipText: ComputedRef<string> = computed(() => {
  return buttonText.value === defaultViewsLabel ?
    defaultViewsLabel :
    `${Translator.trans('segments.bookmarks.view')}: ${buttonText.value}`
})

const updateButtonText = (newText: string): void => {
  buttonText.value = newText
  tooltipKey.value++
}

const setHasUnsavedChanges = (value: boolean): void => {
  hasUnsavedChanges.value = value
}

defineEmits(['click'])

defineExpose({
  setHasUnsavedChanges,
  updateButtonText,
})
</script>
