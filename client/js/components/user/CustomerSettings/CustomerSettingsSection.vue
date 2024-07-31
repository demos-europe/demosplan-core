<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Displays a collapsible section of content wrapped by a box with rounded corners. -->
</documentation>

<template>
  <fieldset>
    <legend
      class="sr-only"
      v-text="title" />
    <dp-accordion
      :data-cy="dataCy !== '' ? dataCy : false"
      :is-open="isOpen"
      :title="title">
      <div class="o-box--dark u-p">
        <slot />
      </div>
    </dp-accordion>
  </fieldset>
</template>

<script>
import { DpAccordion } from '@demos-europe/demosplan-ui'

export default {
  name: 'CustomerSettingsSection',

  components: {
    DpAccordion
  },

  props: {
    dataCy: {
      type: String,
      required: false,
      default: ''
    },

    isOpen: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * The section title is used as accordion trigger. It should provide a concise but unique term or group of words.
     */
    title: {
      required: true,
      type: String
    }
  }
}
</script>
