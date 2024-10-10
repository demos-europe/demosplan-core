<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <template v-if="hasPermission('feature_statements_vote')">
      <p class="lbl">
        {{ Translator.trans('statement.voter') }}:
      </p>
      <dp-editable-list
        :entries="voters"
        :has-permission-to-edit="!!(readonly !== '1' && isManual)"
        @delete="handleDelete"
        @reset="resetForm"
        @saveEntry="index => dpValidateAction('newVoterForm', () => addElement(index), false)"
        :translation-keys="translationKeys"
        ref="listComponent">
        <!-- List of voters -->
        <template v-slot:list="{entry, index}">
          <ul class="o-list o-list--csv inline">
            <input
              type="hidden"
              :name="preFix(index) + '[id]'"
              :id="preFix(index) + '[id]'"
              :value="entry.id">
            <input
              type="hidden"
              :name="preFix(index) + '[role]'"
              :id="preFix(index) + '[role_' + entry.role + ']'"
              :value="entry.role">

            <!-- User name -->
            <li
              v-if="entry.userName"
              class="o-list__item">
              {{ entry.userName }}
              <input
                type="hidden"
                :name="preFix(index) + '[author_name]'"
                :id="preFix(index) + '[author_name]'"
                :value="entry.userName">
            </li>

            <!-- Organisation name -->
            <li
              v-if="entry.organisationName"
              class="o-list__item">
              {{ entry.organisationName }}
              <input
                type="hidden"
                :name="preFix(index) + '[orga_name]'"
                :id="preFix(index) + '[orga_name]'"
                :value="entry.organisationName">
            </li>

            <!-- Department name -->
            <li
              v-if="entry.departmentName"
              class="o-list__item">
              {{ entry.departmentName }}
              <input
                type="hidden"
                :name="preFix(index) + '[orga_department_name]'"
                :id="preFix(index) + '[orga_department_name]'"
                :value="entry.departmentName">
            </li>

            <!-- User postal code +  city -->
            <li
              v-if="entry.userPostcode || entry.userCity"
              class="o-list__item">
              <span v-if="entry.userPostcode">
                {{ entry.userPostcode }}
                <input
                  type="hidden"
                  :name="preFix(index) + '[postalcode]'"
                  :id="preFix(index) + '[postalcode]'"
                  :value="entry.userPostcode">
              </span>
              <span v-if="entry.userCity">
                {{ entry.userCity }}
                <input
                  type="hidden"
                  :name="preFix(index) + '[orga_city]'"
                  :id="preFix(index) + '[orga_city]'"
                  :value="entry.userCity">
              </span>
            </li>

            <!-- User Mail -->
            <li
              v-if="entry.userMail"
              class="o-list__item">
              {{ entry.userMail }}
              <input
                type="hidden"
                :value="entry.userMail"
                :name="preFix(index) + '[email]'"
                :id="preFix(index) + '[email]'">
            </li>
          </ul>
        </template>

        <!-- Form to add new voters -->
        <template v-slot:form>
          <div
            data-dp-validate="newVoterForm"
            v-if="readonly !== '1' && isManual"
            class="space-stack-s space-inset-s border">
            <p class="lbl">
              {{ updating ? Translator.trans("statement.voter.change") : translationKeys.new }}:
            </p>
            <!-- Role -->
            <div>
              <dp-radio
                id="role_0"
                data-cy="statementVoter:roleCitizen"
                :label="{
                  text: Translator.trans('role.citizen')
                }"
                value="0"
                :checked="formFields.role === 0"
                @change="formFields.role = 0" />
              <dp-radio
                id="role_1"
                data-cy="statementVoter:invitableInstitution"
                :label="{
                  text: Translator.trans('invitable_institution')
                }"
                value="1"
                :checked="formFields.role === 1"
                @change="formFields.role = 1" />
            </div>

            <div
              v-show="isInstitutionParticipation && (hasPermission('field_statement_meta_orga_name') || hasPermission('field_statement_meta_orga_department_name'))"
              class="layout">
              <dp-input
                v-show="hasPermission('field_statement_meta_orga_name')"
                id="voter_publicagency"
                data-cy="voterPublicAgency"
                v-model="formFields.organisationName"
                class="layout__item u-1-of-2"
                :label="{
                  text: Translator.trans('invitable_institution')
                }" /><!--
           --><dp-input
                v-show="hasPermission('field_statement_meta_orga_department_name')"
                id="voter_department"
                data-cy="voterDepartment"
                v-model="formFields.departmentName"
                class="layout__item u-1-of-2"
                :label="{
                  text: Translator.trans('department')
                }" />
            </div>

            <div class="layout">
              <dp-input
                v-if="hasPermission('field_statement_meta_submit_name')"
                id="voter_username"
                data-cy="voterUsername"
                v-model="formFields.userName"
                class="layout__item u-1-of-2"
                :label="{
                  text: Translator.trans('statement.form.name')
                }" /><!--
           --><dp-input
                v-if="hasPermission('field_statement_meta_email')"
                id="voter_email"
                data-cy="voterEmail"
                v-model="formFields.userMail"
                class="layout__item u-1-of-2"
                :label="{
                  text: Translator.trans('email')
                }"
                type="email" />
            </div>

            <div class="layout">
              <dp-input
                v-if="hasPermission('field_statement_meta_postal_code')"
                id="voter_postalcode"
                data-cy="voterPostalCode"
                v-model="formFields.userPostcode"
                class="layout__item u-1-of-8"
                :label="{
                  text: Translator.trans('postalcode')
                }"
                pattern="^[0-9]{4,5}$" /><!--
           --><dp-input
                v-if="hasPermission('field_statement_meta_city')"
                id="voter_city"
                data-cy="voterCity"
                v-model="formFields.userCity"
                :class="hasPermission('field_statement_meta_postal_code') ? 'layout__item u-3-of-8' : 'layout__item'"
                :label="{
                  text: Translator.trans('city')
                }" />
            </div>
          </div>
        </template>
      </dp-editable-list>

      <!-- Anonymous voters -->
      <div v-if="editable">
        <p class="lbl inline-block">
          {{ Translator.trans('more') }}
        </p>
        <label class="lbl--text inline-block">
          <input
            id="r_voters_anonym"
            name="r_voters_anonym"
            data-cy="votersAnonym"
            class="layout__item text-center align-baseline o-form__control-input u-3-of-12 u-mr-0_125"
            :disabled="('1' === readonly)"
            type="number"
            placeholder=""
            v-model="anonymVotes">
          {{ Translator.trans('statement.voter.anonym') }}
        </label>
      </div>
    </template>
  </div>
