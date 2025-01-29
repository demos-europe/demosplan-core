<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
    <!-- This component is used for multi-step editing of fragments. It contains three views (edit, confirm, success)
    that are toggled as you progress in the editing process. Data is sent to BE only in the final view.
    -->
    <usage variant="minimum (required props)">
        <dp-bulk-edit-fragment
            :procedure-id="procedureId"
        >
        </dp-bulk-edit-fragment>
    </usage>
    <usage variant="full">
        <dp-bulk-edit-fragment
            :procedure-id="procedureId"
            filter-hash="filterHash"
        >
        </dp-bulk-edit-fragment>
    </usage>
</documentation>

<template>
  <div>
    <!-- Edit view -->
    <div v-if="mode === 'edit'">
      <h3 class="u-mv">
        {{ Translator.trans('actions.fragments.configure', { count: selectedFragmentsCount }) }}
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
          {{ Translator.trans('fragments.assign.other') }}
        </label>

        <div
          v-if="options.newAssignee.checked"
          class="u-ml">
          <!--when assignee reset will be possible in BE, this should be back-->
          <!--<label-->
          <!--for="r_consideration_value"-->
          <!--class="u-mb-0_25 u-n-mt-0_5" />&lt;!&ndash;-->
          <!--&ndash;&gt;<p class="lbl__hint u-mb-0_5">-->
          <!--{{ Translator.trans('fragments.assign.other.hint.reset') }}-->
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

      <!--CONSIDERATION-->
      <div class="border--bottom u-mb">
        <input
          type="checkbox"
          id="r_consideration"
          v-model="options.consideration.checked">
        <label
          for="r_consideration"
          class="inline-block">
          {{ Translator.trans('consideration.text.add') }}
        </label>
        <div
          v-if="options.consideration.checked"
          class="u-ml">
          <label
            for="r_consideration_value"
            class="u-mb-0_25  u-n-mt-0_5" /><!--
          --><p class="lbl__hint u-mb-0_5">
          {{ Translator.trans('consideration.text.add.explanation') }}
        </p>

          <dp-editor
            ref="consideration"
            :value="options.consideration.value"
            @input="updateConsiderationText">
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
        {{ Translator.trans('actions.fragments.confirm', { count: selectedFragmentsCount }) }}
      </h3>

      <div
        v-if="options.newAssignee.checked"
        class="u-mv">
        <label class="u-mb-0_25">
          {{ Translator.trans('fragments.assign.other.confirmation') }}:
        </label>
        <p>{{ options.newAssignee.value.id !== '' ? options.newAssignee.value.name : Translator.trans('fragments.assign.reset') }}</p>
      </div>

      <div
        v-if="options.consideration.checked"
        class="u-mv">
        <label class="u-mb-0_25">
          {{ Translator.trans('consideration.text.to.be.added') }}:
        </label>
        <p class="lbl__hint u-mb-0_5">
          {{ Translator.trans('consideration.text.add.explanation') }}
        </p>

        <div class="u-mt-0_5 u-mb border u-p-0_75">
          <text-content-renderer :text="options.consideration.value" />
        </div>
      </div>

      <!-- Back to edit and apply buttons-->
      <div class="text-right">
        <dp-button
          v-if="isError === false"
          :busy="isLoading"
          icon-after="chevron-right"
          :text="Translator.trans('actions.fragments.apply', { count: selectedFragmentsCount })"
          @click.once="submitData" />
        <!-- if there's an error in response (so edit failed), show the 'back to ATabelle' button -->
        <a
          v-if="isError"
          class="btn btn--secondary float-right"
          role="button"
          :href="Routing.generate('dplan_assessmenttable_view_table', { procedureId: procedureId, filterHash: filterHash })">
          {{ Translator.trans('considerationtable.back') }}
        </a>

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
      <a
        class="btn btn--primary float-left u-mt-0_5"
        role="button"
        :href="Routing.generate('dplan_assessmenttable_view_table', { procedureId: procedureId, filterHash: filterHash })">
        <i class="fa fa-angle-left u-pr-0_25" />
        {{ Translator.trans('considerationtable.back') }}
      </a>
    </div>
  </div>
