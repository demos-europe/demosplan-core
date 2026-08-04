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
    <div class="-ml-5 border-y border-neutral-light-3">
      <div class="ml-5 mr-5 pt-4 border-b border-neutral-light-3">
        <dp-inline-notification
          class="mb-4"
          :message="Translator.trans('segment.send.via.email.explanation')"
          type="info"
        />
        <dp-input
          id="recipient"
          v-model="recipient"
          :label="{
            text: Translator.trans('recipients'),
            hint: Translator.trans('segment.send.via.email.recipient.hint')
          }"
          class="mb-4"
          required
        />
        <dp-input
          id="replyToEmail"
          v-model="replyToEmail"
          :label="{
            text: Translator.trans('segment.send.via.email.reply.to'),
            hint: Translator.trans('segment.send.via.email.reply.to.hint')
          }"
          class="mb-4"
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
      <div class="ml-5 mr-5">
        <p class="font-semibold pt-4">
          {{ Translator.trans('segment.send.via.mail.add.attachments') }}
        </p>
        <p>
          {{ Translator.trans('segment.send.via.mail.add.attachments.hint') }}
        </p>
        <dp-accordion
          :title="Translator.trans('segment.text')"
          class="pt-2"
          compressed
        >
          <template v-slot:titlePrefix>
            <dp-checkbox
              id="attachSegmentText"
              v-model="attachSegmentText"
              :aria-label="Translator.trans('segment.text')"
              class=""
              data-cy="segmentRecommendationEmail:attachSegmentText"
            />
          </template>
          Test
        </dp-accordion>
        <dp-accordion
          :title="Translator.trans('recommendation.text')"
          class="py-2"
          compressed
        >
          <template v-slot:titlePrefix>
            <dp-checkbox
              id="attachRecommendation"
              v-model="attachRecommendation"
              :aria-label="Translator.trans('recommendation.text')"
              data-cy="segmentRecommendationEmail:attachRecommendation"
            />
          </template>
          Test
        </dp-accordion>
      </div>
    </div>
  </div>
</template>

<script>
import { DpAccordion, DpCheckbox, DpInlineNotification, DpInput, DpTextArea } from '@demos-europe/demosplan-ui'
export default {
  name: 'DpSegmentRecommendationemail',

  components: {
    DpAccordion,
    DpCheckbox,
    DpInlineNotification,
    DpInput,
    DpTextArea,
  },

  props: {
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
  },

  data () {
    return {
      attachRecommendation: false,
      attachSegmentText: true,
      externId: '',
      isVisible: false,
      message: '',
      recipient: '',
      replyToEmail: this.currentUserEmail,
      subject: '',
    }
  },

  computed: {
  },

  methods: {
    onSendViaMail (segmentId, entityType, externId) {
      this.isVisible = true
      this.externId = externId
      this.subject = Translator.trans('segment.send.via.email.subject.default', {
        externId,
        procedureName: this.procedureName,
      })
    },

    onVersionHistory () {
      this.isVisible = false
    },
  },

  mounted () {
    this.$root.$on('segment:send-via-mail', this.onSendViaMail)
    this.$root.$on('version:history', this.onVersionHistory)
  },

  beforeUnmount () {
    this.$root.$off('segment:send-via-mail', this.onSendViaMail)
    this.$root.$off('version:history', this.onVersionHistory)
  },
}

</script>
