<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    :id="`segment_${segment.id}`"
    ref="statementSegment"
    class="segment-list-row"
    :class="{'segment-list-row--assigned': isAssignedToMe, 'fullscreen': isFullscreen, 'rounded-lg': !isFullscreen}"
    @mouseenter="isHover = true"
    @mouseleave="isHover = false"
  >
    <div class="flex flex-col justify-start basis-1/5 pt-2 pl-2">
      <v-popover
        :container="$refs.statementSegment"
        trigger="hover focus"
      >
        <i
          class="fa fa-hashtag color--grey-light"
          :class="{'color--grey-dark': isAssignedToMe || isHover}"
          aria-hidden="true"
        />
        <span>{{ segment.attributes.externId }}</span>
        <template v-slot:popover>
          <div class="c-statement-meta-tooltip px-0 py-0">
            <dl>
              <div class="weight--bold pb-2 pr-2">
                {{ Translator.trans('segment') }} {{ segment.attributes.externId }}
              </div>
              <div v-if="segment.attributes.externId">
                <dt class="weight--bold">
                  {{ Translator.trans('id') }}:
                </dt>
                <dd>{{ segment.attributes.externId }}</dd>
              </div>
              <div>
                <dt class="weight--bold">
                  {{ Translator.trans('tags') }}:
                </dt>
                <dd>{{ tagsAsString }}</dd>
              </div>
              <div v-if="segment.relationships.place">
                <dt class="weight--bold">
                  {{ Translator.trans('workflow.place') }}:
                </dt>
                <dd>{{ segmentPlace.name }}</dd>
              </div>
              <div v-if="assignee.name !== ''">
                <dt class="weight--bold">
                  {{ Translator.trans('assigned.to') }}:
                </dt>
                <dd>{{ assignee.name }}</dd>
              </div>
            </dl>
            <custom-fields-list
              v-if="hasPermission('field_segments_custom_fields')"
              :definition-source-id="procedureId"
              :resource-id="segment.id"
              :show-title="false"
              mode="readonly"
              resource-type="StatementSegment"
              source-entity="PROCEDURE"
              target-entity="SEGMENT"
            />
          </div>
        </template>
      </v-popover>

      <dp-claim
        entity-type="segment"
        :assigned-id="assignee.id || ''"
        :assigned-name="assignee.name || ''"
        :assigned-organisation="assignee.orgaName || ''"
        :current-user-id="currentUserId"
        :current-user-name="currentUserName"
        :is-loading="claimLoading"
        @click="toggleClaimSegment"
      />
    </div>
    <text-content-renderer
      class="segment-list-col--l overflow-word-break c-styled-html"
      :text="visibleSegmentText"
    />
    <div class="segment-list-col--s">
      <button
        v-if="!isFullscreen"
        class="segment-list-toggle-button btn--blank mx-auto"
        :class="{'reverse': !isCollapsed}"
        :aria-label="Translator.trans('aria.expand')"
        @click="isCollapsed = !isCollapsed"
      >
        <i
          class="fa fa-arrow-up"
          aria-hidden="true"
        />
        <i
          class="fa fa-arrow-down"
          aria-hidden="true"
        />
      </button>
    </div>
    <div class="segment-list-col--l overflow-word-break">
      <image-modal
        ref="imageModal"
        data-cy="recommendation:imgModal"
      />
      <div
        v-if="isAssignedToMe === false"
        ref="recommendationContainer"
        v-cleanhtml="visibleRecommendation || Translator.trans('segment.recommendation.none')"
        :class="{ 'color--grey': visibleRecommendation === '' }"
        :title="visibleRecommendation ? Translator.trans('explanation.segment.claim.to.edit.recommendation') : Translator.trans('explanation.segment.claim.to.add.recommendation')"
      />
      <div v-else-if="isAssignedToMe && isEditing === false">
        <div
          v-if="visibleRecommendation !== ''"
          ref="recommendationContainer"
          v-cleanhtml="visibleRecommendation"
          class="mb-2"
        />
      </div>
      <div v-else>
        <dp-editor
          class="mb-2"
          editor-id="recommendationText"
          :routes="{
            getFileByHash: (hash) => Routing.generate('core_file_procedure', { procedureId: procedureId, hash: hash })
          }"
          :toolbar-items="{
            fullscreenButton: false,
            imageButton: true,
            linkButton: true
          }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          :value="segment.attributes.recommendation"
          @input="value => updateSegment('recommendation', value)"
        >
          <template v-slot:modal="modalProps">
            <dp-boiler-plate-modal
              v-if="hasPermission('area_admin_boilerplates')"
              ref="boilerPlateModal"
              boiler-plate-type="consideration"
              editor-id="recommendationText"
              :procedure-id="procedureId"
              :preview-segment-id="segment.id"
              @insert="text => modalProps.handleInsertText(text)"
            />
            <recommendation-modal
              ref="recommendationModal"
              :procedure-id="procedureId"
              :segment-data-loaded="true"
              :segment-id="segment.id"
              @addons:loaded="hasRecommendationTabs = true"
              @recommendation:insert="closeRecommendationModalAfterInsert"
            />
          </template>
          <template v-slot:button>
            <button
              v-if="hasPermission('area_admin_boilerplates')"
              v-tooltip="Translator.trans('boilerplate.insert')"
              :class="prefixClass('menubar__button')"
              data-cy="segmentEditor:boilerplate"
              type="button"
              @click.stop="openBoilerPlate"
            >
              <i :class="prefixClass('fa fa-puzzle-piece')" />
            </button>
            <button
              v-if="hasRecommendationTabs"
              v-tooltip="Translator.trans('segment.recommendation.insert.similar')"
              :class="prefixClass('menubar__button')"
              data-cy="segmentEditor:similarRecommendation"
              type="button"
              @click.stop="toggleRecommendationModal"
            >
              <i :class="prefixClass('fa fa-lightbulb-o')" />
            </button>
          </template>
        </dp-editor>
      </div>
      <div v-if="isAssignedToMe">
        <dp-checkbox
          :id="'showWorkflowActions_' + segment.id"
          v-model="showWorkflowActions"
          :label="{
            text: displayEditableFieldsLabel
          }"
        />
        <div
          v-if="showWorkflowActions"
          class="my-2"
        >
          <dp-label
            class="mb-0.5 mt-2"
            :text="Translator.trans('assignee')"
            :bold="false"
            for="assignableUsersSegment"
          />
          <dp-multiselect
            id="assignableUsersSegment"
            v-model="selectedAssignee"
            :options="assignableUsers"
            class="w-full"
            label="name"
            track-by="id"
          />
          <dp-label
            :text="Translator.trans('workflow.place')"
            :bold="false"
            class="mb-0.5 mt-2"
            for="segmentPlace"
          />
          <dp-multiselect
            id="segmentPlace"
            v-model="selectedPlace"
            :allow-empty="false"
            class="w-full"
            label="name"
            :options="places"
            :sub-slots="['option', 'singleLabel', 'tag']"
            track-by="id"
          >
            <template v-slot:option="{ props }">
              <div
                v-for="prop in props"
                :key="prop.id"
                v-tooltip="prop.description"
              >
                {{ prop.name }}
                <dp-contextual-help
                  v-if="prop.solved"
                  class="float-right color--grey"
                  icon="check"
                  size="small"
                  :text="Translator.trans('statement.solved.description')"
                />
              </div>
            </template>
            <template v-slot:singleLabel="{ props }">
              <div
                v-for="prop in props"
                :key="prop.id"
                v-tooltip="prop.description"
              >
                {{ prop.name }}
                <dp-contextual-help
                  v-if="prop.solved"
                  class="float-right color--grey mt-0.5"
                  icon="check"
                  size="small"
                  :text="Translator.trans('statement.solved.description')"
                />
              </div>
            </template>
          </dp-multiselect>
          <custom-fields-list
            v-if="hasPermission('field_segments_custom_fields')"
            v-slot:default="{ fieldsWithDefinitions }"
            :definition-source-id="procedureId"
            :resource-id="segment.id"
            :show-empty="true"
            :show-title="false"
            mode="editable"
            resource-type="StatementSegment"
            source-entity="PROCEDURE"
            target-entity="SEGMENT"
            @loaded="onCustomFieldsLoaded"
          >
            <div
              v-for="{ field, definition } in fieldsWithDefinitions"
              :key="field.id"
            >
              <dp-label
                :bold="false"
                :for="`custom-field-${field.id}`"
                :text="definition?.attributes?.name || ''"
                class="mb-0.5 mt-2"
              />
              <custom-field
                :definition="definition"
                :field-data="{ id: field.id, value: customFieldValueForId(field.id) }"
                :resource-id="segment.id"
                :show-label="false"
                mode="editable"
                resource-type="StatementSegment"
                @update:value="newValue => onCustomFieldValueUpdate({ fieldId: field.id, value: newValue })"
              />
            </div>
          </custom-fields-list>
        </div>
      </div>
      <dp-button-row
        v-if="isAssignedToMe && (isEditing || showWorkflowActions)"
        align="left"
        class="mt-3"
        primary
        secondary
        @primary-action="save"
        @secondary-action="abort"
      />
    </div>
    <div class="segment-list-col--m text-right shrink-2 px-2">
      <div
        class="segment-list-toolbar"
        :class=" isAssignedToMe ? '' : 'segment-list-toolbar--dark'"
      >
        <button
          v-tooltip="{
            container: `#segment_${segment.id}`,
            content: Translator.trans('editor.fullscreen')
          }"
          class="segment-list-toolbar__button btn--blank"
          data-cy="editorFullscreen"
          :aria-label="Translator.trans('editor.fullscreen')"
          @click="isFullscreen = !isFullscreen"
        >
          <dp-icon
            class="inline-block"
            :icon="isFullscreen ? 'compress' : 'expand'"
            aria-hidden="true"
          />
        </button>

        <button
          v-if="isAssignedToMe"
          v-tooltip="{
            container: `#segment_${segment.id}`,
            content: Translator.trans('edit')
          }"
          class="segment-list-toolbar__button btn--blank"
          :class="{ 'is-active' : isEditing}"
          data-cy="segmentEdit"
          :aria-label="Translator.trans('edit')"
          @click="startEditing"
        >
          <i
            class="fa fa-pencil"
            aria-hidden="true"
          />
        </button>

        <button
          v-tooltip="{
            container: `#segment_${segment.id}`,
            content: Translator.trans('history')
          }"
          class="segment-list-toolbar__button btn--blank"
          :class="{ 'is-active' : slidebar.showTab === 'history' && slidebar.segmentId === segment.id }"
          type="button"
          :aria-label="Translator.trans('history')"
          data-cy="segmentVersionHistory"
          @click.prevent="showSegmentVersionHistory"
        >
          <dp-icon
            class="inline-block"
            icon="history"
          />
        </button>

        <button
          v-if="hasPermission('feature_segment_comment_list_on_segment')"
          v-tooltip="{
            container: `#segment_${segment.id}`,
            content: Translator.trans('comments')
          }"
          class="segment-list-toolbar__button btn--blank"
          :class="{ 'is-active' : slidebar.showTab === 'comments' && slidebar.segmentId === segment.id }"
          type="button"
          :aria-label="Translator.trans('comments')"
          data-cy="segmentComments"
          @click.prevent="showComments"
        >
          <i
            class="fa fa-comment-o"
            aria-hidden="true"
          />
          <span
            v-if="commentCount > 0"
            class="segment-list-toolbar__badge o-badge--darker block absolute ml-4 -mt-4"
          >
            {{ commentCount }}
          </span>
        </button>
        <button
          v-if="hasPermission('feature_segment_polygon_read')"
          v-tooltip="{
            container: `#segment_${segment.id}`,
            content: Translator.trans('public.participation.relation')
          }"
          class="segment-list-toolbar__button btn--blank"
          :class="{ 'is-active' : slidebar.showTab === 'map' && slidebar.segmentId === segment.id }"
          type="button"
          :aria-label="Translator.trans('public.participation.relation')"
          data-cy="segmentMap"
          @click.prevent="showMap"
        >
          <dp-icon
            class="mx-auto"
            icon="map-pin"
            :weight="hasPolygonFeatures() ? 'fill' : 'regular'"
          />
        </button>
      </div>
    </div>
  </div>
