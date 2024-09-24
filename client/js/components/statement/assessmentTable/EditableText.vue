<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- Wrapper component for DpEditor.vue
   - contains save/reset/update/toggleEdit methods
   - use this component if you want to save text directly via the editor/inline-editing (as in assessment table)
   - do not use this component if you save text via a form element (as in new statement view), use only DpEditor instead with hidden input prop (prop value is the hidden input id and name)
   -->
</documentation>

<template>
  <div class="c-edit-field">
    <p class="relative flow-root">
      <span class="weight--bold">
        {{ Translator.trans(title) }}
      </span>

      <slot name="hint" />
    </p>

    <div v-if="isEditing">
      <dp-editor
        ref="editor"
        v-model="fullText"
        class="u-mb-0_5"
        :editor-id="editorId"
        :entity-id="entityId"
        :toolbar-items="{
          insertAndDelete: insertAndDelete,
          linkButton: linkButton,
          mark: mark,
          obscure: obscure,
          strikethrough: strikethrough
        }"
        @transformObscureTag="transformObscureTag">
        <template v-slot:modal="modalProps">
          <dp-boiler-plate-modal
            v-if="boilerPlate"
            ref="boilerPlateModal"
            boiler-plate-type="consideration"
            :editor-id="editorId"
            :procedure-id="procedureId"
            @insert="text => modalProps.handleInsertText(text)" />
        </template>
        <template v-slot:button>
          <button
            v-if="boilerPlate"
            :class="prefixClass('menubar__button')"
            type="button"
            v-tooltip="Translator.trans('boilerplate.insert')"
            @click.stop="openBoilerPlate">
            <i :class="prefixClass('fa fa-puzzle-piece')" />
          </button>
        </template>
      </dp-editor>
      <div class="text-right space-inline-s">
        <dp-button
          :busy="loading"
          data-cy="tipTapSave"
          :text="Translator.trans('save')"
          @click="save" />
        <button
          class="btn btn--secondary"
          type="button"
          @click="reset">
          {{ Translator.trans('reset') }}
        </button>
      </div>
    </div>

    <div
      class="relative u-pr"
      v-else>
      <template v-if="shortText !== ''">
        <height-limit
          class="c-styled-html"
          :short-text="shortText"
          :full-text="fullText"
          :element="editLabel"
          :is-shortened="isShortened"
          @heightLimit:toggle="update"
          @click.native="toggleEditMode" />
        <button
          type="button"
          :disabled="!editable"
          class="c-edit-field__trigger btn--blank o-link--default"
          v-if="!loading"
          @click.prevent.stop="toggleEditMode"
          :title="Translator.trans(editable ? editLabel : 'locked.title')"
          data-cy="toggleTipTapEditMode">
          <i
            class="fa fa-pencil"
            aria-hidden="true" />
        </button>
      </template>
      <button
        v-else
        type="button"
        :disabled="!editable"
        class="btn--blank o-link--default"
        :title="Translator.trans(editable ? editLabel : 'locked.title')"
        @click="toggleEditMode">
        {{ Translator.trans('author.verb') }}
      </button>
      <dp-loading
        v-if="loading"
        class="c-edit-field__trigger"
        hide-label />
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton, DpLoading, hasOwnProp, prefixClassMixin } from '@demos-europe/demosplan-ui'
import { Base64 } from 'js-base64'
import DpBoilerPlateModal from '@DpJs/components/statement/DpBoilerPlateModal'
import HeightLimit from '@DpJs/components/statement/HeightLimit'

