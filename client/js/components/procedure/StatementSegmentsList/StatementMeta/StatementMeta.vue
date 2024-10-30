<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="c-statement-meta-box mb-2 flex flex-col">
    <div class="relative flex border--bottom pb-2">
      <h2 class="mb-0 text-lg">
        {{ Translator.trans('statement.metadata') }}
      </h2>
      <button
        class="btn--blank o-link--default ml-auto"
        @click="close">
        <dp-icon icon="close" />
      </button>
    </div>

    <div class="flex">
      <div class="sticky top-[70px] mt-2 basis-1/4 max-h-11">
        <ul
          aria-label="Metadaten Menü"
          class="pr-5"
          role="menu" >
          <li
            v-for="entry in menuEntries"
            :class="{
                'bg-selected': activeItem === entry.id
              }"
            class="p-1.5 rounded"
            role="presentation">
            <button
              class="text-left"
              role="menuitem"
              v-text=Translator.trans(entry.transKey)
              @click="setActiveItem(entry.id)"/>
          </li>
        </ul>
      </div>
      <form
        class="mt-2 pt-1.5 mr-5 basis-3/4"
        data-dp-validate="statementMetaData">

        <statement-entry
          :editable="editable"
          :statement="statement"
          :submit-type-options="submitTypeOptions"
          @save="(data) => save(data)"/>
<!--        <statement-submitter/>-->
<!--        <statement-publication-and-voting/>-->

        <similar-statement-submitters
          class="mb-4"
          :editable="editable"
          :procedure-id="procedure.id"
          :similar-statement-submitters="similarStatementSubmitters"
          :statement-id="statement.id" />

        <!-- need to add statement.attributes.counties and availableCounties in the BE (Array) -->
        <statement-meta-multiselect
          v-if="hasPermission('field_statement_county')"
          :editable="editable"
          :label="Translator.trans('counties')"
          name="counties"
          :options="availableCounties"
          :value="localStatement.attributes.counties"
          @change="updateLocalStatementProperties" />

        <!-- need to add statement.attributes.municipalities and availableMunicipalities in the BE (Array) -->
        <statement-meta-multiselect
          v-if="hasPermission('field_statement_municipality') && formDefinitions.mapAndCountyReference.enabled"
          :editable="editable"
          :label="Translator.trans('municipalities')"
          name="municipalities"
          :options="availableMunicipalities"
          :value="localStatement.attributes.municipalities"
          @change="updateLocalStatementProperties" />

        <!-- need to add statement.attributes.priorityAreas and availablePriorityAreas in the BE (Array) -->
        <statement-meta-multiselect
          v-if="procedureStatementPriorityArea && formDefinitions.mapAndCountyReference.enabled"
          :editable="editable"
          :label="Translator.trans('priorityAreas')"
          name="priorityAreas"
          :options="availablePriorityAreas"
          :value="localStatement.attributes.priorityAreas"
          @change="updateLocalStatementProperties" />

        <!--    <dp-button-row-->
        <!--      v-if="editable"-->
        <!--      class="u-mt-0_5 w-full"-->
        <!--      primary-->
        <!--      secondary-->
        <!--      @primary-action="dpValidateAction('statementMetaData', save, false)"-->
        <!--      @secondary-action="reset" />-->

        <fieldset>
          <legend
            id="locationAndDocuments"
            class="mb-3 color-text-muted font-normal">
            {{ Translator.trans('location.and.document.reference') }}
          </legend>
          <div class="font-semibold mb-1">
            {{ Translator.trans('location') }}
          </div>
          <dp-button
            :aria-label="Translator.trans('location.reference_view')"
            :text="Translator.trans('see')"
            variant="outline" />
        </fieldset>

        <fieldset>
          <legend
            id="attachments"
            class="mb-3 color-text-muted font-normal">
            {{ Translator.trans('attachments') }}
          </legend>
          <statement-meta-attachments
            :attachments="attachments"
            :editable="editable"
            :procedure-id="procedure.id"
            :statement-id="statement.id"
            @change="(value) => emitInput('attachments', value)" />
        </fieldset>
      </form>
    </div>
  </div>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpContextualHelp,
  DpDatepicker,
  DpIcon,
  DpInput,
  DpLabel,
  DpSelect,
  DpTextArea,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import SimilarStatementSubmitters from '@DpJs/components/procedure/Shared/SimilarStatementSubmitters/SimilarStatementSubmitters'
