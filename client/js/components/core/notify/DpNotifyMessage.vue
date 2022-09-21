<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    :class="prefixClass('c-notify__message ' + messageClass)">
    <i
      :class="prefixClass('c-notify__closer fa fa-times-circle cursor--pointer')"
      aria-hidden="true"
      @click.stop.prevent="hide" />

    <div :class="prefixClass('cf')">
      <i
        :class="prefixClass('fa u-mt-0_125 u-mr-0_25 float--left ' + messageIcon)" />
      <div :class="prefixClass('u-ml')">
        {{ message.text }}
        <a
          v-if="message.linkUrl"
          :class="prefixClass('c-notify__link u-mt-0_25')"
          :href="message.linkUrl"
          data-cy="messageLink">
          {{ message.linkText || message.linkUrl }}
        </a>
      </div>
    </div>
  </div>
</template>

<script>
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpNotifyMessage',

  mixins: [prefixClassMixin],

  props: {
    /**
     * A single notification message object containing type, text(, linkUrl, linkText)
     */
    message: {
      type: Object,
      required: true
    },
    hideTimer: {
      type: Number,
      required: false,
      default: 20000
    }
  },

  data () {
    return {
      messageId: '',
      icons: {
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle',
        error: 'fa-exclamation-circle',
        confirm: 'fa-check-circle',
        dev: 'fa-info-circle'
      }
    }
  },

  computed: {
    messageClass () {
      return 'c-notify__message--' + this.message.type
    },

    messageIcon () {
      return this.icons[this.message.type]
    }
  },

  methods: {
    hide () {
      this.$emit('dp-notify-remove', this.message)
    }
  },

  mounted () {
    if (this.message.type !== 'error') {
      setTimeout(() => {
        this.hide()
      }, this.hideTimer)
    }
  }
}
</script>
