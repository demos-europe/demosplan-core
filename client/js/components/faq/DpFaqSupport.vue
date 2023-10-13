<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div
    id="scFaq"
    class="c-support-wrapper space-inset-m">
    <h2 class="font-normal">
      {{ Translator.trans('support.heading') }}
    </h2>
    <p class="mb-5">
      {{ Translator.trans('support.introduction') }}
    </p>
    <p>
      {{ Translator.trans('support.contact.advice') }}
    </p>
    <h3 class="mt-5">
      {{ Translator.trans('support.content') }}
    </h3>
    <ul
      class="mb-5"
      :class="contactLength === 1 ? '' : 'grid lg:grid-cols-3 gap-3'">
      <li
        v-for="contact in contacts"
        :v-key="contact.id"
        class="space-inset-m h-48 c-support-card"
        :class="contactLength === 1 ? 'lg:w-8/12' : ''">
        <dp-faq-support-card
            :title="contact.attributes.title"
            :email="contact.attributes.eMailAddress"
            :phone-number="contact.attributes.phoneNumber"
            :reachability="contact.attributes.text" />
      </li>
    </ul>
    <h3>
      {{ Translator.trans('support.technical') }}
    </h3>
    <div class="lg:w-8/12 space-inset-m pt-0 h-48 c-support-card">
      <dp-faq-support-card />
    </div>
  </div>
</template>
<script>

import { mapActions, mapState } from 'vuex'
import DpFaqSupportCard from './DpFaqSupportCard.vue'

export default {
  name: 'DpFaqSupport',
  components: { DpFaqSupportCard },

  data() {
    return {
      contactList: this.contacts,
      email: '',
      phoneNumber: '',
      reachability: '',
      title: ''
    }
  },

  computed: {
    ...mapState('customerContact', {
      contacts: 'items'
    }),

    contactLength () {
      return Object.entries(this.contacts).length
    }
  },

  methods: {
    ...mapActions('customerContact', {
      fetchContact: 'list',
    })
  },

  mounted () {
    this.fetchContact({
      fields: {
        CustomerContact: [
          'title',
          'phoneNumber',
          'text',
          'visible',
          'eMailAddress'
        ].join()
      }
    })
  }
}
</script>
