<template>
  <dp-modal
    ref="unlockModal"
    content-classes="w-1/4 px-2 pb-4"
  >
    <h3
      class="font-semibold mb-4 mt-2"
    >
      {{ Translator.trans('segment.unlock') }}
    </h3>
    <p class="mb-2">
      {{ Translator.trans('segment.lock.hint.admin') }}
    </p>
    <p class="mb-2">
      {{ Translator.trans('field.required.asterisk') }}
    </p>
    <dp-label
      class="mb-0 mt-4"
      :text="Translator.trans('place')"
      :hint="Translator.trans('place.set')"
      :bold="false"
      for="segmentUnlockPlace"
    />
    <dp-multiselect
      id="segmentUnlockPlace"
      class="mb-2"
      :allow-empty="false"
      label="name"
      :options="places"
      track-by="id"
      required
    />
    <dp-label
      class="mb-0 mt-4"
      :text="Translator.trans('assignee')"
      :hint="Translator.trans('assignee.assign')"
      :bold="false"
      for="segmentUnlockAssignee"
    />
    <dp-multiselect
      id="segmentUnlockAssignee"
      class="mb-6"
      label="name"
      :options="assignableUsers"
      track-by="id"
      required
    />
    <dp-button-row
      primary
      secondary
    />
  </dp-modal>
</template>

<script setup>
import { ref } from 'vue'
import { DpButtonRow, DpLabel, DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'

const { assignableUsers, places } = defineProps({
  assignableUsers: {
    type: Object,
    required: true,
  },

  places: {
    type: Object,
    required: true,
  },

  segment: {
    type: Object,
    required: true,
  },
})

const unlockModal = ref()

defineExpose({
  toggle: () => unlockModal.value.toggle(),
})

</script>

