<template>
  <dp-modal
    ref="unlockModal"
    content-classes="w-1/4 px-2 pb-4"
  >
    <h3 class="font-bold text-lg border-b border-neutral pb-4 mb-4 mt-2">
      {{ Translator.trans('segment.unlock') }}
    </h3>
    <dp-inline-notification
      :message="Translator.trans('segment.lock.hint.admin')"
      class="mb-4 mt-2"
      dismissible-key="segmentUnlockModalHint"
      type="info"
      dismissible
    />
    <div data-dp-validate="unlockSegment">
      <dp-label
        :text="Translator.trans('place')"
        class="mb-0 mt-4"
        for="segmentUnlockPlace"
        required
      />
      <dp-multiselect
        id="segmentUnlockPlace"
        v-model="localPlace"
        :allow-empty="false"
        :options="places"
        class="mb-2"
        label="name"
        track-by="id"
        required
      />
      <dp-label
        :text="Translator.trans('assignee')"
        class="mb-0 mt-4"
        for="segmentUnlockAssignee"
      />
      <dp-multiselect
        id="segmentUnlockAssignee"
        v-model="localAssignee"
        :allow-empty="false"
        :options="assignableUsers"
        class="mb-6"
        label="name"
        track-by="id"
      />
      <dp-button-row
        primary
        secondary
        @primary-action="dpValidateAction('unlockSegment', save, false)"
        @secondary-action="toggle"
      />
    </div>
  </dp-modal>
</template>

<script>
import { dpValidateMixin } from '@demos-europe/demosplan-ui'

export default {
  mixins: [dpValidateMixin],
}
</script>

<script setup>
// eslint-disable-next-line sort-imports -- second <script> block above imports dpValidateMixin (single-member) from the same module
import { DpButtonRow, DpInlineNotification, DpLabel, DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'
import { ref } from 'vue'

const { assignableUsers, places } = defineProps({
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

// Default the assignee to "not assigned"
const localAssignee = ref(assignableUsers.find(user => user.id === 'noAssigneeId'))
const localPlace = ref(null)
const unlockModal = ref()

const toggle = () => unlockModal.value.toggle()

const save = () => {
  emit('unlock', { assignee: localAssignee.value, place: localPlace.value })
  toggle()
}

defineExpose({ toggle })
</script>
