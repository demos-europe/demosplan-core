<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div class="space-inset-m bg-color--blue-light-3">
    <h2 class="font-normal color--black">
      {{ Translator.trans('support.heading') }}
    </h2>
    <p class="u-mt-0_75">
      {{ Translator.trans('support.introduction') }}
    </p>
    <p>
      {{ Translator.trans('support.contact.advice') }}
    </p>
    <h3
      v-if="contacts.length > 0"
      class="u-mt-0_75">
      {{ Translator.trans('support') }}
    </h3>
    <ul
      class="u-mb-0_75"
      :class="{ 'grid lg:grid-cols-3 gap-3': visibleContacts.length !== 1 }">
      <li
        v-for="contact in visibleContacts"
        :key="contact.id"
        class="space-inset-m bg-color--white"
        :class="{ 'lg:w-8/12': visibleContacts.length === 1 }">
        <dp-support-card
          :title="contact.attributes.title"
          :email="contact.attributes.eMailAddress"
          :phone-number="contact.attributes.phoneNumber"
          :reachability="{ officeHours: contact.attributes.text }" />
      </li>
    </ul>
    <h3>
      {{ Translator.trans('support.technical') }}
    </h3>
    <div class="lg:w-8/12 space-inset-m bg-color--white">
      <dp-support-card
        :phone-number="Translator.trans('support.contact.number')"
        :reachability="{
          service: Translator.trans('support.contact.service'),
          officeHours: Translator.trans('support.contact.office_hours'),
          exception: Translator.trans('support.contact.exception')
        }" />
    </div>
  </div>
</template>
<script>

import { mapActions, mapState } from 'vuex'
import DpSupportCard from './DpSupportCard'

export default {
  name: 'DpSupport',

  components: { DpSupportCard },

  data () {
    return {
      contactList: this.contacts,
      email: '',
      phoneNumber: '',
      reachability: {},
      title: ''
    }
  },

  computed: {
    ...mapState('customerContact', {
      contacts: 'items'
    }),

    visibleContacts () {
      const contacts = []
      Object.values(this.contacts).forEach(contact => {
        if (contact.attributes.visible !== false) {
          contacts.push(contact)
        }
      })

      return contacts
    }
  },

  methods: {
    ...mapActions('customerContact', {
      fetchContacts: 'list'
    })
  },

  mounted () {
    this.fetchContacts({
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
