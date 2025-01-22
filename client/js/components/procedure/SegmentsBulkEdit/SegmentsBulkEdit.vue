<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <action-stepper
    class="u-mv"
    :busy="busy"
    :valid="hasActions && hasSegments"
    :return-link="returnLink"
    :step="step"
    :selected-elements="segments.length"
    @apply="apply"
    @confirm="step = 2"
    @edit="step = 1">
    <!-- Step 1 - Chose action -->
    <template v-slot:step-1>
      <div class="border-between-vertical">
        <!-- Assign user -->
        <action-stepper-action
          v-if="hasPermission('feature_statement_assignment')"
          v-model="actions.assignSegment.checked"
          id="selectAssignAction"
          :label="Translator.trans('assign.for.editing')"
        >
          <dp-multiselect
            class="w-12"
            id="assignSegment"
            :disabled="!hasSegments"
            :options="assignableUsers"
            v-model="actions.assignSegment.selected"
            label="name"
            track-by="id" />
        </action-stepper-action>

        <!-- Assign place/status -->
        <action-stepper-action
          v-model="actions.assignPlace.checked"
          id="selectAssignPlaceAction"
          :label="Translator.trans('segments.bulk.edit.place.add')">
          <dp-multiselect
            class="w-12"
            id="assignPlace"
            :disabled="!hasPlaces"
            :options="places"
            v-model="actions.assignPlace.selected"
            label="name"
            track-by="id" />
        </action-stepper-action>

        <!-- Add tags -->
        <action-stepper-action
          v-model="actions.addTags.checked"
          id="selectAddTagsAction"
          :label="Translator.trans('segments.bulk.edit.tags.add')">
          <dp-multiselect
            id="addTags"
            multiple
            :disabled="!hasSegments"
            :options="filteredTagsByTopic"
            v-model="actions.addTags.selected"
            label="title"
            track-by="id"
            group-values="tags"
            group-label="title"
            :group-select="false" />
        </action-stepper-action>

        <!-- Remove tags -->
        <action-stepper-action
          v-model="actions.deleteTags.checked"
          id="selectDeleteTagsAction"
          :label="Translator.trans('segments.bulk.edit.tags.delete')">
          <dp-multiselect
            id="deleteTags"
            multiple
            :disabled="!hasSegments"
            :options="filteredTagsByTopic"
            v-model="actions.deleteTags.selected"
            label="title"
            track-by="id"
            group-values="tags"
            group-label="title"
            :group-select="false" />
        </action-stepper-action>

        <!-- Append text to recommendation -->
        <action-stepper-action
          v-model="actions.addRecommendations.checked"
          id="selectAddRecommendationAction"
          :label="Translator.trans('segments.bulk.edit.recommendations.add')">
          <div class="u-mb-0_5">
            <dp-radio
              id="attachTextRadioId"
              v-model="actions.addRecommendations.isTextAttached"
              :checked="actions.addRecommendations.isTextAttached"
              :label="{
                bold: false,
                text: Translator.trans('segments.bulk.edit.recommendations.radio.text.attach')
              }"
              @change="actions.addRecommendations.isTextReplaced = false" />
            <dp-radio
              id="replaceTextRadioId"
              v-model="actions.addRecommendations.isTextReplaced"
              :checked="actions.addRecommendations.isTextReplaced"
              :label="{
                bold: false,
                text: Translator.trans('segments.bulk.edit.recommendations.radio.text.replace')
              }"
              @change="actions.addRecommendations.isTextAttached = false" />
          </div>
          <dp-editor
            id="addRecommendationTipTap"
            v-model="actions.addRecommendations.text"
            editor-id="recommendationText"
            :toolbar-items="{
              fullscreenButton: true,
              linkButton: true
            }">
            <template v-slot:modal="modalProps">
              <dp-boiler-plate-modal
                v-if="hasPermission('area_admin_boilerplates')"
                ref="boilerPlateModal"
                boiler-plate-type="consideration"
                editor-id="recommendationText"
                :procedure-id="procedureId"
                @insert="text => modalProps.handleInsertText(text)" />
            </template>
            <template v-slot:button>
              <button
                v-if="hasPermission('area_admin_boilerplates')"
                :class="prefixClass('menubar__button')"
                :disabled="!hasSegments"
                type="button"
                v-tooltip="Translator.trans('boilerplate.insert')"
                @click.stop="openBoilerPlate">
                <i :class="prefixClass('fa fa-puzzle-piece')" />
              </button>
            </template>
          </dp-editor>
        </action-stepper-action>
      </div>
    </template>

    <!-- Step 2 - Confirm -->
    <template v-slot:step-2>
      <div class="border-between-vertical">
        <dp-inline-notification
          type="info"
          :message="Translator.trans('bulk.edit.info.assigned', { count: segments.length})"
          class="border-between-none mt-3 mb-2" />

        <dp-inline-notification
          v-if="actions.addRecommendations.text === '' && addRecommendationsChecked"
          type="warning"
          :message="emptyRecommendationWarning"
          class="border-between-none mt-3 mb-2" />

        <div
          v-if="hasPermission('feature_statement_assignment') && assignSegmentCheckedAndSelected"
          class="u-mt u-pb-0_5">
          <label class="u-mb-0_25 weight--normal">
            {{ Translator.trans('segments.assign.other.confirmation') }}
          </label>
          <p>
            {{ actions.assignSegment.selected.name }}
          </p>
        </div>

        <div
          v-if="assignPlaceCheckedAndSelected"
          class="u-pv">
          <p v-html="Translator.trans('segments.bulk.edit.place.assigned.description')" />
          <p v-cleanhtml="actions.assignPlace.selected.name" />
        </div>

        <div
          v-if="addTagsCheckedAndSelected"
          class="u-pv">
          <p v-html="Translator.trans('segments.bulk.edit.tags.add.description', { count: segments.length})" />
          <selected-tags-list :selected-tags="actions.addTags.selected" />
        </div>

        <div
          v-if="deleteTagsCheckedAndSelected"
          class="u-pv">
          <p v-html="Translator.trans('segments.bulk.edit.tags.delete.description', { count: segments.length})" />
          <selected-tags-list :selected-tags="actions.deleteTags.selected" />
        </div>

        <div
          v-if="addRecommendationsChecked && actions.addRecommendations.text !== ''"
          class="u-pv">
          <p v-html="addOrReplaceRecommendationMessage" />
          <p v-html="actions.addRecommendations.text" />
        </div>
      </div>
    </template>

    <!-- Step 3 - System feedback -->
    <template v-slot:step-3>
      <action-stepper-response
        v-if="hasPermission('feature_statement_assignment') && assignSegmentCheckedAndSelected"
        :success="actions.assignSegment.success"
        :description-error="Translator.trans('segments.bulk.edit.segments.assigned.error')"
        :description-success="Translator.trans('segments.bulk.edit.segments.assigned.success')" />

      <action-stepper-response
        v-if="assignPlaceCheckedAndSelected"
        :success="actions.assignPlace.success"
        :description-error="Translator.trans('segments.bulk.edit.place.assigned.error', {count: segments.length})"
        :description-success="Translator.trans('segments.bulk.edit.place.assigned.success', {count: segments.length})">
        <p
          v-cleanhtml="actions.assignPlace.selected.name"
          class="u-mt-0_5" />
      </action-stepper-response>

      <action-stepper-response
        v-if="addTagsCheckedAndSelected"
        :success="actions.addTags.success"
        :description-error="Translator.trans('segments.bulk.edit.tags.added.error', {count: segments.length})"
        :description-success="Translator.trans('segments.bulk.edit.tags.added.success', {count: segments.length})">
        <selected-tags-list :selected-tags="actions.addTags.selected" />
      </action-stepper-response>

      <action-stepper-response
        v-if="deleteTagsCheckedAndSelected"
        :success="actions.deleteTags.success"
        :description-error="Translator.trans('segments.bulk.edit.tags.deleted.error', {count: segments.length})"
        :description-success="Translator.trans('segments.bulk.edit.tags.deleted.success', {count: segments.length})">
        <selected-tags-list :selected-tags="actions.deleteTags.selected" />
      </action-stepper-response>

      <action-stepper-response
        v-if="addRecommendationsChecked"
        :success="actions.addRecommendations.success"
        :description-error="Translator.trans('segments.bulk.edit.recommendations.added.error', {count: segments.length})"
        :description-success="addRecommendationsSuccess">
        <p
          v-html="actions.addRecommendations.text"
          class="u-mt-0_5" />
      </action-stepper-response>
    </template>
  </action-stepper>
