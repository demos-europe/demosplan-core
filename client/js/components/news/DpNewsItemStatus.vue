<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-toggle
      :value="newsStatus"
      @input="e => $emit('statusChanged', e)" />
    <dp-contextual-help
      v-if="determinedToSwitch"
      class="u-ml-0_25"
      icon="clock"
      large
      :text="tooltipText" />
  </div>
</template>

<script>
import { DpContextualHelp, DpToggle } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpNewsItemStatus',

  components: {
    DpContextualHelp,
    DpToggle
  },

  props: {
    determinedToSwitch: {
      type: Boolean,
      required: true
    },

    newsStatus: {
      type: Boolean,
      required: true
    },

    switchDate: {
      type: [Number, String],
      required: true
    },

    switchState: {
      type: String,
      required: true
    }
  },

  emits: [
    'statusChanged'
  ],

  computed: {
    tooltipText () {
      return this.switchDate !== ''
        ? `${Translator.trans('phase.autoswitch.date')} ${(new Date(this.switchDate)).toLocaleDateString('de-DE')}<br>${Translator.trans('phase.autoswitch.value')} ${Translator.trans(this.switchState)}`
        : ''
    }
  }
}
</script>
