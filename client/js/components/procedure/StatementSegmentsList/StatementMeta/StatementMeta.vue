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
          role="menu">
          <li
            v-for="entry in filterMenue"
            :class="{
              'bg-selected': activeItem === entry.id
            }"
            class="p-1.5 rounded"
            role="presentation">
            <button
              class="text-left"
              role="menuitem"
              v-text="Translator.trans(entry.transKey)"
              @click="setActiveItem(entry.id)" />
          </li>
        </ul>
      </div>
      <form
        class="mt-2 pt-1.5 mr-5 basis-3/4 max-w-[80%]"
        data-dp-validate="statementMetaData">
        <statement-entry
          :editable="editable"
          :statement="statement"
          :submit-type-options="submitTypeOptions"
          @save="(data) => save(data)" />

        <statement-submitter
          :editable="editable"
          :procedure="procedure"
          :statement="statement"
          :statement-form-definitions="statementFormDefinitions"
          @save="(data) => save(data)" />

        <statement-publication-and-voting
          v-if="hasPermission('feature_statements_vote') || hasPermission('feature_statements_publication')"
          :editable="editable"
          :statement="statement"
          @save="(data) => save(data)"
          @updatedVoters="() => $emit('updatedVoters')" />

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

        <statement-meta-location-and-document-reference
          v-if="hasPermission('feature_statements_location_and_document_refrence')"
          :editable="editable"
          :initially-selected-document-id="initiallySelectedDocumentId"
          :initially-selected-element-id="initiallySelectedElementId"
          :initially-selected-paragraph-id="initiallySelectedParagraphId"
          :procedure-id="procedure.id"
          :statement="statement"
          @save="updatedStatement => save(updatedStatement)" />

        <statement-meta-attachments
          :initial-attachments="attachments"
          :editable="editable"
          :procedure-id="procedure.id"
          :statement-id="statement.id"
          @change="(value) => emitInput('attachments', value)" />
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
  hasAnyPermissions,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import StatementEntry from './StatementEntry'
import StatementMetaAttachments from './StatementMetaAttachments'
import StatementMetaLocationAndDocumentReference from './StatementMetaLocationAndDocumentReference'
import StatementMetaMultiselect from './StatementMetaMultiselect'
import StatementPublicationAndVoting from './StatementPublicationAndVoting'
import StatementSubmitter from './StatementSubmitter'

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
    StatementEntry,
    StatementMetaAttachments,
    StatementMetaLocationAndDocumentReference,
    StatementMetaMultiselect,
    StatementPublicationAndVoting,
    StatementSubmitter
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
      isScrolling: false,
      localStatement: null,
      menuEntries: [
        { id: 'entry', transKey: 'entry' },
        { id: 'submitter', transKey: 'submitted.author' },
        { id: 'publicationAndVoting', transKey: 'publication.and.voting', condition: hasAnyPermissions(['feature_statements_vote', 'feature_statements_publication']) },
        { id: 'locationAndDocuments', transKey: 'location.and.document.reference', condition: hasPermission('feature_statements_location_and_document_refrence') },
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

    filterMenue () {
      return this.menuEntries.filter(entry => entry.condition ?? true)
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

    initiallySelectedDocumentId () {
      return this.statement.relationships.document?.data ? this.statement.relationships.document.get()?.id : ''
    },

    initiallySelectedElementId () {
      return this.statement.relationships.elements?.data ? this.statement.relationships.elements.get()?.id : ''
    },

    initiallySelectedParagraphId () {
      return this.statement.attributes.paragraphParentId || ''
    },

    // TO DO: Is this still needed?
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

    handleScroll () {
      if (this.isScrolling) return

      const sections = this.menuEntries.map(entry => document.querySelector(`#${entry.id}`))
      const scrollPosition = window.scrollY + 62

      for (let i = sections.length - 1; i >= 0; i--) {
        const section = sections[i]
        if (section && section.offsetTop <= scrollPosition) {
          this.activeItem = this.menuEntries[i].id
          break
        }
      }
    },

    reset () {
      this.setInitValues()
    },

    save (data) {
      this.$emit('save', data)
    },

    scrollToItem (id) {
      this.isScrolling = true
      const element = document.querySelector(`#${id}`)
      if (element) {
        const headerOffset = 62
        const elementPosition = element.getBoundingClientRect().top + window.pageYOffset
        const offsetPosition = elementPosition - headerOffset

        const onScrollEnd = () => {
          this.isScrolling = false
          window.removeEventListener('scrollend', onScrollEnd)
        }

        window.addEventListener('scrollend', onScrollEnd)

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

    setInitValues () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))

      this.finalMailDefaultText = Translator.trans('statement.send.final_mail.default', {
        hasStatementText: this.localStatement.attributes.fullText.length < 2000 ? 0 : 1,
        orgaName: this.procedure.orgaName,
        procedureName: this.procedure.name,
        statementText: this.localStatement.attributes.fullText,
        statementRecommendation: this.localStatement.attributes.recommendation
      })
    },

    updateLocalStatementProperties (value, field) {
      this.localStatement.attributes[field] = value
      this.localStatement.attributes[field].sort((a, b) => a.name.localeCompare(b.name))
    }
  },

  created () {
    this.setInitValues()
  },

  mounted () {
    window.addEventListener('scroll', this.handleScroll)
  },

  beforeDestroy () {
    window.removeEventListener('scroll', this.handleScroll)
  }
}
</script>
