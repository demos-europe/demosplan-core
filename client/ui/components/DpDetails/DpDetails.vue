<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <details
    class="o-details space-stack-xs"
    role="group">
    <summary
      :aria-expanded="isOpen"
      class="o-details__trigger o-link--default cursor--pointer display--inline-block"
      role="button"
      @click="isOpen = !isOpen">
      <i
        class="fa width-s text--center"
        :class="{'fa-caret-down': isOpen, 'fa-caret-right': !isOpen}"
        aria-hidden="true" />
      <strong v-cleanhtml="summary" />
    </summary>
    <div>
      <slot />
    </div>
  </details>
</template>

<script>
import { CleanHtml } from '../../directives'

export default {
  name: 'DpDetails',

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    /**
     * The short summary of the content, which is also used as a trigger.
     */
    summary: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      isOpen: false
    }
  }
}
</script>
