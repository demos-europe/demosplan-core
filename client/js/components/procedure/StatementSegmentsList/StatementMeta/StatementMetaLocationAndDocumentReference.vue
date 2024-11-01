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
          data-cy="statement:elementSelect"
          :label="{
          text: Translator.trans('plandocument')
        }"
          :options="elementsOptions"
          v-model="selectedElementId" />

        <dp-select
          data-cy="statement:paragraphSelect"
          :label="{
          text: Translator.trans('paragraph')
        }"
          :options="paragraphOptions"
          v-model="selectedParagraphId" />
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
        <div>
          <dt class="font-semibold">
            {{ Translator.trans('paragraph') }}
          </dt>
          <dd>
            {{ selectedParagraphTitle }}
          </dd>
        </div>
      </dl>

      <dp-button-row
        v-if="editable"
        class="w-full"
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
      selectedElementId: this.initiallySelectedElementId,
      selectedParagraphId: this.initiallySelectedParagraphId
    }
  },

  computed: {
    ...mapState('ElementsDetails', {
      elements: 'items'
    }),

    elementsOptions () {
      return Object.values(this.elements).map(element => ({
        label: element.attributes.title,
        value: element.id
      })
      )
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

    selectedParagraphTitle () {
      const paragraphs = this.getParagraphs()

      return paragraphs.length > 0
        ? paragraphs.find(paragraph => paragraph.id === this.selectedParagraphId)?.attributes?.title
        : '-'
    }
  },

  methods: {
    ...mapActions('ElementsDetails', {
      getElementsDetailsAction: 'list'
    }),

    getParagraphs () {
      const selectedElement = this.elements[this.selectedElementId]

      return selectedElement?.relationships?.paragraphs?.data.length > 0
        ?  Object.values(selectedElement?.relationships?.paragraphs.list())
        : []
    },

    reset () {
      this.setInitialStatementData()
    },

    save () {
      this.$emit('save', this.localStatement)
    },

    setInitialStatementData () {
      this.localStatement = { ...this.statement }
    },

    toggleLocationModal () {
      const polygon = this.statement.attributes.polygon ? JSON.parse(this.statement.attributes.polygon) : null

      if (polygon) {
        this.$refs.locationReferenceModal.toggleModal(polygon)
      }
    }
  },

  created () {
    this.setInitialStatementData()
  },

  mounted () {
    this.getElementsDetailsAction({
      fields: {
        ElementsDetails: [
          'paragraphs',
          'title'
        ].join(),
        Paragraph: [
          'title'
        ].join()
      },
      include: [
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
