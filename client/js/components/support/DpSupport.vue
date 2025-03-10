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
    <p v-if="hasPermission('feature_customer_support_technical_read')">
      {{ Translator.trans('support.contact.advice') }}
    </p>
    <h3
      v-if="Object.keys(contacts).length > 0"
      class="u-mt-0_75">
      {{ Translator.trans('support') }}
    </h3>
    <ul
      class="u-mb-0_75"
      :class="{ 'grid lg:grid-cols-3 gap-3': Object.keys(contacts).length !== 1 }">
      <li
        v-for="contact in contacts"
        :key="contact.id"
        class="bg-color--white"
        :class="{ 'lg:w-8/12': Object.keys(contacts).length === 1 }">
        <dp-support-card
          :title="contact.attributes.title"
          :email="contact.attributes.eMailAddress"
          :phone-number="contact.attributes.phoneNumber"
          :reachability="{ officeHours: contact.attributes.text }" />
      </li>
    </ul>
    <h3
      v-if="hasPermission('feature_customer_support_technical_read')">
      {{ Translator.trans('support.technical') }}
    </h3>
    <div
      class="lg:w-8/12"
      v-if="hasPermission('feature_customer_support_technical_read')">
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
import { hasPermission } from '@demos-europe/demosplan-ui'

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
    ...mapState('CustomerContact', {
      contacts: 'items'
    })
  },

  methods: {
    ...mapActions('CustomerContact', {
      fetchContacts: 'list'
    }),

    fetchCustomerContactsData () {
      let params = {
        fields: {
          CustomerContact: [
            'title',
            'phoneNumber',
            'text',
            'eMailAddress'
          ].join()
        }
      }

      if (hasPermission('feature_customer_support_contact_administration')) {
        params = {
          ...params,
          filter: {
            onlyVisible: {
              condition: {
                path: 'visible',
                value: 1
              }
            }
          }
        }
      }

      this.fetchContacts(params)
    }
  },

  mounted () {
    this.fetchCustomerContactsData()
  }
}
</script>
