<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <section class="c-support__wrapper">
    <h4
      v-if="title"
      class="font-semibold font-size-large"
      v-text="title" />
    <a
      v-if="phoneNumber"
      class="u-mt-0_75 inline-block font-semibold font-size-large"
      :href="`tel:${phoneNumber}`">
      <dp-icon
        class="inline-block"
        icon="phone" />
      {{ phoneNumber }}
    </a>
    <p
      v-if="email"
      v-text="email" />
    <div
      v-if="reachability.service"
      class="u-pb-0_75">
      <h4
        class="u-mt-0_75 font-semibold"
        v-text="reachability.service" />
      <p v-cleanhtml="reachability.officeHours" />
      <span
        class="color--grey-light font-size-smaller"
        v-text="reachability.exception" />
    </div>
    <div
      v-else
      v-cleanhtml="reachability.officeHours"
      class="u-mt-0_75 lg:mt-2">
    </div>
  </section>
</template>
<script>

import { CleanHtml, DpIcon } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSupportCard',

  components: {
    DpIcon
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    email: {
      required: false,
      type: String,
      default: ''
    },

    phoneNumber: {
      required: false,
      type: String,
      default: ''
    },

    reachability: {
      required: false,
      type: Object,
      default: () => ({})
    },

    title: {
      required: false,
      type: String,
      default: ''
    }
  }
}
</script>
