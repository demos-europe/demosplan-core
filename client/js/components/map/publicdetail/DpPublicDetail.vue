<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :active-action-box-tab="activeActionBoxTab"
      :active-statement="activeStatement"
      :active-tab="activeTab"
      :check-key-event="checkKeyEvent"
      :fold-open-toolbar-items="foldOpenToolbarItems"
      :handle-fullscreen-focus="handleFullscreenFocus"
      :prefix-class="prefixClass"
      :set-ref="setRef"
      :toggle-statement-modal="toggleStatementModal"
      :toggle-tabs="toggleTabs"
      :update="update"
      :update-statement-and-open-modal="updateStatementAndOpenModal"
    />
  </div>
</template>

<script>
import { mapMutations, mapState } from 'vuex'
import { prefixClassMixin } from '@demos-europe/demosplan-ui'
import { useSlotRefs } from '@DpJs/composables/useSlotRefs'

/*
 * The components the Twig markup uses are registered on the app by the bundle entrypoints, not here:
 * as scoped slot content it's compiled in the app's scope, not in this component's
 */
export default {
  name: 'DpPublicDetail',

  mixins: [prefixClassMixin],

  props: {
    isMapEnabled: {
      type: Boolean,
      required: false,
      default: false,
    },

    procedureId: {
      required: true,
      type: String,
    },

    userId: {
      required: true,
      type: String,
    },
  },

  setup () {
    const { setRef, slotRefs } = useSlotRefs()

    return { setRef, slotRefs }
  },

  data () {
    return {
      activeTab: this.isMapEnabled ? '#procedureDetailsMap' : '#procedureDetailsDocumentlist',
      focusableElements: [],
      lastFocusedElement: '',
    }
  },

  computed: {
    ...mapState('PublicStatement', [
      'activeActionBoxTab',
      'initForm',
      'statement',
    ]),

    activeStatement () {
      return this.initForm !== JSON.stringify(this.statement)
    },
  },

  methods: {
    ...mapMutations('PublicStatement', ['initialiseStore', 'update', 'updateHighlighted']),

    checkKeyEvent (event) {
      if (this.isFullscreen) {
        // Close modal and return early if escape
        if (event.key === 'Escape') {
          this.toggle()
        } else if (event.key === 'Tab') {
          event.preventDefault()
          const eventTargetIndex = this.focusableElements.findIndex(el => el === event.target)
          const last = this.focusableElements.length - 1

          if (this.focusableElements.length < 2) {
            // Do nothing if only 1 or no elements to focus
          } else if (event.shiftKey === false && event.target === this.focusableElements[last]) {
            // If last element was previously focused, on tab jump to the first element
            this.focusableElements[0].focus()
          } else if (event.shiftKey === true && event.target === this.focusableElements[0]) {
            // If first element was focused, on tab+shift focus the last element
            this.focusableElements[last].focus()
          } else {
            const idxToFocus = event.shiftKey ? eventTargetIndex - 1 : eventTargetIndex + 1

            this.focusableElements[idxToFocus].focus()
          }
        }
      }
    },

    foldOpenToolbarItems (items) {
      items.forEach(item => {
        if (this.slotRefs[item] && typeof this.slotRefs[item].fold === 'function') {
          this.slotRefs[item].fold()
        }
      })
    },

    getFocusableElements () {
      this.focusableElements = [...document.getElementById('procedureDetailsMap').querySelectorAll('a, button:not([disabled]), input, textarea, select, details, [tabindex]:not([tabindex="-1"])')].filter(el => this.isElementVisible(el))
    },

    handleFullscreenFocus (isFullscreen) {
      this.isFullscreen = isFullscreen
      if (isFullscreen) {
        this.lastFocusedElement = document.activeElement
        document.querySelector('html').setAttribute('style', 'overflow: hidden')
        this.$nextTick(() => {
          this.getFocusableElements()
        })
      } else {
        this.lastFocusedElement.focus()
        document.querySelector('html').removeAttribute('style')
      }
    },

    isElementVisible (el) {
      const isInDom = el.offsetParent !== null
      const style = window.getComputedStyle(el)
      const isDisplayed = style.display !== 'none' && style.opacity !== '0'

      return isInDom && isDisplayed
    },

    toggleStatementModal (updateStatementPayload) {
      this.slotRefs.statementModal.toggleModal(true, updateStatementPayload)
    },

    toggleTabs (tabId) {
      this.activeTab = tabId
      history.pushState(null, null, this.activeTab)
    },

    updateStatementAndOpenModal (updateStatementPayload) {
      this.toggleStatementModal(updateStatementPayload)
      // This is doubled to start the green fading and allow to start at change again
      this.updateHighlighted({ key: 'documents', val: false })
      this.updateHighlighted({ key: 'documents', val: true })
    },
  },

  created () {
    this.initialiseStore({ procedureId: this.procedureId, userId: this.userId })
  },

  mounted () {
    const currentHash = window.document.location.hash.split('?')[0]

    if (['#procedureDetailsMap', '#procedureDetailsDocumentlist', '#procedureDetailsStatementsPublic'].includes(currentHash)) {
      this.toggleTabs(currentHash)
    }
  },
}
</script>
