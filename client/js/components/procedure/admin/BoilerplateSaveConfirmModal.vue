<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <dp-modal
    ref="modal"
    content-classes="w-1/3"
  >
    <template v-slot:header>
      <h3
        class="text-lg font-semibold"
        data-cy="boilerplateSaveConfirm:title"
        v-text="Translator.trans('boilerplate.usage.confirm.title', { count: countText })"
      />
    </template>

    <dp-inline-notification
      class="mt-2"
      data-cy="boilerplateSaveConfirm:hint"
      type="warning"
    >
      <span v-text="Translator.trans('boilerplate.usage.confirm.hint.before', { count: countText })" />
      <span
        class="font-semibold"
        v-text="title"
      />
      <span v-text="Translator.trans('boilerplate.usage.confirm.hint.after')" />
    </dp-inline-notification>

    <template v-slot:footer>
      <dp-button-row
        :primary-text="Translator.trans('boilerplate.usage.confirm.button')"
        data-cy="boilerplateSaveConfirm"
        primary
        secondary
        @primary-action="confirm"
        @secondary-action="toggle"
      />
    </template>
  </dp-modal>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { DpButtonRow, DpInlineNotification, DpModal } from '@demos-europe/demosplan-ui'

const props = defineProps<{
  // Number of segments that use the boilerplate
  count: number
  // Saved title of the boilerplate
  title: string
}>()

const emit = defineEmits<{
  confirm: []
}>()

// Translator expects string params
const countText = computed(() => String(props.count))

const modal = ref<{ toggle(): void } | null>(null)

const toggle = () => modal.value?.toggle()

const confirm = () => {
  toggle()
  emit('confirm')
}

defineExpose({ toggle })
</script>
