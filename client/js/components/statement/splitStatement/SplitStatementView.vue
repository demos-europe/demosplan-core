<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="split-statement">
    <div>
      <dp-sticky-element border>
        <header
          id="header"
          class="u-pv-0_25 flow-root">
          <dp-inline-notification
            v-if="!isLoading && isSegmentDraftUpdated"
            class="mt-3 mb-2"
            :message="Translator.trans('last.saved', { date: lastSavedTime })"
            type="info" />
          <h1 class="font-size-h1 align-bottom inline-block u-m-0">
            {{ Translator.trans('statement.do.segment', { id: statementExternId }) }}
          </h1>

          <ul
            v-if="segmentationStatus === 'inUserSegmentation'"
            class="float-right u-pt-0_25 u-m-0">
            <li class="inline-block">
              <dp-flyout
                ref="metadataFlyout"
                :has-menu="false">
                <template v-slot:trigger>
                  <span>
                    {{ Translator.trans('statement.information', { id: statementExternId }) }}
                    <i
                      class="fa fa-angle-down"
                      aria-hidden="true" />
                  </span>
                </template>
                <statement-meta-tooltip
                  v-if="statement"
                  :statement="statement"
                  toggle-button
                  @toggle="toggleInfobox" />
              </dp-flyout>
            </li>
          </ul>
        </header>
      </dp-sticky-element>

      <addon-wrapper
        :addon-props="{
          class: 'u-mb',
          processingTime: processingTime,
          statementId: statementId,
          status: segmentationStatus
        }"
        hook-name="split.statement.preprocessor"
        @addons:loaded="fetchSegments"
        @segmentationStatus:change="setSegmentationStatus" />

      <transition
        name="slide-fade"
        mode="out-in">
        <div v-if="segmentationStatus === 'inUserSegmentation'">
          <transition
            name="slide-fade"
            mode="out-in">
            <button
              v-show="displayScrollButton"
              :aria-label="Translator.trans('scroll.back.to.segment')"
              class="scroll-button text-center"
              :style="scrollButtonStyles"
              @click="scrollToSegment">
              <i
                :class="scrollButtonPosition.direction === 'top' ? 'fa fa-angle-double-up' : scrollButtonPosition.direction === 'bottom' ? 'fa fa-angle-double-down' : ''"
                aria-hidden="true" />
            </button>
          </transition>

          <statement-meta
            v-if="statement && showInfobox"
            :statement="statement"
            :submit-type-options="submitTypeOptions"
            @close="showInfobox = false" />

          <div v-if="isLoading">
            <dp-loading class="u-mt u-ml" />
          </div>
          <main
            ref="main"
            class="container pt-2"
            v-else-if="initialData">
            <segmentation-editor
              @prosemirror-initialized="runPostInitTasks"
              @prosemirror-max-range="setMaxRange"
              @focus="event => handleMouseOver(event)"
              @focusout="handleMouseLeave"
              @mouseover="event => handleMouseOver(event)"
              @mouseleave="handleMouseLeave"
              :init-statement-text="initText ?? ''"
              :segments="segments"
              :range-change-callback="handleSegmentChanges"
              :class="{ 'is-fullwidth': !showTags }" />

            <transition
              name="slide-fade"
              mode="out-in">
              <card-pane
                v-if="showTags && editModeActive === false && maxRange"
                id="cardPane"
                class="u-ml"
                :key="tagsCounter"
                :max-range="maxRange"
                :offset="headerOffset"
                @segment:confirm="handleSegmentConfirmation"
                @edit-segment="enableEditMode"
                @delete-segment="immediatelyDeleteSegment" />
            </transition>

            <transition
              name="slide-fade"
              mode="out-in">
              <dp-sticky-element
                v-if="editModeActive"
                :apply-z-index="false"
                :context="$refs.main"
                :offset="headerOffset">
                <side-bar
                  id="sideBar"
                  class="u-mb-0_25"
                  :offset="headerOffset"
                  ref="sideBar"
                  @abort="abortEdit"
                  @keydown.esc="toggleSideBar"
                  @save="save(editingSegment)" />
              </dp-sticky-element>
            </transition>
          </main>

          <div
            v-if="editModeActive === false"
            class="button-container">
            <dp-button
              @click="saveAndFinish"
              :busy="isBusy"
              :text="Translator.trans('statement.split.complete')"
              data-cy="statementSplitComplete"
              variant="outline" />
          </div>
        </div>
      </transition>
    </div>
  </div>
