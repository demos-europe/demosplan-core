<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
<!--
This is the component used in the assessment table to display fragments that belong to each statement.
THIS IS NOT THE COMPONENT USED IN THE FRAGMENTS LST FOR FACHBEHÖRDE!1!!11

Parent: DpFragmentList.vue
Children: DpItemRows with DpEditField, DpEditFieldSingleSelect, DpEditFieldMultiSelect, DpClaim.

useful info about the component:

- in general fragments are controlled by 'area_statements_fragment'
- then there are some additional permissions, like:
  1. feature_statements_fragment_add - for creating new fragments
  2. feature_statements_fragment_add_reviewer - to be able to assign the fragment to organization
  3. feature_statements_fragment_vote - as far as I understand it, this is turned on for Fachplaner, i.e. owners of fragment who decide which vote the fragment gets at the end
  4. feature_statements_fragment_advice - this is turned on for Fachbehörde, i.e. people who only advice about the vote
  5. feature_statement_content_changes_save - enables the older versions of texts / version history
  6. feature_statement_assignment - general permission for assigning entities to the user
-->
</documentation>

<template>
  <div>
    <!-- CHECKBOX & CLAIM -->
    <div
      class="u-ml-0_5 u-pt-0_5"
      data-cy="fragmentHead">
      <div>
        <input
          type="checkbox"
          v-model="fragmentSelected"
          :disabled="Object.keys(selectedElements).length > 0"
          :title="Object.keys(selectedElements).length > 0 ? Translator.trans('unselect.entity.first', {entity: Translator.trans('fragment')}) : false">
        <v-popover
          class="inline-block u-ml-0_125 weight--bold"
          placement="top"
          trigger="hover focus">
          {{ fragment.displayId }}
          <template v-slot:popover>
            <div>
              {{ Translator.trans('fragment.created') }} {{ fragmentCreatedDate }}<br>
              {{ Translator.trans('fragment.id') }}: {{ fragment.displayId }}<br>
            </div>
          </template>
        </v-popover>

        <!-- Navigation for fragment history, delete, assign to other user -->
        <table-card-flyout-menu
          class="float-right"
          :current-user-id="currentUserId"
          entity="fragment"
          :entity-id="fragment.id"
          :editable="isClaimed"
          :extern-id="fragment.displayId"
          :fragment-assignee-id="fragment.assignee?.id"
          :statement-id="statement.id"
          @fragment-delete="deleteFragment" />
      </div><!--
   --><dp-claim
        class="c-at-item__row-icon inline-block"
        entity-type="fragment"
        :assigned-id="(fragment.assignee?.id || '')"
        :assigned-name="(fragment.assignee?.name || '')"
        :assigned-organisation="(fragment.assignee?.orgaName || '')"
        :current-user-id="currentUserId"
        :current-user-name="currentUserName"
        :is-loading="updatingClaimState"
        :last-claimed-user-id="fragment.lastClaimedUserId"
        v-if="hasPermission('feature_statement_assignment')"
        @click="updateClaim" />
    </div>

    <article
      class="c-at-item u-ml-1_5 o-animate--bg-color-light"
      :id="'fragment_' + initialFragment.id">
      <!--REVIEWER - assign fragment to reviewer orga - Reviewer can be assigned only if no voteAdvice has been given-->
      <div
        class="layout--flush border--bottom border--top"
        v-if="hasPermission('feature_statements_fragment_add_reviewer')">
