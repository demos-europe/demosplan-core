<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<!-- @improve check for a11y issues, see https://inclusive-components.design/notifications/ -->
<template>
  <div
    :class="prefixClass('c-notify')"
    :aria-live="liveState">
    <transition-group name="transition-slide-up">
      <dp-notify-message
        v-for="message in messages"
        :key="message.uid"
        :message="message"
        @dp-notify-remove="removeMessage"
        :role="messageRole" />
    </transition-group>
  </div>
</template>

<script>
import { mapMutations, mapState } from 'vuex'
import DpNotifyMessage from './DpNotifyMessage'
import { hasOwnProp } from 'demosplan-utils'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpNotifyContainer',

  components: {
    DpNotifyMessage
  },

  mixins: [prefixClassMixin],

  props: {
    /**
     * { confirm: [{
     *      message: String,
     *      linkUrl: String,
     *      linkText: String
     *    }, {
     *      message: String,
     *      linkUrl: String,
     *      linkText: String
     *    }]
     * }
     */
    notifications: {
      type: [Object, Array],
      required: false,
      default () {
        return []
      }
    }
  },

  data () {
    return {
      isVisible: false
    }
  },

  computed: {
    ...mapState('notify', ['messages']),

    liveState () {
      return (this.isVisible) ? 'polite' : 'off'
    },

    messageRole () {
      return (this.isVisible) ? 'message' : 'none'
    }
  },

  methods: {
    ...mapMutations('notify', ['add', 'remove']),

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

      document.addEventListener('visibilitychange', () => { this.isVisible = document.hidden })
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