</template>

<script>
import {
  activateRangeEdit,
  applySelectionChange,
  removeRange,
  setRange,
  setRangeEditingState
} from '@DpJs/lib/prosemirror/commands'
import { dpApi, DpButton, DpFlyout, DpInlineNotification, DpLoading, DpStickyElement } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import AddonWrapper from '@DpJs/components/addon/AddonWrapper'
import CardPane from './CardPane'
import dayjs from 'dayjs'
import { generateRangeChangeMap } from '@DpJs/lib/prosemirror/utilities'
import SegmentationEditor from './SegmentationEditor'
import SideBar from './SideBar'
import StatementMeta from '@DpJs/components/procedure/StatementSegmentsList/StatementMeta/StatementMeta'
import StatementMetaTooltip from '../StatementMetaTooltip'
import { v4 as uuid } from 'uuid'
/**
 * This function merges ranges with their corresponding segments.
 * Ranges are used to represent range shaped information in prosemirror.
 * Their attributes do not perfectly match segment attributes.
 * Thus, range attributes are mapped to their corresponding segment attributes and an Array of updated segments is returned.
 *
 * @param {Array} ranges
 * @param {Array} segments
 * @return {undefined|Array}
 */
const mergeRangesAndSegments = (ranges, segments) => {
  const mergedSegments = []
  ranges.forEach(range => {
    const segment = segments.find(seg => seg.id === range.rangeId)
    const mergedSegment = { ...segment }
    if (!segment) {
      console.warn('A segment was updated in Prosemirror but no corresponding segment found in store.')
      return
    }

    mergedSegment.charStart = range.from
    mergedSegment.charEnd = range.to
    mergedSegment.status = range.isConfirmed ? 'confirmed' : false
    mergedSegment.text = range.text
    mergedSegments.push(mergedSegment)
  })

  return mergedSegments
}