<!--
     --><template v-if="fragment.archivedOrgaName === null && fragment.archivedDepartmentName === null && fragment.voteAdvice === null">
          <div class="layout__item c-at-item__row u-1-of-1 u-pr-0_5">
            <dp-edit-field-single-select
              :label="Translator.trans('fragment.assign.reviewer')"
              field-key="departmentId"
              :entity-id="fragment.id"
              :options="fragmentReviewer"
              :value="fragment.departmentId || ''"
              @field:save="saveFragment"
              @toggleEditing="isEditing => { reviewerEditing = isEditing }"
              ref="departmentId"
              :editable="isClaimed && fragment.voteAdvice === null"
              :label-grid-cols="5" />
            <div
              class="layout__item c-at-item__row u-pt-0_25 lbl__hint u-7-of-12 float-right"
              v-if="reviewerEditing">
              <input
                id="notifyOrga"
                type="checkbox"
                v-model="notifyOrga">
              <label
                for="notifyOrga"
                class="inline-block u-mb-0_25">{{ Translator.trans('fragment.notify.reviewer') }}</label>
            </div>
          </div>
        </template>
        <template v-else>
          <div class="inline-block u-1-of-2 weight--bold u-pr-0_5 align-top u-mt-0_25">
            {{ Translator.trans('fragment.assign.reviewer') }}:
          </div><!--
         --><div class="inline-block u-1-of-2 lbl__hint u-ph-0_5 u-mt-0_25">
            <template v-if="fragment.archivedOrgaName === null && fragment.archivedDepartmentName === null && fragment.voteAdvice !== null">
              {{ hasPermission('feature_statements_fragment_advice') ? Translator.trans("fragment.assign.reviewer.voteAdvice.pending.reset") : Translator.trans('fragment.assign.reviewer.voteAdvice.pending') }}
            </template>
            <template v-else>
              {{ Translator.trans('fragment.voteAdvice.given') }}
            </template>
          </div>
        </template>
      </div>

      <!-- STATUS / VOTE / VOTEADVICE /  -->
      <div class="layout--flush border--bottom">
<!--
   --><div
          v-if="hasPermission('field_fragment_status')"
          class="layout__item c-at-item__row u-1-of-2 u-pr-0_5 border--right">
          <!--  If fragment has been assignedToFB, status select is disabled  -->
          <dp-edit-field-single-select
            label="Status"
            field-key="status"
            :entity-id="fragment.id"
            :options="fragmentStatus"
            :value="fragment.status || ''"
            @field:save="saveFragment"
            :title="fragment.status === 'assignedToFB' ? 'Dieser Status wird vom System automatisch zugewiesen.' : ''"
            ref="status"
            :editable="isClaimed"
            :label-grid-cols="4" />
        </div><!--
      --><div
           v-if="hasPermission('feature_statements_fragment_vote')"
           class="layout__item c-at-item__row u-1-of-2">
            <dp-edit-field-single-select
              label="fragment.vote.short"
              class="u-mh-0_5"
              field-key="vote"
              :entity-id="fragment.id"
              :options="adviceValues"
              :value="fragment.vote || ''"
              @field:save="saveFragment"
              ref="vote"
              data-cy="fragmentVote"
              :editable="isClaimed && editableVoteAdvice === false && !fragment.departmentId"
              :label-grid-cols="4" />
        </div><!--
       --><div
        class="layout__item c-at-item__row u-1-of-1 u-pr-0_5 border--top"
        v-if="hasPermission('field_statement_fragment_advice')">
            <!-- If project features Reviewers, voteAdvice can only be modified when fragment is not assigned to Reviewer (so departmentId is '' or null) -->
            <dp-edit-field-single-select
              v-if="(hasPermission('feature_statements_fragment_add_reviewer') && !fragment.departmentId) || false === hasPermission('feature_statements_fragment_add_reviewer')"
              label="fragment.voteAdvice.short"
              field-key="voteAdvice"
              :entity-id="fragment.id"
              :options="adviceValues"
              :value="fragment.voteAdvice || ''"
              @field:save="saveFragment"
              ref="voteAdvice"
              :v-tooltip="voteAdvicePending"
              :readonly="editableVoteAdvice === false"
              :editable="editableVoteAdvice && fragment.archivedOrgaName === null && isClaimed"
              :label-grid-cols="2" />
            <template v-else>
              <div class="inline-block u-2-of-8 weight--bold align-top u-mt-0_25">
                {{ Translator.trans('fragment.voteAdvice.short') }}:
              </div><!--
           --><div class="inline-block u-6-of-8 flash-warning lbl__hint u-ph-0_25 u-mt-0_25">
                {{ Translator.trans('fragment.voteAdvice.assigned') }}
              </div>
            </template>
          </div>
      </div>

      <!-- TAGS -->
      <div
        class="layout--flush border--bottom"
        v-if="hasPermission('feature_statements_fragment_add')">