import StatementEntry from './StatementEntry'
import StatementMetaAttachments from './StatementMetaAttachments'
import StatementMetaMultiselect from './StatementMetaMultiselect'
import StatementPublicationAndVoting from './StatementPublicationAndVoting'
import StatementPublish from '@DpJs/components/statement/statement/StatementPublish'
import StatementSubmitter from './StatementSubmitter'
import StatementVoter from '@DpJs/components/statement/voter/StatementVoter'

const convert = (dateString) => {
  const date = dateString.split('T')[0].split('-')
  return date[2] + '.' + date[1] + '.' + date[0]
}

export default {
  name: 'StatementMeta',

  components: {
    DpButton,
    DpButtonRow,
    DpContextualHelp,
    DpDatepicker,
    DpIcon,
    DpInput,
    DpLabel,
    DpSelect,
    DpTextArea,
    SimilarStatementSubmitters,
    StatementEntry,
    StatementMetaAttachments,
    StatementMetaMultiselect,
    StatementPublicationAndVoting,
    StatementPublish,
    StatementSubmitter,
    StatementVoter
  },

  mixins: [dpValidateMixin],

  props: {
    attachments: {
      type: Object,
      required: true
    },

    availableCounties: {
      type: Array,
      required: false,
      default: () => []
    },

    availableMunicipalities: {
      type: Array,
      required: false,
      default: () => []
    },

    availablePriorityAreas: {
      type: Array,
      required: false,
      default: () => []
    },

    currentUserId: {
      type: String,
      required: false,
      default: ''
    },

    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    procedure: {
      type: Object,
      required: true
    },

    procedureStatementPriorityArea: {
      type: Boolean,
      required: false,
      default: false
    },

    statement: {
      type: Object,
      required: true
    },

    statementFormDefinitions: {
      required: true,
      type: Object
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      activeItem: 'entry',
      finalMailDefaultText: '',
      localStatement: null,
      menuEntries: [
        { id: 'entry', transKey: 'entry' },
        { id: 'submitter', transKey: 'submitted.author' },
        { id: 'publicationAndVoting', transKey: 'publication.and.voting' },
        { id: 'locationAndDocuments', transKey: 'location.and.document.reference' },
        { id: 'attachments', transKey: 'attachments' }
      ]
    }
  },

  computed: {
    ...mapState('Statement', {
      storageStatement: 'items'
    }),

    currentDate () {
      let today = new Date()
      const dd = today.getDate().toString().padStart(2, '0')
      const mm = (today.getMonth() + 1).toString().padEnd(2, '0') // January is 0
      const yyyy = today.getFullYear()

      today = dd + '.' + mm + '.' + yyyy
      return today
    },

    isCurrentUserAssigned () {
      if (this.storageStatement[this.statement.id].relationships.assignee.data) {
        return this.currentUserId === this.storageStatement[this.statement.id].relationships.assignee.data.id
      }
      return false
    },

    isStatementManual () {
      return this.statement.attributes.isManual
    },

    similarStatementSubmitters () {
      if (typeof this.statement.hasRelationship === 'function' && this.statement.hasRelationship('similarStatementSubmitters')) {
        return Object.values(this.statement.relationships.similarStatementSubmitters.list())
      }
      return null
    },

    statementSubmitterField () {
      const attr = this.localStatement.attributes
      let submitterField = 'authorName'
      // If submitter is an orga and name has a value
      if (attr.submitName && !attr.isSubmittedByCitizen) {
        submitterField = 'submitName'
      }

      return submitterField
    },

    statementSubmitterValue: {
      get () {
        return this.isSubmitterAnonymized() ? Translator.trans('anonymized') : this.localStatement.attributes[this.statementSubmitterField]
      },
      set (value) {
        this.localStatement.attributes[this.statementSubmitterField] = value
      }
    },

    submitterHelpText () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes
      let helpText = ''

      const isAnonymized = hasPermission('area_statement_anonymize') && submitterAndAuthorMetaDataAnonymized

      if (consentRevoked) {
        helpText = Translator.trans('personal.data.usage.revoked')

        if (isAnonymized) {
          helpText = helpText + `<br><br>${Translator.trans('statement.anonymized.submitter.data')}`
        }
      }

      if (!consentRevoked && isAnonymized) {
        helpText = Translator.trans('statement.anonymized.submitter.data')
      }

      return helpText
    },

    submitterRole () {
      const isSubmittedByCitizen = this.localStatement.attributes.isSubmittedByCitizen &&
        this.localStatement.attributes.submitterRole !== 'publicagency'

      return isSubmittedByCitizen ? Translator.trans('role.citizen') : Translator.trans('institution')
    },

    submitType () {
      if (!this.statement.attributes.submitType) {
        return '-'
      }
      const option = this.submitTypeOptions.find(option => option.value === this.statement.attributes.submitType)
      return option ? Translator.trans(option.label) : ''
    }
  },

  methods: {
    ...mapActions('Statement', {
      restoreStatementAction: 'restoreFromInitial'
    }),

    ...mapMutations('Statement', {
      setStatement: 'setItem'
    }),

    close () {
      this.reset()
      this.$emit('close')
    },

    // TO DO: Deprecated? Remove?
    emitInput (fieldName, value) {
      this.$emit('input', { fieldName, value })
    },

    convertDate (date) {
      if (!date) {
        return ''
      }
      return date.match(/[0-9]{2}.[0-9]{2}.[0-9]{4}/)
        ? date
        : convert(date)
    },

    isSubmitterAnonymized () {
      const { consentRevoked, submitterAndAuthorMetaDataAnonymized } = this.localStatement.attributes

      return consentRevoked || submitterAndAuthorMetaDataAnonymized
    },

    reset () {
      this.setInitValues()
    },

    save (data) {
      this.$emit('save', data)
    },

    scrollToItem (id) {
      const element = document.querySelector(`#${id}`)
      if (element) {
        const headerOffset = 62
        const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
        const offsetPosition = elementPosition - headerOffset

        window.scrollTo({
          top: offsetPosition,
          behavior: 'smooth'
        })
      }
    },

    setActiveItem (id) {
      this.activeItem = id
      this.scrollToItem(id)
    },

    setDate (val, field) {
      this.localStatement.attributes[field] = val
      this.emitInput(field, val)
    },

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
      this.localStatement.attributes.authoredDate = this.convertDate(this.localStatement.attributes.authoredDate)
      this.localStatement.attributes.submitDate = this.convertDate(this.localStatement.attributes.submitDate)

      this.finalMailDefaultText = Translator.trans('statement.send.final_mail.default', {
        hasStatementText: this.localStatement.attributes.fullText.length < 2000 ? 0 : 1,
        orgaName: this.procedure.orgaName,
        procedureName: this.procedure.name,
        statementText: this.localStatement.attributes.fullText,
        statementRecommendation: this.localStatement.attributes.recommendation
      })
    },

    syncAuthorAndSubmitter () {
      this.localStatement.attributes.submitName = this.localStatement.attributes.authorName
    },

    updateLocalStatementProperties (value, field) {
      this.localStatement.attributes[field] = value
      this.localStatement.attributes[field].sort((a, b) => a.name.localeCompare(b.name))
    }
  },

  created () {
    this.setInitValues()
  }
}
</script>
