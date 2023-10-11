<template>
  <div class="layout u-pl">
    <dp-editable-list
      ref="editableList"
      :entries="contacts"
      :translation-keys="translationKeys"
      @reset="resetForm"
      @saveEntry="id => dpValidateAction('contactData', () => createOrUpdateContact(id), false)">
      <template v-slot:list="contact">
        <p
          class="weight--bold u-mt"
          v-text="contact.attributes.title" />
        <span
          class="block"
          v-text="contact.attributes.phoneNumber" />
        <span
          class="block"
          v-text="contact.attributes.eMailAddress" />
        <span
          class="block"
          v-html="contact.attributes.text" />
        <span v-text="Translator.trans('customer.contact.visibleText', {isVisible: contact.attributes.visible})" />
      </template>
      <template v-slot:form>
        <div data-dp-validate="contactData">
          <dp-input
            id="contactTitle"
            v-model="customerContact.title"
            class="u-mb-0_75"
            data-cy="contactTitle"
            :placeholder="Translator.trans('customer.contact.title')"
            required
            type="text" />
          <dp-input
            id="phoneNumber"
            v-model="customerContact.phoneNumber"
            autocomplete="tel"
            class="u-mb-0_75"
            data-cy="phoneNumber"
            pattern="^(\+?)(-| |[0-9]|\(|\))*$"
            :placeholder="Translator.trans('customer.contact.phoneNumber')"
            :required="phoneIsRequired"
            type="tel"
            @input="input => setRequiredEmail(input)" />
          <dp-input
            id="emailAddress"
            v-model="customerContact.eMailAddress"
            autocomplete="email"
            class="u-mb-0_75"
            data-cy="emailAddress"
            :placeholder="Translator.trans('email.address')"
            :required="emailIsRequired"
            type="email"
            @input="input => setRequiredPhone(input)" />
          <dp-editor
            id="supportText"
            class="u-mb-0_75"
            v-model="customerContact.text"
            hidden-input="supportText"
            :toolbar-items="{
              fullscreenButton: true,
              headings: [2,3,4],
              linkButton: true
            }"
            :routes="{
              getFileByHash: (hash) => Routing.generate('core_file', { hash: hash })
            }" />
          <dp-checkbox
            id="contactVisible"
            v-model="customerContact.visible"
            data-cy="contactVisible"
            :label="{
              text: Translator.trans('customer.contact.visible')
            }" />
        </div>
      </template>
    </dp-editable-list>
  </div>
</template>

<script>
import { DpCheckbox, DpEditableList, DpEditor, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

export default {
  name: 'CustomerSettingsContact',

  components: {
    DpCheckbox,
    DpEditableList,
    DpEditor,
    DpInput
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      emailIsRequired: true,
      phoneIsRequired: true,
      showContactForm: false,
      translationKeys: {
        new: Translator.trans('customer.contact.new'),
        add: Translator.trans('customer.contact.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('customer.contact.update'),
        noEntries: Translator.trans('customer.contact.no'),
        delete: Translator.trans('customer.contact.delete')
      }
    }
  },

  computed: {
    ...mapState('customerContact', {
      contacts: 'items'
    }),

    customerContact () {
      return {
        title: '',
        phoneNumber: '',
        eMailAddress: '',
        text: '',
        visible: false
      }
    }
  },

  methods: {
    ...mapActions('customerContact', {
      createContact: 'create',
      fetchContact: 'list',
      deleteContact: 'delete',
      saveContact: 'save'
    }),

    ...mapMutations('customerContact', {
      updateContact: 'setItem'
    }),

    reset () {
      this.showContactForm = false
    },

    createOrUpdateContact (id) {
      if (id === 'new') {
        const payload = {
          type: 'CustomerContact',
          attributes: {
            title: this.customerContact.title,
            phoneNumber: this.customerContact.phoneNumber ? this.customerContact.phoneNumber : null,
            text: this.customerContact.text ? this.customerContact.text : null,
            visible: this.customerContact.visible,
            eMailAddress: this.customerContact.eMailAddress ? this.customerContact.eMailAddress : null
          }
        }
        this.createContact(payload).then((response) => {
          this.getContacts()
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
      } else {
        const payload = {
          id: id,
          type: 'CustomerContact',
          attributes: {
            title: this.customerContact.title,
            phoneNumber: this.customerContact.phoneNumber ? this.customerContact.phoneNumber : null,
            text: this.customerContact.text ? this.customerContact.text : null,
            visible: this.customerContact.visible,
            eMailAddress: this.customerContact.eMailAddress ? this.customerContact.eMailAddress : null
          }
        }
        this.updateContact(payload)
        this.saveContact(id).then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
      }
      this.$refs.editableList.toggleFormVisibility(false)
      this.resetForm()
    },

    getContacts () {
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
    },

    resetForm () {
      this.customerContact.title = ''
      this.customerContact.phoneNumber = ''
      this.customerContact.eMailAddress = ''
      this.customerContact.visible = false
      this.customerContact.text = ''
    },

    setRequiredEmail (input) {
      this.emailIsRequired = !input
    },

    setRequiredPhone (input) {
      this.phoneIsRequired = !input
    },

    updateForm (index) {
      const currentData = this.contacts[index].attributes
      this.customerContact.title = currentData.title
      this.customerContact.phoneNumber = currentData.phoneNumber
      this.customerContact.eMailAddress = currentData.eMailAddress
      this.customerContact.text = currentData.text
      this.customerContact.visible = currentData.visible
    }
  },

  mounted () {
    this.$on('showUpdateForm', (index) => {
      this.updateForm(index)
    })

    this.$on('delete', (id) => {
      this.deleteContact(id).then(() => {
        dplan.notify.notify('confirm', Translator.trans('customer.contact.deleted'))
      })
    })

    this.getContacts()
  }
}
</script>
