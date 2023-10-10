<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div
    id="scFaq"
    class="c-support__wrapper">
    <h2>
      {{ Translator.trans('support.heading') }}
    </h2>
    <section class="u-mb-0_75">
      <p class="u-mb-0_75">{{ Translator.trans('support.introduction') }}</p>
      <p>Sie haben eine Frage zur Technik? Dann hilft Ihnen unser technischer Support gerne weiter.</p>
        <h3 class="font-size-h4 u-mt-0_75">
          {{ Translator.trans('support.content') }}
        </h3>
      <ul>
        <li
          v-for="contact in contactList"
          class="c-support__groupcard space-inset-m layout__item">
          <dp-faq-support-card>
            <template v-slot:title>
              <h2>{{ contact.title }}</h2>
            </template>
            <template v-slot:phonenumber>
              <p class="u-mt-0_75">{{ contact.phoneNumber }}</p>
            </template>
            <template v-slot:email>
              <p>{{ contact.emailAddress }}</p>
            </template>
            <template v-slot:reachability>
              <p class="u-mt-0_125">{{ contact.text }}</p>
            </template>
          </dp-faq-support-card>
        </li>
      </ul>
    </section>
    <h3 class="font-size-h4">
      {{ Translator.trans('support.technical') }}
    </h3>
    <div class="c-support__singlecard space-inset-m">
      <dp-faq-support-card>
        <template v-slot:phonenumber>
          <p class="u-mt-0_75">(+49) 40 428 46 2694</p>
        </template>
        <template v-slot:reachability>
          <p class="u-mt-0_75">
            <h5>Servicezeiten</h5>
            <p>Montag bis Freitag: 6.30 - 18 Uhr<br>
              Freitag: 6.30 - 17Uhr
            </p>
          </p>
        </template>
        <template v-slot:special-reachability>
          <span>Ausgenommen am 24.12. und 31.12., sowie an gesetzlichen Feiertagen in Schleswig-Holstein.</span>
        </template>
      </dp-faq-support-card>
    </div>
  </div>
</template>
<script>

import {visible} from "chalk";
import DpFaqSupportCard from "./DpFaqSupportCard.vue";

export default {
  name: 'DpFaqSupport',
  components: {DpFaqSupportCard},

  data () {
    return {
      contactList: [],
      contact: {
        title: '',
        phoneNumber: '',
        text: '',
        visible: '',
        emailAddress: ''
      }
    }
  },

  methods: {
    visible,
    getContacts () {
      this.contactList = [{
        title: 'Naruto Uzumaki',
        phoneNumber: '0145758798078',
        text: 'Montag bis Freitag: 6.30 - 18 Uhr',
        visible: true,
        emailAddress: 'naruto@gmail.com'
      },
        {
          title: 'Tanjiro Kamado',
          phoneNumber: '0145756748',
          text: 'Dienstag bis Freitag: 7.00 - 20 Uhr',
          visible: true,
          emailAddress: 'kamado@gmail.com'
        },
        {
          title: 'Nezuko Kamado',
          phoneNumber: '01457568078',
          text: 'Montag bis Donnerstag: 9.00 - 15 Uhr',
          visible: true,
          emailAddress: 'kamadochan@gmail.com'
        }]
    }
  },

  mounted () {
    this.getContacts()
  }
}
</script>