</template>

<script>
import {
  CleanHtml,
  dpApi,
  DpButtonRow,
  DpCheckbox,
  DpContextualHelp,
  DpIcon,
  DpLabel,
  DpMultiselect,
  prefixClassMixin,
  Tooltip,
  VPopover,
} from '@demos-europe/demosplan-ui'
import { mapActions, mapMutations, mapState } from 'vuex'
import { defineAsyncComponent } from 'vue'
import CustomField from '@DpJs/components/customFields/CustomField'
import CustomFieldsList from '@DpJs/components/customFields/CustomFieldsList'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import DpClaim from '@DpJs/components/statement/DpClaim'
import ImageModal from '@DpJs/components/shared/ImageModal'
import RecommendationModal from '../Shared/RecommendationModal'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'
import { useCustomFields } from '@DpJs/composables/useCustomFields'
import { useUnsavedChangesGuard } from '@DpJs/composables/useUnsavedChangesGuard'

export default {
  name: 'StatementSegment',

  inject: ['procedureId'],

  components: {
    CustomField,
    CustomFieldsList,
    DpBoilerPlateModal,
    DpButtonRow,
    DpCheckbox,
    DpContextualHelp,
    DpClaim,
    DpEditor: defineAsyncComponent(async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }),
    DpIcon,
    DpLabel,
    DpMultiselect,
    ImageModal,
    RecommendationModal,
    TextContentRenderer,
    VPopover,
  },

  directives: {
    cleanhtml: CleanHtml,
    tooltip: Tooltip,
  },

  mixins: [prefixClassMixin],

  setup () {
    const { init, cleanup } = useUnsavedChangesGuard()

    return {
      initUnsavedChangesGuard: init,
      cleanupUnsavedChangesGuard: cleanup,
    }
  },

  props: {
    currentUserFirstName: {
      required: false,
      type: String,
      default: '',
    },

    currentUserId: {
      required: true,
      type: String,
    },

    currentUserLastName: {
      required: false,
      type: String,
      default: '',
    },

    currentUserOrga: {
      type: String,
      required: false,
      default: '',
    },

    segment: {
      required: true,
      type: Object,
    },

    statementId: {
      required: true,
      type: String,
    },
  },

  data () {
    return {
      claimLoading: false,
      customFieldsChanged: false,
      customFieldValues: [],
      currentUserName: this.currentUserFirstName + ' ' + this.currentUserLastName,
      hasRecommendationTabs: false,
      isCollapsed: !(this.segment.relationships?.assignee?.data && this.segment.relationships.assignee.data.id === this.currentUserId),
      isEditing: false,
      isFullscreen: false,
      isHover: false,
      selectedAssignee: {},
      selectedPlace: { id: '', type: 'Place' },
      showWorkflowActions: false,
    }
  },

  computed: {
    ...mapState('SegmentSlidebar', [
      'slidebar',
    ]),

    ...mapState('AssignableUser', {
      assignableUserItems: 'items',
    }),

    ...mapState('Place', {
      placeItems: 'items',
    }),

    assignableUsers () {
      const assigneeOptions = Object.values({ ...this.assignableUserItems })
        .map(assignableUser => {
          return {
            name: assignableUser.attributes.firstname + ' ' + assignableUser.attributes.lastname,
            id: assignableUser.id,
          }
        })
      assigneeOptions.unshift({
        name: Translator.trans('not.assigned'),
        id: 'noAssigneeId',
      })

      return assigneeOptions
    },

    assignee () {
      if (this.segment?.relationships?.assignee?.data?.id && this.segment.relationships.assignee.data.id !== '') {
        const assignee = this.assignableUserItems[this.segment.relationships.assignee.data.id]
        const name = `${assignee.attributes.firstname} ${assignee.attributes.lastname}`
        const orga = assignee?.rel ? assignee.rel('orga') : ''

        return { id: this.segment.relationships.assignee.data.id, name, orgaName: orga ? orga.attributes.name : '' }
      } else {
        return { id: '', name: '', orgaName: '' }
      }
    },

    commentCount () {
      return this.segment.relationships.comments?.data?.length || 0
    },

    displayEditableFieldsLabel () {
      return Translator.trans(hasPermission('field_segments_custom_fields') ? 'fields.more.edit' : 'workflow.change.assignee.place')
    },

    /**
     * Required by useUnsavedChangesGuard composable
     */
    hasUnsavedChanges () {
      return this.$store.state.StatementSegment.initial[this.segment.id].attributes.recommendation !== this.segment.attributes.recommendation
    },

    isAssignedToMe () {
      return this.assignee.id === this.currentUserId
    },

    places () {
      return this.$store.state.Place ?
        Object.values(this.$store.state.Place.items)
          .map(pl => ({ ...pl.attributes, id: pl.id })) :
        []
    },

    segmentPlace () {
      return this.segment.relationships.place ?
        this.places.find(place => place.id === this.segment.relationships.place.data.id) :
        {}
    },

    tagsAsString () {
      if (this.segment.hasRelationship('tags')) {
        return Object.values(this.segment.rel('tags')).map(el => el.attributes.title).join(', ')
      }

      return '-'
    },

    visibleRecommendation () {
      const shortText = this.segment.attributes.recommendation.length > 40 ? this.segment.attributes.recommendation.slice(0, 40) + '...' : this.segment.attributes.recommendation
      return this.isCollapsed ? shortText : this.segment.attributes.recommendation
    },

    visibleSegmentText () {
      const shortText = this.segment.attributes.text.length > 40 ? this.segment.attributes.text.slice(0, 40) + '...' : this.segment.attributes.text
      return this.isCollapsed ? shortText : this.segment.attributes.text
    },
  },

  watch: {
    isCollapsed: {
      handler (newVal) {
        if (!newVal) {
          this.$nextTick(() => {
            if (this.$refs.recommendationContainer) {
              this.$refs.imageModal.addClickListener(this.$refs.recommendationContainer.querySelectorAll('img'))
            }
          })
        }
      },
      deep: false, // Set default for migrating purpose. To know this occurrence is checked
      immediate: true, // This ensures the handler is executed immediately after the component is created
    },
  },

  methods: {
    ...mapActions('SegmentSlidebar', [
      'toggleSlidebarContent',
    ]),

    ...mapMutations('SegmentSlidebar', [
      'setProperty',
    ]),

    ...mapActions('StatementSegment', {
      restoreSegmentAction: 'restoreFromInitial',
      saveSegmentAction: 'save',
    }),

    ...mapMutations('StatementSegment', {
      updateSegment: 'update',
      setSegment: 'setItem',
    }),

    abort () {
      // Restore initial recommendation value, set it also in tiptap
      const initText = this.$store.state.StatementSegment.initial[this.segment.id].attributes.recommendation
      this.updateSegment('recommendation', initText)
      // Update interface
      this.isFullscreen = false
      this.isEditing = false

      this.toggleAssignableUsersSelect()
    },

    checkIfToolIsActive (tool) {
      return (this.segment.id === this.slidebar.segmentId && this.slidebar.showTab === tool)
    },

    claimSegment () {
      const dataToUpdate = {
        ...this.segment,
        ...{
          relationships: {
            ...this.segment.relationships,
            ...{
              assignee: {
                data: {
                  type: 'AssignableUser',
                  id: this.currentUserId,
                },
              },
            },
          },
        },
      }
      this.setSegment({ ...dataToUpdate, id: this.segment.id })

      const payload = {
        data: {
          id: this.segment.id,
          type: 'StatementSegment',
          relationships: {
            assignee: {
              data: {
                type: 'AssignableUser',
                id: this.currentUserId,
              },
            },
          },
        },
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: this.segment.id }), {}, payload)
        .then(() => {
          this.claimLoading = false
          this.isCollapsed = false
          this.selectedAssignee = {
            id: this.currentUserId,
            name: this.currentUserName,
          }
        })
        .catch((err) => {
          console.error(err)
          // Restore segment in store if it didn't work
          this.restoreSegmentAction(this.segment.id)
          this.claimLoading = false
        })
    },

    closeRecommendationModalAfterInsert () {
      this.toggleRecommendationModal()
      dplan.notify.notify('confirm', Translator.trans('recommendation.pasted'))
    },

    customFieldsSaveError (definitions) {
      const fieldNames = this.customFieldValues
        .map(({ id }) => definitions.find(def => def.id === id)?.attributes?.name)
        .filter(Boolean)
        .join(', ')

      return Translator.trans('error.custom_fields.save', { fields: fieldNames })
    },

    customFieldValueForId (fieldId) {
      const entry = this.customFieldValues.find(valueEntry => valueEntry.id === fieldId)

      return entry?.value ?? null
    },

    finalizeSave (comments) {
      this.restoreComments(comments)
      this.setProperty({ prop: 'isLoading', val: false })
      this.isEditing = false
    },

    hasPolygonFeatures () {
      const raw = this.segment.attributes.polygon

      if (!raw || typeof raw !== 'string') {
        return false
      }

      const parsedPolygon = JSON.parse(raw)
      const features = parsedPolygon?.features

      return Array.isArray(features) && features.length > 0
    },

    initAssignableUsers () {
      const assignableUsersLoaded = Object.keys(this.assignableUserItems).length

      if (assignableUsersLoaded) {
        this.setSelectedAssignee()

        return
      }

      this.fetchAssignableUsers({
        include: 'department',
        sort: 'lastname',
      })
        .then(() => {
          this.setSelectedAssignee()
        })
    },

    initPlaces () {
      const placeItemsLoaded = Object.keys(this.placeItems).length

      if (placeItemsLoaded) {
        this.setSelectedPlace()

        return
      }

      this.fetchPlaces({
        fields: {
          Place: [
            'description',
            'name',
            'solved',
            'sortIndex',
          ].join(),
        },
        sort: 'sortIndex',
      })
        .then(() => {
          this.setSelectedPlace()
        })
    },

    onCustomFieldsLoaded (values) {
      this.customFieldValues = values
      this.customFieldsChanged = false
    },

    onCustomFieldValueUpdate ({ fieldId, value }) {
      this.customFieldsChanged = true
      const fieldIndex = this.customFieldValues.findIndex(field => field.id === fieldId)

      if (fieldIndex === -1) {
        this.customFieldValues = [...this.customFieldValues, { id: fieldId, value }]
      } else {
        this.customFieldValues = this.customFieldValues.map((field, index) =>
          index === fieldIndex ?
            { ...field, value } :
            field,
        )
      }
    },

    /**
     * Required by useUnsavedChangesGuard composable
     */
    onDiscardChanges () {
      this.abort()
    },

    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
    },

    /**
     * Remove non-updatable comments from segments relationships for update request
     * @param relations {Object}
     */
    removeComments (relations) {
      if (relations.comments) {
        this.setProperty({ prop: 'isLoading', val: true })
        delete relations.comments
      }
    },

    restoreComments (comments) {
      if (comments) {
        const segmentWithComments = {
          ...this.segment,
          relationships: {
            ...this.segment.relationships,
            comments,
          },
        }
        this.setSegment({ ...segmentWithComments, id: this.segment.id })
      }
    },

    save () {
      const comments = this.segment.relationships.comments ? { ...this.segment.relationships.comments } : null
      const { assignee, place } = this.updateRelationships()

      const updatedSegment = {
        id: this.segment.id,
        type: 'StatementSegment',
        attributes: {
          ...this.segment.attributes,
        },
        relationships: {
          ...this.segment.relationships,
          assignee,
          place,
        },
      }

      this.removeComments(updatedSegment.relationships)

      this.setSegment({
        ...updatedSegment,
        id: this.segment.id,
      })

      return this.saveSegmentAction({ id: this.segment.id })
        .then(() => {
          /*
           * Custom fields are saved via a separate PATCH using the composable's updateCustomFields,
           * which bypasses the vuex-json-api diff mechanism (unreliable for array attributes)
           * and properly invalidates the composable's value cache.
           */
          const saveCustomFields = this.customFieldsChanged && hasPermission('field_segments_custom_fields') ?
            useCustomFields().updateCustomFields('StatementSegment', this.segment.id, this.customFieldValues).then(() => {
              this.customFieldsChanged = false
            }) :
            Promise.resolve()

          return saveCustomFields
            .then(() => {
              dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
              this.isFullscreen = false

              this.finalizeSave(comments)

              this.toggleAssignableUsersSelect()
              this.$nextTick(() => {
                if (this.$refs.recommendationContainer) {
                  this.$refs.imageModal.addClickListener(this.$refs.recommendationContainer.querySelectorAll('img'))
                }
              })
            })
            .catch(() => {
              const { getCustomFieldsDefinitions } = useCustomFields()
              const definitions = getCustomFieldsDefinitions(this.procedureId, {
                targetEntity: 'SEGMENT',
                sourceEntity: 'PROCEDURE',
              }) || []

              dplan.notify.error(this.customFieldsSaveError(definitions))
              this.finalizeSave(comments)
            })
        })
        .catch(() => {
          this.finalizeSave(comments)
        })
    },

    /**
     * Required by useUnsavedChangesGuard composable
     */
    saveUnsavedChanges () {
      return this.save()
    },

    setActiveTabId (id) {
      this.activeId = id
    },

    setSelectedAssignee () {
      if (this.segment.relationships?.assignee?.data?.id) {
        this.selectedAssignee = this.assignableUsers.find(user => user.id === this.segment.relationships.assignee.data.id)
      }
    },

    setSelectedPlace () {
      if (this.segment.relationships.place) {
        this.selectedPlace = this.places.find(place => place.id === this.segment.relationships.place.data.id) || this.places[0]
      }
    },

    showComments () {
      if (this.checkIfToolIsActive('comments')) {
        return
      }

      this.$parent.$parent.resetSlidebar()

      this.toggleSlidebarContent({
        prop: 'commentsList',
        val: {
          ...this.commentsList,
          currentCommentText: '',
          externId: this.segment.attributes.externId,
          segmentId: this.segment.id,
          show: true,
        },
      })
      this.toggleSlidebarContent({ prop: 'slidebar', val: { isOpen: true, segmentId: this.segment.id, showTab: 'comments' } })
      this.$root.$emit('show-slidebar')
    },

    showMap () {
      if (this.checkIfToolIsActive('map')) {
        return
      }

      this.$parent.$parent.resetSlidebar()
      this.toggleSlidebarContent({ prop: 'slidebar', val: { isOpen: true, segmentId: this.segment.id, showTab: 'map' } })
      this.$root.$emit('show-slidebar')
    },

    showSegmentVersionHistory () {
      if (this.checkIfToolIsActive('history')) {
        return
      }

      this.$root.$emit('version:history', this.segment.id, 'segment', this.segment.attributes.externId)
      this.$root.$emit('show-slidebar')
      this.toggleSlidebarContent({ prop: 'slidebar', val: { isOpen: true, segmentId: this.segment.id, showTab: 'history' } })
    },

    startEditing () {
      this.isEditing = true
      this.isCollapsed = false
    },

    toggleAssignableUsersSelect () {
      if (this.showWorkflowActions === true) {
        this.showWorkflowActions = false
      }
    },

    /**
     * Don't use vuex-json-api lib for claiming and un-claiming because there is a problem if data in relationship is
     * null (=un-claiming); using vuex-json-api lib only for claiming but not for un-claiming doesn't work because the
     * initial items in the lib store are not updated when un-claiming outside of lib
     */
    toggleClaimSegment () {
      this.claimLoading = true
      const userIdToSet = this.segment.hasRelationship('assignee') && this.segment.relationships.assignee.data.id === this.currentUserId ? null : this.currentUserId
      const isClaim = userIdToSet !== null

      if (isClaim) {
        this.claimSegment()
      } else {
        this.unclaimSegment()
      }
    },

    toggleRecommendationModal () {
      this.$refs.recommendationModal.toggle()
    },

    unclaimSegment () {
      const payload = {
        data: {
          type: 'StatementSegment',
          id: this.segment.id,
          relationships: {
            assignee: {
              data: null,
            },
          },
        },
      }

      return dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'StatementSegment', resourceId: this.segment.id }), {}, payload)
        .then(() => {
          this.isFullscreen = false
          this.isEditing = false
          this.isCollapsed = true
          const dataToUpdate = JSON.parse(JSON.stringify(this.segment))
          delete dataToUpdate.relationships.assignee
          // Reset recommendation text in store (segment might have been in edit mode with some changes)
          dataToUpdate.attributes.recommendation = this.$store.state.StatementSegment.initial[this.segment.id].attributes.recommendation
          // Set segment in store, without the assignee and with resetted recommendation
          this.setSegment({ ...dataToUpdate, id: this.segment.id })
          this.claimLoading = false
          this.selectedAssignee = { id: '', name: '' }
        })
        .catch((err) => {
          console.error(err)
          this.claimLoading = false
        })
    },

    updateRelationships () {
      let relations = { ...this.segment.relationships }

      /**
       *  Comments need to be removed as updating them is technically not supported
       *  After completing the request, they are added again to the store to be able to display them
       */
      this.removeComments(relations)

      if (this.showWorkflowActions) {
        let assignee = { assignee: { data: null } }

        if (this.selectedAssignee && this.selectedAssignee.id !== 'noAssigneeId') {
          assignee = {
            assignee: {
              data: {
                id: this.selectedAssignee.id,
                type: 'AssignableUser',
              },
            },
          }
        }

        const place = {
          place: {
            data: {
              id: this.selectedPlace.id,
              type: 'Place',
            },
          },
        }

        relations = {
          ...relations,
          ...place,
          ...assignee,
        }
      }

      const updated = {
        ...this.segment,
        relationships: relations,
      }

      this.setSegment({ ...updated, id: this.segment.id })

      return relations
    },

    updateSegment (key, val) {
      const updated = { ...this.segment, ...{ attributes: { ...this.segment.attributes, ...{ [key]: val } } } }
      this.setSegment({ ...updated, id: this.segment.id })
    },

  },

  mounted () {
    this.initPlaces()
    this.initAssignableUsers()


    // Initialize unsaved changes guard
    this.initUnsavedChangesGuard({
      hasUnsavedChanges: () => this.hasUnsavedChanges,
      saveUnsavedChanges: () => this.saveUnsavedChanges(),
      onDiscardChanges: () => this.onDiscardChanges(),
      componentId: `segment-${this.segment.id}`,
    })
  },

  beforeUnmount () {
    this.cleanupUnsavedChangesGuard()
  },
}
</script>
