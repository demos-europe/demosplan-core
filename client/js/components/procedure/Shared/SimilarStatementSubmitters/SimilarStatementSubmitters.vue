<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <div class="flex items-center space-inline-s u-mv-0_5">
      <p
        class="weight--bold u-m-0"
        v-text="Translator.trans('statement.similarStatementSubmitters')" />
      <dp-contextual-help :text="Translator.trans('statement.similarStatementSubmitters.hint')" />
    </div>
    <dp-editable-list
      ref="listComponent"
      class="o-list"
      :entries="listEntries"
      :has-permission-to-edit="editable"
      :translation-keys="translationKeys"
      @reset="resetFormFields"
      @saveEntry="index => dpValidateAction('similarStatementSubmitterForm', () => handleSaveEntry(index), false)">
      <template v-slot:list="{ entry, index }">
        <template v-if="isRequestFormPost">
          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][fullName]'"
            :value="entry.submitterName">

          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][city]'"
            :value="entry.submitterCity">

          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][streetName]'"
            :value="entry.submitterAddress">

          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][streetNumber]'"
            :value="entry.submitterHouseNumber">

          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][postalCode]'"
            :value="entry.submitterPostalCode">

          <input
            type="hidden"
            :name="'r_similarStatementSubmitters[' + index + '][emailAddress]'"
            :value="entry.submitterEmailAddress">
        </template>

        <span
          v-if="entry.submitterName"
          class="o-list__item separated"
          v-text="entry.submitterName" />
        <span
          v-if="entry.submitterEmailAddress"
          class="o-list__item separated"
          v-text="entry.submitterEmailAddress" />
        <span
          v-if="entry.submitterAddress"
          class="o-list__item separated"
          v-text="entry.submitterAddress" />
        <span
          v-if="entry.submitterHouseNumber"
          class="o-list__item separated"
          v-text="entry.submitterHouseNumber" />
        <span
          v-if="entry.submitterPostalCode"
          class="o-list__item separated"
          v-text="entry.submitterPostalCode" />
        <span
          v-if="entry.submitterCity"
          class="o-list__item separated"
          v-text="entry.submitterCity" />
      </template>

      <template v-slot:form>
        <div
          class="grid grid-cols-1 gap-x-4"
          :class="fieldsFullWidth ? '' : 'md:grid-cols-2'"
          data-dp-validate="similarStatementSubmitterForm">
          <dp-input
            id="statementSubmitterName"
            v-model="formFields.submitterName"
            class="mb-2"
            data-cy="voterUsername"
            :label="{
              text: Translator.trans('name')
            }"
            required />
          <dp-input
            id="statementSubmitterEmail"
            v-model="formFields.submitterEmailAddress"
            class="mb-2"
            data-cy="voterEmail"
            :label="{
              text: Translator.trans('email')
            }"
            type="email" />

          <div class="o-form__group mb-2">
            <dp-input
              id="statementSubmitterAddress"
              v-model="formFields.submitterAddress"
              class="o-form__group-item"
              data-cy="voterStreet"
              :label="{
                text: Translator.trans('street')
              }" />
            <dp-input
              id="statementSubmitterHouseNumber"
              v-model="formFields.submitterHouseNumber"
              class="o-form__group-item shrink"
              data-cy="voterHousenumber"
              :label="{
                text: Translator.trans('street.number.short')
              }"
              :size="3" />
          </div>

          <div class="o-form__group mb-2">
            <dp-input
              id="statementSubmitterPostalCode"
              v-model="formFields.submitterPostalCode"
              class="o-form__group-item shrink"
              data-cy="voterPostalCode"
              :label="{
                text: Translator.trans('postalcode')
              }"
              pattern="^[0-9]{4,5}$"
              :size="5" />
            <dp-input
              id="statementSubmitterCity"
              v-model="formFields.submitterCity"
              class="o-form__group-item"
              data-cy="voterCity"
              :label="{
                text: Translator.trans('city')
              }" />
          </div>
        </div>
      </template>
    </dp-editable-list>
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpContextualHelp,
  DpEditableList,
  DpInput,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapMutations } from 'vuex'