export default {
  name: 'EditableText',

  components: {
    DpBoilerPlateModal,
    DpButton,
    HeightLimit,
    DpLoading,
    DpEditor: async () => {
      const { DpEditor } = await import('@demos-europe/demosplan-ui')
      return DpEditor
    }
  },

  mixins: [prefixClassMixin],

  props: {
    // Set to true if you want to enable the 'add boilerplate' button
    boilerPlate: {
      type: Boolean,
      required: false,
      default: false
    },

    editable: {
      required: false,
      type: Boolean,
      default: true
    },

    editLabel: {
      type: String,
      required: true
    },

    // Needed to identify editor, used for inserting boilerplates into correct editor
    editorId: {
      type: String,
      required: false,
      default: ''
    },

    entityId: {
      type: String,
      required: true
    },

    fieldKey: {
      type: String,
      required: true
    },

    fullTextFetchRoute: {
      type: String,
      required: true
    },

    heightLimitElementLabel: {
      type: String,
      required: true
    },

    initialIsShortened: {
      type: Boolean,
      required: true
    },

    initialText: {
      type: String,
      required: true
    },

    // Set to true if you want to use the 'insert/delete' buttons
    insertAndDelete: {
      type: Boolean,
      required: false,
      default: false
    },

    // Set to true if you want to use 'link' buttons
    linkButton: {
      type: Boolean,
      required: false,
      default: false
    },

    // Set to true if you want to use the 'mark' button
    mark: {
      type: Boolean,
      required: false,
      default: false
    },

    // Set to true if you want to use the 'obscure text' button
    obscure: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      required: true,
      type: String
    },

    // Set to true if you want to enable a line through (strike through) option
    strikethrough: {
      type: Boolean,
      required: false,
      default: false
    },

    title: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      fullText: '',
      fullTextLoaded: false,
      isEditing: false,
      isInitialUpdate: true,
      isShortened: false,
      loading: false,
      shortText: '',
      transformedText: '',
      uneditedFullText: ''
    }
  },

  methods: {
    openBoilerPlate () {
      if (hasPermission('area_admin_boilerplates')) {
        this.$refs.boilerPlateModal.toggleModal()
      }
    },

    reset () {
      this.fullText = this.uneditedFullText
      this.isEditing = false
      this.loading = false
    },

    save () {
      /** transformedText contains the text with the obscure tag applied.
       * To avoid the cursor jumping to the end, we update the fullText with transformedText only when the save action is triggered.
       * */
      if (this.transformedText && this.transformedText !== this.fullText) {
        this.fullText = this.transformedText
      }

      // If there are no changes, no need to save something.
      if (this.uneditedFullText === this.fullText) {
        this.isEditing = false
        return
      }

      const emitData = {
        id: this.entityId
      }

      if (this.fullText === '' && this.fieldKey === 'text') {
        return dplan.notify.notify('error', Translator.trans('error.statement.empty'))
      }

      emitData[this.fieldKey] = this.fullText

      this.loading = true
      this.$emit('field:save', emitData)

      this.fullTextLoaded = false
    },

    transformObscureTag (value) {
      this.transformedText = value
    },

    toggleEditMode () {
      if (this.editable === false) {
        return
      }
      if (!this.isEditing && this.fullTextFetchRoute !== '') {
        this.update(() => {
          this.isEditing = true
        })
      } else if (!this.editing && this.fullTextFetchRoute === '' && this.isShortened === false) {
        this.isEditing = true
      }
    },

    /**
     * @param callback function will be called after load completion
     * @param fullUpdate boolean if true, the short text will also be updated from the server
     */
    update (callback, fullUpdate = false) {
      if (typeof callback !== 'function') {
        console.error('Did not pass function as callback', callback)
        callback = () => {
          // Define fallback for callback to be able to always execute it onFulfilled
        }
      }

      if (this.fullTextLoaded) {
        callback()
        return
      }

      this.loading = true

      const params = (fullUpdate) ? { includeShortened: true } : {}

      /*
       * @TODO: This Request can be faster than the Update of the Statement which gets requested here. In that case the
       *   receiving Data is not updated yet.
       * @IMPROVE T15529: Refactor the Handling of the Text. Get it straight from the statement so it's not necessary to
       * use Ajax here. passing it as prop from the outer component or getting it from the store as reactive should do a
       * better job
       *
       */
      dpApi.get(
        Routing.generate(this.fullTextFetchRoute, { statementId: this.entityId }),
        params,
        { serialize: true }
      ).then(response => {
        this.fullTextLoaded = true

        // Check if it is the first update
        if (this.isInitialUpdate) {
          this.uneditedFullText = response.data.data.original
          this.isInitialUpdate = false
        } else {
          this.uneditedFullText = this.fullText
        }

        // As far as i get it, this should always be the same if the update succeeds ?!?
        if (hasOwnProp(response.data.data, 'original')) {
          this.fullText = response.data.data.original
        }

        if (fullUpdate && hasOwnProp(response.data.data, 'shortened')) {
          this.shortText = response.data.data.shortened
        }

        if (hasOwnProp(response.data.data, 'shortened')) {
          this.isShortened = this.fullText.length > this.shortText.length
        }

        this.loading = false
      }, () => {
        dplan.notify.error(Translator.trans('error.api.generic'))
        this.loading = false
      }).then(callback)
    }
  },

  mounted () {
    this.isShortened = this.initialIsShortened
    /*
     * Set initial text values
     * initially the text is always the shortened Version so we don't get the full text from BE unless necessary
     */
    this.shortText = Base64.decode(this.initialText)

    if (this.initialIsShortened === false) {
      this.fullText = this.shortText
      this.fullTextLoaded = true
    }
    this.uneditedFullText = this.shortText

    // Update texts after entity save
    this.$root.$on('entityTextSaved:' + this.entityId, (updatedData) => {
      if (updatedData.entityId === this.entityId && updatedData.field === this.fieldKey) {
        if (this.fullTextFetchRoute !== '') {
          this.update(() => {
            this.isEditing = false
          }, true)
        } else if (this.fullTextFetchRoute === '' && this.isShortened === false) {
          this.shortText = this.fullText
        }
      }
    })
  }
}
</script>
