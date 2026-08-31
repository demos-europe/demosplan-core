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
  >
    <!-- message is server-controlled markdown (SERVER_BANNER.md via ServerBannerLoader), never user input -->
    <div
      v-html="message"
    />
    <dp-button
      :class="prefixClass('absolute top-2 right-2')"
      :text="Translator.trans('close')"
      hide-text
      icon="x"
      variant="subtle"
      @click="dismiss"
    />
  </div>
</template>

<script setup>
import { DpButton, prefixClass } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'

defineProps({
  message: {
    type: String,
    required: true,
  },
})

const storageKey = 'serverBannerDismissed'
const isVisible = ref(sessionStorage.getItem(storageKey) === null)

const dismiss = () => {
  isVisible.value = false
  sessionStorage.setItem(storageKey, '1')
}
</script>
