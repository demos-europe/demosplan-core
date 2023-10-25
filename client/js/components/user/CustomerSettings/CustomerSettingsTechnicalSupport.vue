<template>
  <div data-dp-validate="technicalSupport">
    <dp-input
      id="technicalSupportTitle"
      v-model="contact.title"
      class="u-mb-0_75"
      data-cy="contactTitle"
      data-dp-validate-error="error.name.required"
      :label="{
        text: Translator.trans('contact.name')
      }"
      required
      type="text" />
    <dp-input
      id="technicalSupportPhone"
      v-model="contact.phoneNumber"
      autocomplete="tel"
      class="u-mb-0_75"
      data-cy="phoneNumber"
      :data-dp-validate-error="!contact.phoneNumber ? 'error.phone.required' : 'error.phone.pattern'"
      :label="{
        text: Translator.trans('contact.phone_number')
      }"
      pattern="^(\(?\+?)(-| |[0-9]|\(|\))*$"
      required
      type="tel" />
    <dp-input
      id="technicalSupportEmail"
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
      @primary-action="dpValidateAction('technicalSupport', () => updateContact(), false)"
      @secondary-action="setFormFromStore">
      <dp-button
        color="secondary"
        :disabled="this.contact.id === 'new'"
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
  name: 'CustomerSettingsTechnicalSupport',

  components: {
    DpButton,
    DpButtonRow,
    DpEditor,
    DpInput
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      contact: emptyContact,
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
    ...mapState('customerLoginSupportContact', {
      contacts: 'items'
    })
  },

  methods: {
    ...mapActions('customerLoginSupportContact', {
      create: 'create',
      delete: 'delete',
      save: 'save'
    }),

    ...mapMutations('customerLoginSupportContact', {
      update: 'setItem'
    }),

    checkIfContactIsEmpty () {
      Object.values(this.contact).forEach(el => {
        if (el !== '') {
          return false
        }
      })

      return true
    },

    deleteContact () {
      if (this.contact.id !== 'new') {
        this.delete(this.contact.id).then(() => {
          dplan.notify.notify('confirm', Translator.trans('contact.deleted'))
        })
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
      /*
       * The ressource type is a 1-n relation to the customer, but we hve to use it like a 1 to 1 relationship.
       * Therefor we have to fetch a list and then just use the first element
       */
      const contact = Object.values(this.contacts)[0]
      this.contact = contact ? { ...contact.attributes, id: contact.id } : emptyContact
    },

    updateContact () {
      const id = this.contact.id || 'new'

      if (this.checkIfContactIsEmpty()) {
        this.delete()

        return
      }

      const payload = {
        ...((id === 'new') ? null : { id }),
        type: 'CustomerLoginSupportContact',
        attributes: {
          title: this.contact.title,
          phoneNumber: this.contact.phoneNumber,
          text: this.contact.text ? this.contact.text : null,
          eMailAddress: this.contact.eMailAddress ? this.contact.eMailAddress : null
        }
      }

      if (id === 'new') {
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
  }
}
</script>
