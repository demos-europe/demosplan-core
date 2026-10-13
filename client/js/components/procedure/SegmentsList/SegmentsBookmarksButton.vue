<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-button
    class="float-right"
    color="secondary"
    data-cy="segmentsBookmarksButton:open"
    icon="bookmark"
    :text="buttonText"
    variant="outline"
    @click="$emit('click')"
  />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { DpButton } from '@demos-europe/demosplan-ui'

const buttonText = ref<string>(Translator.trans('views'))

const updateButtonText = (newText: string): void => {
  buttonText.value = newText
}

defineEmits(['click'])

defineExpose({
  updateButtonText
})
</script>
