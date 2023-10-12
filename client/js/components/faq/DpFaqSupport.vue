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
    <section class="mb-5">
      <p class="mb-5">
        {{ Translator.trans('support.introduction') }}
      </p>
      <p>
        Sie haben eine Frage zur Technik? Dann hilft Ihnen unser technischer Support gerne weiter.
      </p>
        <h3 class="font-size-h4 mt-5">
          {{ Translator.trans('support.content') }}
        </h3>
      <ul class="grid lg:grid-cols-3 gap-3">
        <li
          v-for="contact in contacts"
          class="space-inset-m h-48 c-support-singlecard">
          <dp-faq-support-card>
            <template v-slot:title>
              <h2 class="font-semibold">
                {{ contact.attributes.title }}
              </h2>
            </template>
            <template v-slot:phonenumber>
              <p class="mt-3 mb-0 inline-block font-semibold">
                {{ contact.attributes.phoneNumber }}
              </p>
            </template>
            <template v-slot:email>
              <p class="mb-0 font-normal">
                {{ contact.attributes.eMailAddress }}
              </p>
            </template>
            <template v-slot:reachability>
              <p class="mt-4 lg:mt-2 font-normal">
                {{ (contact.attributes.text).replace(/<(?:"[^"]*"['"]*|'[^']*'['"]*|[^'">])+>/g, "") }}
              </p>
            </template>
          </dp-faq-support-card>
        </li>
      </ul>
    </section>
    <h3 class="font-size-h4 font-semibold">
      {{ Translator.trans('support.technical') }}
    </h3>
    <div class="lg:w-8/12 space-inset-m pt-0 h-48 c-support-singlecard">
      <dp-faq-support-card>
        <template v-slot:phonenumber>
          <p class="mt-8 mb-0 inline-block font-semibold">
            (+49) 40 428 46 2694
          </p>
        </template>
        <template v-slot:reachability>
          <p class="mt-3">
            <h5 class="mb-0 font-semibold">
              Servicezeiten
            </h5>
            <p class="font-normal">
              Montag bis Freitag: 6.30 - 18 Uhr<br>
              Freitag: 6.30 - 17Uhr
            </p>
          </p>
        </template>
        <template v-slot:special-reachability>
          <span>
            Ausgenommen am 24.12. und 31.12., sowie an gesetzlichen Feiertagen in Schleswig-Holstein.
          </span>
        </template>
      </dp-faq-support-card>
    </div>
  </div>
</template>
<script>

import DpFaqSupportCard from './DpFaqSupportCard.vue'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'DpFaqSupport',
  components: { DpFaqSupportCard },

  computed: {
    ...mapState('customerContact', {
      contacts: 'items'
    })
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
