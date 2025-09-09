<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-inline-notification
    class="mt-3 mb-2"
    :message="success ? descriptionSuccess : descriptionError"
    :type="success ? 'confirm' : 'error'">
    <slot />
  </dp-inline-notification>
</template>

<script>
import { DpInlineNotification } from '@demos-europe/demosplan-ui'

export default {
  name: 'ActionStepperResponse',

  components: {
    DpInlineNotification
  },

  props: {
    descriptionSuccess: {
      required: true,
      type: String
    },

    descriptionError: {
      required: true,
      type: String
    },

    success: {
      type: Boolean,
      required: true
    }
  }
}
</script>
