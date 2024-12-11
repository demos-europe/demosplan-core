<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <form
      :action="Routing.generate(submitRouteName, { procedureId })"
      data-dp-validate="simplifiedNewStatementForm"
      enctype="multipart/form-data"
      method="post"
      ref="simplifiedNewStatementForm">
      <input
        type="hidden"
        name="r_action"
        value="new">
      <input
        v-if="statementImportEmailId !== null"
        type="hidden"
        name="r_statement_import_email_id"
        :value="statementImportEmailId">
      <input
        type="hidden"
        name="r_ident"
        :value="procedureId">
      <input
        name="_token"
        type="hidden"
        :value="csrfToken">

      <div class="u-mb">
        <dp-accordion
          data-cy="simplifiedNewStatementForm:userDetails"
          :title="Translator.trans('user.details')"
          :is-open="expandAll">
          <div class="u-mv">
            <dp-radio
              name="r_role"
              value="0"
              data-cy="roleInput:citizen"
              :id="`${instanceId}r_role_0`"
              :label="{
                text: Translator.trans('citizen')
              }"
              :checked="values.submitter.institution === false || values.submitter.institution === undefined"
              @change="setInstitutionValue(false)" />
            <dp-radio
              name="r_role"
              value="1"
              data-cy="roleInput:invitableInstitution"
              :id="`${instanceId}r_role_1`"
              :label="{
                text: Translator.trans('institution')
              }"
              :checked="values.submitter.institution === true"
              @change="setInstitutionValue(true)" />
          </div>

          <div class="space-stack-s">
            <!-- Additional institution fields: orga name, department name -->
            <div
              v-if="values.submitter.institution"
              :class="fieldsFullWidth ? 'space-stack-s' : 'layout'">
              <dp-input
                id="r_orga_name"
                data-cy="submitterForm:orgaName"
                v-model="values.submitter.orga"
                :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }"
                :label="{
                  text: Translator.trans('institution')
                }"
                name="r_orga_name" /><!--
           --><dp-input
                id="r_orga_department_name"
                data-cy="submitterForm:orgaDepartmentName"
                v-model="values.submitter.department"
                :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }"
                :label="{
                  text: Translator.trans('department')
                }"
                name="r_orga_department_name" />
            </div>

            <!-- Name, E-Mail Address -->
            <div :class="fieldsFullWidth ? 'space-stack-s' : 'layout'">
              <div :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }">
                <dp-input
                  id="r_author_name"
                  data-cy="submitterForm:authorName"
                  v-model="values.submitter.name"
                  :label="{
                    text: Translator.trans('name')
                  }"
                  name="r_author_name" />
              </div><!--
           --><div :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }">
                <dp-input
                  id="r_orga_email"
                  data-cy="submitterForm:orgaEmail"
                  v-model="values.submitter.email"
                  :label="{
                    text: Translator.trans('email')
                  }"
                  name="r_orga_email"
                  type="email" />
              </div>
            </div>

            <!-- `flex` is applied just to let the textarea grow -->
            <div :class="fieldsFullWidth ? 'space-stack-s' : 'layout flex'">
              <div
                class="space-stack-s"
                :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }">
                <!-- Street, House number -->
                <div class="o-form__group">
                  <dp-input
                    id="r_orga_street"
                    data-cy="submitterForm:orgaStreet"
                    v-model="values.submitter.street"
                    class="o-form__group-item"
                    :label="{
                      text: Translator.trans('street')
                    }"
                    name="r_orga_street" />
                  <dp-input
                    id="r_houseNumber"
                    data-cy="submitterForm:houseNumber"
                    v-model="values.submitter.housenumber"
                    class="o-form__group-item shrink"
                    :label="{
                      text: Translator.trans('street.number.short')
                    }"
                    name="r_houseNumber"
                    :size="3" />
                </div>

                <!-- PLZ, City -->
                <div class="o-form__group">
                  <dp-input
                    id="r_orga_postalcode"
                    data-cy="submitterForm:orgaPostalcode"
                    v-model="values.submitter.plz"
                    class="o-form__group-item shrink"
                    :label="{
                      text: Translator.trans('postalcode')
                    }"
                    name="r_orga_postalcode"
                    pattern="^[0-9]{5}$"
                    :size="5" />
                  <dp-input
                    id="r_orga_city"
                    data-cy="submitterForm:orgaCity"
                    v-model="values.submitter.ort"
                    class="o-form__group-item"
                    name="r_orga_city"
                    :label="{
                      text: Translator.trans('city')
                    }" />
                </div>
              </div><!--

              Note
           --><dp-text-area
                v-if="hasPermission('field_statement_memo')"
                data-cy="submitterForm:memo"
                :class="{ 'layout__item u-1-of-2': !fieldsFullWidth }"
                :grow-to-parent="!fieldsFullWidth"
                id="r_memo"
                :label="Translator.trans('memo')"
                name="r_memo"
                reduced-height
                v-model="values.memo" />
            </div>

            <similar-statement-submitters
              editable
              :fields-full-width="fieldsFullWidth"
              :procedure-id="procedureId"
              is-request-form-post />
          </div>
        </dp-accordion>
      </div>

      <div class="u-mb">
        <dp-accordion
          data-cy="simplifiedNewStatementForm:statementData"
          :title="Translator.trans('statement.data')"
          :is-open="expandAll">
          <!-- Einreichungsdatum, Verfassungsdatum -->
          <div
            class="u-mv"
            :class="{ 'u-pr-0_5 u-1-of-2 inline-block': !fieldsFullWidth }">
            <dp-label
              :text="Translator.trans('statement.date.submitted')"
              :hint="Translator.trans('explanation.statement.date')"
              for="r_submitted_date" />
            <dp-datepicker
              class="o-form__control-wrapper"
              data-cy="submitterForm:submittedDate"
              name="r_submitted_date"
              value=""
              :calendars-before="2"
              :max-date="nowDate"
              :min-date="values.authoredDate"
              id="r_submitted_date"
              v-model="values.submittedDate" />
          </div><!--
       --><div
            class="u-mb"
            :class="{ 'u-pl-0_5 u-1-of-2 inline-block': !fieldsFullWidth }">
            <dp-label
              :text="Translator.trans('statement.date.authored')"
              :hint="Translator.trans('explanation.statement.date.authored')"
              for="r_authored_date" />
            <dp-datepicker
              class="o-form__control-wrapper"
              data-cy="submitterForm:authoredDate"
              name="r_authored_date"
              value=""
              :calendars-before="2"
              :max-date="values.submittedDate || nowDate"
              id="r_authored_date"
              v-model="values.authoredDate" />
          </div>

          <!-- Art der Einreichung, Eingangsnummer -->
          <div
            class="u-mb"
            :class="{ 'u-pr-0_5 u-1-of-2 inline-block': !fieldsFullWidth }">
            <dp-select
              id="r_submit_type"
              data-cy="submitterForm:submitType"
              :label="{
                hint: Translator.trans('explanation.statement.submit.type'),
                text: Translator.trans('submit.type')
              }"
              name="r_submit_type"
              :options="submitTypeOptions"
              selected="unknown" />
          </div><!--
       --><div
            v-if="hasPermission('field_statement_intern_id')"
            class="u-mb"
            :class="{ 'u-pl-0_5 u-1-of-2 inline-block': !fieldsFullWidth }">
            <dp-input
              id="r_internId"
              data-cy="submitterForm:internId"
              :data-dp-validate-error="Translator.trans('validation.error.internId')"
              :label="{
                hint: Translator.trans('last.used') + ' ' + newestInternId,
                text: Translator.trans('internId'),
                tooltip: Translator.trans('validation.error.internId')
              }"
              name="r_internId"
              :pattern="internIdsPattern"
              value="" />
          </div>

          <!-- Hidden input for phase -->
          <input
            type="hidden"
            name="r_phase"
            :value="currentProcedurePhase">

          <!-- Tags -->
          <template v-if="hasPermission('feature_statements_tag')">
            <dp-label
              :text="Translator.trans('tags')"
              for="r_tags[]" />
            <dp-multiselect
              v-model="values.tags"
              class="u-mb"
              group-label="title"
              :group-select="false"
              group-values="tags"
              label="name"
              multiple
              :options="tags"
              track-by="id"
              @input="sortSelected('tags', 'title')">
              <template v-slot:option="{ props }">
                <span v-if="props.option.$isLabel">
                  {{ props.option.$groupLabel }}
                </span>
                <span v-else>
                  {{ props.option.title }}
                </span>
              </template>
              <template v-slot:tag="{ props }">
                <span class="multiselect__tag">
                  {{ props.option.title }}
                  <i
                    aria-hidden="true"
                    class="multiselect__tag-icon"
                    tabindex="1"
                    @click="props.remove(props.option)" />
                  <input
                    name="r_tags[]"
                    type="hidden"
                    :value="props.option.id">
                </span>
              </template>
            </dp-multiselect>
          </template>
        </dp-accordion>
      </div>

      <!-- Statement text -->
      <dp-label
        :text="Translator.trans('statement.text.short')"
        for="r_text"
        required />
      <dp-editor
        ref="statementText"
        :procedure-id="procedureId"
        :toolbar-items="{ linkButton: true }"
        required
        hidden-input="r_text"
        v-model="values.text" />

      <slot />

      <!-- File upload fields -->
      <template v-if="allowFileUpload">
        <dp-label
          :text="Translator.trans('attachment.original')"
          for="r_attachment_original"
          class="u-mt" />

        <dp-upload-files
          class="u-mb"
          id="r_attachment_original"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          name="r_attachment_original"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="1"
          needs-hidden-input
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint" />
      </template>
      <dp-label
        :text="Translator.trans('more.attachments')"
        for="r_upload"
        class="u-mt" />

      <dp-upload-files
        id="r_upload"
        name="r_upload"
        allowed-file-types="all"
        :basic-auth="dplan.settings.basicAuth"
        :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
        :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
        :max-number-of-files="1000"
        needs-hidden-input
        :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
        :tus-endpoint="dplan.paths.tusEndpoint" />

      <!-- When used from annotated pdf view, a hidden input with annotatedStatementPdf.id has to be sent to BE -->
      <input
        type="hidden"
        name="r_annotated_statement_pdf_id"
        :value="documentId"
        v-if="documentId !== ''">

      <dp-button-row
        :busy="isSaving"
        class="u-mv"
        data-cy="submitterForm"
        :href="Routing.generate('DemosPlan_procedure_dashboard', { procedure: procedureId })"
        primary
        secondary
        @primary-action="submit" />
    </form>
  </div>
