<template>
  <div
    data-dp-validate="technicalSupport"
    class="space-stack-s space-inset-s border">
    <p class="lbl">
      {{ Translator.trans(updating ? 'contact.change' : 'contact.new') }}:
    </p>
    <dp-input
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
      v-model="contact.phoneNumber"
      autocomplete="tel"
      class="u-mb-0_75"
      data-cy="phoneNumber"
      :data-dp-validate-error="!contact.phoneNumber ? 'error.phone.required' : 'error.phone.pattern'"
      :label="{
        text: Translator.trans('contact.phone_number')
      }"
      pattern="^(\+?)(-| |[0-9]|\(|\))*$"
      required
      type="tel" />
    <dp-input
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
      @primary-action="dpValidateAction('technicalSupport', () => updateContact(), false)"
      @secondary-action="resetForm">
      <dp-button
        color="secondary"
        :text="Translator.trans('delete')"
        @click.prevent="deleteContact" />
    </dp-button-row>
  </div>
</template>

<script>
import { DpButton, DpButtonRow, DpEditor, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

const emptyCustomer = {
  title: '',
  phoneNumber: '',
  eMailAddress: '',
  text: ''
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
      contact: emptyCustomer,
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
    ...mapState('customerContact', {
      contacts: 'items'
    })
  },

  methods: {
    ...mapActions('customerLoginSupportContact', {
      create: 'create',
      fetch: 'list',
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
      })
    },

    reset () {
      this.showContactForm = false
      this.updating = false
    },

    resetForm () {
      this.contact = emptyCustomer
      this.updating = false
    },

    updateContact () {
      const id = 'new'

      if (this.checkIfContactIsEmpty()) {
        this.delete()

        return
      }

      const payload = {
        ...((id === 'new') ? null : { id }),
        type: 'CustomerLoginSupportContact',
        attributes: {
          title: this.contact.title,
          phoneNumber: this.contact.phoneNumber ? this.contact.phoneNumber : null,
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
    },

    updateForm (index) {
      const currentData = this.contacts[index].attributes

      this.updating = true
      this.contact = {
        title: currentData.title,
        phoneNumber: currentData.phoneNumber ? currentData.phoneNumber : '',
        eMailAddress: currentData.eMailAddress ? currentData.eMailAddress : '',
        text: currentData.text ? currentData.text : '',
        visible: currentData.visible
      }
    }
  },

  mounted () {
    this.$on('showUpdateForm', (index) => {
      this.updateForm(index)
      this.$nextTick(() => {
        document.getElementById('contactForm').scrollIntoView()
      })
    })

    this.$on('delete', (id) => {
      this.delete(id).then(() => {
        dplan.notify.notify('confirm', Translator.trans('contact.deleted'))
      })
    })

    this.getContacts()
  }
}
</script>
