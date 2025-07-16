<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <!-- item -->
  <article
    class="c-at-item o-animate--bg-color u-mb-0_5"
    v-if="fragmentExists"
    :id="fragment.id || 0"
    :data-fragment-id="fragment.id || 0"
    :data-fragment-vote-advice="fragment.voteAdvice === null ? '' : fragment.voteAdvice">
    <!-- header -->
    <div class="c-at-item__header can-animate flow-root">
      <!-- claim, id, date created -->
      <div class="layout--flush weight--bold inline-block u-mv-0_25 u-mh-0_5 u-mr">
        <dp-claim
          class="c-at-item__row-icon inline-block"
          entity-type="fragment"
          :ignore-last-claimed="true"
          :assigned-id="(fragment.assignee?.id || '')"
          :assigned-name="(fragment.assignee?.name || '')"
          :assigned-organisation="(fragment.assignee?.orgaName || '')"
          :current-user-id="currentUserId"
          :current-user-name="currentUserName"
          :is-loading="updatingClaimState"
          :last-claimed-user-id="fragment.lastClaimedUserId"
          v-if="!isArchive && hasPermission('feature_statement_assignment')"
          @click="updateClaim" />

        <v-popover
          class="inline-block u-mr"
          placement="top"
          trigger="hover focus">
          <div>
            <span
              v-if="isArchive"
              class="c-at-item__row-icon inline-block">
              <input
                type="checkbox"
                :id="fragment.id ? fragment.id + ':item_check[]' : '0:item_check[]'"
                name="item_check[]"
                :value="fragment.id || 0"
                data-selection-checkbox
                aria-describedby="exportCheckboxDescription">
            </span>

            <input
              v-else
              type="checkbox"
              :id="fragment.id ? fragment.id + ':item_check[]' : '0:item_check[]'"
              name="item_check[]"
              :value="fragment.id || 0"
              data-selection-checkbox
              aria-describedby="exportCheckboxDescription">

            <label
              class="u-m-0 inline-block"
              :for="fragment.id ? fragment.id + ':item_check[]' : '0:item_check[]'">
              ID {{ Translator.trans(missKeyValue(fragment.displayId, 'notspecified')) }} ({{ Translator.trans(missKeyValue(fragment.statement.externId, 'notspecified')) }})
            </label>
          </div>

          <template v-slot:popover>
            <div>
              {{ Translator.trans('fragment.created') }} {{ createdDateFragment }}<br>
              {{ Translator.trans('fragment.id') }}: {{ Translator.trans(missKeyValue(fragment.displayId, 'notspecified')) }}<br>
              {{ Translator.trans('statement.id') }}: {{ Translator.trans(missKeyValue(fragment.statement.externId, 'notspecified')) }}
            </div>
          </template>
        </v-popover>
      </div>

      <!-- voteAdvice badge -->
      <dp-fragment-status
        v-if="hasPermission('feature_statements_fragment_advice')"
        :status="status || ''"
        :archived-orga-name="fragment.archivedOrgaName || ''"
        :archived-department-name="fragment.archivedDepartmentName || ''"
        :fragment-id="fragment.id || 0"
        :badge="true"
        :tooltip="true"
        class="inline-block u-mv-0_25 u-mh-0_5">
        <template v-slot:title>
          {{ Translator.trans('fragment.voteAdvice.short') }}
        </template>
      </dp-fragment-status>

      <!-- Tabs -->
      <div class="text-right float-right">
        <a
          class="c-at-item__tab-trigger o-link--icon inline-block u-pv-0_25 u-ph-0_5"
          :class="{'is-active-toggle': tab==='fragment'}"
          @click="setActiveTab('fragment')"
          :href="`#fragment_${fragment.id || 0}`"
          rel="noopener">
          <i
            class="fa fa-sitemap"
            aria-hidden="true" />
          {{ Translator.trans('fragment') }}
        </a>

        <a
          class="c-at-item__tab-trigger o-link--icon inline-block u-pv-0_25 u-ph-0_5"
          :class="{'is-active-toggle': tab==='statement'}"
          @click="setActiveTab('statement')"
          :href="`#statement_${fragment.id || 0}`"
          rel="noopener">
          <i
            class="fa fa-file-o"
            aria-hidden="true" />
          {{ Translator.trans('statement') }}
        </a>
      </div>
    </div>

    <!-- display procedure name to add some context -->
    <dp-item-row
      icon="fa-folder"
      title="procedure"
      class="bg-color--grey-light-2">
      <a
        :href="Routing.generate('DemosPlan_procedure_public_detail', { procedure: fragment.procedureId })"
        rel="noopener">
        {{ missKeyValue(fragment.procedureName) }}
      </a>
    </dp-item-row>

    <!-- tab content: fragment -->
    <div
      v-if="tab==='fragment'"
      class="layout--flush bg-color--grey-light-2"
      :id="`#fragment_${fragment.id || 0}`">
      <!-- tags -->
      <dp-item-row
        icon="fa-tag"
        title="tags.assigned">
        <template v-if="fragment.tags.length">
          <ul class="o-list o-list--csv">
            <v-popover
              v-for="tag in fragment.tags"
              :key="tag.id"
              placement="top"
              class="o-list__item">
              <li>{{ tag.title }}</li>
              <template v-slot:popover>
                <div>
                  <strong class="block">{{ tag.topicTitle }}</strong>
                  {{ tag.title }}
                </div>
              </template>
            </v-popover>
          </ul>
        </template>
        <p
          v-else
          class="u-m-0">
          {{ Translator.trans('tags.notassigned') }}
        </p>
      </dp-item-row>

      <!-- location -->
      <dp-item-row
        icon="fa-map-marker"
        title="location"
        v-if="hasPermission('field_statement_county') || hasPermission('field_statement_municipality') || dplan.procedureStatementPriorityArea">
        <dl v-if="fragment.counties.length || fragment.priorityAreas.length || fragment.municipalities.length">
          <template v-if="fragment.counties.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('counties') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <ul class="o-list o-list--csv">
                <li
                  v-for="(county, idx) in fragment.counties"
                  class="o-list__item"
                  :key="idx"
                  v-text="county.name" />
              </ul>
            </dd>
          </template>
          <template v-if="fragment.priorityAreas.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('priorityAreas.all') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <ul class="o-list o-list--csv">
                <li
                  class="o-list__item"
                  v-for="(priorityArea, idx) in fragment.priorityAreas"
                  :key="idx"
                  v-text="priorityArea.key" />
              </ul>
          </dd>
          </template>
          <template v-if="fragment.municipalities.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('municipalities') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <ul class="o-list o-list--csv">
                <li
                  class="o-list__item"
                  v-for="(municipality, idx) in fragment.municipalities"
                  :key="idx"
                  v-text="municipality.name" />
              </ul>
            </dd>
          </template>
        </dl>
        <p
          v-else
          class="u-m-0">
          {{ Translator.trans('location.notassigned') }}
        </p>
      </dp-item-row>

      <!-- element -->
      <dp-item-row
        icon="fa-file-text"
        title="element.assigned">
        <dl v-if="fragment.elementTitle != null">
          <dt class="layout__item u-1-of-6 weight--bold">
            {{ Translator.trans('document') }}:
          </dt><!--
       --><dd class="layout__item u-5-of-6">
            <a
              v-if="fragment.elementCategory === 'paragraph'"
              :href="Routing.generate('DemosPlan_public_plandocument_paragraph', { procedure: fragment.procedureId, elementId: fragment.elementId || '' }) || '#'"
              :title="fragment.elementTitle || ''"
              rel="noopener">
              {{ Translator.trans(missKeyValue(fragment.elementTitle, 'document.notavailable')) }}
            </a>
            <p
              v-else-if="fragment.elementCategory === 'file'"
              :title="fragment.elementTitle || ''">
              {{ Translator.trans(missKeyValue(fragment.elementTitle, 'document.notavailable')) }}
            </p>
            <p
              v-else-if="fragment.elementCategory === 'map'"
              :title="fragment.elementTitle || ''">
              {{ Translator.trans(missKeyValue(fragment.elementTitle, 'document.notavailable')) }}
            </p>
            <p
              v-else-if="fragment.elementCategory === 'statement'"
              :title="fragment.elementTitle || ''">
              {{ Translator.trans(missKeyValue(fragment.elementTitle, 'document.notavailable')) }}
            </p>
          </dd>
          <template v-if="fragment.paragraphTitle != null">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('paragraph') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              {{ Translator.trans(missKeyValue(fragment.paragraphTitle, 'paragraph.notavailable')) }}
            </dd>
          </template>
          <template v-if="hasPermission('feature_single_document_fragment') && hasFile">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('file') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              {{ fragmentDocumentTitle }}
            </dd>
          </template>
        </dl>

        <p
          v-else
          class="u-m-0">
          {{ Translator.trans('element.notassigned') }}
        </p>
      </dp-item-row>

      <!-- fragment text -->
      <dp-item-row
        icon="fa-comment"
        title="fragment.text">
        <text-content-renderer :text="fragment.text" />
      </dp-item-row>

      <!-- fragment consideration -->
      <dp-item-row
        icon="fa-comment-o"
        title="fragment.consideration"
        :border-bottom="false">
        <span v-cleanhtml="fragment.considerationAdvice ? fragment.considerationAdvice : `<p>${Translator.trans('notspecified')}</p>`" />
      </dp-item-row>

      <!-- fragment versions -->
      <dp-item-row
        class="u-pt-0"
        :border-bottom="!isArchive">
        <dp-fragment-versions
          :fragment-id="fragment.id"
          :statement-id="fragment.statement.id"
          ref="history" />
      </dp-item-row>

      <!-- edit fragment -->
      <div
        v-if="!isArchive"
        :title="editable ? '' : Translator.trans('locked.title')">
        <!-- edit fragment: toggle -->
        <div class="layout--flush u-pv-0_25 u-ph-0_5 u-pl-0_25">
          <a
            class="inline-block cursor-pointer"
            :class="{ 'is-active-toggle': editing }"
            @click="toggleEditing"
            rel="noopener"
            :style="editable ? '' : 'opacity: .4 !important; pointer-events: none;'">
            <i
              class="o-toggle__icon o-toggle__icon--caret u-pl-0_25 u-pr-0_25"
              aria-hidden="true" />
            {{ Translator.trans('fragment.update') }}
          </a>
        </div>

        <!-- edit fragment: content -->
        <dp-item-row
          title="fragment.consideration"
          :border-bottom="false"
          v-if="editable && editing">
          <dp-fragment-edit
            @closeEditMode="closeEditMode"
            :csrf-token="csrfToken"
            :fragment-id="fragment.id"
            :procedure-id="fragment.procedureId"
            :consideration-advice-initial="fragment.considerationAdvice"
            :vote-advice-initial="fragment.voteAdvice || ''"
            :advice-values="adviceValues"
            :element-id="fragment.elementId"
            :paragraph-id="fragment.paragraphId"
            ref="editor" />
        </dp-item-row>
      </div>
    </div>

    <!-- tab content: statement -->
    <div
      v-if="tab==='statement'"
      class="layout--flush bg-color--grey-light-2"
      :id="`#statement_${fragment.id || 0}`">
      <!-- tags -->
      <dp-item-row
        icon="fa-tag"
        title="tags.assigned">
        <template v-if="fragment.statement.tags.length">
          <ul
            class="o-list o-list--csv inline-block u-pb-0_25"
            style="max-width: 95%">
            <v-popover
              v-for="(tag, idx) in fragment.statement.tags"
              :key="idx"
              placement="top"
              class="o-list__item inline">
              <li>{{ tag.title }}</li>
              <template v-slot:popover>
                <div>
                  <strong class="block">{{ tag.topicTitle }}</strong>
                  {{ tag.title }}
                </div>
              </template>
            </v-popover>
          </ul>
        </template>
        <p
          v-else
          class="u-mb-0">
          {{ Translator.trans('tags.notassigned') }}
        </p>
      </dp-item-row>

      <!-- location -->
      <dp-item-row
        icon="fa-map-marker"
        title="location"
        v-if="hasPermission('field_statement_county') || hasPermission('field_statement_municipality') || dplan.procedureStatementPriorityArea">
        <dl v-if="fragment.statement.counties.length && fragment.statement.priorityAreas.length && fragment.statement.municipalities.length">
          <template v-if="fragment.statement.counties.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('counties') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <span
                v-for="(county, idx) in fragment.statement.counties"
                :key="idx">
                {{ (idx >= fragment.statement.counties.length - 1) ? county.name : county.name + ',' }}
              </span>
            </dd>
          </template>
          <template v-if="fragment.statement.priorityAreas.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('priorityAreas.all') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <span
                v-for="(priorityArea, idx) in fragment.statement.priorityAreas"
                :key="idx">
                  {{ (idx >= fragment.statement.priorityAreas.length - 1) ? priorityArea.key : priorityArea.key + ',' }}
              </span>
            </dd>
          </template>
          <template v-if="fragment.statement.municipalities.length">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('municipalities') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              <span
                v-for="(municipality, idx) in fragment.statement.municipalities"
                :key="idx">
                {{ (idx >= fragment.statement.municipalities.length - 1) ? municipality.name : municipality.name + ',' }}
              </span>
            </dd>
          </template>
        </dl>
        <p
          v-else
          class="u-m-0">
          {{ Translator.trans('location.notassigned') }}
        </p>
      </dp-item-row>

      <!-- element -->
      <dp-item-row
        icon="fa-file-text"
        title="element.assigned">
        <dl v-if="fragment.statement.elementTitle != null">
          <dt class="layout__item u-1-of-6 weight--bold">
            {{ Translator.trans('document') }}:
          </dt><!--
       --><dd class="layout__item u-5-of-6">
            <a
              v-if="fragment.statement.elementCategory === 'paragraph'"
              :href="Routing.generate('DemosPlan_public_plandocument_paragraph', { procedure: fragment.procedureId, elementId: fragment.statement.elementId }) || '#'"
              rel="noopener"
              :title="fragment.statement.elementTitle || ''">
              {{ Translator.trans(missKeyValue(fragment.statement.elementTitle, 'document.notavailable')) }}
            </a>
            <p
              v-else-if="fragment.statement.elementCategory === 'file'"
              :title="fragment.statement.elementTitle || ''">
              {{ Translator.trans(missKeyValue(fragment.statement.elementTitle, 'document.notavailable')) }}
            </p>
          </dd>

          <template v-if="fragment.statement.paragraphTitle != null">
            <dt class="layout__item u-1-of-6 weight--bold">
              {{ Translator.trans('paragraph') }}:
            </dt><!--
         --><dd class="layout__item u-5-of-6">
              {{ Translator.trans(missKeyValue(fragment.statement.paragraphTitle, 'paragraph.notavailable')) }}
            </dd>
          </template>
        </dl>
        <p
          v-else
          class="u-m-0">
          {{ Translator.trans('element.notassigned') }}
        </p>
      </dp-item-row>

      <!-- attached files -->
      <dp-item-row
        icon="fa-paperclip"
        title="fragment.statement.files.uploaded"
        v-if="fragment.statement && fragment.statement.files && fragment.statement.files.length">
        <a
          v-for="file in statementFiles"
          :key="file.hash"
          class="o-hellip u-pr-0_5"
          :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: fragment.procedureId })"
          rel="noopener"
          target="_blank">
          {{ file.name }}
        </a>
      </dp-item-row>

      <!-- statement text -->
      <dp-item-row
        icon="fa-comment"
        title="statement.text"
        :border-bottom="false">
        <height-limit
          :short-text="fragment.statement.textShort"
          :full-text="fragment.statement.text"
          element="statement"
          class="u-mr"
          :is-shortened="fragment.statement.textShort.length < fragment.statement.text.length"
          no-event />
      </dp-item-row>
    </div>
  </article>