export default {
  name: 'SimilarStatementSubmitters',

  components: {
    DpContextualHelp,
    DpEditableList,
    DpInput
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    fieldsFullWidth: {
      type: Boolean,
      required: false,
      default: false
    },

    isRequestFormPost: {
      type: Boolean,
      default: false,
      required: false
    },

    procedureId: {
      type: String,
      required: true
    },

    similarStatementSubmitters: {
      type: Array,
      default: () => ([]),
      required: false
    },

    statementId: {
      type: String,
      default: '',
      required: false
    }
  },

  data () {
    return {
      isFormVisible: false,
      listEntries: [],
      formFields: {
        submitterName: null,
        submitterEmailAddress: null,
        submitterAddress: null,
        submitterHouseNumber: null,
        submitterPostalCode: null,
        submitterCity: null
      },
      updating: false
    }
  },

  computed: {
    /**
     * The "add" button text of EditableList is too long when inside the narrow context.
     * This is why a shorter button text is rendered there.
     * @return {{add, new, abort, update, noEntries, delete}}
     */
    translationKeys () {
      return {
        new: Translator.trans('add'),
        add: Translator.trans(this.fieldsFullWidth ? 'add' : 'statement.similarStatementSubmitters.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('save'),
        noEntries: Translator.trans('none'),
        delete: Translator.trans('delete')
      }
    }
  },

  methods: {
    ...mapMutations('Statement', {
      updateStatement: 'update'
    }),

    ...mapMutations('SimilarStatementSubmitter', {
      setSimilarStatementSubmitter: 'setItem'
    }),

    createSimilarStatementSubmitter () {
      const index = this.listEntries.length - 1
      const payload = {
        type: 'SimilarStatementSubmitter',
        attributes: this.getSimilarStatementSubmitterAttributes(index),
        relationships: {
          similarStatements: {
            data: [
              {
                type: 'Statement',
                id: this.statementId
              }
            ]
          },
          procedure: {
            data: {
              type: 'Procedure',
              id: this.procedureId
            }
          }
        }
      }

      dpApi.post(Routing.generate('api_resource_create', { resourceType: 'SimilarStatementSubmitter' }), {}, { data: payload })
        .then(response => {
          // Assign backend generated id to local item
          const similarStatementSubmitterId = this.listEntries[index].id = response.data.data.id

          // Update local state - statement
          this.updateStatement({
            id: this.statementId,
            relationship: 'similarStatementSubmitters',
            action: 'add',
            value: {
              id: similarStatementSubmitterId,
              type: 'SimilarStatementSubmitter'
            }
          })

          // Update local state - similarStatementSubmitter
          this.setSimilarStatementSubmitter({
            ...payload,
            id: similarStatementSubmitterId
          })
        })
    },

    deleteSimilarStatementSubmitter () {
      const payload = {
        type: 'Statement',
        id: this.statementId,
        relationships: {
          similarStatementSubmitters: {
            data: this.listEntries.map((entry) => {
              return {
                type: 'SimilarStatementSubmitter',
                id: entry.id
              }
            })
          }
        }
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'Statement', resourceId: this.statementId }), {}, { data: payload })
        .then(response => { checkResponse(response) })
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.entry.deleted'))
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.entry.deleted'))
        })
      this.resetFormFields()
    },

    getSimilarStatementSubmitterAttributes (index) {
      return {
        fullName: this.listEntries[index].submitterName,
        city: this.listEntries[index].submitterCity || null,
        streetName: this.listEntries[index].submitterAddress || null,
        streetNumber: this.listEntries[index].submitterHouseNumber || null,
        postalCode: this.listEntries[index].submitterPostalCode || null,
        emailAddress: this.listEntries[index].submitterEmailAddress || null
      }
    },

    /**
     * Save new or update existing entry from the list.
     * @param index
     */
    handleSaveEntry (index) {
      // Are we editing an existing list entry, or are we adding a new one?
      const updatingExistingEntry = !!this.listEntries.find(entry => entry.id === this.listEntries[index]?.id)

      // The id is generated in the backend when adding a new entry
      const listEntry = {
        id: updatingExistingEntry ? this.listEntries[index].id : '',
        ...this.formFields
      }

      if (updatingExistingEntry) {
        this.listEntries.splice(index, 1, listEntry)

        if (this.isRequestFormPost === false) {
          this.updateSimilarStatementSubmitter(index)
        }
      } else {
        this.listEntries.push(listEntry)

        if (this.isRequestFormPost === false) {
          this.createSimilarStatementSubmitter(index)
        }
      }

      this.resetFormFields()
      this.$refs.listComponent.toggleFormVisibility(false)
    },

    loadInitialListEntries () {
      if (this.similarStatementSubmitters) {
        this.listEntries = this.similarStatementSubmitters.map(el => {
          const { city, emailAddress, fullName, postalCode, streetName, streetNumber } = el.attributes
          return {
            id: el.id,
            submitterAddress: streetName,
            submitterCity: city,
            submitterEmailAddress: emailAddress,
            submitterHouseNumber: streetNumber,
            submitterName: fullName,
            submitterPostalCode: postalCode
          }
        })
      }
    },

    resetFormFields () {
      for (const [key] of Object.entries(this.formFields)) {
        this.formFields[key] = null
      }
    },

    toggleFormVisibility (visibility) {
      this.isFormVisible = visibility
    },

    updateSimilarStatementSubmitter (index) {
      const payload = {
        type: 'SimilarStatementSubmitter',
        id: this.listEntries[index].id,
        attributes: this.getSimilarStatementSubmitterAttributes(index)
      }

      dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'SimilarStatementSubmitter', resourceId: this.listEntries[index].id }), {}, { data: payload })
        .then(response => { checkResponse(response) })
        .then(() => {
          // Update local state - similarStatementSubmitter.
          this.setSimilarStatementSubmitter(payload)
          dplan.notify.notify('confirm', Translator.trans('confirm.entry.updated'))
        })
        .catch(() => {
          dplan.notify.notify('error', Translator.trans('error.entry.updated'))
        })
    }
  },

  mounted () {
    this.loadInitialListEntries()

    this.$on('delete', (index) => {
      this.updateStatement({
        id: this.statementId,
        relationship: 'similarStatementSubmitters',
        action: 'remove',
        value: {
          id: this.listEntries[index].id,
          type: 'SimilarStatementSubmitter'
        }
      })

      this.listEntries.splice(index, 1)

      if (this.isRequestFormPost === false) {
        this.deleteSimilarStatementSubmitter()
      }
      if (this.isRequestFormPost) {
        this.resetFormFields()
      }
    })

    this.$on('showUpdateForm', (index) => {
      this.formFields.submitterCity = this.listEntries[index].submitterCity
      this.formFields.submitterName = this.listEntries[index].submitterName
      this.formFields.submitterAddress = this.listEntries[index].submitterAddress
      this.formFields.submitterHouseNumber = this.listEntries[index].submitterHouseNumber
      this.formFields.submitterPostalCode = this.listEntries[index].submitterPostalCode
      this.formFields.submitterEmailAddress = this.listEntries[index].submitterEmailAddress
    })
  }
}
</script>
