<template>
  <div class="layout u-pl">
    <ul v-if="contactSupportList.length > 0">
      <li
        v-for="contact in contactSupportList"
        class="u-mb-0_75">
        <dp-input
          disabled
          :value="contact.name" />
        <dp-input
          disabled
          :value="contact.phone"></dp-input>
        <dp-input
          disabled
          :value="contact.email"></dp-input>
        <dp-input
          disabled
          :value="contact.text"></dp-input>
      </li>
    </ul>
    <dp-button
      v-if="!showContactForm"
      text="Neuen Kontakt anlegen"
      @click="addContactForm" />
    <template v-if="showContactForm">
      <dp-input
        id="supportName"
        v-model="contactSupport.name"
        class="u-mb-0_75"
        data-cy="contactName"
        placeholder="Name"
        type="text" />
      <dp-input
        id="phoneNumber"
        v-model="contactSupport.phone"
        class="u-mb-0_75"
        data-cy="phoneNumber"
        placeholder="Telefonnummer"
        type="text" />
      <dp-input
        id="emailAddress"
        v-model="contactSupport.email"
        class="u-mb-0_75"
        data-cy="emailAddress"
        :placeholder="Translator.trans('email.address')"
        type="email" />
      <dp-editor
        id="supportText"
        class="u-mb-0_75"
        v-model="contactSupport.text"
        hidden-input="supportText"
        :toolbar-items="{
          fullscreenButton: true,
          headings: [2,3,4],
          imageButton: true,
          linkButton: true
          }"
        :routes="{
          getFileByHash: (hash) => Routing.generate('core_file', { hash: hash })
          }" />
      <dp-button-row
        class="u-mt"
        primary
        secondary
        @secondary-action="reset"
        @primary-action="saveContact" />
    </template>
  </div>
</template>

<script>
import { DpButton, DpButtonRow, DpEditor, DpInput } from '@demos-europe/demosplan-ui'

export default {
  name: 'CustomerSettingsContentSupport',

  components: {
    DpButton,
    DpButtonRow,
    DpEditor,
    DpInput
  },

  data () {
    return {
      contactSupport: {
        name: '',
        phone: '',
        email: '',
        text: ''
      },
      contactSupportList: [],
      showContactForm: false
    }
  },

  methods: {
    addContactForm () {
      this.showContactForm = true
    },

    getContactsSupport () {
      this.contactSupportList = [{
        name: 'Bester Kontakt',
        email: 'email@test.de',
        phone: '0176 3493492',
        text: 'Unser Öffnungszeiten sind so: Nie'
      },
      {
        name: 'Zweitbester Kontakt',
        email: 'ema222il@test.de',
        phone: '012276 3493492',
        text: 'Unser Öffnungszeiten sind so: Immer'
      }]
    },

    reset () {
      this.showContactForm = false
    },

    saveContact () {
      this.showContactForm = false
      this.contactSupportList.push({
        name: this.contactSupport.name,
        phone: this.contactSupport.phone,
        email: this.contactSupport.email,
        text: this.contactSupport.text
      })
    }
  },

  mounted () {
    this.getContactsSupport()
  }
}
</script>
