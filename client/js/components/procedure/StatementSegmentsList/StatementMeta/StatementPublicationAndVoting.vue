<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <fieldset data-dp-validate="statementPublicationAndVotingData">
    <legend
      id="publicationAndVoting"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('publication.and.voting') }}
    </legend>
    <div class="font-semibold mb-3">
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

    <statement-voter
      id="dp-statement-voter"
      :anonym-votes-string="localStatement.attributes.numberOfAnonymVotes?.toString() || '0'"
      :init-voters="statement.attributes.votes"
      :is-manual="statement.attributes.isManual ? 'true' : ''"
      :public-allowed="statement.attributes.publicVerified === 'publication_approved'"
      :readonly="!editable ? '1' : '0'"
      @updateAnonymVotes="val => localStatement.attributes.numberOfAnonymVotes = val"
      @updateVoter="updateVoter"/>

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
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'

export default {
  name: 'StatementPublicationAndVoting',

  components: {
    DpButtonRow,
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
      localStatement: null
    }
  },

  methods: {
    reset () {
      this.setInitValues()
    },

    save () {
      this.$emit('save', this.localStatement)
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
    },

    updateVoter (data) {
      const newVoter = {
        city: data.userCity,
        createdByCitizen: data.role === 0,
        departmentName: data.departmentName,
        email: data.userMail,
        name: data.userName,
        organisationName: data.organisationName,
        postcode: data.userPostcode,
      }

      // TODO: Implement Api post (only after save?)
    },
  },

  created() {
    this.setInitValues()
  },
}
</script>
