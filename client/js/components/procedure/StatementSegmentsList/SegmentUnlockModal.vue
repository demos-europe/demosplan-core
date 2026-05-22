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
      v-model="localPlace"
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
      v-model="localAssignee"
      class="mb-6"
      :allow-empty="false"
      label="name"
      :options="assignableUsers"
      track-by="id"
      required
    />
    <dp-button-row
      primary
      secondary
      @primary-action="save"
      @secondary-action="toggle"
    />
  </dp-modal>
</template>

<script setup>
import { DpButtonRow, DpLabel, DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'

defineProps({
  assignableUsers: {
    type: Object,
    required: true,
  },

  places: {
    type: Object,
    required: true,
  },
})

const emit = defineEmits(['unlock'])

defineExpose({ toggle })

const localAssignee = ref(null)
const localPlace = ref(null)
const unlockModal = ref()

const save = () => {
  emit('unlock', { assignee: localAssignee.value, place: localPlace.value })
  toggle()
}
function toggle () { unlockModal.value.toggle() }

</script>