</template>

<script>
import { checkResponse, dpApi, DpButton, DpMultiselect, hasOwnProp, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'
import { v4 as uuid } from 'uuid'

export default {
  name: 'DpBulkEditFragment',

  components: {
    DpBoilerPlateModal,
    DpButton,
    TextContentRenderer,
    DpMultiselect,
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
      mode: 'edit',
      users: this.authorisedUsers,
      options: {
        newAssignee: {
          checked: false,
          // Value: { id: '', name: '-' }, // when assignee reset will be possible in BE, this should be back
          value: '',
          // IsValid: () => true, // when assignee reset will be possible in BE, this should be back
          isValid: () => this.options.newAssignee.value !== '',
          elementToReceiveErrorBorder: '.multiselect__tags',
          errorNotification: 'user.choose.from.list',
          successMessage: 'confirm.fragments.assignment.changed'
        },
        consideration: {
          checked: false,
          value: '',
          isValid: () => this.options.consideration.value !== '',
          elementToReceiveErrorBorder: '.editor__content',
          errorNotification: 'consideration.text.add.error',
          successMessage: 'consideration.text.added'
        }
      },
      isLoading: false,
      isError: false // Shows if the save action failed or not (to display the link back to assessment table on error)
    }
  },

  computed: {
    ...mapGetters('Fragment', ['selectedFragments']),

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

    // Used in save action
    payloadAttributes () {
      return {
        markedStatementFragmentsCount: this.selectedFragmentsCount,
        statementFragmentIds: this.selectedFragmentsIds,
        ...(this.options.consideration.checked && { considerationAddition: this.options.consideration.value })
      }
    },

    payloadRelationships () {
      return {
        ...(this.options.newAssignee.checked && { assignee: { data: this.options.newAssignee.value !== '' ? { type: 'user', id: this.options.newAssignee.value.id } : null } })
      }
    },

    selectedFragmentsCount () {
      return Object.keys(this.selectedFragments).length
    },

    selectedFragmentsIds () {
      const ids = []
      Object.keys(this.selectedFragments).forEach((elem) => ids.push(elem))
      return ids
    }
  },

  methods: {
    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
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

    // Update the consideration value after boilerplate has been added
    updateConsiderationText (newText) {
      this.options.consideration.value = newText
      if (this.options.consideration.value !== '' && this.$refs.consideration.$el.querySelector('.editor__content').classList.contains('border--error')) {
        this.$refs.consideration.$el.querySelector('.editor__content').classList.remove('border--error')
      }
    },

    setInitialUsers () {
      this.users = this.authorisedUsers
      this.users.sort((a, b) => a.name.localeCompare(b.name, 'de', { sensitivity: 'base' }))
    },

    submitData () {
      this.isLoading = true
      const payload = {
        data: {
          id: uuid(),
          type: 'statement-fragment-update',
          attributes: this.payloadAttributes,
          relationships: this.payloadRelationships
        }
      }
      return dpApi.post(Routing.generate('dplan_api_assessment_table_statement_fragment_update_create'),
        {}, payload)
        .then(checkResponse)
        .then(() => {
          this.mode = 'success'
          this.isLoading = false
        })
        .catch(error => {
          this.submitted = false
          this.isLoading = false
          this.mode = 'confirm'
          this.isError = true
          // Display error messages from response
          const errorMeta = error.response.data.meta
          if (hasOwnProp(errorMeta, 'messages')) {
            for (const type in errorMeta.messages) {
              for (const message in errorMeta.messages[type]) {
                dplan.notify.notify(type, Translator.trans(errorMeta.messages[type][message]))
              }
            }
          }
        })
    },

    ...mapActions('Fragment', ['setSelectedFragmentsAction']),
    ...mapMutations('Fragment', ['setProcedureId'])
  },

  created () {
    // Add empty option to dropdown
    this.setInitialUsers()
    // This.users.unshift({id: '', name: '-'}) // when assignee reset will be possible in BE, this should be back
  },

  mounted () {
    //  Get selected statements from the store
    this.setProcedureId(this.procedureId)
    this.setSelectedFragmentsAction()
  }

}
</script>
