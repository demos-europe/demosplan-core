<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    v-if="isDismissed === false"
    :class="`flash flash-${type} flex`">
    <i
      class="fa u-pr-0_25 line-height--1_4"
      :class="iconClasses[type]"
      aria-hidden="true" />
    <div class="space-stack-xs">
      <div
        v-if="message"
        v-html="message" />
      <slot />
      <button
        v-if="dismissible"
        class="btn--blank o-link--default weight--bold"
        v-text="Translator.trans('hint.dismiss')"
        @click="dismiss" />
    </div>
  </div>
  <div
    v-else
    class="cf">
    <button
      :aria-label="Translator.trans('hint.show')"
      class="btn--blank color--grey float--right"
      @click="show">
      <dp-icon
        icon="info"
        aria-hidden="true" />
    </button>
  </div>
</template>

<script>
import { DpIcon } from 'demosplan-ui/components'
import lscache from 'lscache'

export default {
  name: 'DpInlineNotification',

  components: {
    DpIcon
  },

  props: {
    /**
     * A notification may be too prominent if permanently visible. In that case it can be dismissed.
     * A small icon will take the place of the notification to bring it back if needed.
     */
    dismissible: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * If set, the dismissed state will be preserved via localStorage.
     */
    dismissibleKey: {
      type: String,
      required: false,
      default: null
    },

    message: {
      type: String,
      required: false,
      default: null
    },

    type: {
      type: String,
      required: false,
      default: 'error'
    }
  },

  data () {
    return {
      iconClasses: {
        confirm: 'fa-check',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle',
        warning: 'fa-exclamation-triangle'
      },
      isDismissed: true
    }
  },

  methods: {
    dismiss () {
      this.isDismissed = true
      this.dismissibleKey && lscache.set(this.dismissibleKey, Date.now())
    },

    show () {
      this.isDismissed = false
      this.dismissibleKey && lscache.remove(this.dismissibleKey)
    }
  },

  mounted () {
    this.isDismissed = (this.dismissible && this.dismissibleKey) ? !!lscache.get(this.dismissibleKey) : false
  }
}
</script>
