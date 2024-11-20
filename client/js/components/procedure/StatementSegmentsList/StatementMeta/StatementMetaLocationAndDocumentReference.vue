<template>
  <fieldset data-dp-validate="statementLocationAndDocumentReference">
    <legend
      id="locationAndDocuments"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans( hasPermission('field_statement_polygon') ? 'location.and.document.reference' : 'document.reference') }}
    </legend>

    <!-- Location reference -->
    <div
      v-if="hasPermission('field_statement_polygon')"
      class="font-semibold mb-1">
      {{ Translator.trans('location') }}
    </div>

    <div class="space-y-4">
      <div v-if="hasPermission('field_statement_polygon')">
        <template v-if="statement.attributes.polygon">
          <dp-button
            :aria-label="Translator.trans('location.reference_view')"
            data-cy="statementMeta:toggleLocationReference"
            :text="Translator.trans('map.view')"
            variant="outline"
            @click="toggleLocationModal" />
          <dp-map-modal
            ref="locationReferenceModal"
            :procedure-id="procedureId" />
        </template>
        <template v-else>
          -
        </template>
      </div>

      <!-- Document reference -->
      <div
        v-if="editable"
        class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
        <dp-select
          v-model="selectedElementId"
          data-cy="statement:elementSelect"
          :label="{
            text: Translator.trans('plandocument')
          }"
          :options="elementsOptions"
          @select="handleSelect" />

        <dp-select
          v-if="paragraphOptions.length > 0"
          data-cy="statement:paragraphSelect"
          :label="{
            text: Translator.trans('paragraph')
          }"
          :options="paragraphOptions"
          required
          v-model="selectedParagraphId" />

        <dp-select
          v-if="documentOptions.length > 0"
          data-cy="statement:documentSelect"
          :label="{
            text: Translator.trans('file')
          }"
          :options="documentOptions"
          required
          v-model="selectedDocumentId" />
      </div>

      <dl
        v-else
        class="grid grid-cols-1 gap-x-4 md:grid-cols-2 mb-0">
        <div>
          <dt class="font-semibold">
            {{ Translator.trans('plandocument') }}
          </dt>
          <dd>
            {{ elements[selectedElementId] ? elements[selectedElementId].attributes.title : '-' }}
          </dd>
        </div>
        <div v-if="selectedParagraphId">
          <dt class="font-semibold">
            {{ Translator.trans('paragraph') }}
          </dt>
          <dd>
            {{ selectedParagraphTitle }}
          </dd>
        </div>
        <div v-if="selectedDocumentId">
          <dt class="font-semibold">
            {{ Translator.trans('file') }}
          </dt>
          <dd>
            {{ selectedDocumentTitle }}
          </dd>
        </div>
      </dl>

      <dp-button-row
        v-if="editable"
        class="w-full"
        data-cy="statementLocationAndDocumentReference:buttonRow"
        :disabled="isButtonRowDisabled"
        primary
        secondary
        @primary-action="dpValidateAction('statementLocationAndDocumentReference', save, false)"
        @secondary-action="reset" />
    </div>
  </fieldset>
</template>

