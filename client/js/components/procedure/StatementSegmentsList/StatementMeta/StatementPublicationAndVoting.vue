<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset
    class="c-statement-meta-voting"
    data-dp-validate="statementPublicationAndVotingData">
    <legend
      id="publicationAndVoting"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('publication.and.voting') }}
    </legend>
    <div class="font-semibold">
      {{ Translator.trans('publish.on.platform') }}
    </div>

    <statement-publish
      class="mb-4"
      :editable="editable && statement.attributes.publicVerified === 'publication_pending'"
      :files-length="statement.relationships?.files?.length || '0'"
      :is-manual="statement.attributes.isManual"
      :public-verified="localStatement.attributes.publicVerified"
      :public-verified-trans-key="statement.attributes.publicVerifiedTranslation"
      :submitter-email="statement.attributes.submitterEmailAddress"
      @update="val => localStatement.attributes.publicVerified = val" />

    <div class="font-semibold">
      {{ Translator.trans('statement.voter') }}
    </div>
    <p
      class="color-text-muted"
      v-text="Translator.trans('statement_vote.length', { count: votesLength })" />
    <dp-loading v-if="isLoading" />
    <dp-editable-list
      v-else
      :entries="votes"
      :has-permission-to-edit="editable && statement.attributes.isManual"
      :translation-keys="translationKeys"
      ref="listComponent"
      @saveEntry="index => dpValidateAction('newVoterForm', () => addVote(index), false)">
      <template v-slot:list="{entry, index}">
        <span v-if="entry.attributes.name" class="voteEntry">{{ entry.attributes.name }}</span>
        <span v-if="entry.attributes.organisationName" class="voteEntry">{{ entry.attributes.organisationName }}</span>
        <span v-if="entry.attributes.departmentName" class="voteEntry">{{ entry.attributes.departmentName }}</span>
        <span v-if="entry.attributes.postcode" class="voteEntry">{{ entry.attributes.postcode }}</span>
        <span v-if="entry.attributes.city" class="voteEntry">{{ entry.attributes.city }}</span>
        <span v-if="entry.attributes.email" class="voteEntry">{{ entry.attributes.email }}</span>
      </template>
      <template v-slot:form>
        <div
          data-dp-validate="newVoterForm"
          v-if="editable && statement.attributes.isManual"
          class="space-stack-s border-t py-3">
          <!-- Role -->
          <div class="flex">
            <dp-radio
              id="createdByCitizen_true"
              data-cy="statementVoter:roleCitizen"
              :label="{
                text: Translator.trans('role.citizen')
              }"
              value="true"
              :checked="formFields.createdByCitizen"
              @change="formFields.createdByCitizen = true" />
            <dp-radio
              id="createdByCitizen_false"
              class="ml-5"
              data-cy="statementVoter:invitableInstitution"
              :label="{
                text: Translator.trans('invitable_institution')
              }"
              value="false"
              :checked="formFields.createdByCitizen === false"
              @change="formFields.createdByCitizen = false" />
          </div>
          <div
            v-show="isInstitutionParticipation && (hasPermission('field_statement_meta_orga_name') || hasPermission('field_statement_meta_orga_department_name'))"
            class="flex">
            <dp-input
              v-show="hasPermission('field_statement_meta_orga_name')"
              id="voter_publicagency"
              data-cy="voterPublicAgency"
              v-model="formFields.organisationName"
              class="pr-2"
              :label="{
                text: Translator.trans('invitable_institution')
              }" />
            <dp-input
              v-show="hasPermission('field_statement_meta_orga_department_name')"
              id="voter_department"
              data-cy="voterDepartment"
              v-model="formFields.departmentName"
              class="pl-2"
              :label="{
                text: Translator.trans('department')
              }" />
          </div>

          <div class="flex">
            <dp-input
              v-if="hasPermission('field_statement_meta_submit_name')"
              id="voter_username"
              data-cy="voterUsername"
              v-model="formFields.name"
              class="pr-2"
              :label="{
                text: Translator.trans('statement.form.name')
              }" />
            <dp-input
              v-if="hasPermission('field_statement_meta_email')"
              id="voter_email"
              data-cy="voterEmail"
              v-model="formFields.email"
              class="pl-2"
              :label="{
                text: Translator.trans('email')
              }"
              type="email" />
          </div>

          <div class="flex w-1/2">
            <dp-input
              v-if="hasPermission('field_statement_meta_postal_code')"
              id="voter_postalcode"
              data-cy="voterPostalCode"
              v-model="formFields.postcode"
              class="u-1-of-4 pr-2"
              :label="{
                text: Translator.trans('postalcode')
              }"
              pattern="^[0-9]{4,5}$" />
            <dp-input
              v-if="hasPermission('field_statement_meta_city')"
              id="voter_city"
              data-cy="voterCity"
              v-model="formFields.city"
              class="px-2"
              :class="hasPermission('field_statement_meta_postal_code') ? ' u-3-of-4' : ''"
              :label="{
                text: Translator.trans('city')
              }" />
          </div>
        </div>
      </template>
    </dp-editable-list>

    <!-- Anonymous voters -->
    <div class="w-1/4">
      <dp-input
        id="numberOfAnonymVotes"
        v-model.number="localStatement.attributes.numberOfAnonymVotes"
        class="mt-4"
        data-cy="numberOfAnonymVotes"
        :disabled="!editable"
        :label="{
          text: Translator.trans('statement.voter.anonym')
        }"
        name="numberOfAnonymVotes"
        type="number" />
    </div>

    <dp-button-row
      v-if="editable"
      class="mt-2 w-full"
      primary
      secondary
      @primary-action="dpValidateAction('statementPublicationAndVotingData', save, false)"
      @secondary-action="reset" />
  </fieldset>