<!--
     --><div class="layout__item c-at-item__row u-1-of-1 u-pr-0_5">
          <dp-edit-field-multi-select
            :label="Translator.trans('tags')"
            field-key="tags"
            :entity-id="fragment.id"
            :options="tags"
            :value="fragment.tags"
            group-values="tags"
            group-label="title"
            data-cy="fragmentTag"
            :is-group-select="true"
            @field:save="saveFragment"
            @toggleEditing="isEditing => { tagsEditing = isEditing }"
            ref="tags"
            :editable="isClaimed"
            :label-grid-cols="2" />

            <div
              class="layout__item c-at-item__row u-pt-0_25 lbl__hint u-10-of-12 float-right"
              v-if="tagsEditing && hasPermission('feature_optional_tag_propagation')">
              <label
                class="inline-block u-mb-0_25"
                :for="`r_forward_tags_to_statements_${fragment.id}`">
                <input
                  type="checkbox"
                  v-model="forwardTags"
                  data-cy="forwardTagsToStatementsTable"
                  :id="`r_forward_tags_to_statements_${fragment.id}`">
                {{ Translator.trans('forward.tags.to.statements') }}
              </label>
            </div>
          </div>
      </div>

      <!-- LOCATION - counties / municipalities / priorityAreas -->
      <div
        v-if="showLocationRow"
        class="layout--flush border--bottom">
<!--
       --><div class="layout__item c-at-item__row u-1-of-1 u-pr-0_5">
          <dp-edit-field-multi-select
            v-if="hasPermission('field_statement_county')"
            :label="Translator.trans('counties')"
            field-key="counties"
            :entity-id="fragment.id"
            :options="counties"
            :value="Object.values(fragment.counties)"
            @field:save="saveFragment"
            ref="counties"
            :editable="isClaimed"
            :label-grid-cols="2" />

        <dp-edit-field-multi-select
          v-if="hasPermission('field_statement_municipality') && statementFormDefinitions.mapAndCountyReference.enabled"
          :label="Translator.trans('municipalities')"
          field-key="municipalities"
          :entity-id="fragment.id"
          :options="municipalities"
          :value="Object.values(fragment.municipalities)"
          @field:save="saveFragment"
          ref="municipalities"
          :editable="isClaimed"
          :label-grid-cols="2" />

          <dp-edit-field-multi-select
            v-if="dplan.procedureStatementPriorityArea && statementFormDefinitions.mapAndCountyReference.enabled"
            :label="Translator.trans('priorityAreas.all')"
            field-key="priorityAreas"
            :entity-id="fragment.id"
            :options="priorityAreas"
            :value="Object.values(fragment.priorityAreas)"
            @field:save="saveFragment"
            ref="priorityAreas"
            :editable="isClaimed"
            :label-grid-cols="2" />
        </div>
      </div>

      <!--fragment ELEMENT -->
      <div
        class="layout--flush border--bottom u-pr-0_5"
        v-if="hasPermission('field_procedure_elements')">
