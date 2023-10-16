<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div
    class="c-support space-inset-m color--black bg-color--blue-light-3">
    <h2 class="font-normal color--black">
      {{ Translator.trans('support.heading') }}
    </h2>
    <p class="u-mt-0_75">
      {{ Translator.trans('support.introduction') }}
    </p>
    <p>
      {{ Translator.trans('support.contact.advice') }}
    </p>
    <h3 class="u-mt-0_75">
      {{ Translator.trans('support.content') }}
    </h3>
    <ul
      class="u-mb-0_75"
      :class="contactLength === 1 ? '' : 'grid lg:grid-cols-3 gap-3'">
      <li
        v-for="contact in contacts"
        :key="contact.id"
        class="space-inset-m c-support__card color--black bg-color--white"
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
    <div class="lg:w-8/12 space-inset-m u-pv-0 c-support__card color--black bg-color--white">
      <dp-faq-support-card />
    </div>
  </div>
</template>
<script>

import { mapActions, mapState } from 'vuex'
import DpFaqSupportCard from './DpFaqSupportCard'

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
      fetchContact: 'list'
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
