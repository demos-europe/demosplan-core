<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <dp-editable-list
      :entries="emails"
      :data-cy="dataCy !== '' ? `${dataCy}:emailList` : `emailList`"
      @delete="handleDelete"
      @reset="resetForm"
      @saveEntry="handleSubmit(itemIndex !== null ? itemIndex : 'new')"
      @show-update-form="showUpdateForm"
      :translation-keys="translationKeys"
      ref="listComponent">
      <template v-slot:list="entry">
        <span>{{ entry.mail }}
          <input
            type="email"
            :value="entry.mail"
            :name="formFieldName"
            class="sr-only">
        </span>
      </template>

      <template v-slot:form>
        <dp-input
          id="emailAddress"
          :data-cy="dataCy !== '' ? `${dataCy}:emailAddressInput` : `emailAddressInput`"
          :placeholder="Translator.trans('email.address')"
          type="email"
          v-model="formFields.mail"
          width="u-1-of-2"
          @enter="handleSubmit(itemIndex !== null ? itemIndex : 'new')" />
      </template>
    </dp-editable-list>
  </div>
</template>

<script>
import { DpEditableList, DpInput, validateEmail } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpEmailList',

  components: {
    DpEditableList,
    DpInput
  },

  props: {
    allowUpdatesFromOutside: {
      type: Boolean,
      required: false,
      default: false
    },

    dataCy: {
      type: String,
      required: false,
      default: ''
    },

    initEmails: {
      required: true,
      type: Array
    },

    formFieldName: {
      type: String,
      required: false,
      default: 'agencyExtraEmailAddresses[][fullAddress]'
    }
  },

  emits: [
    'saved'
  ],

  data () {
    return {
      formFields: {
        mail: ''
      },
      itemIndex: null,
      emails: this.initEmails,
      translationKeys: {
        new: Translator.trans('email.address.new'),
        add: Translator.trans('email.address.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('email.address.update'),
        noEntries: Translator.trans('email.address.no'),
        delete: Translator.trans('email.address.delete')
      }
    }
  },

  watch: {
    initEmails: {
      handler (newVal) {
        if (this.allowUpdatesFromOutside) {
          this.emails = newVal
        }
      },
      deep: true
    }
  },

  methods: {
    addElement () {
      this.emails.push({
        mail: this.formFields.mail
      })
    },

    deleteEntry (index) {
      this.emails.splice(index, 1)
    },

    handleDelete (index) {
      this.deleteEntry(index)
      this.resetForm()
    },

    handleSubmit (index) {
      if (validateEmail(this.formFields.mail)) {
        if (index === 'new') {
          this.addElement()
          this.saveExtraEmailAddress(this.formFields.mail)
        } else {
          this.updateEmailAddress(index)
        }

        this.resetForm()
        this.$refs.listComponent.toggleFormVisibility(false)
        this.$refs.listComponent.currentlyUpdating = ''
      }
    },

    resetForm () {
      this.formFields.mail = ''
      this.itemIndex = null
    },

    saveExtraEmailAddress (extraEmailAddress) {
      this.$emit('saved', extraEmailAddress)
    },

    showUpdateForm (index) {
      this.formFields.mail = this.emails[index].mail
      this.itemIndex = index
    },

    updateEmailAddress (index) {
      this.emails[index].mail = this.formFields.mail
    }
  }
}
</script>
