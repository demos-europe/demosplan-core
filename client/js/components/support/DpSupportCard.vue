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
      class="mb-3 font-semibold font-size-large break-words"
      v-text="title"
    />
    <a
      v-if="phoneNumber"
      class="mt-1 inline-block font-semibold font-size-large color--black break-words"
      :href="`tel:${phoneNumber}`"
    >
      <dp-icon
        class="inline-block"
        icon="phone"
      />
      {{ phoneNumber }}
    </a>
    <a
      v-if="email"
      class="mt-1 inline-block font-semibold font-size-large color--black break-words"
      :href="`mailto:${email}`"
    >
      <dp-icon
        class="inline-block"
        icon="mail"
      />
      {{ email }}
    </a>
    <template v-if="reachability.officeHours">
      <div
        v-if="reachability.service"
      >
        <h4
          class="mt-3 font-semibold break-words"
          v-text="reachability.service"
        />
        <div
          v-cleanhtml="reachability.officeHours"
          class="c-styled-html"
        />
        <span
          class="color--grey-light font-size-smaller break-words"
          v-text="reachability.exception"
        />
      </div>
      <div
        v-else
        v-cleanhtml="reachability.officeHours"
        class="mt-3 lg:mt-2 break-words c-styled-html"
      />
    </template>
  </section>
</template>
<script>

import { CleanHtml, DpIcon } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpSupportCard',

  components: {
    DpIcon,
  },

  directives: {
    cleanhtml: CleanHtml,
  },

  props: {
    email: {
      required: false,
      type: String,
      default: '',
    },

    phoneNumber: {
      required: false,
      type: String,
      default: '',
    },

    reachability: {
      required: false,
      type: Object,
      default: () => ({}),
    },

    title: {
      required: false,
      type: String,
      default: '',
    },
  },
}
</script>
