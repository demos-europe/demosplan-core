<template>
  <dp-modal
    ref="unlockModal"
    content-classes="w-1/4 px-4 pb-4"
  >
    <template v-slot:header>
      <span>{{ Translator.trans('segment.unlock') }}</span>
    </template>
    <dp-label
      class="mb-0"
      :text="'Bearbeitungsschritt'"
      :hint="'Berbeitungsschritt festlegen'"
      :bold="false"
      for="segmentUnlockPlace"
    />
    <dp-multiselect
      id="segmentUnlockPlace"
      class="mb-1"
      :allow-empty="false"
      label="name"
      :options="places"
      track-by="id"
      required
    />
    <dp-label
      class="mb-0"
      :text="'Bearbeiter*in'"
      :hint="'Bearbeiter*in zuweisen'"
      :bold="false"
      for="segmentUnlockAssignee"
    />
    <dp-multiselect
      id="segmentUnlockAssignee"
      label="name"
      :options="assignableUsers"
      track-by="id"
      required
    />
  </dp-modal>
</template>

<script setup>
import { ref } from 'vue'
import { DpLabel, DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'

const props = defineProps({
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