export default {
  name: 'SplitStatementView',

  components: {
    AddonWrapper,
    CardPane,
    DpButton,
    DpFlyout,
    DpInlineNotification,
    DpLoading,
    DpStickyElement,
    SegmentationEditor,
    SideBar,
    StatementMeta,
    StatementMetaTooltip
  },

  provide () {
    return {
      procedureId: this.procedureId
    }
  },

  props: {
    editable: {
      required: false,
      type: Boolean,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    showTags: {
      type: Boolean,
      required: false,
      default: true
    },

    statementExternId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    },

    submitTypeOptions: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      assignableUsers: [],
      bubblePosition: { x: 0, y: 0 },
      bubbleVisible: false,
      createdSegmentProsemirrorId: null,
      displayScrollButton: false,
      ignoreProsemirrorUpdates: true,
      isLoading: !this.initialData,
      isSegmentDraftUpdated: false,
      lastSavedTime: '',
      marksCounter: 0,
      maxRange: 0,
      oldState: {},
      processingTime: 0,
      scrollButtonPosition: {
        direction: '',
        offset: ''
      },
      prosemirror: null,
      segmentationStatus: 'processing',
      showInfobox: false,
      tagsCounter: 0
    }
  },

  computed: {
    ...mapGetters('SplitStatement', [
      'currentlyHighlightedSegmentId',
      'editingSegment',
      'editingSegmentId',
      'editModeActive',
      'initialData',
      'initText',
      'isBusy',
      'segmentById',
      'segments',
      'statement',
      'statementSegmentDraftList'
    ]),

    /**
     * The height of the sticky header plus a little margin between both elements when fixed.
     * As the header may be bigger when the "last saved" notice is displayed, the offset
     * must be calculated according to the same logic.
     * @return {*}
     */
    headerOffset () {
      return 58 + (!this.isLoading && this.isSegmentDraftUpdated ? 80 : 0)
    },

    isHighlightedSegmentConfirmed () {
      return this.segmentById(this.currentlyHighlightedSegmentId) && this.segmentById(this.currentlyHighlightedSegmentId).status === 'confirmed'
    },

    isHighlightedSegment () {
      return !!this.currentlyHighlightedSegmentId
    },

    scrollButtonStyles () {
      return {
        top: this.scrollButtonPosition.offset
      }
    }
  },

  watch: {
    editModeActive: {
      handler (newVal) {
        if (newVal) {
          window.addEventListener('scroll', this.handleScroll, false)
        } else {
          window.removeEventListener('scroll', this.handleScroll, false)
          this.displayScrollButton = false
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    },

    initialData: {
      handler (newVal) {
        this.calculateProcessingTime()
        if (newVal) {
          this.isLoading = false
        }
      },
      deep: false // Set default for migrating purpose. To know this occurrence is checked
    }
  },

  methods: {
    ...mapMutations('SplitStatement', [
      'locallyDeleteSegments',
      'locallyUpdateSegments',
      'resetSegments',
      'setProperty'
    ]),

    ...mapActions('SplitStatement', [
      'acceptSegmentProposal',
      'deleteSegmentAction',
      'setInitialData',
      'fetchInitialData',
      'fetchStatementSegmentDraftList',
      'fetchTags',
      'saveSegmentsDrafts',
      'saveSegmentsFinal'
    ]),

    setCurrentTime () {
      this.lastSavedTime = dayjs().format('HH:mm')
    },

    abortEdit () {
      this.resetSegments()
      this.disableEditMode(false)
      this.resetProsemirrorState()
    },

    /**
     * Calculates total processing time for the statement, which is displayed for the user
     * 2000 characters take about 1s to be processed, we add some extra seconds so it feels faster than expected
     * if the processing time is 2s or shorter, we do not need to display the time for the user, as they won't be able
     * to read it
     * @return {number|number}
     */
    calculateProcessingTime () {
      const statementLength = this.initialData.attributes.textualReference.length
      this.processingTime = statementLength > 4000 ? Math.round(statementLength / 2000) + 3 : 0
    },

    determineDisplayScrollButton () {
      const segmentSpans = document.querySelectorAll('span[data-range-active="true"]')
      const lastSegmentSpan = segmentSpans[segmentSpans.length - 1]
      const segmentBottom = lastSegmentSpan.getBoundingClientRect().bottom
      const headerHeight = document.getElementById('header').getBoundingClientRect().height
      const firstSegmentSpan = segmentSpans[0]
      const segmentTop = firstSegmentSpan.getBoundingClientRect().top
      const segmentIsAtTop = segmentBottom < headerHeight
      const segmentIsAtBottom = segmentTop > (window.innerHeight || document.documentElement.clientHeight)

      if (segmentIsAtTop) {
        this.scrollButtonPosition = {
          direction: 'top',
          offset: '65px'
        }
      } else if (segmentIsAtBottom) {
        const vh = document.documentElement.clientHeight
        this.scrollButtonPosition = {
          direction: 'bottom',
          offset: `${vh - 70}px`
        }
      }

      return segmentSpans.length
        ? segmentIsAtTop || segmentIsAtBottom
        : false
    },

    determineIfStatementReady (counter = 0) {
      if (this.segmentationStatus === 'aiSegmented' || this.segmentationStatus === 'inUserSegmentation') {
        return
      }
      const time = counter < 3 ? 5000 : 2000
      setTimeout(() => {
        this.fetchStatementSegmentDraftList(this.statementId)
          .then(({ data }) => {
            /**
             * Here, we only want to check if the segmentDraftList was filled by the pipeline.
             * We don't check for the existence of segments because the pipeline might just not find any segments,
             * which would leave the user in an endless loop.
             */
            if (data.data.attributes.segmentDraftList !== null) {
              this.fetchInitialData().then(() => {
                this.segmentationStatus = 'aiSegmented'
              })
              return
            }
            counter++
            this.determineIfStatementReady(counter)
          })
      }, time)
    },

    disableEditMode (resetState = true) {
      if (resetState) {
        this.stateBeforeEditing = null
      }

      this.ignoreProsemirrorUpdates = true
      const { rangeTrackerKey, editingDecorationsKey } = this.prosemirror.keyAccess
      setRangeEditingState(this.prosemirror.view, rangeTrackerKey, editingDecorationsKey)(this.editingSegment.id, false)
      this.ignoreProsemirrorUpdates = false
      this.setProperty({ prop: 'editingSegment', val: null })
      this.setProperty({ prop: 'editModeActive', val: false })
    },

    enableEditMode (segmentId) {
      if (this.editModeActive) {
        console.warn('Tried to enable edit mode although it was already active')
        return
      }

      const id = segmentId
      const { state } = this.prosemirror.view
      const { rangeTrackerKey, editingDecorationsKey, editStateTrackerKey } = this.prosemirror.keyAccess

      this.stateBeforeEditing = rangeTrackerKey.getState(state)
      this.setProperty({ prop: 'editingSegment', val: this.segmentById(id) })
      this.setProperty({ prop: 'editModeActive', val: true })

      this.ignoreProsemirrorUpdates = true
      setRangeEditingState(this.prosemirror.view, rangeTrackerKey, editingDecorationsKey)(id, true)
      activateRangeEdit(
        this.prosemirror.view,
        rangeTrackerKey,
        editStateTrackerKey,
        id,
        { active: this.editingSegment.charEnd, fixed: this.editingSegment.charStart }
      )
      this.ignoreProsemirrorUpdates = false
    },

    fetchAssignableUsers () {
      const url = Routing.generate('api_resource_list', { resourceType: 'AssignableUser' })
      return dpApi.get(url, { sort: 'lastname' })
        .then(response => {
          this.assignableUsers = response.data.data.map(assignableUser => {
            return {
              name: assignableUser.attributes.firstname + ' ' + assignableUser.attributes.lastname,
              id: assignableUser.id
            }
          })
          this.assignableUsers.unshift({ name: Translator.trans('not.assigned'), id: 'noAssigneeId' })
          this.setProperty({ prop: 'assignableUsers', val: this.assignableUsers })
        })
    },

    fetchAvailablePlaces () {
      return dpApi.get(Routing.generate('api_resource_list', {
        resourceType: 'Place',
        fields: {
          Place: [
            'name',
            'description',
            'solved'
          ].join()
        },
        sort: 'sortIndex'
      })).then((response) => {
        const availablePlaces = response.data.data.map(place => {
          return {
            name: place.attributes.name,
            id: place.id,
            description: place.attributes.description
          }
        })
        this.setProperty({ prop: 'availablePlaces', val: availablePlaces })
      })
        .catch((err) => console.error(err))
    },

    fetchSegments (addonsLoaded) {
      // We only want to fetch segments here when the addon is not installed, otherwise it happens in the addon
      if (!addonsLoaded.includes('SplitStatementPreprocessor')) {
        this.fetchStatementSegmentDraftList(this.statementId)
          .then(({ data }) => {
            if (data.data.attributes.segmentDraftList) {
              this.fetchInitialData()
            } else {
              this.setInitialData()
              this.fetchTags()
            }

            this.segmentationStatus = 'inUserSegmentation'
          })
      }
    },

    handleCardHighlighting (segmentId, highlight) {
      const card = document.querySelector(`div[data-range="${segmentId}"]`)
      if (card) {
        if (highlight) {
          card.classList.add('highlighted')
        } else {
          if (card.classList.contains('highlighted')) {
            card.classList.remove('highlighted')
          }
        }
      }
    },

    /**
     * Removes highlighting background color from segment and border color from corresponding card
     * Updates currentlyHighlightedSegmentId in the store
     */
    handleMouseLeave () {
      if (!this.editModeActive) {
        this.handleSegmentHighlighting(this.currentlyHighlightedSegmentId, false)
        this.handleCardHighlighting(this.currentlyHighlightedSegmentId, false)
        this.setProperty({ prop: 'currentlyHighlightedSegmentId', val: null })
      }
    },

    /**
     * Adds highlighting background color to segment and border color to corresponding card
     * Updates currentlyHighlightedSegmentId in the store
     */
    handleMouseOver (e) {
      if (!this.editModeActive) {
        let segmentId = e.target.getAttribute('data-range') || e.target.closest('span[data-range]')?.getAttribute('data-range')

        /**
         * If the target element doesn't have the attribute 'data-range', it may be an html element inside the segment span,
         * and we don't know how deeply nested
         * In this case, we look for the next parent with the attribute 'data-range' to retrieve the segmentId; if none
         * exists, the hovered element is not inside a segment and highlighting is removed
         */
        if (!segmentId) {
          const closestParent = e.target.closest('span[data-range]')
          segmentId = closestParent ? closestParent.getAttribute('data-range') : null
        }

        if (segmentId) {
          if (segmentId !== this.currentlyHighlightedSegmentId) {
            this.handleSegmentHighlighting(this.currentlyHighlightedSegmentId, false)
          }
          this.handleCardHighlighting(segmentId, true)
          this.handleSegmentHighlighting(segmentId, true)
        } else {
          this.handleSegmentHighlighting(this.currentlyHighlightedSegmentId, false)
        }
      }
    },

    handleScroll () {
      this.displayScrollButton = this.determineDisplayScrollButton()
    },

    handleSegmentChanges (oldSegments, newSegments, { deletedRanges, createdRanges, updatedRanges }) {
      if (this.ignoreProsemirrorUpdates) {
        return
      }
      if (this.editModeActive === false) {
        this.stateBeforeEditing = oldSegments
      }
      if (createdRanges.length > 1) {
        console.warn('More than one segment was created since the last update event. There might be issues with the segment creation process.')
      }

      if (createdRanges.length) {
        this.handleSegmentCreation(createdRanges[0])
      }

      if (updatedRanges.length) {
        this.updateSegments(updatedRanges)
      }

      if (deletedRanges.length) {
        this.handleSegmentDeletions(deletedRanges)
      }
    },

    handleSegmentConfirmation (segmentId) {
      this.ignoreProsemirrorUpdates = true
      const { id, charStart, charEnd } = this.segmentById(segmentId)
      setRange(this.prosemirror.view)(charStart, charEnd, { rangeId: id, isConfirmed: true })
      this.acceptSegmentProposal()
      this.ignoreProsemirrorUpdates = false
    },

    handleSegmentCreation (segmentToCreate) {
      const segment = {
        id: uuid(),
        charStart: segmentToCreate.from,
        charEnd: segmentToCreate.to,
        tags: [],
        hasProsemirrorIndex: true,
        status: 'confirmed',
        text: segmentToCreate.text
      }
      this.setProperty({ prop: 'editingSegment', val: segment })
      this.setProperty({ prop: 'editModeActive', val: true })
      this.locallyUpdateSegments([segment])
      this.ignoreProsemirrorUpdates = true
      setRange(this.prosemirror.view)(segment.charStart, segment.charEnd, { rangeId: segment.id, isConfirmed: true, isActive: false })
      const { rangeTrackerKey, editingDecorationsKey, editStateTrackerKey } = this.prosemirror.keyAccess
      setRangeEditingState(this.prosemirror.view, rangeTrackerKey, editingDecorationsKey)(segment.id, true)
      activateRangeEdit(this.prosemirror.view, rangeTrackerKey, editStateTrackerKey, segment.id, { active: segment.charEnd, fixed: segment.charStart })
      this.ignoreProsemirrorUpdates = false
    },

    handleSegmentDeletions (segments) {
      const IdsToDelete = segments.map(segment => segment.rangeId)
      this.locallyDeleteSegments(IdsToDelete)
    },

    handleSegmentHighlighting (segmentId, highlight) {
      const id = segmentId || this.currentlyHighlightedSegmentId
      const highlightedSegmentId = highlight ? segmentId : null
      const segmentParts = Array.from(document.querySelectorAll(`span[data-range="${id}"]`))

      segmentParts.forEach(part => {
        if (highlight && !part.classList.contains('highlighted')) {
          part.classList.add('highlighted')
        }
        if (!highlight && part.classList.contains('highlighted')) {
          part.classList.remove('highlighted')
        }
      })

      this.setProperty({ prop: 'currentlyHighlightedSegmentId', val: highlightedSegmentId })
    },

    immediatelyDeleteSegment (segmentId) {
      const segment = this.segmentById(segmentId)
      const { state } = this.prosemirror.view
      const tr = removeRange(state, segment.charStart, segment.charEnd)

      this.ignoreProsemirrorUpdates = true
      this.prosemirror.view.dispatch(tr)
      this.ignoreProsemirrorUpdates = false
      this.deleteSegmentAction(segmentId)
      this.isSegmentDraftUpdated = true
      this.setCurrentTime()
    },

    resetProsemirrorState () {
      this.ignoreProsemirrorUpdates = true
      const { state } = this.prosemirror.view

      const newSegments = this.prosemirror.keyAccess.rangeTrackerKey.getState(state)
      const oldSegments = this.stateBeforeEditing

      const changes = generateRangeChangeMap(newSegments, oldSegments)

      changes.createdRanges.forEach(range => {
        setRange(this.prosemirror.view)(range.from, range.to, { rangeId: range.rangeId, isConfirmed: range.isConfirmed })
      })

      changes.updatedRanges.forEach(range => {
        setRange(this.prosemirror.view)(range.from, range.to, { rangeId: range.rangeId, isConfirmed: range.isConfirmed })
      })

      changes.deletedRanges.forEach(range => {
        const tr = removeRange(state, range.from, range.to)
        this.prosemirror.view.dispatch(tr)
      })
      this.ignoreProsemirrorUpdates = false
      this.stateBeforeEditing = null
    },

    /**
     * After prosemirror was initialized we need to perform a few tasks:
     * 1. Making prosemirror and the pluginstates available in this component by assigning it to this.prosemirror.
     * 2. Update segments because prosemirror might have made non-conforming range positions conformant.
     * 3. Set this.ignoreProsemirrorUpdates to true in order to listen to prosemirror changes.
     */
    runPostInitTasks (prosemirrorState) {
      this.prosemirror = prosemirrorState
      const { state } = this.prosemirror.view
      const ranges = Object.values(this.prosemirror.keyAccess.rangeTrackerKey.getState(state))
      const updatedSegments = mergeRangesAndSegments(ranges, this.segments)
      this.locallyUpdateSegments(updatedSegments)
      this.ignoreProsemirrorUpdates = false
    },

    // Matomo Tracking Event Tagging & Slicing
    clickTrackerSaveButton () {
      if (window._paq) {
        window._paq.push(['trackEvent', 'ST Slicing Tagging', 'Click', Translator.trans('statement.split.complete')])
      }
    },

    async saveAndFinish () {
      this.clickTrackerSaveButton()

      if (this.segments.length > 0) {
        if (window.dpconfirm(Translator.trans('statement.split.complete.confirm'))) {
          this.setProperty({ prop: 'isBusy', val: true })
          try {
            // Set data with html not only charStart and charEnd
            const ranges = this.prosemirror.keyAccess.rangeTrackerKey.getState(this.prosemirror.view.state)
            const segmentsWithText = this.segments
              .filter(segment => !!ranges[segment.id])
              .map(segment => {
                return {
                  ...segment,
                  text: ranges[segment.id].text
                }
              })
            this.setProperty({ prop: 'segmentsWithText', val: segmentsWithText })
            const currentStatementText = this.prosemirror.getContent(this.prosemirror.view.state)
            this.setProperty({ prop: 'statementText', val: currentStatementText })
            this.saveSegmentsFinal()
              .then(() => this.setProperty({ prop: 'isBusy', val: false }))
          } catch (err) {
            dplan.notify.error(Translator.trans('error.api.generic'))
            this.setProperty({ prop: 'isBusy', val: false })
          }
        }
      } else {
        dplan.notify.error(Translator.trans('error.statement.missing.segment.drafts'))
        this.setProperty({ prop: 'isBusy', val: false })
      }
    },

    /**
     * This function will apply all changes from the changed selection. It will generate new ranges/range boundaries.
     * The range changes are handled by handleSegmentChanges (as a callback). After handleSegmentChanges has persisted
     * the range changes locally, saveSegmentsDrafts will save the new json to our API.
     */
    save () {
      const validSegment = applySelectionChange(this.prosemirror.view, this.prosemirror.keyAccess.editStateTrackerKey, this.prosemirror.keyAccess.rangeTrackerKey)

      if (!validSegment) {
        return
      }

      this.saveSegmentsDrafts(true)
      this.isSegmentDraftUpdated = true
      this.disableEditMode()
      this.setCurrentTime()
    },

    scrollToSegment () {
      const segment = document.querySelector(`span[data-range="${this.editingSegment.id}"`)
      const bodyRect = document.body.getBoundingClientRect().top
      const elementRect = segment.getBoundingClientRect().top
      const elementPosition = elementRect - bodyRect
      const offsetPosition = elementPosition - 100
      window.scrollTo({
        top: offsetPosition,
        behavior: 'smooth'
      })
    },

    setMaxRange (range) {
      this.maxRange = range
    },

    setSegmentationStatus (status) {
      this.segmentationStatus = status
    },

    toggleInfobox () {
      this.showInfobox = true
      this.$refs.metadataFlyout.isExpanded = false
    },

    toggleSideBar (e) {
      if (this.editModeActive && e.keyCode === 27) {
        this.$refs.sideBar.reset()
      }
    },

    updateSegments (updatedSegments) {
      const newSegments = mergeRangesAndSegments(updatedSegments, this.segments)
      this.locallyUpdateSegments(newSegments)
    }
  },

  created () {
    this.setProperty({ prop: 'statementId', val: this.statementId })
    this.setProperty({ prop: 'procedureId', val: this.procedureId })
  },

  mounted () {
    this.fetchAssignableUsers()
    this.fetchAvailablePlaces()

    // Add event listener to close sidebar on esc
    document.addEventListener('keydown', (e) => this.toggleSideBar(e))
  },

  unmounted () {
    /**
     * Remove event listener when component is destroyed
     * This is necessary to prevent memory leaks
     */
    document.removeEventListener('keydown', (e) => this.toggleSideBar(e))
  }
}
</script>
