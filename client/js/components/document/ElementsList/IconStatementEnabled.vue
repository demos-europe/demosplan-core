<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <i
      class="fa cursor-help"
      :class="{
        'fa-microphone color-message-success-fill': enabled,
        'fa-microphone-slash color-message-severe-fill': enabled === false
      }"
      aria-hidden="true"
      v-tooltip="text"
    />
    <span
      class="sr-only"
      v-text="text" />
  </div>
</template>

<script>
export default {
  name: 'IconStatementEnabled',

  props: {
    enabled: {
      type: Boolean,
      required: true
    }
  },

  computed: {
    text () {
      return Translator.trans(this.enabled ? 'statement.possible' : 'statement.not.possible')
    }
  }
}
</script>
