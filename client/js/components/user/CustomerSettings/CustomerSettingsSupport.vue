<template>
  <div class="layout u-pl">
    <dp-editable-list
      ref="editableList"
      :entries="contacts"
      :translation-keys="translationKeys"
      @delete="deleteEntry"
      @reset="resetForm"
      @saveEntry="id => dpValidateAction('contactData', () => createOrUpdateContact(id), false)"
      @show-update-form="showUpdateForm">
      <template v-slot:list="contact">
        <h3
          class="break-words"
          v-text="contact.attributes.title" />
        <p
          class="break-words"
          v-text="contact.attributes.phoneNumber" />
        <p
          class="break-words"
          v-text="contact.attributes.eMailAddress" />
        <template v-html="contact.attributes.text" />
        <dp-badge
          class="color--white rounded-full whitespace--nowrap bg-color--grey u-mt-0_125"
          size="smaller"
          :text="Translator.trans(contact.attributes.visible ? 'visible' : 'visible.not')" />
      </template>
      <template v-slot:form>
        <div
          id="contactForm"
          data-dp-validate="contactData"
          class="space-stack-s space-inset-s border">
          <p class="lbl">
            {{ Translator.trans(updating ? 'contact.change' : 'contact.new') }}:
          </p>
          <dp-input
            id="contactTitle"
            v-model="customerContact.title"
            class="u-mb-0_75"
            data-cy="contactTitle"
            :pattern="titlesInUsePattern"
            :data-dp-validate-error="Translator.trans(customerContact.title === '' ? 'error.name.required' : 'error.name.unique')"
            :label="{
              text: Translator.trans('contact.name')
            }"
            required
            type="text" />
          <dp-input
            id="phoneNumber"
            v-model="customerContact.phoneNumber"
            autocomplete="tel"
            class="u-mb-0_75"
            data-cy="phoneNumber"
            :data-dp-validate-error="Translator.trans(!customerContact.phoneNumber ? 'error.phone.required' : 'error.phone.pattern')"
            :label="{
              text: Translator.trans('contact.phone_number')
            }"
            pattern="^(\+?)(-| |[0-9]|\(|\))*$"
            required
            type="tel" />
          <dp-input
            id="emailAddress"
            v-model="customerContact.eMailAddress"
            autocomplete="email"
            class="u-mb-0_75"
            data-cy="emailAddress"
            :label="{
              text: Translator.trans('email.address')
            }"
            type="email" />
          <dp-editor
            id="supportText"
            class="u-mb-0_75"
            v-model="customerContact.text"
            hidden-input="supportText"
            :toolbar-items="{
              fullscreenButton: true,
              headings: [2,3,4],
              linkButton: true
            }" />
          <dp-checkbox
            id="contactVisible"
            v-model="customerContact.visible"
            data-cy="contactVisible"
            :label="{
              text: Translator.trans('contact.visible')
            }" />
        </div>
      </template>
    </dp-editable-list>
  </div>
</template>

<script>
import { DpBadge, DpCheckbox, DpEditableList, DpEditor, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

const emptyCustomer = {
  title: '',
  phoneNumber: '',
  eMailAddress: '',
  text: '',
  visible: false
}
export default {
  name: 'CustomerSettingsSupport',

  components: {
    DpBadge,
    DpCheckbox,
    DpEditableList,
    DpEditor,
    DpInput
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      customerContact: emptyCustomer,
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
    ...mapState('CustomerContact', {
      contacts: 'items'
    }),

    titlesInUsePattern () {
      const usedTitle = Object.values(this.contacts)
        .filter(contact => contact.id !== this.customerContact.id)
        .map(contact => contact.attributes.title)

      return `^(?!(?:${usedTitle.join('|')})$)`
    }
  },

  methods: {
    ...mapActions('CustomerContact', {
      createContact: 'create',
      fetchContacts: 'list',
      deleteContact: 'delete',
      saveContact: 'save'
    }),

    ...mapMutations('CustomerContact', {
      updateContact: 'setItem'
    }),

    reset () {
      this.showContactForm = false
      this.updating = false
    },

    createOrUpdateContact (id) {
      const payload = {
        ...((id === 'new') ? null : { id }),
        type: 'CustomerContact',
        attributes: {
          title: this.customerContact.title,
          phoneNumber: this.customerContact.phoneNumber ?? null,
          text: this.customerContact.text ?? null,
          visible: this.customerContact.visible,
          eMailAddress: this.customerContact.eMailAddress ?? null
        }
      }

      if (id === 'new') {
        this.createContact(payload).then(() => {
          this.getContacts()
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
      } else {
        this.updateContact(payload)
        this.saveContact(id).then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
      }

      this.$refs.editableList.toggleFormVisibility(false)
      this.resetForm()
    },

    deleteEntry (id) {
      this.deleteContact(id).then(() => {
        dplan.notify.notify('confirm', Translator.trans('contact.deleted'))
      })
    },

    getContacts () {
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
    },

    resetForm () {
      this.customerContact = { ...emptyCustomer }
      this.updating = false
    },

    showUpdateForm (index) {
      this.updateForm(index)

      this.$nextTick(() => {
        document.getElementById('contactForm').scrollIntoView()
      })
    },

    updateForm (id) {
      const currentData = this.contacts[id].attributes

      this.updating = true
      this.customerContact = {
        title: currentData.title,
        phoneNumber: currentData.phoneNumber ?? '',
        eMailAddress: currentData.eMailAddress ?? '',
        text: currentData.text ?? '',
        visible: currentData.visible,
        id
      }
    }
  },

  mounted () {
    this.getContacts()
  }
}
</script>
