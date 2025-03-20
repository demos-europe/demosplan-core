<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!--
        @TODO find out correct tag for general info about component
        @TODO use autoSuggest instead of Multiselect, get submitters data while user types ahead

        This component is intended to let planners quickly fill in address data
        of existing statement submitters when creating manual statements.
     -->
</documentation>

<template>
  <div class="layout">
    <!-- Role of submitter (citizen or institution) -->
    <div
      class="layout__item u-mb-wide"
      :class="{'u-4-of-11 u-1-of-1-desk-down': currentRoleHasSelect, 'u-1-of-1': !currentRoleHasSelect}"
      v-if="hasPermission('feature_institution_participation') && (formDefinitions.citizenXorOrgaAndOrgaName.enabled === true || participationGuestOnly === false)">
      <p class="lbl u-mb-0_5">
        {{ Translator.trans('submitted.author') }}
      </p>
      <template
        v-for="role in roles"
        :key="role.value">
        <input
          type="radio"
          :data-cy="`roleInput-${role.dataCy}`"
          name="r_role"
          :value="role.value"
          @change="() => $emit('role-changed', currentRole)"
          :id="`r_role_${role.value}`"
          v-model="currentRole"><!--
     --><label
          class="lbl--text inline-block u-mb-0_5 u-pr u-ml-0_25"
          :for="`r_role_${role.value}`">
          {{ Translator.trans(role.label) }}
        </label>
      </template>
    </div><!--
    Assuming t_role defaults to value=0 if feature_institution_participation is set to false:
 --><input
      type="hidden"
      name="r_role"
      value="0"
    v-else><!--

    Display the autofill interface element
 --><div
      class="layout__item u-7-of-11 u-1-of-1-desk-down u-mb"
      v-if="currentRoleHasSelect">
<!-- Label & contextual help -->
      <label
        class="u-mb-0_25 flow-root"
        for="submitterSelect">
        {{ Translator.trans('statement.form.autofill.label') }} ({{ Translator.trans(currentRoleKeyword) }})
        <dp-contextual-help
          class="float-right"
          :text="autoFillLabel" />
      </label>

       <!--Multiselect component-->
      <dp-multiselect
        id="submitterSelect"
        data-cy="submitterForm:submitterSelect"
        v-model="submitter"
        :custom-label="customOption"
        :disabled="currentListIsEmpty"
        label="submitter"
        :options="submitterOptions"
        :placeholder="Translator.trans('choose.search')"
        :sub-slots="['option', 'singleLabel']"
        track-by="entityId"
        @input="emitSubmitterData">
        <!-- Template for select options -->
          <template v-slot:option="{ props }">
            <span v-cleanhtml="customOption(props.option, true)" />
          </template>

          <!-- Template for element that is visible when Multiselect is closed -->
          <template v-slot:singleLabel="{ props }">
            <span v-cleanhtml="customSingleLabel(props.option)" />
          </template>
      </dp-multiselect>
    </div>

    <!-- Bob-HH displays an additional hint regarding user data. -->
    <!-- @improve T18818 -->
    <div
      class="layout__item u-1-of-1"
      v-if="isBobHH">
      <p>
        <u>{{ Translator.trans('statement.invitable_institution.hint') }}</u>:
        {{ Translator.trans('statement.invitable_institution.assessment.table.print') }}
      </p>
      <p>
        <u>{{ Translator.trans('statement.citizen.hint') }}</u>:
        {{ Translator.trans('statement.citizen.assessment.table.print') }}
      </p>
    </div>

    <!-- User fields that are specific to institutions: orga, department. These fields shall not be changeable in Bob-HH, but visible and present to submit their values when filled by autoFill function -->
    <template
      v-if="hasPermission('feature_institution_participation') && currentRole === '1' && (hasPermission('field_statement_meta_orga_name') || hasPermission('field_statement_meta_orga_department_name')) && this.participationGuestOnly === false">
      <dp-input
        v-if="hasPermission('field_statement_meta_orga_name')"
        id="r_orga_name"
        data-cy="submitterForm:orgaName"
        class="layout__item u-1-of-2 u-mb-0_75"
        :label="{
          text: Translator.trans('invitable_institution')
        }"
        name="r_orga_name"
        :readonly="isBobHH"
        :required="true"
        v-model="submitterData.organisation" /><!--
   --><dp-input
        v-if="hasPermission('field_statement_meta_orga_department_name')"
        id="r_orga_department_name"
        data-cy="submitterForm:orgaDepartmentName"
        class="layout__item u-1-of-2 u-mb-0_75"
        :label="{
          text: translateFieldLabel({ field: 'department', label: 'department' })
        }"
        name="r_orga_department_name"
        :readonly="isBobHH"
        v-model="submitterData.department" />
    </template>

    <!-- General user fields: name, email, phoneNumber, street, postalcode, city. Email address (input.noSync) shall not be auto
        filled, see comment in data.inputFields.general -->
    <div
      v-for="(row, idx) in inputFields.general"
      :key="idx">
      <dp-input
        v-for="(element, index) in generalElements(idx)"
        v-bind="element"
        class="layout__item u-1-of-2 u-mb-0_75"
        :key="`${element.id}_${index}`" />
    </div>
  </div>