</template>

<script>
import {
  CleanHtml,
  formatDate,
  getFileInfo,
  hasOwnProp,
  VPopover
} from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import DpClaim from '../DpClaim'
import DpFragmentEdit from '../fragment/Edit'
import DpFragmentStatus from '../fragment/Status'
import DpFragmentVersions from '../fragment/Version'
import DpItemRow from '../assessmentTable/ItemRow'
import HeightLimit from '@DpJs/components/statement/HeightLimit'
import TextContentRenderer from '@DpJs/components/shared/TextContentRenderer'

export default {
  name: 'DpStatementFragment',

  components: {
    DpClaim,
    DpFragmentEdit,
    DpFragmentStatus,
    DpFragmentVersions,
    HeightLimit,
    DpItemRow,
    TextContentRenderer,
    VPopover
  },

  directives: {
    cleanhtml: CleanHtml
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    isArchive: {
      type: Boolean,
      required: false,
      default: false
    },

    fragmentId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    },

    currentUserId: {
      type: String,
      required: true
    },

    currentUserName: {
      type: String,
      required: true
    },

    adviceValues: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      editing: false,
      fragmentExists: true,
      editable: false,
      tab: 'fragment',
      considerationAdvice: '',
      status: '',
      updatingClaimState: false
    }
  },

  computed: {
    assigneeId () {
      if (hasOwnProp(this.fragment, 'assignee') && this.fragment.assignee?.id) {
        return this.fragment.assignee.id
      }
      return ''
    },

    assigneeName () {
      if (hasOwnProp(this.fragment, 'assignee') && this.fragment.assignee?.name) {
        return this.fragment.assignee.name
      }
      return ''
    },

    createdDateFragment () {
      return formatDate(this.fragment.created)
    },

    fragmentDocumentTitle () {
      return this.fragment.document ? Translator.trans(this.fragment.document.title) : Translator.trans('file.notavailable')
    },

    hasFile () {
      if (Array.isArray(this.fragment.document)) {
        return false
      }
      return true
    },

    assigneeOrgaName () {
      if (hasOwnProp(this.fragment, 'assignee') && this.fragment.assignee?.orgaName) {
        return this.fragment.assignee.orgaName
      }
      return ''
    },

    fragment () {
      return this.fragmentById(this.statementId, this.fragmentId)
    },

    statementFiles () {
      const files = []

      for (let i = 0; i < this.fragment.statement.files.length; i++) {
        files.push(getFileInfo(this.fragment.statement.files[i]))
      }
      return files
    },

    ...mapGetters('Fragment', ['fragmentById'])
  },

  methods: {
    closeEditMode () {
      this.$refs.editor.considerationAdvice = this.fragment.considerationAdvice
      this.editing = false
    },

    missKeyValue (value, defaultValue) {
      if (typeof value === 'undefined' || value === null) {
        if (typeof defaultValue === 'undefined' || value === null) {
          defaultValue = ''
        }

        return defaultValue
      }

      return value
    },

    toggleEditing () {
      this.editing = !this.editing
    },

    updateClaim () {
      this.updatingClaimState = true
      /*
       * If we reset the assignee (give fragment back to FP), the lastClaimed should be ignored. Otherwise not, because we have to show the empty user-icon to FP (so that they know that fragment is being edited by FB)
       * let shouldIgnoreLastClaimed = (hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee.id === this.currentUserId)
       */
      this.setAssigneeAction({ fragmentId: this.fragmentId, statementId: this.statementId, ignoreLastClaimed: true, assigneeId: (hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee?.id === this.currentUserId ? '' : this.currentUserId) })
        .then(() => {
          this.updatingClaimState = false
          this.editable = hasOwnProp(this.fragment.assignee, 'id') && this.fragment.assignee?.id !== ''
        })
    },

    setActiveTab (tab) {
      this.tab = tab
    },

    ...mapActions('Fragment', ['setAssigneeAction']),
    ...mapMutations('Fragment', ['updateFragment', 'deleteFragment'])
  },

  mounted () {
    this.status = this.fragment.voteAdvice
    this.considerationAdvice = this.fragment.considerationAdvice
    this.editable = hasOwnProp(this.fragment, 'assignee') && this.fragment.assignee?.id === this.currentUserId
    //  Sync contents of child components on save
    this.$root.$on('fragment-saved', data => {
      if (this.fragmentId === data.id) {
        data.fragmentId = data.id
        data.statementId = this.statementId
        this.updateFragment(data)

        this.$refs.history.load()// @TODO lass ihn doch mal selber
        /*
         * changing the computed "fragment" doesn't trigger fragment.considerationAdvice update,
         * so we have to do it manually with a data-prop
         * @TODO make it reactive
         */
        this.considerationAdvice = this.fragment.considerationAdvice
        this.status = this.fragment.voteAdvice
        this.editing = false
      }
    })

    //  Destroy instance on reassign
    this.$root.$on('fragment-reassigned', data => {
      if (data.id === this.fragmentId) {
        this.deleteFragment({ fragmentId: this.fragmentId, statementId: this.fragment.statement.id })
        /*
         * For now we just hide the Fragment
         * this can be refactored when the fragment-list gets the data from the store
         */
        this.fragmentExists = false
      }
    })
  }
}
</script>
