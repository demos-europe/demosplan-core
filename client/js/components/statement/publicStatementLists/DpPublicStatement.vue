<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-table-card
    class="c-public-statement"
    :open="isOpen">
    <template v-slot:header="">
      <div
        class="c-public-statement__header"
        :class="{'border--bottom': isOpen}">
        <div class="layout__item u-11-of-12 u-1-of-4-desk-up align-sub u-pl-0">
          <div class="inline-block u-mr-0_5">
            <input
              v-if="showCheckbox"
              type="checkbox"
              :id="number"
              name="item_check[]"
              :value="id">
            <span
              class="c-public-statement__tooltip"
              v-tooltip="renderTooltipContent(tooltipContent)">
              <label
                :for="number"
                data-cy="statementNumber"
                class="inline u-mb-0">
                {{ `${ number || externId }` }}
              </label>
            </span>
          </div><!--
       --><div class="inline-block">
            <span>{{ headerContent }}</span>
            <button
              v-if="unsavedChangesItem"
              v-bind="unsavedChangesItem.attrs"
              :key="unsavedChangesItem.name"
              class="btn--blank o-link--default"
              @click.prevent.stop="(e) => typeof unsavedChangesItem.callback === 'function' ? unsavedChangesItem.callback(e, _self) : false">
              <i
                class="fa fa-exclamation-circle color-message-severe-fill u-mr-0_5"
                v-tooltip="Translator.trans('unsaved.changes')" />
            </button>
          </div>
        </div><!--
     --><div class="layout__item u-1-of-12 u-3-of-4-desk-up u-pl-0 text-right">
          <div class="show-desk-up-i">
            <div
              v-for="item in menuItems"
              :key="item.id"
              class="inline u-mr-0_5">
              <button
                v-if="item.type === 'button'"
                v-bind="item.attrs"
                class="btn--blank o-link--default align-middle"
                @click="(e) => typeof item.callback === 'function' ? item.callback(e, _self) : false">
                {{ item.text }}
              </button>
              <a
                v-else-if="item.type === 'link'"
                v-bind="item.attrs"
                class="o-link--default align-middle"
                :href="item.url">
                {{ item.text }}
              </a>
              <h4
                v-else-if="item.type === 'heading'"
                v-bind="item.attrs"
                class="color--grey u-mb-0 u-mt-0_25 font-size-small align-middle">
                {{ item.text }}
              </h4>
            </div>
          </div><!--
       --><div class="hide-desk-up-i">
            <dp-flyout>
              <div
                v-for="item in menuItems"
                :key="item.id">
                <a
                  v-if="item.type === 'link'"
                  v-bind="item.attrs"
                  class="o-link--default"
                  :href="item.url">
                  {{ item.text }}
                </a>
                <button
                  v-if="item.type === 'button'"
                  v-bind="item.attrs"
                  class="btn--blank o-link--default"
                  @click="(e) => typeof item.callback === 'function' ? item.callback(e, _self) : false">
                  {{ item.text }}
                </button>
                <h4
                  v-if="item.type === 'heading'"
                  v-bind="item.attrs"
                  class="color--grey u-mb-0 u-mt-0_25 font-size-small">
                  {{ item.text }}
                </h4>
              </div>
            </dp-flyout>
          </div><!--
       --><div class="inline">
            <button
              @click="isOpen = false === isOpen"
              type="button"
              class="btn--blank o-link--default u-pr-0_25 c-public-statement__toggle">
              <i
                class="fa"
                :class="isOpen ? 'fa-angle-up': 'fa-angle-down'" />
            </button>
          </div>
        </div>
      </div>
    </template>

    <div class="u-1-of-2 u-1-of-1-palm c-public-statement__content-container">
      <div class="u-1-of-1 c-public-statement__content-item">
        <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
          {{ Translator.trans('organisation') }}
        </div><!--
     --><div class="inline-block u-2-of-3 u-1-of-1-palm">
          {{ organisation || '-' }}
        </div>
      </div><!--
   --><div class="u-1-of-1 c-public-statement__content-item">
        <div
            v-if="showAuthor"
            class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('authored.by') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ authoredBy }}
      </div>
      </div><!--
 --><div class="u-1-of-1 c-public-statement__content-item">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('department') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ department || '-' }}
      </div>
      </div><!--
 --><div class="u-1-of-1 c-public-statement__content-item">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('phase') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ phase || '-' }}
      </div>
      </div><!--
 --><div class="u-1-of-1 c-public-statement__content-item">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('document') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ document }}
      </div>
      </div><!--
 --><div
      v-if="hasPermission('feature_documents_new_statement')"
      class="u-1-of-1 c-public-statement__content-item">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('paragraph') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ paragraph }}
      </div>
    </div>
    </div><!--
