<template>
  <div data-dp-validate="loginSupport">
    <dp-input
      id="loginSupportTitle"
      v-model="contact.title"
      class="u-mb-0_75"
      data-cy="contactTitle"
      :data-dp-validate-error="Translator.trans('error.name.required')"
      :label="{
        text: Translator.trans('contact.name')
      }"
      required
      type="text" />
    <dp-input
      id="loginSupportPhone"
      v-model="contact.phoneNumber"
      autocomplete="tel"
      class="u-mb-0_75"
      data-cy="phoneNumber"
      :data-dp-validate-error="Translator.trans(!contact.phoneNumber ? 'error.phone.required' : 'error.phone.pattern')"
      :label="{
        text: Translator.trans('contact.phone_number')
      }"
      pattern="^(\(?\+?)(-| |[0-9]|\(|\))*$"
      required
      type="tel" />
    <dp-input
      id="loginSupportEmail"
      v-model="contact.eMailAddress"
      autocomplete="email"
      class="u-mb-0_75"
      data-cy="emailAddress"
      :label="{
        text: Translator.trans('email.address')
      }"
      type="email" />
    <dp-editor
      class="u-mb-0_75"
      v-model="contact.text"
      hidden-input="supportText"
      :toolbar-items="{
        fullscreenButton: true,
        headings: [2,3,4],
        linkButton: true
      }"
      :tus-endpoint="dplan.paths.tusEndpoint" />
    <dp-button-row
      primary
      secondary
      :secondary-text="Translator.trans('reset')"
      @primary-action="dpValidateAction('loginSupport', () => updateContact(), false)"
      @secondary-action="setFormFromStore">
      <dp-button
        color="secondary"
        :disabled="this.contact.id === emptyContact.id"
        :text="Translator.trans('delete')"
        @click.prevent="deleteContact" />
    </dp-button-row>
  </div>
</template>

<script>
import { DpButton, DpButtonRow, DpEditor, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

const emptyContact = {
  title: '',
  phoneNumber: '',
  eMailAddress: '',
  text: '',
  id: 'new'
}
export default {
  name: 'CustomerSettingsloginSupport',

  components: {
    DpButton,
    DpButtonRow,
    DpEditor,
    DpInput
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      emptyContact: emptyContact,
      contact: { ...emptyContact },
      showContactForm: false,
      translationKeys: {
        new: Translator.trans('contact.new'),
        add: Translator.trans('contact.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('contact.update'),
        noEntries: Translator.trans('contact.no_entries'),
        delete: Translator.trans('contact.delete')
      },
      updating: false
    }
  },

  computed: {
    ...mapState('CustomerLoginSupportContact', {
      contacts: 'items'
    })
  },

  methods: {
    ...mapActions('CustomerLoginSupportContact', {
      create: 'create',
      delete: 'delete',
      fetch: 'list',
      save: 'save'
    }),

    ...mapMutations('CustomerLoginSupportContact', {
      update: 'setItem'
    }),

    checkIfContactIsEmpty () {
      Object.values(this.contact).forEach(el => {
        if (el !== '') {
          return true
        }
      })

      return false
    },

    deleteContact () {
      if (this.contact.id !== emptyContact.id) {
        this.delete(this.contact.id).then(() => {
          this.contact = { ...this.emptyContact }
          dplan.notify.notify('confirm', Translator.trans('contact.deleted'))
        })
      } else {
        this.contact = { ...this.emptyContact }
      }
    },

    getContacts () {
      this.fetch({
        fields: {
          CustomerLoginSupportContact: [
            'title',
            'phoneNumber',
            'text',
            'eMailAddress'
          ].join()
        }
      }).then(() => {
        this.setFormFromStore()
      })
    },

    setFormFromStore () {
      const contact = Object.values(this.contacts)[0]
      const attrs = contact?.attributes || { ...this.emptyContact }

      this.contact = contact
        ? {
            eMailAddress: attrs.eMailAddress || '',
            id: contact?.id || emptyContact.id,
            phoneNumber: attrs.phoneNumber,
            text: attrs.text || '',
            title: attrs.title
          }
        : { ...this.emptyContact }
    },

    updateContact () {
      const id = this.contact.id || emptyContact.id

      if (this.checkIfContactIsEmpty()) {
        this.deleteContact()

        return
      }

      const payload = {
        ...((id === emptyContact.id) ? null : { id }),
        type: 'CustomerLoginSupportContact',
        attributes: {
          title: this.contact.title,
          phoneNumber: this.contact.phoneNumber,
          text: this.contact.text ? this.contact.text : null,
          eMailAddress: this.contact.eMailAddress ? this.contact.eMailAddress : null
        }
      }

      if (id === emptyContact.id) {
        this.create(payload)
          .then(() => {
            this.getContacts()

            dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
          })
      } else {
        this.update(payload)
        this.save(id)
          .then(() => {
            dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
          })
      }
    }
  },

  mounted () {
    this.getContacts()
  }
}
</script>