<!--
     --><div class="layout__item c-at-item__row u-1-of-1">
          <dp-edit-field-single-select
            class="relative"
            label="document"
            :entity-id="fragment.id"
            field-key="elementId"
            :value="fragment.elementId ? fragment.elementId : ''"
            :options="elements"
            :label-grid-cols="2"
            :editable="isClaimed"
            ref="elementId"
            @field:save="saveFragment" />

          <!--PARAGRAPH-->
          <dp-edit-field-single-select
            class="relative"
            label="paragraph"
            :label-grid-cols="2"
            :entity-id="fragment.id"
            field-key="paragraphParentId"
            :value="fragment.paragraphParentId ? fragment.paragraphParentId : ''"
            :options="selectedElementParagraph"
            :editable="isClaimed"
            ref="paragraphParentId"
            @field:save="saveFragment"
            v-if="elementHasParagraphs" />

          <!--FILE-->
          <dp-edit-field-single-select
            v-if="hasPermission('feature_single_document_fragment') && elementHasFiles"
            class="relative"
            label="file"
            :label-grid-cols="2"
            :entity-id="fragment.id"
            field-key="documentParentId"
            :value="fragment.documentParentId ? fragment.documentParentId : ''"
            :options="selectedElementFile"
            :editable="isClaimed"
            ref="documentParentId"
            @field:save="saveFragment" />
        </div>
      </div>

      <!--TEXT and CONSIDERATION / considerationAdvice - either consideration field or considerationAdvice field is displayed, depending on permissions -->
      <div class="layout--flush">
<!--
     --><div class="flex">
          <editable-text
            title="fragment.text"
            class="c-styled-html u-mt-0_25 u-pr-0_5 u-1-of-2 u-pb-0_5 border--right"
            :initial-text="fragmentText"
            :entity-id="fragment.id"
            :initial-is-shortened="false"
            :procedure-id="procedureId"
            full-text-fetch-route=""
            field-key="text"
            :editable="isClaimed"
            edit-label="fragment.edit"
            mark
            strikethrough
            :obscure="hasPermission('feature_obscure_text')"
            height-limit-element-label="fragment"
            @field:save="saveFragment"
            ref="text" />

          <editable-text
            v-if="editableConsiderationAdvice"
            title="fragment.considerationAdvice"
            class="c-styled-html u-mt-0_25 u-1-of-2 u-ph-0_5 u-pb-0_5"
            :initial-text="fragmentConsideration"
            :entity-id="fragment.id"
            :initial-is-shortened="false"
            :procedure-id="procedureId"
            full-text-fetch-route=""
            field-key="fragment.considerationAdvice"
            :editable="isClaimed && editableConsiderationAdvice"
            edit-label="fragment.considerationAdvice"
            link-button
            :boiler-plate="hasPermission('area_admin_boilerplates')"
            height-limit-element-label="fragment"
            @field:save="saveFragment"
            ref="considerationAdvice"
            data-cy="considerationAdvice" />
          <editable-text
            v-else
            title="fragment.consideration"
            class="u-mt-0_25 u-1-of-2 u-ph-0_5 u-pb-0_5"
            :initial-text="fragmentConsideration"
            :entity-id="fragment.id"
            :procedure-id="procedureId"
            :initial-is-shortened="false"
            full-text-fetch-route=""
            field-key="consideration"
            :editable="isClaimed && editableConsideration"
            edit-label="fragment.consideration"
            link-button
            :boiler-plate="hasPermission('area_admin_boilerplates')"
            height-limit-element-label="fragment"
            @field:save="saveFragment"
            ref="consideration"
            data-cy="fragmentConsideration" />
        </div>
      </div>
    </article>
  </div>
</template>

<script>
import { dpApi, formatDate, hasOwnProp, VPopover } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapState } from 'vuex'
import { Base64 } from 'js-base64'
import DpClaim from '../DpClaim'
import DpEditFieldMultiSelect from './DpEditFieldMultiSelect'
import DpEditFieldSingleSelect from './DpEditFieldSingleSelect'
import EditableText from './EditableText'
import TableCardFlyoutMenu from './TableCardFlyoutMenu'

