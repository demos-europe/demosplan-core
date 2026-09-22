<template>
  <div class="layout u-pl c-support-contacts">
    <dp-editable-list
      ref="editableList"
      :entries="contacts"
      :translation-keys="translationKeys"
      @delete="deleteEntry"
      @reset="resetForm"
      @save-entry="id => dpValidateAction('contactData', () => createOrUpdateContact(id), false)"
      @show-update-form="showUpdateForm"
    >
      <template v-slot:list="contact">
        <div
          class="c-support-contacts__entry"
          :class="contact.index === lastContactId ? 'pt-3' : 'border-b border-neutral py-3'"
        >
          <div class="flex items-center gap-2 mb-1">
            <h3
              class="m-0 break-words"
              v-text="contact.attributes.title"
            />
            <dp-badge
              :color="contact.attributes.visible ? 'confirm' : 'default'"
              size="small"
              :text="Translator.trans(contact.attributes.visible ? 'visible' : 'visible.not')"
            />
          </div>
          <p
            v-if="contact.attributes.phoneNumber"
            class="break-words"
          >
            <a :href="`tel:${contact.attributes.phoneNumber}`">
              <dp-icon
                class="inline-block"
                icon="phone"
              />
              {{ contact.attributes.phoneNumber }}
            </a>
          </p>
          <p
            v-if="contact.attributes.eMailAddress"
            class="break-words"
          >
            <a :href="`mailto:${contact.attributes.eMailAddress}`">
              <dp-icon
                class="inline-block"
                icon="mail"
              />
              {{ contact.attributes.eMailAddress }}
            </a>
          </p>
          <div
            v-if="contact.attributes.text"
            v-cleanhtml="contact.attributes.text"
            class="c-styled-html"
          />
        </div>
      </template>
      <template v-slot:form>
        <div
          id="contactForm"
          data-dp-validate="contactData"
          class="space-stack-s space-inset-s border"
        >
          <p class="lbl">
            {{ Translator.trans(updating ? 'contact.change' : 'contact.new') }}:
          </p>
          <dp-input
            id="contactTitle"
            v-model="customerContact.title"
            class="u-mb-0_75"
            data-cy="contactTitle"
            :data-dp-validate-error="Translator.trans(customerContact.title === '' ? 'error.name.required' : 'error.name.unique')"
            :label="{
              text: Translator.trans('contact.name')
            }"
            :pattern="titlesInUsePattern"
            required
            type="text"
          />
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
            type="tel"
          />
          <dp-input
            id="emailAddress"
            v-model="customerContact.eMailAddress"
            autocomplete="email"
            class="u-mb-0_75"
            data-cy="emailAddress"
            :label="{
              text: Translator.trans('email.address')
            }"
            type="email"
          />
          <dp-editor
            id="supportText"
            v-model="customerContact.text"
            class="u-mb-0_75"
            hidden-input="supportText"
            :toolbar-items="{
              fullscreenButton: true,
              headings: [2,3,4],
              linkButton: true
            }"
          />
          <dp-checkbox
            id="contactVisible"
            v-model="customerContact.visible"
            data-cy="contactVisible"
            :label="{
              text: Translator.trans('contact.visible')
            }"
          />
        </div>
      </template>
    </dp-editable-list>
  </div>
</template>

<script>
import { CleanHtml, DpBadge, DpCheckbox, DpEditableList, DpEditor, DpIcon, DpInput, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'

const emptyCustomer = {
  title: '',
  phoneNumber: '',
  eMailAddress: '',
  text: '',
  visible: false,
}

export default {
  name: 'CustomerSettingsSupport',

  components: {
    DpBadge,
    DpCheckbox,
    DpEditableList,
    DpEditor,
    DpIcon,
    DpInput,
  },

  directives: {
    cleanhtml: CleanHtml,
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
        delete: Translator.trans('contact.delete'),
      },
      updating: false,
    }
  },

  computed: {
    ...mapState('CustomerContact', {
      contacts: 'items',
    }),

    titlesInUsePattern () {
      const usedTitle = Object.values(this.contacts)
        .filter(contact => contact.id !== this.customerContact.id)
        .map(contact => contact.attributes.title)

      return `^(?!(?:${usedTitle.join('|')})$)`
    },

    /*
     * `contacts` (store items) is an object keyed by id, so the editable list
     * slot's `index` is the id key rather than a numeric index. Identify the
     * last entry by its key to draw a divider below every entry but the last.
     */
    lastContactId () {
      const keys = Object.keys(this.contacts)

      return keys[keys.length - 1]
    },
  },

  methods: {
    ...mapActions('CustomerContact', {
      createContact: 'create',
      fetchContacts: 'list',
      deleteContact: 'delete',
      saveContact: 'save',
    }),

    ...mapMutations('CustomerContact', {
      updateContact: 'setItem',
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
          eMailAddress: this.customerContact.eMailAddress ?? null,
        },
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
        this.getContacts()
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
            'eMailAddress',
          ].join(),
        },
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
        id,
      }
    },
  },

  mounted () {
    this.getContacts()
  },
}
</script>