</template>

<script>
import {
  DpAccordion,
  DpButtonRow,
  DpDatepicker,
  DpInput,
  DpLabel,
  DpMultiselect,
  DpRadio,
  DpSelect,
  DpTextArea,
  DpUploadFiles,
  dpValidateMixin
  , hasOwnProp
} from '@demos-europe/demosplan-ui'
import dayjs from 'dayjs'
import { defineAsyncComponent } from 'vue'
import SimilarStatementSubmitters from '@DpJs/components/procedure/Shared/SimilarStatementSubmitters/SimilarStatementSubmitters'
import { v4 as uuid } from 'uuid'

const submitterProperties = {
  date: '',
  department: '',
  email: '',
  institution: false,
  name: '',
  orga: '',
  ort: '',
  plz: ''
}

export default {
  name: 'DpSimplifiedNewStatementForm',

  components: {
    DpAccordion,
    DpButtonRow,
    DpDatepicker,
    DpInput,
    DpLabel,
    DpMultiselect,
    DpRadio,
    DpSelect,
    DpTextArea,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }),
    DpUploadFiles,
    SimilarStatementSubmitters
  },

  mixins: [dpValidateMixin],

  props: {
    allowFileUpload: {
      type: Boolean,
      required: false,
      default: false
    },

    csrfToken: {
      type: String,
      required: true
    },

    currentProcedurePhase: {
      type: String,
      required: false,
      default: 'analysis'
    },

    documentId: {
      type: String,
      required: false,
      default: ''
    },

    expandAll: {
      type: Boolean,
      required: false,
      default: false
    },

    fieldsFullWidth: {
      type: Boolean,
      required: false,
      default: false
    },

    initValues: {
      type: Object,
      required: false,
      default: () => ({
        authoredDate: '',
        quickSave: '',
        submittedDate: '',
        tags: [],
        text: '',
        submitter: submitterProperties
      })
    },

    newestInternId: {
      type: String,
      required: false,
      default: '-'
    },

    procedureId: {
      type: String,
      required: true
    },

    statementImportEmailId: {
      type: String,
      required: false,
      default: ''
    },

    submitRouteName: {
      type: String,
      required: false,
      default: 'dplan_simplified_new_statement_create'
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    tags: {
      type: Array,
      required: false,
      default: () => []
    },

    usedInternIds: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      isLoading: false,
      isSaving: false,
      values: {
        authoredDate: '',
        memo: '',
        quickSave: '',
        submittedDate: '',
        tags: [],
        text: '',
        submitter: submitterProperties
      }
    }
  },

  computed: {
    escapedUsedInternIds () {
      const specialCharEscaper = /\[|\\|\^|\$|\.|\||\?|\*|\+|\(|\)|\//g
      return this.usedInternIds.map(id => id.replace(specialCharEscaper, (specialChar) => `\\${specialChar}`))
    },

    internIdsPattern () {
      let pattern = ''
      if (this.escapedUsedInternIds.length > 0) {
        pattern = '^(?!(?:' + this.escapedUsedInternIds.join('|') + ')$)'
      }
      pattern = pattern + '[0-9a-zA-Z-_ /().?!,+*#äüöß]{1,}$'
      return pattern
    },

    nowDate () {
      const date = new Date()
      let day = date.getDate()
      let month = date.getMonth()
      month = month + 1
      if ((String(day)).length === 1) {
        day = '0' + day
      }
      if ((String(month)).length === 1) {
        month = '0' + month
      }

      return day + '.' + month + '.' + date.getFullYear()
    }
  },

  watch: {
    // We have to watch it because the values are loaded from the async request response
    initValues: {
      handler () {
        this.setInitialValues()
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    setInitialValues () {
      this.values = { ...this.initValues }

      // Set default values to ensure reactivity.
      if (typeof this.values.submitter !== 'undefined' && typeof this.values.submitter.institution === 'undefined') {
        // Since Data sends us the key toeb instead of institution, we need to transform this for now but keep all init values
        this.values.submitter.institution = this.values.submitter.toeb
        this.$delete(this.values.submitter, 'toeb')
      }

      if (typeof this.values.submitter === 'undefined' || Object.keys(this.values.submitter).length === 0) {
        this.values.submitter = {}
        for (const [key, value] of Object.entries(submitterProperties)) {
          this.values.submitter[key] = value
        }
      }
    },

    /**
     * @param { Boolean } val
     * To ensure the reactivity the state of 'values.submitter.institution'.
     */
    setInstitutionValue (val) {
      this.$nextTick(() => {
        this.values.submitter.institution = val
      })
    },

    sortSelected (property, sortBy = 'name') {
      this.values[property].sort((a, b) => (a[sortBy] > b[sortBy]) ? 1 : ((b[sortBy] > a[sortBy]) ? -1 : 0))
    },

    submit () {
      this.dpValidateAction('simplifiedNewStatementForm', () => {
        this.isSaving = true
        this.$refs.simplifiedNewStatementForm.submit()
      }, false)
    }
  },

  created () {
    this.instanceId = uuid()
  },

  mounted () {
    this.setInitialValues()

    // Synchronize values.authoredDate with the date value provided by data only if date is existing and format is valid.
    if (hasOwnProp(this.values.submitter, 'date') && dayjs(this.values.submitter.date, 'YYYY-MM-DD', true).isValid()) {
      this.values.authoredDate = dayjs(this.values.submitter.date).format('DD.MM.YYYY')
    }
  }
}
</script>