<script>
import {
  DpButton,
  DpButtonRow,
  DpSelect,
  dpValidateMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'StatementMetaLocationAndDocumentReference',

  components: {
    DpButton,
    DpButtonRow,
    DpMapModal: () => import('@DpJs/components/statement/assessmentTable/DpMapModal'),
    DpSelect
  },

  mixins: [dpValidateMixin],

  props: {
    editable: {
      type: Boolean,
      required: false,
      default: false
    },

    initiallySelectedDocumentId: {
      type: String,
      required: false,
      default: ''
    },

    initiallySelectedElementId: {
      type: String,
      required: false,
      default: ''
    },

    initiallySelectedParagraphId: {
      type: String,
      required: false,
      default: ''
    },

    procedureId: {
      type: String,
      required: true
    },

    statement: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      localStatement: null,
      selectedDocumentId: this.initiallySelectedDocumentId,
      selectedElementId: this.initiallySelectedElementId,
      selectedParagraphId: this.initiallySelectedParagraphId
    }
  },

  computed: {
    ...mapState('ElementsDetails', {
      elements: 'items'
    }),

    documentOptions () {
      const documents = this.getDocuments()

      return documents.length > 0
        ? documents.map(document => ({
          label: document.attributes.title,
          value: document.id
        }))
        : []
    },

    elementsOptions () {
      return Object.values(this.elements).map(element => ({
        label: element.attributes.title,
        value: element.id
      })
      )
    },

    isButtonRowDisabled () {
      const elementIsChanged = this.selectedElementId !== this.initiallySelectedElementId
      const paragraphIsChanged = this.selectedParagraphId !== this.initiallySelectedParagraphId
      const documentIsChanged = this.selectedDocumentId !== this.initiallySelectedDocumentId

      return !elementIsChanged
        || (elementIsChanged && ((this.paragraphOptions.length > 0 && !paragraphIsChanged) || (this.documentOptions.length > 0 && !documentIsChanged)))
    },

    paragraphOptions () {
      const paragraphs = this.getParagraphs()

      return paragraphs.length > 0
        ? paragraphs.map(paragraph => ({
          label: paragraph.attributes.title,
          value: paragraph.id
        }))
        : []
    },

    selectedDocumentTitle () {
      const documents = this.getDocuments()

      return documents.length > 0
        ? documents.find(document => document.id === this.selectedDocumentId)?.attributes?.title || '-'
        : '-'
    },

    selectedParagraphTitle () {
      const paragraphs = this.getParagraphs()

      return paragraphs.length > 0
        ? paragraphs.find(paragraph => paragraph.id === this.selectedParagraphId)?.attributes?.title || '-'
        : '-'
    }
  },

  methods: {
    ...mapActions('ElementsDetails', {
      getElementsDetailsAction: 'list'
    }),

    getDocuments () {
      const selectedElement = this.elements[this.selectedElementId]

      return selectedElement?.relationships?.documents?.data.length > 0
        ? Object.values(selectedElement?.relationships?.documents.list())
        : []
    },

    getParagraphs () {
      const selectedElement = this.elements[this.selectedElementId]

      return selectedElement?.relationships?.paragraphs?.data.length > 0
        ?  Object.values(selectedElement?.relationships?.paragraphs.list())
        : []
    },

    handleSelect () {
      this.unsetSelectedParagraphId()
      this.unsetSelectedDocumentId()
    },

    reset () {
      this.setInitiallySelectedElementId()
      this.selectedParagraphId = this.initiallySelectedParagraphId
    },

    save () {
      if (this.selectedElementId) {
        this.localStatement.relationships.elements = {
          data: {
            id: this.selectedElementId,
            type: 'ElementsDetails'
          }
        }
      }

      if (this.selectedParagraphId !== this.initiallySelectedParagraphId) {
        if (this.selectedParagraphId === '') {
          this.localStatement.attributes.paragraphParentId = null
        } else {
          this.localStatement.attributes.paragraphParentId = this.selectedParagraphId
        }
      }

      if (this.selectedDocumentId !== this.initiallySelectedDocumentId) {
        if (this.selectedDocumentId === '') {
          this.localStatement.relationships.document = {
            data: null
          }
        } else {
          this.localStatement.relationships.document = {
            data: {
              id: this.selectedDocumentId,
              type: 'SingleDocument'
            }
          }
        }
      }

      this.$emit('save', this.localStatement)
    },

    /*
      * Set id of initially selected element
      * If no element is selected, set it to 'Gesamtstellungnahme'
     */
    setInitiallySelectedElementId () {
      this.selectedElementId = this.initiallySelectedElementId
    },

    setInitialStatementData () {
      this.localStatement = JSON.parse(JSON.stringify(this.statement))
    },

    toggleLocationModal () {
      const polygon = this.statement.attributes.polygon ? JSON.parse(this.statement.attributes.polygon) : null

      if (polygon) {
        this.$refs.locationReferenceModal.toggleModal(polygon)
      }
    },

    unsetSelectedDocumentId () {
      this.selectedDocumentId = ''
    },

    unsetSelectedParagraphId () {
      this.selectedParagraphId = ''
    },
  },

  created () {
    this.setInitialStatementData()
  },

  mounted () {
    this.getElementsDetailsAction({
      fields: {
        ElementsDetails: [
          'documents',
          'paragraphs',
          'title'
        ].join(),
        Paragraph: [
          'title'
        ].join(),
        SingleDocument: [
          'title'
        ].join()
      },
      include: [
        'documents',
        'paragraphs'
      ].join(),
      filter: {
        procedureId: {
          condition: {
            path: 'procedure.id',
            value: this.procedureId
          }
        }
      }
    })
  }
}
</script>
