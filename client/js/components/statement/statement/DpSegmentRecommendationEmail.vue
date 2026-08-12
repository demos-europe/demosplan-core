<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Wrapper component for sending segments and recommendations via email to an external recipient.
    It prepares and displays the email-related content and delegates detailed rendering/interaction to child components.
   -->
</documentation>

<template>
  <div
    v-if="isVisible"
  >
    <h2 class="pb-4">
      {{ Translator.trans('segment.send.via.email') }}
    </h2>
    <div class="-ml-5 py-5 border-y border-neutral-light-3">
      <div class="ml-5 mr-5 border-b border-neutral-light-3">
        <dp-inline-notification
          class="mb-4"
          :message="Translator.trans('segment.send.via.email.explanation')"
          type="info"
        />
        <dp-input
          id="recipient"
          v-model="recipient"
          :label="{
            text: Translator.trans('recipient'),
            hint: Translator.trans('segment.send.via.email.recipient.hint')
          }"
          class="mb-4"
          required
          type="email"
        />
        <dp-input
          id="emailCC"
          v-model="emailCC"
          :label="{
            text: Translator.trans('cc'),
            hint: Translator.trans('explanation.email.cc')
          }"
          class="mb-4"
          data-cy="segmentRecommendationEmail:emailCC"
          type="email"
        />
        <dp-input
          id="replyToEmail"
          v-model="replyToEmail"
          :label="{
            text: Translator.trans('segment.send.via.email.reply.to'),
            hint: Translator.trans('segment.send.via.email.reply.to.hint')
          }"
          class="mb-4"
          data-cy="segmentRecommendationEmail:replyToEmail"
          type="email"
        />
        <dp-input
          id="subject"
          v-model="subject"
          :label="{
            text: Translator.trans('subject')
          }"
          class="mb-4"
          required
        />
        <dp-text-area
          id="message"
          v-model="message"
          :hint="Translator.trans('segment.send.via.email.message.hint')"
          :label="Translator.trans('message')"
          class="mb-4"
        />
      </div>
      <fieldset
        aria-describedby="attachmentsHint"
        class="ml-5 mr-5 mt-4 pt-2"
      >
        <legend class="font-semibold">
          {{ Translator.trans('segment.send.via.mail.add.attachments') }}
        </legend>
        <p id="attachmentsHint">
          {{ Translator.trans('segment.send.via.mail.add.attachments.hint') }}
        </p>
        <dp-accordion
          :title="Translator.trans('segment.text.attach')"
          class="pt-2"
          compressed
        >
          <template v-slot:titlePrefix>
            <dp-checkbox
              id="attachSegmentText"
              v-model="attachSegmentText"
              :label="{
                text: Translator.trans('segment.text'),
                hide: true,
              }"
              class="h-4 w-4"
              data-cy="segmentRecommendationEmail:attachSegmentText"
            />
          </template>
          <dp-editor
            :aria-label="Translator.trans('segment.text')"
            :toolbar-items="obscureOnlyToolbar"
            :value="segmentTextToSend"
            class="-mt-1"
            obscure-only
            @input="value => segmentTextToSend = value"
            @transform-obscure-tag="value => segmentTextToSend = value"
          />
        </dp-accordion>
        <dp-accordion
          :title="Translator.trans('recommendation.text.attach')"
          class="pt-2"
          compressed
        >
          <template v-slot:titlePrefix>
            <dp-checkbox
              id="attachRecommendation"
              v-model="attachRecommendation"
              :disabled="!hasRecommendation"
              :label="{
                text: Translator.trans('recommendation.text'),
                hide: true,
              }"
              class="h-4 w-4"
              data-cy="segmentRecommendationEmail:attachRecommendation"
            />
          </template>
          <dp-editor
            :aria-label="Translator.trans('recommendation.text')"
            :toolbar-items="obscureOnlyToolbar"
            :value="recommendationTextToSend"
            class="-mt-1"
            obscure-only
            @input="value => recommendationTextToSend = value"
            @transform-obscure-tag="value => recommendationTextToSend = value"
          />
        </dp-accordion>
      </fieldset>
    </div>
    <dp-button-row
      :busy="isSending"
      :primary-text="Translator.trans('email.send')"
      :secondary-text="Translator.trans('abort')"
      class="mr-5 my-5"
      data-cy="segmentRecommendationEmail"
      primary
      secondary
      @primary-action="onSendEmail"
      @secondary-action="onAbort"
    />
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { DpAccordion, DpButtonRow, DpCheckbox, DpEditor, DpInlineNotification, DpInput, dpRpc, DpTextArea } from '@demos-europe/demosplan-ui'
import { useRootEventBus } from '@DpJs/composables/useRootEventBus'
import { useStore } from 'vuex'

