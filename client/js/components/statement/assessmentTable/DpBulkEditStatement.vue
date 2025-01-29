<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!-- This component is used for multi-step editing of statements. It contains three views (edit, confirm, success)
    that are toggled as you progress in the editing process. Data is sent to BE only in the final view.
    -->
    <usage variant="minimum (required props)">
        <dp-bulk-edit-statement
            :procedure-id="procedureId"
        >
        </dp-bulk-edit-statement>
    </usage>
    <usage variant="full">
        <dp-bulk-edit-statement
            :procedure-id="procedureId"
            filter-hash="filterHash"
        >
        </dp-bulk-edit-statement>
    </usage>
</documentation>

<template>
  <div>
    <!-- Edit view -->
    <div v-if="mode === 'edit'">
      <h3 class="u-mv">
        {{ Translator.trans('actions.statements.configure', { count: selectedElementsCount }) }}
      </h3>

      <!--ASSIGN TO OTHER-->
      <div
        class="border--bottom u-mb"
        v-if="hasPermission('feature_statement_assignment')">
        <input
          type="checkbox"
          id="r_new_assignee"
          v-model="options.newAssignee.checked">
        <label
          for="r_new_assignee"
          class="inline-block">
          {{ Translator.trans('statements.assign.other') }}
        </label>

        <div
          v-if="options.newAssignee.checked"
          class="u-ml">
          <!--when assignee reset will be possible in BE, this should be back-->
          <!--<label-->
          <!--for="r_recommendation_value"-->
          <!--class="u-mb-0_25 u-n-mt-0_5" />&lt;!&ndash;-->
          <!--&ndash;&gt;<p class="lbl__hint u-mb-0_5">-->
          <!--{{ Translator.trans('statements.assign.other.hint.reset') }}-->
          <!--</p>-->
          <dp-multiselect
            ref="newAssignee"
            v-model="options.newAssignee.value"
            :allow-empty="false"
            class="u-mb w-13"
            :custom-label="option => `${option.name} ${option.id === currentUserId ? '(Sie)' : ''}`"
            :options="users"
            track-by="id"
            @input="() => {options.newAssignee.isValid() ? $refs.newAssignee.$el.querySelector(options.newAssignee.elementToReceiveErrorBorder).classList.remove('border--error') : null}">
            <template v-slot:option="{ props }">
              {{ props.option.name }} {{ props.option.id === currentUserId? ` (Sie)` : '' }}
            </template>
          </dp-multiselect>
        </div>
      </div>

      <!--RECOMMENDATION-->
      <div class="u-mb">
        <input
          type="checkbox"
          id="r_recommendation"
          v-model="options.recommendation.checked">
        <label
          for="r_recommendation"
          class="inline-block">
          {{ Translator.trans('considerationadvice.text.add') }}
        </label>
        <div
          v-if="options.recommendation.checked"
          class="u-ml">
          <p class="lbl__hint u-mb-0_5">
            {{ Translator.trans('considerationadvice.text.add.explanation') }}
          </p>
          <dp-editor
            ref="recommendation"
            :value="options.recommendation.value"
            @input="updateRecommendationText">
            <template v-slot:modal="modalProps">
              <dp-boiler-plate-modal
                v-if="hasPermission('area_admin_boilerplates')"
                ref="boilerPlateModal"
                boiler-plate-type="consideration"
                :procedure-id="procedureId"
                @insert="text => modalProps.handleInsertText(text)" />
            </template>
            <template v-slot:button>
              <button
                v-if="hasPermission('area_admin_boilerplates')"
                :class="prefixClass('menubar__button')"
                type="button"
                v-tooltip="Translator.trans('boilerplate.insert')"
                @click.stop="openBoilerPlate">
                <i :class="prefixClass('fa fa-puzzle-piece')" />
              </button>
            </template>
          </dp-editor>
        </div>
      </div>

      <!-- 'Continue' and 'Back to consideration table' buttons-->
      <div class="text-right">
        <a
          class="btn btn--primary"
          role="button"
          @click.prevent="toggleMode('confirm')">
          {{ Translator.trans('continue.confirm') }}
          <i class="fa fa-angle-right u-pl-0_25" />
        </a>
        <a
          class="btn btn--secondary float-left"
          role="button"
          :href="Routing.generate('dplan_assessmenttable_view_table', { procedureId: procedureId, filterHash: filterHash })">
          <i class="fa fa-angle-left u-pr-0_25" />
          {{ Translator.trans('considerationtable.back') }}
        </a>
      </div>
    </div>

    <!-- Confirm view -->
    <div v-else-if="mode === 'confirm'">
      <h3 class="u-mt">
        {{ Translator.trans('actions.statements.confirm', { count: selectedElementsCount }) }}
      </h3>

      <div
        v-if="options.newAssignee.checked"
        class="u-mv">
        <label class="u-mb-0_25">
          {{ Translator.trans('statements.assign.other.confirmation') }}:
        </label>
        <p>
          {{ options.newAssignee.value.id !== '' ? options.newAssignee.value.name : Translator.trans('statements.assign.reset') }}
        </p>
      </div>

      <div
        v-if="options.recommendation.checked"
        class="u-mv">
        <label class="u-mb-0_25">
          {{ Translator.trans('consideration.text.to.be.added') }}:
        </label>
        <p class="lbl__hint u-mb-0_5">
          {{ Translator.trans('consideration.text.add.explanation') }}
        </p>

        <div class="u-mt-0_5 u-mb border u-p-0_75">
          <text-content-renderer :text="options.recommendation.value" />
        </div>
      </div>

      <!-- Back to edit and apply buttons-->
      <div class="text-right">
        <dp-button
          :busy="isLoading"
          icon-after="chevron-right"
          :text="Translator.trans('actions.statements.apply', { count: selectedElementsCount })"
          @click.once="submitData" />

        <a
          class="btn btn--secondary float-left"
          role="button"
          @click.prevent="toggleMode('edit')">
          <i class="fa fa-angle-left u-pr-0_25" />
          {{ Translator.trans('back.to.edit') }}
        </a>
      </div>
    </div>

    <!-- Success view after data has been saved -->
    <div v-else-if="mode === 'success'">
      <h3 class="u-mt">
        {{ Translator.trans('confirm.saved.plural') }}
      </h3>
      <p
        class="flash-confirm u-p-0_5"
        v-for="option in checkedOptions"
        :key="option">
        <i
          class="fa fa-check fa-lg"
          aria-hidden="true" />
        {{ Translator.trans(options[option].successMessage) }}
      </p>
      <dp-button
        icon="chevron-left"
        :text="Translator.trans('considerationtable.back')"
        @click="handleReturn" />
    </div>
  </div>
