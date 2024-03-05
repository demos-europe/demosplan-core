<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <template v-if="status === 'processing' || status === 'unknown'">
      <div class="u-mt-2 u-mb-2 text-center">
        <div class="progress-icon-wrapper">
          <i
            class="fa fa-hourglass-half color--white"
            aria-hidden="true" />
        </div>
        <div class="u-mt">
          {{ Translator.trans('statement.processing.please.wait') }}
          <p
            v-if="processingTime > 0"
            class="u-mt">
            {{ Translator.trans('processing.time.seconds', { time: processingTime }) }}
          </p>
        </div>
      </div>

      <dp-progress-bar
        :label="label"
        class="u-1-of-2"
        indeterminate />
    </template>

    <div
      v-else-if="status === 'segmented'"
      class="u-mt-2 u-mb-2 text-center">
      <div class="progress-icon-wrapper">
        <i
          class="fa fa-check color--white"
          aria-hidden="true" />
      </div>
      <div class="u-mt">
        {{ Translator.trans('statement.ready.segmentation') }}
      </div>

      <dp-button
        class="u-mt-2"
        @click="$emit('continue')"
        :text="Translator.trans('split.now')" />
    </div>
  </div>
</template>
<script>
import { DpButton, DpProgressBar } from '@demos-europe/demosplan-ui'

export default {
  name: 'ProcessingPage',

  components: {
    DpButton,
    DpProgressBar
  },

  props: {
    label: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Possible values:
     * - unknown: waiting for status information
     * - processing: statement is being processed
     * - segmented: statement was segmented by AI and is ready to be segmented by the user
     * - inUserSegmentation: this component is not displayed (statement is being manually segmented by the user)
     */
    status: {
      type: String,
      required: false,
      default: 'unknown'
    },

    processingTime: {
      type: Number,
      required: false,
      default: 0
    }
  }
}
</script>