const props = defineProps({
  currentUserEmail: {
    type: String,
    required: false,
    default: '',
  },

  procedureName: {
    type: String,
    required: false,
    default: '',
  },
})

const store = useStore()
// Only `hide-slidebar` still goes through the bus - it is the API DpSlidebar listens on.
const { emitRootEvent } = useRootEventBus()

const attachRecommendation = ref(false)
const attachSegmentText = ref(true)
const emailCC = ref('')
const externId = ref('')
const isSending = ref(false)
const isVisible = ref(false)
const message = ref('')
const recipient = ref('')
const recommendationTextToSend = ref('')
const replyToEmail = ref(props.currentUserEmail)
const segmentId = ref('')
const segmentTextToSend = ref('')
const subject = ref('')

const segments = computed(() => store.state.StatementSegment.items)
const slidebar = computed(() => store.state.SegmentSlidebar.slidebar)

/**
 * The backend does not append the segment content, it only forwards this body into the
 * mail template. The order matches what the hint above the checkboxes announces.
 */
const emailBody = computed(() => {
  const parts = [message.value]

  if (attachSegmentText.value) {
    parts.push(segmentTextToSend.value)
  }

  if (attachRecommendation.value) {
    parts.push(recommendationTextToSend.value)
  }

  return parts.filter(part => part !== '').join('\n\n')
})

const hasRecommendation = computed(() => recommendationTextToSend.value !== '')

/**
 * Reduce the editor to what is needed here: obscuring content before sending it.
 * Undo and redo are always rendered, everything else is switched off — cutting
 * would remove content instead of making it unreadable.
 */
const obscureOnlyToolbar = computed(() => ({
  cut: false,
  fullscreenButton: false,
  listButtons: false,
  obscure: true,
  textDecoration: false,
}))

const onAbort = () => {
  isVisible.value = false
  emitRootEvent('hide-slidebar')
}

const onSendEmail = () => {
  isSending.value = true

  /*
   * The procedure is not part of the payload, the backend resolves it from the current
   * procedure context, so the third dpRpc argument (the JSON-RPC request id) is omitted.
   */
  return dpRpc('segment.email.sender', {
    body: emailBody.value,
    recipientMail: recipient.value,
    replyTo: replyToEmail.value,
    segmentIds: [segmentId.value],
    sendEmailCC: emailCC.value,
    subject: subject.value,
  })
    .then(() => {
      onAbort()
    })
    .finally(() => {
      isSending.value = false
    })
}

/**
 * Called whenever the form is opened, so that nothing carries over from a previously
 * viewed segment. Fields derived from the segment are set by the caller afterwards.
 */
const resetForm = () => {
  attachRecommendation.value = false
  attachSegmentText.value = true
  emailCC.value = ''
  message.value = ''
  recipient.value = ''
  replyToEmail.value = props.currentUserEmail
}

const onShowEmailForm = (id, segmentExternId) => {
  resetForm()

  isVisible.value = true
  externId.value = segmentExternId
  segmentId.value = id
  subject.value = Translator.trans('segment.send.via.email.subject.default', {
    externId: segmentExternId,
    procedureName: props.procedureName,
  })

  /*
   * Work on copies: obscuring is meant for this email only and must not
   * change the segment itself.
   */
  const segment = segments.value[id]

  segmentTextToSend.value = segment?.attributes.text ?? ''
  recommendationTextToSend.value = segment?.attributes.recommendation ?? ''
}

/*
 * On the procedure-wide list this component is mounted from the twig template, unrelated to the
 * list that triggers it, so the store carries which segment to show instead of a root event.
 */
watch(
  () => [slidebar.value.showTab, slidebar.value.segmentId],
  ([showTab, segmentId]) => {
    if (showTab !== 'sendViaMail') {
      isVisible.value = false

      return
    }

    onShowEmailForm(segmentId, slidebar.value.externId)
  },
)
</script>