</template>

<script>
import { DpEditableList, DpInput, DpRadio, dpValidateMixin } from '@demos-europe/demosplan-ui'
import { mapGetters, mapMutations } from 'vuex'

export default {
  name: 'StatementVoter',

  components: {
    DpEditableList,
    DpInput,
    DpRadio
  },

  mixins: [dpValidateMixin],

  props: {
    anonymVotesString: {
      required: false,
      type: String,
      default: '0'
    },

    dataAttr: {
      required: false,
      type: String,
      default: ''
    },

    initVoters: {
      required: false,
      type: Array,
      default: () => []
    },

    isManual: {
      required: false,
      type: String,
      default: ''
    },

    publicAllowed: {
      required: false,
      type: String,
      default: '0'
    },

    readonly: {
      required: false,
      type: String,
      default: '1'
    }
  },

  data () {
    return {
      formFields: {
        role: '',
        organisationName: '',
        departmentName: '',
        userName: '',
        userMail: '',
        userPostcode: '',
        userCity: '',
        id: '',
        active: true,
        manual: true
      },
      updating: false,
      anonymVotes: 0,
      voters: {},
      translationKeys: {
        new: Translator.trans('add'),
        add: Translator.trans('statement.voter.add'),
        abort: Translator.trans('abort'),
        update: Translator.trans('statement.voter.update'),
        noEntries: Translator.trans('statement.voters.none'),
        delete: Translator.trans('statement.voter.delete')
      }
    }
  },

  computed: {
    ...mapGetters('Voter', ['getVoters']),

    preFix () {
      return (index) => {
        if (index >= 0) {
          return 'r_voters[' + index + ']'
        } else {
          return ''
        }
      }
    },

    votersLength: {
      get () {
        return (this.anonymVotes + Object.keys(this.getVoters).length)
      }
    },

    editable () {
      return (!!this.publicAllowed || this.isManual)
    },

    isInstitutionParticipation () {
      return hasPermission('feature_institution_participation') && this.formFields.role === 1
    }
  },

  methods: {
    ...mapMutations('Voter', [
      'addNewVoter',
      'removeVoter',
      'setVoters',
      'updateVoter'
    ]),

    addElement (index) {
      if (this.checkIfEmpty() === false) {
        if (this.formFields.role === '' || typeof this.formFields.role === 'undefined') {
          this.formFields.role = 0
        }

        if (index === 'new') {
          this.addNewVoter(this.formFields)
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        } else {
          this.updateVoter({ index: index, newData: this.formFields })
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        }

        this.resetForm()
        this.$refs.listComponent.toggleFormVisibility(false)
        this.$refs.listComponent.currentlyUpdating = ''
      }
    },

    checkIfEmpty () {
      let isEmpty = true
      const fieldsToCheck = [
        'organisationName',
        'departmentName',
        'userName',
        'userMail',
        'userPostcode',
        'userCity'
      ]

      for (let i = 0; i < fieldsToCheck.length; i++) {
        if (this.formFields[fieldsToCheck[i]] !== '' && typeof this.formFields[fieldsToCheck[i]] !== 'undefined') {
          isEmpty = false

          return isEmpty
        }
      }

      return isEmpty
    },

    handleDelete (index) {
      this.removeVoter(index)
      this.resetForm()

      dplan.notify.notify('confirm', Translator.trans('confirm.deleted'))
    },

    resetForm () {
      this.formFields = {
        role: 0,
        organisationName: '',
        departmentName: '',
        userName: '',
        userMail: '',
        userPostcode: '',
        userCity: '',
        id: '',
        manual: true,
        active: true
      }

      this.updating = false
    },

    showUpdateForm (index) {
      for (const key in this.formFields) {
        this.formFields[key] = this.getVoters[index][key]
      }

      this.updating = true
    }
  },

  mounted () {
    // Make an object from the array of initial voters, that was passed in twig
    const objVoters = {}

    this.initVoters.forEach((elem, index) => {
      // Set the role of the voter, as it is not defined in BE response
      if (elem.role === '' || typeof elem.role === 'undefined') {
        if (!elem.organisationName || elem.organisationName === '' || elem.createdByCitizen) {
          elem.role = 0
        } else {
          elem.role = 1
        }
      }
      objVoters[index] = elem
    })

    // Set the voters in store and bind them to the component data
    this.setVoters(objVoters)
    this.voters = this.getVoters

    // Add event listener to statement voter div to prevent form submit on enter
    if (document.getElementById('statementVoterDiv')) {
      document.getElementById('statementVoterDiv').addEventListener('keydown', preventSend)
    }
  },

  created () {
    this.anonymVotes = parseInt(this.anonymVotesString)
  },

  unmounted () {
    // Remove event listener from statement voter div
    if (document.getElementById('statementVoterDiv')) {
      document.getElementById('statementVoterDiv').removeEventListener('keydown', preventSend, false)
    }
  }
}

const preventSend = function (e) {
  const key = e.charCode || e.keyCode || 0
  if (key === 13) {
    e.preventDefault()
  }
}
</script>
