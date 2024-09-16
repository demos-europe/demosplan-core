<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<!-- @improve check for a11y issues, see https://inclusive-components.design/notifications/ -->
<template>
  <div
    :class="prefixClass('c-notify')"
    :aria-live="liveState">
    <transition-group
      name="transition-slide-up"
      tag="span">
      <dp-notification
        v-for="message in messages"
        :key="message.uid"
        :message="message"
        :role="messageRole"
        @dp-notify-remove="removeMessage" />
    </transition-group>
  </div>
</template>

<script>
import { DpNotification, hasOwnProp, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { mapMutations, mapState } from 'vuex'

export default {
  name: 'NotifyContainer',

  components: {
    DpNotification
  },

  mixins: [prefixClassMixin],

  props: {
    notifications: {
      type: [Object, Array],
      required: false,
      default: () => ([])
    }
  },

  data () {
    return {
      isVisible: true
    }
  },

  computed: {
    ...mapState('Notify', ['messages']),

    liveState () {
      return (this.isVisible) ? 'polite' : 'off'
    },

    messageRole () {
      return (this.isVisible) ? 'status' : 'none'
    }
  },

  methods: {
    ...mapMutations('Notify', ['add', 'remove']),

    init () {
      for (const type in this.notifications) {
        if (hasOwnProp(this.notifications, type)) {
          const messages = this.notifications[type]
          let i = 0
          const l = messages.length
          let message
          for (; i < l; i++) {
            message = messages[i]
            // Support legacy messages
            if (typeof message === 'string') {
              message = { message: message }
            }
            this.add({
              type,
              text: message.message || '',
              linkUrl: message.linkUrl || '',
              linkText: message.linkText || ''
            })
          }
        }
      }
      document.addEventListener('visibilitychange', () => { this.isVisible = !document.hidden })
    },

    removeMessage (message) {
      this.remove(message)
    }
  },

  created () {
    this.init()
  }
}
</script>
