<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <p class="u-mb">
      {{ Translator.trans('export.settings.hint') }}
    </p>
    <dp-inline-notification
      v-if="singleCheckedFieldId"
      class="mt-3 mb-2"
      :message="Translator.trans('field.selectionRequired')"
      type="warning" />
    <dp-checkbox
      id="check_all"
      v-model="allChecked"
      :disabled="allChecked === true"
      data-cy="exportSettings:allChecked"
      class="u-mb"
      :label="{
        bold: true,
        text: Translator.trans('aria.select.all')
      }"
      @change="toggleAll" />
    <div class="inline-block u-pr align-top u-1-of-4-wide u-1-of-2-desk u-1-of-2-lap u-1-of-1-palm u-mb">
      <p
        class="weight--bold u-mb-0_25"
        id="submitter">
        {{ Translator.trans('submitter') }}
      </p>
      <dp-checkbox-group
        aria-labelledby="submitter"
        :options="submitterFields"
        data-cy="exportSettingsSubmitter"
        @update="checked => updateCheckedFields(checked)"
        :selected-options="getSelectedOptions(submitterFields)" />
    </div><!--
 --><dp-checkbox-group
      :label="Translator.trans('statement.data')"
      :options="metaDataFields"
      data-cy="exportSettingsMetaData"
      @update="checked => updateCheckedFields(checked)"
      :selected-options="getSelectedOptions(metaDataFields)"
    class="inline-block align-top u-1-of-4-wide u-1-of-2-desk u-1-of-2-lap u-1-of-1-palm u-mb u-pr-2" /><!--
 --><dp-checkbox-group
      v-if="hasPermission('field_procedure_elements')"
      :label="Translator.trans('documents')"
      :options="documentFields"
      data-cy="exportSettingsDocuments"
      @update="checked => updateCheckedFields(checked)"
      :selected-options="getSelectedOptions(documentFields)"
    class="inline-block align-top u-1-of-4-wide u-1-of-2-desk u-1-of-2-lap u-1-of-1-palm u-mb" /><!--
 --><dp-checkbox-group
      :label="Translator.trans('publication')"
      :options="publicationField"
      data-cy="exportSettingsPublication"
      @update="checked => updateCheckedFields(checked)"
      :selected-options="getSelectedOptions(publicationField)"
      class="inline-block align-top u-1-of-4-wide u-1-of-2-desk u-1-of-2-lap u-1-of-1-palm" />
  </div>
</template>

<script>
import { DpCheckbox, DpCheckboxGroup, DpInlineNotification } from '@demos-europe/demosplan-ui'