--><div class="u-1-of-2 u-1-of-1-palm c-public-statement__content-container">
    <div class="u-1-of-1 c-public-statement__content-item">
      <template v-if="hasPermission('field_statement_location')">
        <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
          {{ Translator.trans('location') }}
        </div>
        <div class="inline-block u-2-of-3 u-1-of-1-palm">
          <button
            v-if="Object.keys(polygon).length > 0"
            class="btn--blank o-link--default"
            type="button"
            @click.prevent.stop="$emit('openMapModal', polygon)"
            :aria-label="`${Translator.trans('statement.map.drawing.show')} ${Translator.trans('statement')}: ${number}`">
            {{ Translator.trans('see') }}
          </button>
          <span v-else>
            -
          </span>
        </div>
      </template>
    </div><!--
 --><div
      class="u-1-of-1 c-public-statement__content-item"
      v-if="priorityAreas !== null">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('potential.areas') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
        {{ renderPriorityAreas(priorityAreas) }}
      </div>
    </div><!--
   --><div
        class="u-1-of-1 c-public-statement__content-item"
        v-if="county !== null">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('county') }}
      </div><!--
   --><div class="inline-block u-2-of-3 u-1-of-1-palm">
      {{ county }}
      </div>
    </div><!--
 --><div class="u-1-of-1 c-public-statement__content-item">
      <div class="inline-block u-1-of-3 u-1-of-1-palm u-pr c-public-statement__label">
        {{ Translator.trans('attachments') }}
      </div><!--
   --><div
        class="inline-block u-2-of-3 u-1-of-1-palm break-words"
        v-cleanhtml="renderAttachments(attachments)" />
      </div>
    </div>
    <dp-inline-notification
      v-if="rejectedReason"
      class="mt"
      type="info">
      <div>{{ Translator.trans('statement.rejected.with.reason') }}:</div>
      <div>{{ rejectedReason }}</div>
    </dp-inline-notification>
    <div class="u-1-of-1 u-mt">
      <div class="c-public-statement__label">
        {{ Translator.trans('statementtext') }}
      </div>
      <div
        class="c-styled-html"
        v-cleanhtml="text" />
    </div>
  </dp-table-card>
</template>

<script>
import { CleanHtml, DpFlyout, DpInlineNotification } from '@demos-europe/demosplan-ui'
import DomPurify from 'dompurify'
import DpTableCard from '@DpJs/components/user/DpTableCardList/DpTableCard'
import { mapState } from 'vuex'

export default {
  name: 'DpPublicStatement',

  components: {
    DpFlyout,
    DpInlineNotification,
    DpTableCard
  },

  directives: { cleanhtml: CleanHtml },

  props: {
    attachments: {
      type: Array,
      required: false,
      default: () => ([])
    },
    county: {
      type: [String, null],
      required: false,
      validator: (val) => typeof val === 'string' || val === null,
      default: null
    },
    createdDate: {
      type: [String, null],
      required: false,
      validator: (val) => typeof val === 'string' || val === null,
      default: null
    },
    department: {
      type: String,
      required: true
    },
    document: {
      type: String,
      required: false,
      default: () => Translator.trans('none')
    },
    elementId: {
      type: String,
      required: false,
      default: ''
    },
    externId: {
      type: String,
      required: false,
      default: ''
    },
    id: {
      type: String,
      required: true
    },
    isPublished: {
      type: Boolean,
      required: false,
      default: () => false
    },
    menuItemsGenerator: {
      type: Function,
      required: true
    },
    number: {
      type: Number,
      required: false,
      default: 0
    },
    organisation: {
      type: String,
      required: true
    },
    paragraph: {
      type: String,
      required: false,
      default: () => Translator.trans('none')
    },
    paragraphId: {
      type: String,
      required: false,
      default: ''
    },
    phase: {
      type: String,
      required: true
    },
    polygon: {
      type: Object,
      required: false,
      default: () => ({})
    },
    priorityAreas: {
      type: [String, null, Array],
      required: false,
      validator: (val) => typeof val === 'string' || Array.isArray(val) || val === null,
      default: null
    },

    procedureId: {
      type: String,
      required: true
    },

    rejectedReason: {
      type: [String, null],
      required: false,
      validator: (val) => typeof val === 'string' || val === null,
      default: null
    },
    showAuthor: {
      type: Boolean,
      required: false,
      default: false
    },
    showCheckbox: {
      type: Boolean,
      required: false,
      default: false
    },
    submittedDate: {
      type: [String, null],
      required: false,
      validator: (val) => typeof val === 'string' || val === null,
      default: null
    },
    text: {
      type: String,
      required: true
    },
    user: {
      type: String,
      required: true
    }
  },

  emits: [
    'openMapModal'
  ],

  data () {
    return {
      isOpen: true
    }
  },

  computed: {
    ...mapState('PublicStatement', ['unsavedDrafts']),

    authoredBy () {
      return this.showAuthor ? this.user : '-'
    },

    headerContent () {
      const user = this.showAuthor ? `${this.user} | ` : ''

      return this.submittedDate
        ? `${user}${Translator.trans('date.submitted')} ${this.submittedDate} ${Translator.trans('clock')}`
        : `${Translator.trans('date.created')} ${this.createdDate} ${Translator.trans('clock')}`
    },

    menuItems () {
      return this.menuItemsGenerator(this.id, this.elementId, this.paragraphId, this.isPublished)
    },

    tooltipContent () {
      const organisation = `${Translator.trans('organisation')}: ${this.organisation}`
      const submitter = this.showAuthor ? `${Translator.trans('authored.by')}: ${this.user}` : ''
      const department = `${Translator.trans('department')}: ${this.department}`
      const phase = `${Translator.trans('phase')}: ${this.phase}`

      return [organisation, submitter, department, phase]
    },

    unsavedChangesItem () {
      return (this.unsavedDrafts.findIndex(el => el === this.id) > -1) ? this.menuItems.find(el => el.name === 'edit') : false
    }
  },

  methods: {
    renderAttachments (attachments) {
      const transformedAttachments = attachments.map(a => `<a href="${Routing.generate('core_file_procedure', { hash: a.hash, procedureId: this.procedureId })}">${a.name}</a>`)
      return transformedAttachments.length > 0 ? transformedAttachments.join(', ') : Translator.trans('notspecified')
    },

    renderPriorityAreas (priorityAreas) {
      return Array.isArray(priorityAreas) ? priorityAreas.join(', ') : priorityAreas
    },

    renderTooltipContent (lines) {
      let content = ''
      lines.forEach(ln => {
        content += `${ln}<br />`
      })
      return DomPurify.sanitize(content)
    }
  }
}
</script>