</template>

<script>
import {
  checkResponse,
  CleanHtml,
  dpApi,
  DpMultiselect,
  DpRadio,
  dpRpc,
  hasOwnProp,
  prefixClassMixin
} from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'
import ActionStepper from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepper'
import ActionStepperAction from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepperAction'
import ActionStepperResponse from '@DpJs/components/procedure/SegmentsBulkEdit/ActionStepper/ActionStepperResponse'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import lscache from 'lscache'
import SelectedTagsList from '@DpJs/components/procedure/SegmentsBulkEdit/SelectedTagsList'

export default {
  name: 'SegmentsBulkEdit',

  components: {
    ActionStepper,
    ActionStepperAction,
    ActionStepperResponse,
    DpBoilerPlateModal,
    DpInlineNotification: async () => {
      const { DpInlineNotification } = await import('@demos-europe/demosplan-ui')
      return DpInlineNotification
    },
    DpMultiselect,
    DpRadio,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    },
    SelectedTagsList
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [prefixClassMixin],

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      actions: {
        addRecommendations: {
          text: '',
          checked: false,
          success: false,
          // To toggle radio button checked and the isTextAttached value will be send to BE
          isTextAttached: true,
          isTextReplaced: false
        },
        addTags: {
          selected: [],
          checked: false,
          success: false
        },
        assignPlace: {
          selected: [],
          checked: false,
          success: false
        },
        assignSegment: {
          selected: [],
          checked: false,
          success: false
        },
        deleteTags: {
          selected: [],
          checked: false,
          success: false
        }
      },
      assignableUsers: [],
      busy: false,
      isLoading: true,
      returnLink: Routing.generate('dplan_segments_list', { procedureId: this.procedureId }),
      step: 1,
      places: [],
      segments: []
    }
  },

  computed: {
    ...mapState('Tag', {
      tagsItems: 'items'
    }),

    ...mapState('TagTopic', {
      tagTopicsItems: 'items'
    }),

    addOrReplaceRecommendationMessage () {
      if (this.actions.addRecommendations.isTextAttached) {
        return Translator.trans('segments.bulk.edit.recommendations.add.description', { count: this.segments.length })
      }

      if (this.actions.addRecommendations.isTextReplaced) {
        return Translator.trans('segments.bulk.edit.recommendations.replace.description', { count: this.segments.length })
      }

      return ''
    },

    addRecommendationsChecked () {
      return this.actions.addRecommendations.checked
    },

    addRecommendationsSuccess () {
      if (this.actions.addRecommendations.isTextAttached) {
        return Translator.trans('segments.bulk.edit.recommendations.added.success', { count: this.segments.length })
      }

      if (this.actions.addRecommendations.isTextReplaced) {
        return Translator.trans('segments.bulk.edit.recommendations.replaced.success', { count: this.segments.length })
      }

      return ''
    },

    addTagsCheckedAndSelected () {
      return this.actions.addTags.checked && this.actions.addTags.selected.length > 0
    },

    assignSegmentCheckedAndSelected () {
      return this.actions.assignSegment.checked && Object.values(this.actions.assignSegment.selected).length > 0
    },

    assignPlaceCheckedAndSelected () {
      return this.actions.assignPlace.checked && Object.values(this.actions.assignPlace.selected).length > 0
    },

    deleteTagsCheckedAndSelected () {
      return this.actions.deleteTags.checked && this.actions.deleteTags.selected.length > 0
    },

    emptyRecommendationWarning () {
      const isEmptyTextAttached = this.addRecommendationsChecked && this.actions.addRecommendations.isTextAttached === true && this.actions.addRecommendations.text === ''
      const isEmptyTextReplaced = this.addRecommendationsChecked && this.actions.addRecommendations.isTextReplaced === true && this.actions.addRecommendations.text === ''

      if (isEmptyTextAttached) {
        return Translator.trans('segments.bulk.edit.recommendations.warning.empty.text.attach', { count: this.segments.length })
      }
      if (isEmptyTextReplaced) {
        return Translator.trans('segments.bulk.edit.recommendations.warning.empty.text.replace', { count: this.segments.length })
      }
      return ''
    },

    filteredTagsByTopic () {
      return this.topics.map(topic => {
        return {
          title: topic.attributes.title,
          id: topic.id,
          tags: this.tags
            .filter(tag => tag.relationships.topic.data.id === topic.id)
            .map(tag => {
              return {
                title: tag.attributes.title,
                id: tag.id
              }
            })
        }
      })
    },

    hasActions () {
      const addRecommendationAction = this.actions.addRecommendations.checked && this.actions.addRecommendations.text
      const addTagsAction = this.actions.addTags.checked && this.actions.addTags.selected.length > 0
      const assignPlaceAction = this.actions.assignPlace.checked && Object.values(this.actions.assignPlace.selected).length > 0
      const assignSegmentAction = this.actions.assignSegment.checked && Object.values(this.actions.assignSegment.selected).length > 0
      const deleteTagsAction = this.actions.deleteTags.checked && this.actions.deleteTags.selected.length > 0

      return addRecommendationAction || addTagsAction || assignPlaceAction || assignSegmentAction || deleteTagsAction
    },

    hasPlaces () {
      return this.places.length > 0
    },

    hasSegments () {
      return this.segments.length > 0
    },

    tags () {
      return Object.values(this.tagsItems).sort((a, b) => a.attributes.title.localeCompare(b.attributes.title, 'de', { sensitivity: 'base' }))
    },

    topics () {
      return Object.values(this.tagTopicsItems)
    }
  },

  methods: {
    ...mapActions('Tag', {
      listTags: 'list'
    }),
    ...mapActions('TagTopic', {
      listTagTopics: 'list'
    }),

    /**
     * Apply selected actions.
     */
    apply () {
      this.busy = true

      const params = {
        addTagIds: this.actions.addTags.selected.map(tag => tag.id),
        removeTagIds: this.actions.deleteTags.selected.map(tag => tag.id),
        segmentIds: this.segments,
        // Text of DpEditor and attach bool to determine if the text is replaced or attached - default: true
        recommendationTextEdit: {
          text: this.actions.addRecommendations.text,
          attach: this.actions.addRecommendations.isTextAttached
        }
      }

      if (this.assignSegmentCheckedAndSelected) {
        params.assigneeId = this.actions.assignSegment.selected.id
      }

      if (this.assignPlaceCheckedAndSelected) {
        params.placeId = this.actions.assignPlace.selected.id
      }

      dpRpc('segment.bulk.edit', params)
        .then(checkResponse)
        .then((response) => {
          const rpcResult = this.getRpcResult(response)

          for (const property in this.actions) {
            this.actions[property].success = rpcResult
          }
        })
        .catch(() => {
          for (const property in this.actions) {
            this.actions[property].success = false
          }
        })
        .finally(() => {
          // Always delete saved selection to ensure that no action is processed more than one time
          lscache.remove(`${this.procedureId}:toggledSegments`)
          lscache.remove(`${this.procedureId}:allSegments`)
          this.step = 3
          this.busy = false
        })
    },

    fetchAssignableUsers () {
      const url = Routing.generate('api_resource_list', { resourceType: 'AssignableUser' })
      return dpApi.get(url, { include: 'department' })
        .then(response => {
          this.assignableUsers = response.data.data.map(assignableUser => {
            return {
              name: assignableUser.attributes.firstname + ' ' + assignableUser.attributes.lastname,
              id: assignableUser.id
            }
          })

          // Add option to set unassigned to segments
          this.assignableUsers.push({
            name: Translator.trans('not.assigned'),
            id: null
          })
        })
    },

    fetchPlaces () {
      const url = Routing.generate('api_resource_list', { resourceType: 'Place' })
      return dpApi.get(url)
        .then(response => {
          this.places = response.data.data.map(place => {
            return {
              id: place.id,
              name: place.attributes.name
            }
          })
        })
        .catch(err => console.error(err))
    },

    /**
     * Return result of specific action.
     * @param response {Object} The response that shall be parsed
     */
    getRpcResult (response) {
      return hasOwnProp(response, 0) && response[0]?.result === 'ok'
    },

    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
    },

    /**
     * To load the SegmentsList with the same filters that were selected before
     * starting the BulkEdit flow, the current query hash is loaded from localStorage here
     * and applied to the return url.
     */
    setReturnLink () {
      const currentQueryHash = lscache.get(`${this.procedureId}:segments:currentQueryHash`)
      if (currentQueryHash) {
        this.returnLink = Routing.generate('dplan_segments_list_by_query_hash', {
          procedureId: this.procedureId,
          queryHash: currentQueryHash
        })
      }
    },

    /**
     * Set selected segments from localStorage. In the (edge-)case there are no segments selected,
     * the stepTitle will reflect that fact; furthermore no controls will be enabled but the "Back to selection" button.
     */
    setSegments () {
      const segments = lscache.get(`${this.procedureId}:toggledSegments`)
      const allSegments = lscache.get(`${this.procedureId}:allSegments`)

      if (segments && allSegments) {
        const toggledIds = segments.toggledSegments.map(item => item.id)
        if (segments.trackDeselected === false) {
          this.segments = toggledIds
        } else if (segments.trackDeselected === true) {
          this.segments = segments.toggledSegments.length === 0 ? allSegments : allSegments.filter(segment => !toggledIds.includes(segment))
        }
      }
    }
  },

  created () {
    this.setReturnLink()
    this.setSegments()
  },

  mounted () {
    const promises = [
      this.listTagTopics({ include: 'tag' }),
      this.listTags({ include: 'topic' }),
      this.fetchPlaces()
    ]
    if (hasPermission('feature_statement_assignment')) {
      promises.push(this.fetchAssignableUsers())
    }
    Promise.all(promises)
      .then(() => {
        this.isLoading = false
      })
  }
}
</script>
