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
              data-cy="segmentRecommendationEmail:attachSegmentText"
            />
          </template>
          <dp-editor
            :toolbar-items="obscureOnlyToolbar"
            :value="segmentTextToSend"
            class="-mt-1"
            @input="value => segmentTextToSend = value"
            @transform-obscure-tag="value => segmentTextToSend = value"
          />
        </dp-accordion>
        <dp-accordion
          :title="Translator.trans('recommendation.text')"
          class="pt-2"
          compressed
        >
          <template v-slot:titlePrefix>
            <dp-checkbox
              id="attachRecommendation"
              v-model="attachRecommendation"
              :aria-label="Translator.trans('recommendation.text')"
              :disabled="!hasRecommendation"
              data-cy="segmentRecommendationEmail:attachRecommendation"
            />
          </template>
          <dp-editor
            :toolbar-items="obscureOnlyToolbar"
            :value="recommendationTextToSend"
            class="-mt-1"
            @input="value => recommendationTextToSend = value"
            @transform-obscure-tag="value => recommendationTextToSend = value"
          />
        </dp-accordion>
      </div>
      <dp-button-row
        :busy="isSending"
        :primary-text="Translator.trans('email.send')"
        :secondary-text="Translator.trans('abort')"
        class="mr-5 mt-4"
        data-cy="segmentRecommendationEmail"
        primary
        secondary
        @primary-action="onSendEmail"
        @secondary-action="onAbort"
      />
    </div>
  </div>
</template>

<script>
import { DpAccordion, DpButtonRow, DpCheckbox, DpEditor, DpInlineNotification, DpInput, dpRpc, DpTextArea } from '@demos-europe/demosplan-ui'
import { mapState } from 'vuex'
export default {
  name: 'DpSegmentRecommendationemail',

  components: {
    DpAccordion,
    DpButtonRow,
    DpCheckbox,
    DpEditor,
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
      emailCC: '',
      externId: '',
      isSending: false,
      isVisible: false,
      message: '',
      recipient: '',
      recommendationTextToSend: '',
      replyToEmail: this.currentUserEmail,
      segmentId: '',
      segmentTextToSend: '',
      subject: '',
    }
  },

  computed: {
    ...mapState('StatementSegment', {
      segments: 'items',
    }),

    /**
     * The backend does not append the segment content, it only forwards this body into the
     * mail template. The order matches what the hint above the checkboxes announces.
     */
    emailBody () {
      const parts = [this.message]

      if (this.attachSegmentText) {
        parts.push(this.segmentTextToSend)
      }

      if (this.attachRecommendation) {
        parts.push(this.recommendationTextToSend)
      }

      return parts.filter(part => part !== '').join('\n\n')
    },

    hasRecommendation () {
      return this.recommendationTextToSend !== ''
    },

    /**
     * Reduce the editor to what is needed here: obscuring content before sending it.
     * Undo and redo are always rendered, everything else is switched off — cutting
     * would remove content instead of making it unreadable.
     */
    obscureOnlyToolbar () {
      return {
        cut: false,
        fullscreenButton: false,
        listButtons: false,
        obscure: true,
        textDecoration: false,
      }
    },
  },

  methods: {
    onAbort () {
      this.isVisible = false
      this.$root.$emit('hide-slidebar')
    },

    onSendEmail () {
      this.isSending = true

      /*
       * The procedure is not part of the payload, the backend resolves it from the current
       * procedure context, so the third dpRpc argument (the JSON-RPC request id) is omitted.
       */
      return dpRpc('segment.email.sender', {
        body: this.emailBody,
        recipientMail: this.recipient,
        replyTo: this.replyToEmail,
        segmentIds: [this.segmentId],
        sendEmailCC: this.emailCC,
        subject: this.subject,
      })
        .then(() => {
          this.onAbort()
        })
        .finally(() => {
          this.isSending = false
        })
    },

    onShowEmailForm (segmentId, externId) {
      this.resetForm()

      this.isVisible = true
      this.externId = externId
      this.segmentId = segmentId
      this.subject = Translator.trans('segment.send.via.email.subject.default', {
        externId,
        procedureName: this.procedureName,
      })

      /*
       * Work on copies: obscuring is meant for this email only and must not
       * change the segment itself.
       */
      const segment = this.segments[segmentId]

      this.segmentTextToSend = segment?.attributes.text ?? ''
      this.recommendationTextToSend = segment?.attributes.recommendation ?? ''
      console.log(this.segmentId, this.$store.state.StatementSegment.items)
    },

    onVersionHistory () {
      this.isVisible = false
    },

    /**
     * Called whenever the form is opened, so that nothing carries over from a previously
     * viewed segment. Fields derived from the segment are set by the caller afterwards.
     */
    resetForm () {
      this.attachRecommendation = false
      this.attachSegmentText = true
      this.emailCC = ''
      this.message = ''
      this.recipient = ''
      this.replyToEmail = this.currentUserEmail
    },
  },

  mounted () {
    this.$root.$on('segment:send-via-mail', this.onShowEmailForm)
    this.$root.$on('version:history', this.onVersionHistory)
  },

  beforeUnmount () {
    this.$root.$off('segment:send-via-mail', this.onShowEmailForm)
    this.$root.$off('version:history', this.onVersionHistory)
  },
}

</script>
