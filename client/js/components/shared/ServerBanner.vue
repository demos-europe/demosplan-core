<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="isVisible"
    :class="prefixClass('bg-message-warning text-message-warning border border-message-warning relative px-2 pt-2')"
    role="alert"
  >
    <div
      v-cleanhtml="props.message"
    />
    <dp-button
      :class="prefixClass('absolute top-2 right-2')"
      :text="Translator.trans('close')"
      hide-text
      icon="x"
      variant="transparent"
      @click="dismiss"
    />
  </div>
</template>

<script setup>
import { CleanHtml, DpButton, prefixClass } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'

const vCleanhtml = CleanHtml

const props = defineProps({
  message: {
    type: String,
    required: true,
  },
})

const storageKey = 'serverBannerDismissed'
const isVisible = ref(sessionStorage.getItem(storageKey) !== props.message)

const dismiss = () => {
  isVisible.value = false
  sessionStorage.setItem(storageKey, props.message)
}
</script>