export default {
  name: 'ExportSettings',

  components: {
    DpCheckbox,
    DpCheckboxGroup,
    DpInlineNotification
  },

  props: {
    fieldDefinitions: {
      type: Object,
      default: () => ({})
    },

    initialSettings: {
      type: Object,
      default: () => ({})
    }
  },

  data () {
    return {
      activePreventDefaultCheckboxId: '',
      allChecked: false,
      checkedFields: {},
      configurableFields: [
        {
          id: 'r_export_settings[r_extern_id]',
          name: 'r_export_settings[r_extern_id]',
          label: Translator.trans('id'),
          initVal: this.initialSettings.idExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_extern_id'),
          group: 'metaData'
        },
        {
          id: 'r_export_settings[r_submitted_date]',
          name: 'r_export_settings[r_submitted_date]',
          label: Translator.trans('statement.date.submitted'),
          initVal: this.initialSettings.creationDateExportable,
          enabled: true,
          hasPermission: true,
          group: 'metaData'
        },
        {
          id: 'r_export_settings[r_procedure_name]',
          name: 'r_export_settings[r_procedure_name]',
          label: Translator.trans('procedure.name'),
          initVal: this.initialSettings.procedureNameExportable,
          enabled: true,
          hasPermission: hasPermission('field_procedure_name'),
          group: 'metaData'
        },
        {
          id: 'r_export_settings[r_phase]',
          name: 'r_export_settings[r_phase]',
          label: Translator.trans('procedure.public.phase'),
          initVal: this.initialSettings.procedurePhaseExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_phase'),
          group: 'metaData'
        },
        // Currently not implemented in BE, therefore disabled
        {
          id: 'r_export_settings[r_institutionOrCitizen]',
          name: 'r_export_settings[r_institutionOrCitizen]',
          label: Translator.trans('submitter.type'),
          initVal: this.initialSettings.institutionOrCitizenExportable,
          enabled: false,
          hasPermission: true,
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_orga_name]',
          name: 'r_export_settings[r_orga_name]',
          label: Translator.trans('organisation.name'),
          initVal: this.initialSettings.orgaNameExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_meta_orga_name'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_departmentName]',
          name: 'r_export_settings[r_departmentName]',
          label: Translator.trans('department.name'),
          initVal: this.initialSettings.departmentNameExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_meta_orga_name') && hasPermission('field_statement_meta_orga_department_name'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_userOrganisation]',
          name: 'r_export_settings[r_userOrganisation]',
          label: Translator.trans('organisation.name.guest'),
          initVal: this.initialSettings.userOrganisationExportable,
          enabled: this.fieldDefinitions.citizenXorOrgaAndOrgaName.enabled || this.fieldDefinitions.stateAndGroupAndOrgaNameAndPosition.enabled,
          hasPermission: hasPermission('field_statement_user_organisation'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_author_name]',
          name: 'r_export_settings[r_author_name]',
          label: Translator.trans('submitter.name.gendered'),
          initVal: this.initialSettings.submitterNameExportable,
          enabled: this.fieldDefinitions.name.enabled,
          hasPermission: hasPermission('field_statement_meta_submit_name'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_public_show]',
          name: 'r_export_settings[r_public_show]',
          label: Translator.trans('publish.on.platform'),
          initVal: this.initialSettings.showInPublicAreaExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_public_allowed'),
          group: 'publication'
        },
        {
          id: 'r_export_settings[r_element]',
          name: 'r_export_settings[r_element]',
          label: Translator.trans('document.reference'),
          initVal: this.initialSettings.documentExportable,
          enabled: true,
          hasPermission: true,
          group: 'documents'
        },
        {
          id: 'r_export_settings[r_paragraph]',
          name: 'r_export_settings[r_paragraph]',
          label: Translator.trans('paragraph.reference'),
          initVal: this.initialSettings.paragraphExportable,
          enabled: true,
          hasPermission: hasPermission('field_procedure_paragraphs'),
          group: 'documents'
        },
        {
          id: 'r_export_settings[r_document]',
          name: 'r_export_settings[r_document]',
          label: Translator.trans('file.reference'),
          initVal: this.initialSettings.filesExportable,
          enabled: true,
          hasPermission: hasPermission('field_procedure_documents'),
          group: 'documents'
        },
        {
          id: 'r_export_settings[r_attachment]',
          name: 'r_export_settings[r_attachment]',
          label: Translator.trans('files.attached.names'),
          initVal: this.initialSettings.attachmentsExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_file'),
          group: 'documents'
        },
        {
          id: 'r_export_settings[r_priority]',
          name: 'r_export_settings[r_priority]',
          label: Translator.trans('priority'),
          initVal: this.initialSettings.priorityExportable,
          enabled: true,
          hasPermission: hasPermission('field_statement_priority'),
          group: 'metaData'
        },
        {
          id: 'r_export_settings[r_votes]',
          name: 'r_export_settings[r_votes]',
          label: Translator.trans('voters'),
          initVal: this.initialSettings.votesNumExportable,
          enabled: true,
          hasPermission: hasPermission('feature_statements_vote'),
          group: 'metaData'
        },
        {
          id: 'r_export_settings[r_submitterEmailAddress]',
          name: 'r_export_settings[r_submitterEmailAddress]',
          label: Translator.trans('email.address'),
          initVal: this.initialSettings.emailExportable,
          enabled: this.fieldDefinitions.emailAddress.enabled || this.fieldDefinitions.phoneOrEmail.enabled,
          hasPermission: hasPermission('field_statement_submitter_email_address'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_phone]',
          name: 'r_export_settings[r_phone]',
          label: Translator.trans('statement.fieldset.phoneNumber'),
          initVal: this.initialSettings.phoneNumberExportable,
          enabled: this.fieldDefinitions.phoneNumber.enabled || this.fieldDefinitions.phoneOrEmail.enabled,
          hasPermission: true,
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_orga_street]',
          name: 'r_export_settings[r_orga_street]',
          label: Translator.trans('street'),
          initVal: this.initialSettings.streetExportable,
          enabled: this.fieldDefinitions.street.enabled || this.fieldDefinitions.streetAndHouseNumber.enabled,
          hasPermission: hasPermission('field_statement_meta_street') && hasPermission('field_statement_meta_address'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_houseNumber]',
          name: 'r_export_settings[r_houseNumber]',
          label: Translator.trans('street.number'),
          initVal: this.initialSettings.streetNumberExportable,
          enabled: this.fieldDefinitions.streetAndHouseNumber.enabled,
          hasPermission: hasPermission('field_statement_meta_address'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_orga_postalcode]',
          name: 'r_export_settings[r_orga_postalcode]',
          label: Translator.trans('postalcode'),
          initVal: this.initialSettings.postalCodeExportable,
          enabled: this.fieldDefinitions.postalAndCity.enabled,
          hasPermission: hasPermission('field_statement_meta_address'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_orga_city]',
          name: 'r_export_settings[r_orga_city]',
          label: Translator.trans('city'),
          initVal: this.initialSettings.cityExportable,
          enabled: this.fieldDefinitions.postalAndCity.enabled,
          hasPermission: hasPermission('field_statement_meta_address'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_userState]',
          name: 'r_export_settings[r_userState]',
          label: Translator.trans('state'),
          initVal: this.initialSettings.userStateExportable,
          enabled: this.fieldDefinitions.stateAndGroupAndOrgaNameAndPosition.enabled,
          hasPermission: hasPermission('field_statement_user_state'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_userGroup]',
          name: 'r_export_settings[r_userGroup]',
          label: Translator.trans('group'),
          initVal: this.initialSettings.userGroupExportable,
          enabled: this.fieldDefinitions.stateAndGroupAndOrgaNameAndPosition.enabled,
          hasPermission: hasPermission('field_statement_user_group'),
          group: 'submitterData'
        },
        {
          id: 'r_export_settings[r_userPosition]',
          name: 'r_export_settings[r_userPosition]',
          label: Translator.trans('position'),
          initVal: this.initialSettings.userPositionExportable,
          enabled: this.fieldDefinitions.stateAndGroupAndOrgaNameAndPosition.enabled,
          hasPermission: hasPermission('field_statement_user_position'),
          group: 'submitterData'
        }
      ],
      singleCheckedFieldId: ''
    }
  },

  computed: {
    availableFields () {
      return this.configurableFields.filter(field => field.hasPermission === true && field.enabled === true)
    },

    documentFields () {
      return this.availableFields.filter(field => field.group === 'documents')
    },

    metaDataFields () {
      return this.availableFields.filter(field => field.group === 'metaData')
    },

    publicationField () {
      return this.availableFields.filter(field => field.group === 'publication') || []
    },

    submitterFields () {
      return this.availableFields.filter(field => field.group === 'submitterData')
    }
  },

  methods: {
    addPreventDefault (id) {
      const checkbox = document.getElementById(id)
      checkbox.addEventListener('click', this.preventCheck)
    },

    getSelectedOptions (options) {
      const entries = {}
      options.forEach(option => {
        const entry = Object.entries(this.checkedFields).find(el => el[0] === option.id)
        if (entry) {
          const id = entry[0]
          entries[id] = entry[1]
        }
      })
      return entries
    },

    setAllChecked () {
      this.allChecked = typeof Object.values(this.checkedFields).find(val => val === false) === 'undefined'
    },

    getSingleCheckedField () {
      const checkedFieldEntries = Object.entries(this.checkedFields).filter(([, value]) => value)

      if (checkedFieldEntries.length === 1) {
        this.singleCheckedFieldId = checkedFieldEntries[0][0]
      } else {
        this.singleCheckedFieldId = ''
      }
    },

    handlePreventDefaultForSingleField () {
      this.getSingleCheckedField()

      if (this.activePreventDefaultCheckboxId) {
        this.removePreventDefault(this.activePreventDefaultCheckboxId)
        this.activePreventDefaultCheckboxId = ''
      }

      if (this.singleCheckedFieldId) {
        this.addPreventDefault(this.singleCheckedFieldId)
        this.activePreventDefaultCheckboxId = this.singleCheckedFieldId
      }
    },

    preventCheck (e) {
      e.preventDefault()
    },

    removePreventDefault (id) {
      const checkbox = document.getElementById(id)
      checkbox.removeEventListener('click', this.preventCheck)
    },

    setCheckedFields () {
      this.availableFields.forEach(field => {
        this.$set(this.checkedFields, field.id, field.initVal || false)
      })
      this.setAllChecked()
    },

    toggleAll () {
      this.availableFields.forEach(field => {
        this.$set(this.checkedFields, field.id, true)
      })
      this.handlePreventDefaultForSingleField()
    },

    updateCheckedFields (checkedFields) {
      Object.keys(checkedFields).forEach(id => {
        this.$set(this.checkedFields, id, checkedFields[id])
      })
      this.handlePreventDefaultForSingleField()
      this.setAllChecked()
    }
  },

  mounted () {
    this.setCheckedFields()
    this.handlePreventDefaultForSingleField()
  }
}
</script>