</template>

<script>
import { CleanHtml, DpContextualHelp, DpInput, DpMultiselect, hasOwnProp } from '@demos-europe/demosplan-ui'

const emptySubmitterData = {
  city: '',
  department: '',
  email: '',
  name: '',
  organisation: '',
  postalCode: '',
  street: '',
  nr: ''
}

export default {
  name: 'DpAutofillSubmitterData',
  components: {
    DpContextualHelp,
    DpInput,
    DpMultiselect
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    formDefinitions: {
      type: Object,
      required: true
    },

    initSubmitter: {
      type: Object,
      required: false,
      default: () => ({})
    },

    participationGuestOnly: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    /**
     *  If the form passed the frontend validation but failed backend validation, the request data
     *  is filled to not bother the user with lost form input.
     */
    request: {
      type: Object,
      required: true
    },

    /*
     *  List of all submitters including institutions & citizens, depending on permissions.
     *  Expected structure:
     *  submitters: [
     *     {
     *         list: 'institution|citizen',
     *         entityId: 'id-of-entity',
     *         entityType: 'statement|orga',
     *         submitter: {
     *             city: '',
     *             department: '',
     *             name: '',
     *             organisation: '',
     *             postalCode: '',
     *             street: ''
     *         }
     *     }
     *  ]
     *  These are gonna be filtered based on the field `list`.
     */
    submitters: {
      type: Array,
      required: true
    }
  },

  data () {
    return {
      //  Citizen vs. Institution radio buttons
      roles: [
        {
          value: '0',
          label: 'role.citizen',
          dataCy: 'citizen'
        },
        {
          value: '1',
          label: 'invitable_institution',
          dataCy: 'invitableInstitution'
        }
      ],

      /*
       *  Input fields. `label` overrides `field` as the label translation key.
       *  `noSync` excludes field from being updated via the select list. See considerations below.
       *
       *  Auto filling the email address from data of former submissions is intentionally prevented because
       *  this way a submitter who contributes several statements but only specifies an email address with the
       *  first statement could (by laziness of the planer who does not delete the email after auto filling
       *  subscriber data from first statement) get several "Schlussmitteilungen" and be rightly concerned about
       *  system reliability or worse, professional correctness of the procedure.
       */
      inputFields: {
        general: {
          0: [
            {
              permission: 'field_statement_meta_submit_name',
              field: 'name',
              dataCy: 'submitterForm:authorName',
              label: Translator.trans('statement.form.name'),
              name: 'r_author_name',
              type: 'text',
              width: 'u-1-of-2'
            },
            {
              permission: 'field_statement_meta_email',
              label: Translator.trans('statement.fieldset.emailAddress'),
              field: 'email',
              dataCy: 'submitterForm:orgaEmail',
              name: 'r_orga_email',
              type: 'email',
              width: 'u-1-of-2',
              // We don't use a pattern here but we need the attribute for the customValidation. We use the native validation from html5 and rely on the customValidation until we can validate via JSON-API
              noSync: true // Email address shall not be auto filled, see comment above
            },
            {
              field: 'phone',
              dataCy: 'submitterForm:Phone',
              name: 'r_phone',
              type: 'tel', // Type number and tel allow letters so pattern is set
              label: Translator.trans('statement.fieldset.phoneNumber'),
              width: 'u-1-of-2',
              pattern: '^(\\+?)(-| |[0-9]|\\(|\\))*$',
              noSync: true // Do not autofill for now
            }
          ],
          1: [
            {
              permission: 'field_statement_meta_street',
              field: 'street',
              dataCy: 'submitterForm:orgaStreet',
              name: 'r_orga_street',
              type: 'text',
              width: 'u-4-of-10'
            },
            {
              permission: 'field_statement_meta_street',
              field: 'nr',
              dataCy: 'submitterForm:houseNumber',
              name: 'r_houseNumber',
              type: 'text',
              label: Translator.trans('street.number.short'),
              width: 'u-1-of-10'
            },
            {
              permission: 'field_statement_meta_postal_code',
              field: 'postalCode',
              dataCy: 'submitterForm:orgaPostalcode',
              label: Translator.trans('postalcode'),
              name: 'r_orga_postalcode',
              width: 'u-2-of-12',
              type: 'text',
              pattern: '^[0-9]{4,5}$'
            },
            {
              permission: 'field_statement_meta_city',
              field: 'city',
              dataCy: 'submitterForm:orgaCity',
              name: 'r_orga_city',
              width: 'u-4-of-12',
              type: 'text'
            }
          ]
        }
      },

      //  Initially set state of the radio to citizen
      currentRole: '0',

      //  Holds the currently selected submitter object
      submitter: {},

      //  Holds data of currently selected submitter, initially declared to add reactivity
      submitterData: emptySubmitterData
    }
  },

  computed: {
    //  Translated string of label for select, depending on `r_roles`-radio
    autoFillLabel () {
      let label

      if (this.currentRole === '0') {
        label = 'statement.form.autofill.hint.citizen'
      }

      if (this.currentRole === '1') {
        if (hasPermission('feature_statement_create_autofill_submitter_invited')) {
          label = 'statement.form.autofill.hint.invited'
        } else if (hasPermission('feature_statement_create_autofill_submitter_institutions')) {
          label = 'statement.form.autofill.hint.institution'
        }
      }

      return Translator.trans(label)
    },

    //  Returns true if currently filtered list is empty
    currentListIsEmpty () {
      return Object.keys(this.submitterOptions).length <= 0
    },

    //  Does the currently selected role have an Autofill permission?
    currentRoleHasSelect () {
      return this.currentRole === '0' ? this.hasCitizenSelect : this.hasInstitutionSelect
    },

    /**
     *  Transform possible states of the `r_roles`-radio into keywords to filter submitterOptions
     *  The return value is also used as translation key from template
     */
    currentRoleKeyword () {
      return this.currentRole === '0' ? 'citizen' : 'institution'
    },

    //  Shortcut for permission checks for institution select
    hasInstitutionSelect () {
      return hasPermission('feature_institution_participation') && (hasPermission('feature_statement_create_autofill_submitter_invited') || hasPermission('feature_statement_create_autofill_submitter_institutions'))
    },

    //  Shortcut for permission checks for citizen select
    hasCitizenSelect () {
      return hasPermission('feature_statement_create_autofill_submitter_citizens')
    },

    //  Shortcut to check project name
    isBobHH () {
      return PROJECT && PROJECT === 'bobhh'
    },

    //  These are the options of the select, as filtered by the currently selected `r_roles`-radio
    submitterOptions () {
      return this.submitters.filter(option => option.list === this.currentRoleKeyword)
    }
  },

  watch: {
    currentRole: {
      handler () {
        //  Reset Multiselect + fields upon selection of `r_roles`-radio
        this.submitter = {}
        this.submitterData = emptySubmitterData
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    },

    submitter: {
      handler (submitterSelected) {
        //  When `submitter` changes via the `currentRole` watcher, lets not trigger an update
        if (
          typeof submitterSelected === 'undefined' ||
          (
            hasOwnProp(submitterSelected, 'entityId') || hasOwnProp(submitterSelected, 'entityType')
          ) === false
        ) {
          return
        }

        this.submitterData = this.submitter.submitter
      },
      deep: true
    }
  },

  methods: {
    emitSubmitterData () {
      this.$emit('submitter:chosen', { counties: this.submitter.counties, municipalities: this.submitter.municipalities })
    },
    /*
     * Returns select option, either as a concatenated String or wrapped in a simple Html template.
     *
     * To be able to search in Multiselect, a custom function which concatenates all properties
     * of a data object has to be passed to the `custom-label` prop of Multiselect.
     * See https://vue-multiselect.js.org/#sub-select-with-search
     */
    customOption ({ submitter }, renderHtml) {
      if (typeof submitter === 'undefined') {
        return
      }

      if (renderHtml) {
        return this.renderOptionWithMarkup(submitter, this.currentRole)
      }

      return this.renderOptionConcatenated(submitter, this.currentRole)
    },

    //  Return translated label for text inside select field, depending on whether autofill was executed or not
    customSingleLabel () {
      let label

      if (hasOwnProp(this.submitter, 'entityId')) {
        label = 'statement.form.autofill.inserted'
      } else {
        label = 'choose.search'
      }

      return Translator.trans(label)
    },

    /**
     * Returns inputFields.general for which the permission is active for the given form row (idx)
     */
    generalElements (idx) {
      // Map field names with formDefinition group names (key => name in template, value => formDefinitionName)
      const fieldNameMapping = {
        name: 'name',
        postalCode: 'postalAndCity',
        city: 'postalAndCity',
        street: ['street', 'streetAndHouseNumber'],
        nr: 'streetAndHouseNumber',
        phone: ['phoneOrEmail', 'phoneNumber'],
        email: ['emailAddress', 'phoneOrEmail']
      }

      /*
       * Because the submitter of a manual statement may requested feedack, we have to show the eMail-field
       * even if its not visible in the public view
       */
      const definitions = this.formDefinitions
      if (idx === '0' && this.formDefinitions.phoneOrEmail.enabled === false && this.formDefinitions.emailAddress.enabled === false) {
        definitions.emailAddress.enabled = true
      }

      return this.inputFields.general[idx].reduce((acc, curr) => {
        const isControlledBySeveralDefinitions = Array.isArray(fieldNameMapping[curr.field]) || false

        const isEnabledInFormDefinition = isControlledBySeveralDefinitions
          ? fieldNameMapping[curr.field].some(el => definitions[el].enabled === true)
          : definitions[fieldNameMapping[curr.field]].enabled === true

        if (isEnabledInFormDefinition && (curr.permission ? hasPermission(curr.permission) : true)) {
          const isRequiredInFormDefinition = isControlledBySeveralDefinitions
            ? fieldNameMapping[curr.field].some(el => definitions[el].required === true)
            : definitions[fieldNameMapping[curr.field]].required

          acc.push({
            id: curr.name,
            label: {
              text: this.translateFieldLabel(curr)
            },
            name: curr.name,
            pattern: curr.pattern || '',
            value: this.submitterData[curr.field],
            required: isRequiredInFormDefinition,
            type: curr.type,
            width: curr.width,
            dataCy: curr.dataCy
          })
        }
        return acc
      }, [])
    },

    //  Display an option for select
    renderOptionWithMarkup ({ organisation, department, name, postalCode, city }, role) {
      if (role === '0') {
        return `<strong>${organisation}</strong><br><ul><li>${name}</li><li>${postalCode} ${city}</li></ul>`
      }

      if (role === '1') {
        return `<strong>${organisation}</strong><br><ul><li>${department}</li><li>${name}</li></ul>`
      }
    },

    //  Concatenate fields of option that shall be searchable
    renderOptionConcatenated ({ organisation, department, name, postalCode, city }, role) {
      if (role === '0') {
        return `${organisation} ${name} ${postalCode} ${city}`
      }

      if (role === '1') {
        return `${organisation} ${department} ${name}`
      }
    },

    //  Return translated key with fallback field
    translateFieldLabel ({ field, label }) {
      return this.transWithFallback(field, label)
    },

    //  @TODO #move-to-lib
    transWithFallback (fallback, key) {
      return Translator.trans(key || fallback)
    }

  },

  mounted () {
    //  Set currently selected role to request value only if set
    this.currentRole = this.request.role !== '' ? this.request.role : (hasOwnProp(this.initSubmitter, 'role') ? this.initSubmitter.role : this.currentRole)
    setTimeout(() => {
      const hasRequest = Object.values(this.request).join('') !== ''
      const hasInitSubmitter = Object.values(this.initSubmitter).length > 0

      if (hasRequest) {
        this.submitterData = { ...this.request }
      } else if (hasInitSubmitter) {
        const init = JSON.parse(JSON.stringify(this.initSubmitter))
        delete init.role
        this.submitterData = init
      }
    }, 0)
  }
}
</script>