</template>

<script>
import {
  DpButtonRow,
  DpEditableList,
  DpLoading,
  DpInput,
  DpRadio,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { v4 as uuid } from 'uuid'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'

export default {
  name: 'StatementPublicationAndVoting',

  components: {
    DpButtonRow,
    DpEditableList,
    DpLoading,
    DpInput,
    DpRadio,
    StatementPublish,
    StatementVoter
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    statement: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      localStatement: null,
      formFields: {
        createdByCitizen: true,
        organisationName: '',
        departmentName: '',
        name: '',
        email: '',
        postcode: '',
        city: ''
      },
      isLoading: false,
      translationKeys: {
        new: Translator.trans('statement.voter.add'),
        add: Translator.trans('statement.voter.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('statement.voter.update'),
        noEntries: '',
        delete: Translator.trans('statement.voter.delete')
      },
      votes: {},
      votesToDelete: []
    }
  },

  computed: {
    ...mapState('StatementVote', {
      initialVotes: 'initial',
      votesState: 'items'
    }),

    isInstitutionParticipation () {
      return hasPermission('feature_institution_participation') && this.formFields.createdByCitizen === false
    },

    votesLength: {
      get () {
        return Object.keys(this.votes).length
      }
    },
  },

  watch: {
    statement: {
      handler() {
        this.setInitValues();
      },
      deep: true
    }
  },

  methods: {
    ...mapMutations('StatementVote', {
      removeStatementVote: 'remove',
      resetStatementVote: 'resetItems',
      setStatementVote: 'setItem'
    }),

    ...mapActions('StatementVote', {
      createStatementVoteAction: 'create',
      deleteStatementVoteAction: 'delete',
      saveStatementVoteAction: 'save'
    }),

    addVote (index) {
      // TO DO: Do we need this?
      if (this.checkIfEmpty() === false) {
        let voteId = ''
        if (index === 'new') {
          voteId = `newItem${uuid()}`
        } else {
          voteId = index
        }

        const vote = {
          type: 'StatementVote',
          id: voteId,
          attributes: this.formFields
        }

        // due to a reactivity bug in vuex json api, we have to update the store and hold the data locally
        this.votes[voteId] = vote
        this.setStatementVote(vote)

        this.resetForm()
        this.$refs.listComponent.toggleFormVisibility(false)
        this.$refs.listComponent.currentlyUpdating = ''
      }
    },

    checkIfEmpty () {
      let isEmpty = true
      const fieldsToCheck = ['organisationName', 'departmentName', 'name', 'email', 'postcode', 'city']

      for (let i = 0; i < fieldsToCheck.length; i++) {
        if (this.formFields[fieldsToCheck[i]] !== '' && typeof this.formFields[fieldsToCheck[i]] !== 'undefined') {
          isEmpty = false
        }
      }
      return isEmpty
    },

    removeVote (id) {
      // Only send delete request if the vote is not a new one
      if (!id.includes('newItem')) {
        this.votesToDelete.push(this.votes[id])
      }
      // The Vuex-json-Api has a bug, where the store is not updated correctly,
      // so we have to remove the item from the store and the local data
      this.$delete(this.votes, id)
      this.removeStatementVote(id)
    },

    reset () {
      this.setInitValues()
      this.resetStore()
    },

    resetForm () {
      this.formFields = {
        createdByCitizen: true,
        organisationName: '',
        departmentName: '',
        name: '',
        email: '',
        postcode: '',
        city: ''
      }
    },

    resetStore () {
      // Remove items not in initial state
      for (const id in this.votes) {
        if (!this.initialVotes[id]) {
          this.removeVote(id)
        }
      }

      // Set items to their initial values
      for (const id in this.initialVotes) {
        this.setStatementVote({ ...this.initialVotes[id], id })
      }
    },

    save () {
      this.saveStatementVote()
    },

    saveStatementVote () {
      const createVotePromise = this.sendCreateVote()
      const updateVotePromise = this.sendUpdateVote()
      const deleteVotePromise = this.sendDeleteVote()

      const promises = [createVotePromise, updateVotePromise, deleteVotePromise].filter(promise => promise)

      if (promises.length === 0) {
        return // No requests to send
      }

      Promise.any(promises)
        .then(() => {
          this.$emit('updatedVoters')
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
        })
    },

    sendCreateVote () {
      const votesToCreate = Object.values(this.votes).filter(vote => vote.id.includes('newItem'))
      // We need a loading state here, because the added voter in store with fake id gets replaced with the real one after
      // BE response, otherwise UI blinks
      if (votesToCreate.length > 0) {
        this.isLoading = true
      }
      const promises = votesToCreate.map(vote => {
        const payload = {
          type: 'StatementVote',
          attributes: vote.attributes,
          relationships: {
            statement: {
              data: {
                type: 'Statement',
                id: this.statement.id
              }
            }
          }
        }
        return this.createStatementVoteAction(payload)
          .then(() => {
            this.$emit('updatedVoters')
            return true
          })
          .catch(() => {
            dplan.notify.error(Translator.trans('error.api.generic'))
            return false
          })
          .finally(() => {
            this.isLoading = false
          })
      })

      return Promise.all(promises).then(results => results.some(result => result))
    },

    sendDeleteVote () {
      const promises = this.votesToDelete.map(vote => {
        // TO DO: Must also be deleted from initial, or initial must be updated, works for update and create, but not for delete
        this.deleteStatementVoteAction(vote.id)
        .then(() => {
          this.votesToDelete = this.votesToDelete.filter(v => v.id !== vote.id)
          return true
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.api.generic'))
          return false
        })
      }).filter(Boolean) // Remove undefined values

      return Promise.all(promises).then(results => results.some(result => result))
    },

    sendUpdateVote () {
      const promises = Object.values(this.initialVotes).map(vote => {
        if (this.votes[vote.id]) {
          this.saveStatementVoteAction(vote.id)
          .then(() => {
            return true
          })
          .catch(() => {
            dplan.notify.error(Translator.trans('error.api.generic'))
            return false
          })
        }
      }).filter(Boolean) // Remove undefined values

      return Promise.all(promises).then(results => results.some(result => result))
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.numberOfAnonymVotes = this.statement.attributes.numberOfAnonymVotes.toString()
    },
  },

  created() {
    this.setInitValues()
  },

  mounted() {
    this.$on('showUpdateForm', (index) => {
      for (const key in this.formFields) {
        this.formFields[key] = Object.values(this.votes)[index]['attributes'][key]
      }
    })

    this.$on('delete', (voteId) => {
      const name = this.votes[voteId]?.attributes?.name ? this.votes[voteId].attributes.name : false
      if (dpconfirm(Translator.trans('statement_vote.delete_vote', { name: name }))) {
        this.removeVote(voteId)
        this.resetForm()
      }
    })

    this.votes = Object.assign({}, this.votesState)
  }
}
</script>