export default {
  name: 'DpAssessmentFragment',

  components: {
    DpClaim,
    DpEditFieldMultiSelect,
    DpEditFieldSingleSelect,
    EditableText,
    TableCardFlyoutMenu,
    VPopover
  },

  props: {
    currentUserId: {
      type: String,
      required: true
    },

    currentUserName: {
      type: String,
      required: true
    },

    fragmentId: {
      type: String,
      required: true
    },

    initialFragment: {
      required: true,
      type: Object
    },
    procedureId: {
      required: true,
      type: String
    },
    statement: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      editing: false,
      forwardTags: false,
      notifyOrga: false,
      reviewerEditing: false,
      tagsEditing: false,
      updatingClaimState: false
    }
  },

  computed: {
    ...mapState('AssessmentTable', ['statementFormDefinitions']),
    ...mapState('Fragment', ['sideBarInitialized']),

    fragmentCreatedDate () {
      return formatDate(this.fragment.created)
    },

    isClaimed () {
      /*
       * The fragment is only editable if
       * a) in case the permission for the claim feature is active:
       * - the fragment has an assignee
       * - the assignee is the currently logged-in user
       * b) in case the permission for the claim feature is not active:
       * always
       */
      if (hasPermission('feature_statement_assignment')) {
        return this.fragment.assignee && this.fragment.assignee?.id === this.currentUserId
      } else {
        return true
      }
    },

    /**
     * Checks if user has permission to edit consideration
     */
    editableConsideration () {
      return hasPermission('feature_statements_fragment_consideration') && !this.fragment.departmentId
    },

    /**
     * Checks if user has permission to edit consideration advice
     */
    editableConsiderationAdvice () {
      return hasPermission('feature_statements_fragment_consideration') === false && hasPermission('feature_statements_fragment_consideration_advice') && this.fragment.departmentId !== ''
    },
    /**
     * Checks if user has permission to edit vote advice
     */
    editableVoteAdvice () {
      return hasPermission('feature_statements_fragment_vote') === false && hasPermission('feature_statements_fragment_advice')
    },

    elementHasFiles () {
      return this.fragment.elementId && Array.isArray(this.documents[this.fragment.elementId])
    },

    elementHasParagraphs () {
      return this.fragment.elementId && Array.isArray(this.paragraph[this.fragment.elementId])
    },

    elementLink () {
      const routeParams = {
        procedure: this.fragment.procedureId,
        elementId: this.statement.elementId
      }
      let route = '#'

      if (this.statement.elementCategory === 'paragraph') {
        route = Routing.generate('DemosPlan_public_plandocument_paragraph', routeParams)
      } else if (this.statement.elementCategory === 'file') {
        routeParams.category = this.statement.elementCategory
        route = Routing.generate('DemosPlan_elements_administration_edit', routeParams)
      }

      return route
    },

    // Get fragment directly from store to ensure reactivity
    fragment () {
      return this.fragmentById(this.statement.id, this.fragmentId)
    },

    /**
     * Returns consideration or consideration advice if user has permission
     */
    fragmentConsideration () {
      let text = null
      if (hasPermission('feature_statements_fragment_consideration')) {
        text = this.fragment.consideration === null ? '' : this.fragment.consideration
      } else if (hasPermission('feature_statements_fragment_consideration_advice')) {
        text = this.fragment.considerationAdvice === null ? '' : this.fragment.considerationAdvice
      }

      return Base64.encode(text)
    },

    fragmentSelected: {
      get () {
        return hasOwnProp(this.selectedFragments, this.fragment.id)
      },
      set (value) {
        if (value === true) {
          this.addFragmentToSelectionAction({
            id: this.fragment.id,
            statementId: this.statement.id,
            assignee: hasOwnProp(this.fragment.assignee, 'id') ? this.fragment.assignee : { id: '' }
          })
        } else {
          this.removeFragmentFromSelectionAction(this.fragment.id)
        }
      }
    },

    fragmentText () {
      return Base64.encode(this.fragment.text)
    },

    selectedElementParagraph () {
      return this.paragraph[this.fragment.elementId] || []
    },

    selectedElementFile () {
      return this.documents[this.fragment.elementId] || []
    },

    showLocationRow () {
      return this.statementFormDefinitions.countyReference.enabled || this.statementFormDefinitions.mapAndCountyReference.enabled ? (dplan.procedureStatementPriorityArea || hasPermission('field_statement_county') || hasPermission('field_statement_municipality')) : false
    },

    voteAdvicePending () {
      if (hasOwnProp(this.agencies, this.fragment.departmentId)) {
        const assignedDepartment = this.agencies[this.fragment.departmentId]

        return Translator.trans('fragment.voteAdvice.status.pending', {
          orgaName: assignedDepartment.orgaName,
          departmentName: assignedDepartment.departmentName
        })
      } else {
        return ''
      }
    },

    //  Map store getters to local computed properties with object spread operator
    ...mapGetters(
      'AssessmentTable',
      [
        'adviceValues',
        'agencies',
        'counties',
        'documents',
        'elements',
        'fragmentStatus',
        'fragmentReviewer',
        'municipalities',
        'paragraph',
        'priorityAreas',
        'tags'
      ]
    ),
    ...mapGetters('Fragment', ['fragmentById', 'selectedFragments']),
    ...mapGetters('Statement', ['selectedElements'])
  },

  methods: {
    ...mapActions('Fragment', ['updateFragmentAction', 'addFragmentToSelectionAction', 'deleteFragmentAction', 'removeFragmentFromSelectionAction', 'setAssigneeAction']),

    deleteFragment (fragmentId) {
      if (dpconfirm(Translator.trans('check.fragment.delete'))) {
        this.deleteFragmentAction({ procedureId: this.procedureId, statementId: this.statement.id, fragmentId })
      }
    },

    saveFragment (data) {
      // When add new Tags and checkbox of forwardTagsStatement is checked, then save all of changes
      if (hasOwnProp(data, 'tags')) {
        if (this.forwardTags === true) {
          data.forwardTagsStatement = true
        }
        this.tagsEditing = false
      }

      //  When element is saved and selected element has no paragraphs, reset paragraph (which holds the currently selected paragraph) and file to avoid situation, that a paragraph/file from other element is selected
      if (hasOwnProp(data, 'elementId')) {
        data.paragraphParentId = ''
        data.documentParentId = ''
        if (this.$refs.paragraphParentId) {
          this.$refs.paragraphParentId.$data.selected = ''
          this.$refs.paragraphParentId.$data.selectedBefore = ''
        }
        if (this.$refs.documentParentId) {
          this.$refs.documentParentId.$data.selected = ''
          this.$refs.documentParentId.$data.selectedBefore = ''
        }
      }

      if (hasOwnProp(data, 'departmentId')) {
        // If reviewer is changed and orga should be notified, add a new query param (this is used later in store, where the request is sent)
        if (this.notifyOrga === true) {
          data.notifyReviewer = true
        }
        // If reviewer is set, we have to correctly update assignment and automatically change fragment status
        if (data.departmentId !== '') {
          data.lastClaimed = this.currentUserId
          data.status = 'fragment.status.assignedToFB'
        }
        this.reviewerEditing = false
      }

      //  ********** FIRE STORE UPDATE ACTION **********
      this.updateFragmentAction(data).then((updated) => {
        let updatedField = ''

        for (const field in updated) {
          updatedField = field

          // If tags are changed, we have to add the tag content to recommendation text field
          if (field === 'tags') {
            // This string will concatenate all tags' texts we want to later add to consideration/considerationAdvice
            let textToBeAdded = ''
            // First we have to know which field we want to update (which field is visible)
            let fieldToUpdate = ''
            if (this.$refs.consideration) {
              fieldToUpdate = this.$refs.consideration
            } else if (this.$refs.considerationAdvice) {
              fieldToUpdate = this.$refs.considerationAdvice
            }

            // Then we fire a request for each tag - we return an array of promises to wait until all requests are finished
            const tags = Object.values(updated.tags).map(tag => {
              return dpApi.post(Routing.generate('dm_plan_assessment_get_boilerplates_ajax', {
                tag: tag.id,
                procedure: this.fragment.procedureId
              }))
                .then(data => {
                  if (data.data.code === 100 && data.data.success) {
                    // If the tag's text is already in consideration, we don't want to add it again
                    if (fieldToUpdate.$data.fullText.includes(data.data.body) || (fieldToUpdate.$refs.editor && fieldToUpdate.$refs.editor.$data.editor.getHTML().includes(data.data.body))) {
                      return false
                    } else {
                      textToBeAdded += '<p>' + data.data.body + '</p>'
                      return Promise.resolve(true)
                    }
                  }
                })
            })

            // After all requests are completed we can add the tag texts to Begründungsfeld
            Promise.all(tags).then(() => {
              if (textToBeAdded !== '') {
                if (fieldToUpdate.$data.fullText !== 'k.A.') {
                  fieldToUpdate.$data.fullText += textToBeAdded
                } else {
                  fieldToUpdate.$data.fullText = textToBeAdded
                }

                fieldToUpdate.$data.isEditing = true
                dplan.notify.notify('info', Translator.trans('info.tag.text.added'))
              }
            })
          }

          // Update short and full texts in EditableText.vue
          if (field === 'text' || field === 'consideration' || field === 'considerationAdvice') {
            this.$root.$emit('entityTextSaved:' + this.fragmentId, { entityId: this.fragmentId, field })
          }

          //  Unset loading state of saved field
          if (this.$refs[field]) {
            //  Handle components that use <dp-edit-field>
            const editFieldComponent = this.$refs[field].$children.find(child => child.$options.name === 'DpEditField')
            if (editFieldComponent) {
              editFieldComponent.$data.loading = false
              editFieldComponent.$data.editingEnabled = false
            }
            //  Handle components that have a loading state by themselves
            if (hasOwnProp(this.$refs[field].$data, 'loading')) {
              this.$refs[field].$data.loading = false
            }
            if (hasOwnProp(this.$refs[field].$data, 'editingEnabled')) {
              this.$refs[field].$data.editingEnabled = false
            }
            if (hasOwnProp(this.$refs[field].$data, 'isEditing')) {
              this.$refs[field].$data.isEditing = false
            }
          }
        }

        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))

        // Used in DpVersionHistory to update items in version history sidebar
        this.$root.$emit('entity:updated', this.fragmentId, 'fragment')

        return updatedField
      })
        .catch(e => {
          // Set the correct loading states even if error occurred
          Object.values(this.$refs).forEach(ref => {
            const editFieldComponent = ref.$children.find(child => child.$options.name === 'DpEditField')
            if (editFieldComponent) {
              editFieldComponent.$data.loading = false
              editFieldComponent.$data.editingEnabled = false
            }
            //  Handle components that have a loading state by themselves
            if (hasOwnProp(ref.$data, 'loading')) {
              ref.$data.loading = false
            }
            if (hasOwnProp(ref.$data, 'isEditing')) {
              ref.$data.isEditing = false
            }
          })
        })
    },

    updateClaim () {
      this.updatingClaimState = true

      // Last claimed user is only needed if departmentId is set and we want to unassign the fragment. Only then we need the info who was the last assignee to be able to assign the fragment back. Last claimed is also saved when we assign the fragment to department, but this happens in another action (update fragment). therefore, if departmentId === '' and fragment is claimed, ignoreLastClaimed should be false (because when we click on the user icon we want the fragment to be still assigned to department, and not freigegeben). In all other cases should be true.
      const shouldIgnoreLastClaimed = this.fragment.departmentId === '' && hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee?.id === this.currentUserId

      const assigneeData = {
        fragmentId: this.fragmentId,
        statementId: this.statement.id,
        ignoreLastClaimed: shouldIgnoreLastClaimed,
        assigneeId: (hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee?.id === this.currentUserId ? '' : this.currentUserId),
        ...((shouldIgnoreLastClaimed === false && this.fragment.assignee?.id === this.currentUserId) && { lastClaimed: this.currentUserId })
      }

      this.setAssigneeAction(assigneeData)
        .then(() => {
          this.updatingClaimState = false
          this.$root.$emit('entity:updated', this.fragment.id, 'fragment')

          if (hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee?.id !== this.currentUserId) {
            this.reviewerEditing = false
          }
        })
    }
  }
}
</script>