</template>

<script>
import {
  checkResponse,
  dpApi,
  DpButton,
  DpMultiselect,
  prefixClassMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapState } from 'vuex'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'
import { v4 as uuid } from 'uuid'

export default {
  name: 'DpBulkEditStatement',

  components: {
    DpBoilerPlateModal,
    DpMultiselect,
    DpButton,
    TextContentRenderer,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }
  },

  mixins: [prefixClassMixin],

  props: {
    authorisedUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    currentUserId: {
      required: true,
      type: String
    },

    filterHash: {
      required: false,
      type: String,
      default: () => { return '' }
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      isLoading: false,
      isError: false, // Shows if the save action failed or not (to display the link back to assessment table on error)
      mode: 'edit',
      options: {
        newAssignee: {
          checked: false,
          // Value: { id: '', name: '-' }, // when assignee reset will be possible in BE, this should be back
          value: '',
          // IsValid: () => true, // when assignee reset will be possible in BE, this should be back
          isValid: () => this.options.newAssignee.value !== '',
          elementToReceiveErrorBorder: '.multiselect__tags', // Has to be querySelector
          errorNotification: 'user.choose.from.list',
          successMessage: 'confirm.statements.assignment.changed'
        },
        recommendation: {
          checked: false,
          value: '',
          isValid: () => this.options.recommendation.value !== '',
          elementToReceiveErrorBorder: '.editor__content', // Has to be querySelector
          errorNotification: 'consideration.text.add.error',
          successMessage: 'consideration.text.added'
        }
      },
      users: this.authorisedUsers
    }
  },

  computed: {
    ...mapState('Statement', ['selectedElements']),
    ...mapGetters('Statement', ['selectedElementsLength']),

    // Array with keys (names) of all checked options
    checkedOptions () {
      const checkedOptions = []
      Object.keys(this.options).forEach(key => {
        if (this.options[key].checked) {
          checkedOptions.push(key)
        }
      })
      return checkedOptions
    },

    payloadAttributes () {
      return {
        markedStatementsCount: this.selectedElementsCount,
        ...(this.options.recommendation.checked && { recommendationAddition: this.options.recommendation.value })
      }
    },

    // Used in save action
    payloadRelationships () {
      return {
        statements: {
          data: this.selectedElementsIds.map(id => ({ id, type: 'statement' }))
        },
        ...(this.options.newAssignee.checked && { assignee: { data: this.options.newAssignee.value !== '' ? { type: 'user', id: this.options.newAssignee.value.id } : null } })
      }
    },

    selectedElementsIds () {
      return Object.keys(this.selectedElements)
    },

    selectedElementsCount () {
      return (this.selectedElementsLength)
    }
  },

  methods: {
    ...mapActions('Statement', {
      resetSelectionAction: 'resetSelection'
    }),
    ...mapActions('Statement', ['setSelectedElementsAction', 'setProcedureIdAction']),

    handleReturn () {
      this.resetSelectionAction()
        .then(() => {
          this.redirectToAssessmentTable()
        })
    },

    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
    },

    redirectToAssessmentTable () {
      window.location.href = Routing.generate('dplan_assessmenttable_view_table', { procedureId: this.procedureId, filterHash: this.filterHash })
    },

    submitData () {
      this.isLoading = true
      const payload = {
        data: {
          id: uuid(),
          type: 'statementBulkEdit',
          attributes: this.payloadAttributes,
          relationships: this.payloadRelationships
        }
      }
      return dpApi({
        method: 'POST',
        url: Routing.generate('dplan_assessment_table_assessment_table_statement_bulk_edit_api_action', {
          procedureId: this.procedureId
        }),
        data: payload
      })
        .then(checkResponse)
        .then(() => {
          this.mode = 'success'
          this.isLoading = false
        })
        .catch(() => {
          this.isLoading = false
          this.mode = 'confirm'
          this.isError = true
          dplan.notify.error(Translator.trans('statement.change.failed'))
        })
    },

    toggleMode (mode) {
      if (this.checkedOptions.length < 1) {
        dplan.notify.error(Translator.trans('actions.choose'))
        return
      }

      if (mode !== 'confirm') {
        this.mode = mode
      }

      if (mode === 'confirm') {
        let allCheckedValid = true
        // Perform validation
        this.checkedOptions.forEach(opt => {
          const optionElem = this.$refs[opt].$el.querySelector(this.options[opt].elementToReceiveErrorBorder)

          // Display errors for not valid elements; remove errors for elements, that are not valid
          if (this.options[opt].isValid() === false) {
            allCheckedValid = false
            optionElem.classList.add('border--error')
            dplan.notify.error(Translator.trans(this.options[opt].errorNotification))
          } else {
            if (optionElem.classList.contains('border--error')) {
              optionElem.classList.remove('border--error')
            }
          }
        })
        if (allCheckedValid === true) {
          this.mode = 'confirm'
        }
      }
    },

    // Update the recommendation value after boilerplate has been added
    updateRecommendationText (newText) {
      this.options.recommendation.value = newText
      if (this.options.recommendation.value !== '' && this.$refs.recommendation.$el.querySelector('.editor__content').classList.contains('border--error')) {
        this.$refs.recommendation.$el.querySelector('.editor__content').classList.remove('border--error')
      }
    }
  },

  created () {
    // Add empty option to dropdown
    this.users = this.authorisedUsers
    // This.users.unshift({id: '', name: '-'}) // when assignee reset will be possible in BE, this should be back
  },

  mounted () {
    //  Get selected statements from the store
    this.setProcedureIdAction(this.procedureId).then(() => {
      this.setSelectedElementsAction()
    })
  }
}
</script>
