<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    :class="isFullscreen ? 'fixed bottom-3 left-0 right-0 mx-2' : 'sticky bottom-0 left-0 right-0'"
    class="h-[16px] z-[11] relative bg-neutral-light-4 mt-[10px]"
  >
    <div
      :aria-controls="controls"
      :aria-valuenow="scrollPercent"
      :style="thumbStyle"
      aria-valuemax="100"
      aria-valuemin="0"
      class="absolute inset-y-[2px] rounded-full bg-neutral-light-2 cursor-pointer hover:bg-interactive active:bg-interactive-hover"
      role="scrollbar"
      tabindex="0"
      @mousedown="emit('thumbMousedown', $event)"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * Custom scrollbar shown below a scrollable table. To be used together with tableScrollbarMixin,
 * which provides scrollPercent and thumbStyle and handles the thumb dragging.
 */
defineProps<{
  controls: string
  isFullscreen: boolean
  scrollPercent: number
  thumbStyle: Record<string, string>
}>()

const emit = defineEmits<{
  thumbMousedown: [event: MouseEvent]
}>()
</script>
