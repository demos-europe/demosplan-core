<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="relative float-right">
    <dp-button
      color="secondary"
      data-cy="segmentsBookmarksButton:open"
      icon="bookmark"
      :text="buttonText"
      variant="outline"
      @click="$emit('click')"
    />
    <span
      v-if="hasUnsavedChanges"
      class="absolute top-1 left-4 w-1 h-1 bg-interactive rounded-full"
      data-cy="segmentsBookmarksButton:unsavedIndicator"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { DpButton } from '@demos-europe/demosplan-ui'

const buttonText = ref<string>(Translator.trans('views'))
const hasUnsavedChanges = ref<boolean>(false)

const updateButtonText = (newText: string): void => {
  buttonText.value = newText
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
