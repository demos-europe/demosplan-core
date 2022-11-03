<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!-- DpEditor component
      - contains menubar with a number of buttons and an editor
      - use this component without the inline-editing-wrapper TiptapEditText.vue if you want to add a text editor to a form element (as in new statement view)
      - use this component with the inline-editing-wrapper TiptapEditText.vue if you want to save the text directly via inline-editing (as in assessment table)
      - the editor-content component needs a prop with editor instance to work correctly

      To properly set the content we have to update this.currentValue and use editor.setContent() - both actions are needed!
      To use boilerplates we need to mount tiptap editor, where boilerplate modal is imported dynamically. The boilerplates are stored in boilerplates vuex store, which is also registered dynamically if boilerplate prop is specified.

      Possible component props:

      boilerPlate - add it if you want to have boilerplate button in editor menu. Prop value should be a string with boilerplate category (email/consideration/news.notes): boiler-plate="email"
      editorId - to identify it in boilerplates modal
      procedureId
      hiddenInput - to send data with submit form action we sometimes need to have a hidden input with tiptap's content. If the hidden input should be added, the prop should be a string with input name (e.g. r_name),
      obscure - set to true if you want to use the 'obscure text' button. if the permission is not activated the button will not be shown anyay
      value - initial editor's value
      required - determine if hidden input is required, used in with dp-validate-plugin
      linkButton - define if a button to add links should be visible in menu
      readonly - true/false
      headings - determine which heading level (h1-h6) buttons should be visible in menu. It is an array with numbers , e.g. [1,2,3,4,5,6]
      table - true/false - if tables should be supported and buttons for inserting tables should be added this prop has to be true

     To use tiptap import the component dynamically: components = { DpEditor: () => import('@DpJs/components/core/DpEditor/DpEditor') } }

   -->
</documentation>

<template>
  <div class="o-form__control-tiptap">
    <div
      v-if="maxlength !== 0"
      :class="prefixClass('lbl__hint')"
      v-cleanhtml="counterText" />
    <dp-boiler-plate-modal
      v-if="toolbar.boilerPlate && boilerPlateEnabled"
      ref="boilerPlateModal"
      :editor-id="editorId"
      :procedure-id="procedureId"
      :boiler-plate-type="toolbar.boilerPlate"
      @insertBoilerPlate="text => handleInsertText(text)" />
    <dp-link-modal
      v-if="toolbar.linkButton"
      ref="linkModal"
      @insert="insertUrl" />
    <dp-upload-modal
      v-if="toolbar.imageButton"
      ref="uploadModal"
      @insert-image="insertImage"
      @add-alt="addAltTextToImage"
      @close="resetEditingImage" />
    <dp-recommendation-modal
      v-if="toolbar.recommendationButton"
      ref="recommendationModal"
      @insert-recommendation="text => appendText(text)"
      :procedure-id="procedureId"
      :segment-id="segmentId" />
    <div :class="prefixClass('row tiptap')">
      <div :class="prefixClass('col')">
        <div
          :class="[isFullscreen ? 'fullscreen': '', prefixClass('editor')]">
          <editor-menu-bar :editor="editor">
            <div
              slot-scope="{ commands, isActive, getMarkAttrs }"
              :class="[readonly ? prefixClass('readonly'): '', prefixClass('menubar')]">
              <!-- Cut -->
              <button
                @click="cut"
                :class="prefixClass('menubar__button')"
                type="button"
                :aria-label="Translator.trans('editor.cut')"
                v-tooltip="Translator.trans('editor.cut')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-scissors')"
                  aria-hidden="true" />
              </button>
              &#10072;
              <!-- Undo -->
              <button
                @click="commands.undo"
                :class="prefixClass('menubar__button')"
                type="button"
                :aria-label="Translator.trans('editor.undo')"
                v-tooltip="Translator.trans('editor.undo')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-reply')"
                  aria-hidden="true" />
              </button>
              <!-- Redo -->
              <button
                @click="commands.redo"
                :class="prefixClass('menubar__button')"
                type="button"
                :aria-label="Translator.trans('editor.redo')"
                v-tooltip="Translator.trans('editor.redo')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-share')"
                  aria-hidden="true" />
              </button>
              <template v-if="toolbar.textDecoration">
                &#10072;
                <!-- Bold -->

                <button
                  @click="commands.bold"
                  :class="[isActive.bold() ? prefixClass('is-active'): '', prefixClass('menubar__button')]"
                  type="button"
                  :aria-label="Translator.trans('editor.bold')"
                  v-tooltip="Translator.trans('editor.bold')"
                  :disabled="readonly">
                  <i
                    :class="prefixClass('fa fa-bold')"
                    aria-hidden="true" />
                </button>

                <!-- Italic -->
                <button
                  @click="commands.italic"
                  :class="[isActive.italic() ? prefixClass('is-active') : '', prefixClass('menubar__button') ]"
                  type="button"
                  :aria-label="Translator.trans('editor.italic')"
                  v-tooltip="Translator.trans('editor.italic')"
                  :disabled="readonly">
                  <i
                    :class="prefixClass('fa fa-italic')"
                    aria-hidden="true" />
                </button>
                <!-- Underline -->
                <button
                  @click="commands.underline"
                  :class="[isActive.underline() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  type="button"
                  :aria-label="Translator.trans('editor.underline')"
                  v-tooltip="Translator.trans('editor.underline')"
                  :disabled="readonly">
                  <i
                    :class="prefixClass('fa fa-underline')"
                    aria-hidden="true" />
                </button>
              </template>
              <!-- Strike through -->
              <button
                v-if="toolbar.strikethrough"
                @click="commands.strike"
                :class="[isActive.strike() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                type="button"
                :aria-label="Translator.trans('editor.strikethrough')"
                v-tooltip="Translator.trans('editor.strikethrough')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-strikethrough')"
                  aria-hidden="true" />
              </button>
              <div
                v-if="toolbar.insertAndDelete"
                :class="prefixClass('display--inline-block position--relative')">
                <button
                  :class="[isActive.insert() || isActive.delete() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  type="button"
                  @click.stop="toggleSubMenu('diffMenu', !diffMenu.isOpen)"
                  @keydown.tab.shift.exact="toggleSubMenu('diffMenu', false)"
                  :disabled="readonly">
                  <dp-icon
                    class="u-valign--text-top"
                    icon="highlighter" />
                  <i :class="prefixClass('fa fa-caret-down')" />
                </button>
                <div
                  v-if="diffMenu.isOpen"
                  :class="prefixClass('button_submenu')">
                  <button
                    v-for="(button, idx) in diffMenu.buttons"
                    :key="`diffMenu_${idx}`"
                    :class="{ 'is-active': isActive[button.name]() }"
                    type="button"
                    :disabled="readonly"
                    @keydown.tab.exact="() => { idx === diffMenu.buttons.length -1 ? toggleSubMenu('diffMenu', false) : null }"
                    @keydown.tab.shift.exact="() => { idx === 0 ? toggleSubMenu('diffMenu', false) : null }"
                    @click.stop="executeSubMenuButtonAction(button, 'diffMenu', true)">
                    {{ Translator.trans(button.label) }}
                  </button>
                </div>
                &#10072;
              </div>
              <div
                v-else-if="toolbar.mark /* display the Button without fold out, if ony 'mark' is enabled */"
                :class="prefixClass('display--inline-block position--relative')">
                <button
                  v-for="(button, idx) in diffMenu.buttons"
                  :key="`diffMenu_${idx}`"
                  :class="[isActive[button.name]() ? prefixClass('is-active') : '' , prefixClass('menubar__button')]"
                  type="button"
                  :disabled="readonly"
                  :aria-label="Translator.trans(button.label)"
                  v-tooltip="Translator.trans(button.label)"
                  @keydown.tab.exact="() => { idx === diffMenu.buttons.length -1 ? toggleSubMenu('diffMenu', false) : null }"
                  @keydown.tab.shift.exact="() => { idx === 0 ? toggleSubMenu('diffMenu', false) : null }"
                  @click.stop="executeSubMenuButtonAction(button, 'diffMenu', true)">
                  <dp-icon
                    class="u-valign--text-top"
                    icon="highlighter" />
                </button>
              </div>
              <!-- lists -->
              <template v-if="toolbar.listButtons">
                <!-- Unordered List -->
                <button
                  @click="commands.bullet_list"
                  :class="[isActive.bullet_list() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  type="button"
                  :aria-label="Translator.trans('editor.unordered.list')"
                  v-tooltip="Translator.trans('editor.unordered.list')"
                  :disabled="readonly">
                  <i :class="prefixClass('fa fa-list-ul')" />
                </button>
                <!-- Ordered List -->
                <button
                  @click="commands.ordered_list"
                  :class="[isActive.ordered_list() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  type="button"
                  :aria-label="Translator.trans('editor.ordered.list')"
                  v-tooltip="Translator.trans('editor.ordered.list')"
                  :disabled="readonly">
                  <i :class="prefixClass('fa fa-list-ol')" />
                </button>
                &#10072;
              </template>
              <!--Heading Buttons - for each heading level in props a button will be rendered. We want to keep it
              flexible because the user should not always be able to define e.g. H1. It depends where the text should
              appear.-->
              <template v-if="toolbar.headings.length > 0">
                <button
                  v-for="heading in toolbar.headings"
                  :key="'heading_' + heading"
                  type="button"
                  :class="[isActive.heading({ level: heading }) ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  @click="commands.heading({ level: heading })"
                  v-tooltip="Translator.trans('editor.heading.level', {level: heading})"
                  :disabled="readonly">
                  {{ `H${heading}` }}
                </button>
                &#10072;
              </template>
              <!-- Obscure text -->
              <button
                v-if="obscureEnabled"
                @click="commands.obscure"
                :class="[isActive.obscure() ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                type="button"
                v-tooltip="Translator.trans('obscure.title')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-pencil-square')"
                  aria-hidden="true" />
              </button>
              <!--Add links-->
              <button
                v-if="toolbar.linkButton"
                @click.stop="showLinkPrompt(commands.link, getMarkAttrs('link'))"
                :class="prefixClass('menubar__button')"
                type="button"
                v-tooltip="Translator.trans('editor.link.edit.insert')">
                <i
                  :class="prefixClass('fa fa-link')" />
              </button>
              <!-- Add Boilerplate -->
              <button
                v-if="boilerPlateEnabled"
                @click.stop="openBoilerPlateModal"
                :class="prefixClass('menubar__button')"
                type="button"
                v-tooltip="Translator.trans('boilerplate.insert')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-puzzle-piece')" />
              </button>
              <!-- Insert related recommendations -->
              <button
                v-if="toolbar.recommendationButton"
                @click.stop="openRecommendationModal"
                :class="prefixClass('menubar__button')"
                v-tooltip="Translator.trans('segment.recommendation.insert.similar')"
                type="button">
                <i :class="prefixClass('fa fa-lightbulb-o')" />
              </button>
              <!-- Insert images-->
              <button
                v-if="toolbar.imageButton"
                @click.stop="openUploadModal(null)"
                :class="prefixClass('menubar__button')"
                type="button"
                v-tooltip="Translator.trans('image.insert')"
                :disabled="readonly">
                <i
                  :class="prefixClass('fa fa-picture-o')" />
              </button>
              <!-- Insert and edit tables -->
              <div
                v-if="toolbar.table"
                :class="prefixClass('display--inline-block position--relative')">
                <button
                  :class="[tableMenu.isOpen ? prefixClass('is-active') : '', prefixClass('menubar__button')]"
                  type="button"
                  @click.stop="toggleSubMenu('tableMenu', !tableMenu.isOpen)"
                  @keydown.tab.shift.exact="toggleSubMenu('tableMenu', false)"
                  :disabled="readonly">
                  <i :class="prefixClass('fa fa-table')" />
                  <i :class="prefixClass('fa fa-caret-down')" />
                </button>
                <div
                  v-if="tableMenu.isOpen"
                  :class="prefixClass('button_submenu')">
                  <button
                    v-for="(button, idx) in tableMenu.buttons"
                    :key="`tableMenu_${idx}`"
                    type="button"
                    :disabled="readonly"
                    @keydown.tab.exact="() => { idx === tableMenu.buttons.length -1 ? toggleSubMenu('tableMenu', false) : null }"
                    @keydown.tab.shift.exact="() => { idx === 0 ? toggleSubMenu('tableMenu', false) : null }"
                    @click.stop="executeSubMenuButtonAction(button, 'tableMenu')">
                    {{ Translator.trans(button.label) }}
                  </button>
                </div>
              </div>
              <!-- Fullscreen -->
              <button
                v-if="toolbar.fullscreenButton"
                @click="fullscreen"
                :class="[isFullscreen ? prefixClass('is-active') : '', prefixClass('menubar__button float--right')]"
                type="button"
                :aria-label="Translator.trans('editor.fullscreen')"
                v-tooltip="Translator.trans('editor.fullscreen')">
                <i
                  :class="prefixClass('fa fa-arrows-alt')"
                  aria-hidden="true" />
              </button>
            </div>
          </editor-menu-bar>
          <editor-content
            v-if="editor"
            :data-cy="`editor${editorId}`"
            :editor="editor"
            :class="prefixClass('editor__content overflow-hidden')" />
          <!-- this hidden input is needed if we use this component without the inline-editing-wrapper TiptapEditText.vue,
          so we can save the text entered in the textarea via a form element -->
          <input
            v-if="hiddenInput !== ''"
            :data-dp-validate-if="dataDpValidateIf || false"
            type="hidden"
            :id="hiddenInput"
            :name="hiddenInput"
            :class="[required ? prefixClass('is-required') : '', prefixClass('tiptap__input--hidden')]"
            :data-dp-validate-maxlength="maxlength"
            :value="hiddenInputValue">
          <i
            v-if="!isFullscreen"
            aria-hidden="true"
            :class="prefixClass('fa fa-angle-down resizeVertical')"
            @mousedown="resizeVertically"
            draggable="true" />
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import {
  Bold,
  BulletList,
  HardBreak,
  Heading,
  History,
  Italic,
  Link,
  ListItem,
  OrderedList,
  Strike,
  Table,
  TableCell,
  TableHeader,
  TableRow,
  Underline
} from 'tiptap-extensions'

import {
  Editor, // Wrapper for prosemirror state
  EditorContent, // Renderless content element
  EditorMenuBar // Renderless menubar
} from 'tiptap'

import { CleanHtml } from 'demosplan-ui/directives'
import { createSuggestion } from './libs/editorBuildSuggestion'
import { DpIcon } from 'demosplan-ui/components'
import EditorCustomDelete from './libs/editorCustomDelete'
import EditorCustomImage from './libs/editorCustomImage'
import EditorCustomInsert from './libs/editorCustomInsert'
import EditorCustomLink from './libs/editorCustomLink'
import EditorCustomMark from './libs/editorCustomMark'
import EditorInsertAtCursorPos from './libs/editorInsertAtCursorPos'
import EditorObscure from './libs/editorObscure'
import { handleWordPaste } from './libs/handleWordPaste'
import { maxlengthHint } from 'demosplan-ui/utils/lengthHint'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpEditor',

  components: {
    DpIcon,
    EditorMenuBar,
    EditorContent,
    DpBoilerPlateModal: () => import('./DpBoilerPlateModal'),
    DpLinkModal: () => import('./DpLinkModal'),
    DpRecommendationModal: () => import('./DpRecommendationModal'),
    DpUploadModal: () => import('./DpUploadModal')
  },

  directives: {
    cleanhtml: CleanHtml
  },

  mixins: [prefixClassMixin],

  props: {
    /**
     * Defines which boilerplate types we want to see in modal. Possible are: consideration, email, news.notes
     * if this property is set, the boilerPlate button appears in the tiptap editor
     * @deprecated use toolbarItems instead
     */
    boilerPlate: {
      type: [String, Array],
      default: '',
      required: false
    },

    /**
     * Needed to get the correct textarea for adding boilerplates via DpBoilerPlateModal.vue
     */
    editorId: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Array with numbers 1-6 defining which heading-buttons we want to show
     *
     * @deprecated use toolbarItems instead
     */
    headings: {
      required: false,
      type: Array,
      default: () => []
    },

    /**
     * To send data with submit form action we sometimes need to have a hidden input with tiptap's content. If the
     * hidden input should be added, the prop should be a string with input name (e.g. r_name)
     */
    hiddenInput: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * If true, the button to add images will be shown and the initial text will be scanned for img placeholders which will be then replaced by actual images.
     * Inserted pictures will also be converted to placeholders on save.
     *
     * @deprecated use toolbarItems instead
     */
    imageButton: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Enables menu buttons to mark text as deleted and inserted.
     * The buttons will wrap the current text selection with a `del` or `ins` element,
     * enabling users to indicate content changes in relation to a prior content version.
     * This feature is currently only used for planning document paragraphs.
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/del
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/ins
     *
     * @deprecated use toolbarItems instead
     */
    insertAndDelete: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * @deprecated use toolbarItems instead
     */
    fullscreenButton: {
      type: Boolean,
      required: false,
      default: true
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Define if a button to add links should be visible in menu
     *
     * @deprecated use toolbarItems instead
     */
    linkButton: {
      required: false,
      type: Boolean,
      default: false
    },

    /**
     * Define if a button to add ordered/unordered list should be visible in menu
     *
     * @deprecated use toolbarItems instead
     */
    listButtons: {
      required: false,
      type: Boolean,
      default: true
    },

    /**
     * Enables a menu button to highlight/mark text.
     * This will wrap the current text selection with a `mark` element,
     * enabling users to enrich content with a semantic element to highlight text.
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/mark
     *
     * @deprecated use toolbarItems instead
     */
    mark: {
      required: false,
      type: Boolean,
      default: false
    },

    /**
     * Defaults will be set in this.menu:
     * {
     *    boilerPlate: '', # [] || 'string'
     *    headings: [], # Array of numbers 1-6
     *    imageButton: false,
     *    insertAndDelete: false,
     *    fullscreenButton: true,
     *    linkButton: false,
     *    listButtons: true,
     *    mark: false,
     *    recommendationButton: false,
     *    strikethrough: false,
     *    table: false,
     *    textDecoration: true
     * }
     *
     * and can be overwritten
     */
    toolbarItems: {
      required: false,
      type: Object,
      default: () => ({})
    },

    maxlength: {
      type: [Number, null],
      default: null
    },

    /**
     * Set to true if you want to use the 'obscure text' button
     */
    obscure: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * ProcedureId is required if we want to enable boilerplates
     */
    procedureId: {
      type: String,
      required: false,
      default: ''
    },

    readonly: {
      required: false,
      default: false,
      type: Boolean
    },

    recommendationButton: {
      required: false,
      type: Boolean,
      default: false
    },

    segmentId: {
      type: String,
      required: false,
      default: ''
    },

    /**
     * Enables a menu button to strike out text.
     * This will wrap the current text selection with a `s` element, enabling users
     * to enrich content with a semantic element to mark text as no longer relevant.
     * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Element/s
     * @deprecated use toolbarItems instead
     */
    strikethrough: {
      required: false,
      type: Boolean,
      default: false
    },

    /**
     * Pass in an Array of suggestions if you would like to use the suggestion plugin in tiptap.
     */
    suggestions: {
      type: Array,
      validator: (value) => {
        const suggestionGroupSchema = {
          matcher: {
            char: '@|$|#', // A single char that should trigger a suggestion
            allowSpaces: true || false,
            startOfLine: true || false
          },
          suggestions: [{ id: 'a unique id', name: 'a string that should be displayed when inserting the suggestion' }]
        }
        return Array.isArray(value) && value.filter(suggestionGroup => {
          let isValid = suggestionGroup.matcher && suggestionGroup.suggestions
          isValid = isValid && typeof suggestionGroup.matcher.char === typeof suggestionGroupSchema.matcher.char
          isValid = isValid && typeof suggestionGroup.matcher.allowSpaces === typeof suggestionGroupSchema.matcher.allowSpaces
          isValid = isValid && typeof suggestionGroup.matcher.startOfLine === typeof suggestionGroupSchema.matcher.startOfLine
          isValid = isValid && suggestionGroup.suggestions.filter(suggestion => {
            return typeof suggestion.id === typeof suggestionGroupSchema.suggestions[0].id && typeof suggestion.name === typeof suggestionGroupSchema.suggestions[0].name
          }).length === suggestionGroup.suggestions.length
          return isValid
        }).length === value.length
      },
      required: false,
      default: () => ([])
    },

    /**
     * Set to true if you want table-insert button
     *
     * @deprecated use toolbarItems instead
     */
    table: {
      required: false,
      type: Boolean,
      default: false
    },

    /**
     * @deprecated use toolbarItems instead
     */
    textDecoration: {
      type: Boolean,
      required: false,
      default: true
    },

    value: {
      type: String,
      required: true
    },

    dataDpValidateIf: {
      type: String,
      default: '',
      required: false
    }
  },

  data () {
    return {
      currentValue: '',
      diffMenu: {
        isOpen: false,
        buttons: []
      },
      editingImage: null,
      editor: null,
      editorHeight: '',
      isDiffMenuOpen: false,
      isFullscreen: false,
      isTableMenuOpen: false,
      linkUrl: '',
      // We have to check if we have a hidden input and a form, then we have to update the field manually. For Api-requests its not neccessary
      manuallyResetForm: true,
      tableMenu: {
        isOpen: false,
        buttons: [
          {
            label: 'editor.table.create',
            command: (commands) => commands.createTable({ rowsCount: 3, colsCount: 3, withHeaderRow: false }),
            name: 'createTable'
          },
          {
            label: 'editor.table.delete',
            command: (commands) => commands.deleteTable(),
            name: 'deleteTable'
          },
          {
            label: 'editor.table.addColumnBefore',
            command: (commands) => commands.addColumnBefore(),
            name: 'addColumnBefore'
          },
          {
            label: 'editor.table.addColumnAfter',
            command: (commands) => commands.addColumnAfter(),
            name: 'addColumnAfter'

          },
          {
            label: 'editor.table.deleteColumn',
            command: (commands) => commands.deleteColumn(),
            name: 'deleteColumn'
          },
          {
            label: 'editor.table.addRowBefore',
            command: (commands) => commands.addRowBefore(),
            name: 'addRowBefore'
          },
          {
            label: 'editor.table.addRowAfter',
            command: (commands) => commands.addRowAfter(),
            name: 'addRowAfter'
          },
          {
            label: 'editor.table.deleteRow',
            command: (commands) => commands.deleteRow(),
            name: 'deleteRow'
          },
          {
            label: 'editor.table.toggleCellMerge',
            command: (commands) => commands.toggleCellMerge(),
            name: 'toggleCellMerge'
          }
        ]
      },
      toolbar: Object.assign({
        boilerPlate: this.boilerPlate,
        headings: this.headings,
        imageButton: this.imageButton,
        insertAndDelete: this.insertAndDelete,
        fullscreenButton: this.fullscreenButton,
        linkButton: this.linkButton,
        listButtons: this.listButtons,
        mark: this.mark,
        strikethrough: this.strikethrough,
        table: this.table,
        textDecoration: this.textDecoration
      }, this.toolbarItems)
    }
  },

  computed: {
    boilerPlateEnabled () {
      return hasPermission('area_admin_boilerplates') && Boolean(this.toolbar.boilerPlate)
    },

    counterText () {
      return maxlengthHint(this.hiddenInputValue.length, this.maxlength)
    },

    hiddenInputValue () {
      // The blank tiptap editor still contains an empty p element, which shall not be passed into hidden input.
      return (this.currentValue.replace('<p></p>', '') === '') ? '' : this.currentValue
    },

    obscureEnabled () {
      return hasPermission('feature_obscure_text') && this.toolbar.obscure
    }
  },

  watch: {
    value (newValue) {
      if (!this.editor.focused) {
        this.currentValue = newValue
        this.editor.setContent(newValue, false)
      }
    },

    /**
     * The readonly watcher provides the dynamic enabling/disabling of the editor.
     * Also mentioned in the GitHub issue: https://github.com/ueberdosis/tiptap/issues/111
     */
    readonly () {
      this.editor.setOptions({ editable: !this.readonly })
    }
  },

  methods: {
    addAltTextToImage (text) {
      this.$root.$emit('update-image:' + this.editingImage, { alt: text })
      this.resetEditingImage()
      this.setValue()
    },

    appendText (text) {
      let newText

      // Check if any of the two texts is wrapped in a 'p' tag to avoid inserting too many newlines
      const isAnyNodeBlock = this.startsWithTag(this.currentValue, 'p') || this.startsWithTag(text, 'p')

      // If editor is empty, insert only text; if editor contains text, insert empty paragraph + text
      if (this.currentValue === 'k.A.' || this.currentValue === '') {
        newText = text
      } else if (this.currentValue !== 'k.A' && this.currentValue !== '' && isAnyNodeBlock) {
        newText = this.currentValue + text
      } else if (this.currentValue !== 'k.A' && this.currentValue !== '') {
        newText = this.currentValue + '<br>' + text
      }

      this.editor.setContent(newText)
      this.currentValue = newText
      this.$emit('input', this.currentValue)
    },

    cut () {
      document.execCommand('cut')
    },

    fullscreen (e) {
      const editor = e.target.parentElement.parentElement.parentElement.querySelector('.tiptap .editor__content')
      if (this.isFullscreen === false && editor.hasAttribute('style')) {
        this.editorHeight = editor.getAttribute('style')
        editor.removeAttribute('style')
      }

      this.isFullscreen = !this.isFullscreen

      if (this.isFullscreen === false && this.editorHeight !== '') {
        editor.setAttribute('style', this.editorHeight)
      }
    },

    handleInsertText (text) {
      text = text.replace(/\n/g, '<br>')

      // If user hasn't clicked into tiptap editor yet
      if (this.editor.view.input.lastClick.x === 0 && this.editor.view.input.lastClick.y === 0) {
        this.appendText(text)
      } else { // If user has clicked into tiptap editor at some point, but editor may currently not have focus
        this.insertTextAtCursorPos(text)
      }
    },

    insertTextAtCursorPos (text) {
      // Remove p tags so text is inserted without adding new paragraph
      if (this.startsWithTag(text, 'p')) {
        text = text.slice(3, -4)
      }

      this.editor.commands.insertHTML(text)
      this.currentValue = this.editor.getHTML()
    },

    startsWithTag (htmlString, tag) {
      const el = document.createElement('div')
      el.innerHTML = htmlString
      const firstChild = el.firstChild && el.firstChild.nodeName
      return firstChild === tag.toUpperCase()
    },

    insertImage (url, alt) {
      this.editor.commands.insertImage({ src: url, alt })
    },

    insertUrl (linkUrl, newTab, linkText) {
      if (linkUrl === null) {
        this.editor.commands.link({ href: null, ...(newTab && { target: '_blank' }) })
        return
      }

      if (linkUrl !== '' && linkText !== '') {
        const newNode = this.editor.schema.text(linkText, [this.editor.schema.marks.link.create({ href: linkUrl, ...(newTab && { target: '_blank' }) })])
        this.editor.view.dispatch(this.editor.state.tr.replaceSelectionWith(newNode, false))
      }
    },

    getLinkMark (node) {
      const linkMark = node.marks && node.marks.find(mark => mark.type.name === 'link')

      return linkMark
    },

    openBoilerPlateModal () {
      this.$refs.boilerPlateModal.toggleModal()
    },

    openRecommendationModal () {
      this.$refs.recommendationModal.toggleModal('open')
    },

    openUploadModal (data) {
      this.$refs.uploadModal.toggleModal(data)
    },

    prepareInitText () {
      this.currentValue = this.replaceLinebreaks(this.currentValue)
      this.currentValue = this.replacePlaceholdersWithImages(this.currentValue)
    },

    replaceLinebreaks (text) {
      let returnText = text
      returnText = returnText.replace(/<\/p>[\n|\r|\s|\\n|\\r]*?<p>/g, '</p><p>')
      returnText = returnText.replace(/<ul>[\n|\r|\s|\\n|\\r]*?<li>/g, '<ul><li>')
      return returnText.replace(/<\/li>[\n|\r|\s|\\n|\\r]*?<li>/g, '</li><li>')
    },

    replacePlaceholdersWithImages (text = this.currentValue) {
      const placeholder = Translator.trans('image.placeholder')
      const placeholderText = placeholder.startsWith('[') && placeholder.endsWith(']') ? placeholder.slice(1, -1) : placeholder
      const regex = new RegExp(`(\\[${placeholderText}\\].*?-->)`, 'gm')
      try {
        return text.replace(regex, (match, p1) => {
          const altText = p1.match(/{([^}]*?)}/)[1] === Translator.trans('image.alt.placeholder') ? '' : p1.match(/{([^}]*?)}/)[1]
          const placeholder = p1.match(/<!-- (.*?) -->/)[1]
          const imageHash = placeholder.substr(7, 36)
          const imageWidth = placeholder.match(/width=(\d*?)&/)[1]
          const imageHeight = placeholder.match(/height=(\d*?)$/)[1]
          return `<img src="${Routing.generate('core_file', { hash: imageHash })}" width="${imageWidth}" height="${imageHeight}" alt="${altText}">`
        })
      } catch (e) {
        return text
      }
    },

    resetEditingImage () {
      this.editingImage = null
    },

    resizeVertically (e) {
      const editor = e.target.parentElement.querySelector('.tiptap .editor__content')

      e.preventDefault()
      const originalHeight = parseFloat(getComputedStyle(editor, null).getPropertyValue('height').replace('px', ''))
      const originalMouseY = e.pageY
      window.addEventListener('mousemove', resize)
      window.addEventListener('mouseup', stopResize)

      function resize (e) {
        const height = originalHeight + (e.pageY - originalMouseY)
        editor.style.height = height + 'px'
      }

      function stopResize () {
        window.removeEventListener('mousemove', resize)
      }
    },

    resetEditor () {
      this.editor.setContent('')
    },

    setValue () {
      this.currentValue = this.editor.getHTML()
      const regex = new RegExp('<span class="' + this.prefixClass('u-obscure') + '">(.*?)<\\/span>', 'g')
      this.currentValue = this.currentValue.replace(regex, '<dp-obscure>$1</dp-obscure>')
      const isEmpty = (this.currentValue.split('<p>').join('').split('</p>').join('').trim()) === ''
      this.$emit('input', isEmpty ? '' : this.currentValue)
    },

    setSelectionByEditor (nodeBefore, nodeAfter, attrs) {
      const tr = this.editor.view.state.tr

      if (nodeBefore) {
        const linkMark = this.getLinkMark(nodeBefore)
        if (linkMark && linkMark.attrs.href === attrs.href) {
          this.editor.setSelection((tr.selection.anchor - tr.selection.$anchor.nodeBefore.nodeSize), tr.selection.anchor)
        }
      }

      if (nodeAfter) {
        const linkMark = this.getLinkMark(nodeAfter)
        if (linkMark && linkMark.attrs.href === attrs.href) {
          this.editor.setSelection(tr.selection.anchor, (tr.selection.anchor + tr.selection.$anchor.nodeAfter.nodeSize))
        }
      }
    },

    showLinkPrompt (command, attrs) {
      this.linkUrl = attrs.href ? attrs.href : ''
      const selection = this.editor.view.state.tr.selection

      if (attrs.href) {
        // If only a part of existing link text is selected, we want to add the rest of the link to the selection so that the user edits the whole link and not only part of it. To do that we take node before and after selection and check if the href attribute of these nodes is the same as href of the user's selection.
        const selectToLeft = selection.anchor > selection.head

        const selectionBeginning = selectToLeft ? '$head' : '$anchor'
        const selectionEnd = selectToLeft ? '$anchor' : '$head'

        const nodeBefore = selection[selectionBeginning].nodeBefore
        const nodeAfter = selection[selectionEnd].nodeAfter

        this.setSelectionByEditor(nodeBefore, nodeAfter, attrs)
      }
      const selectionText = this.editor.state.doc.textBetween(this.editor.view.state.tr.selection.from, this.editor.view.state.tr.selection.to, ' ')
      this.$refs.linkModal.toggleModal(this.linkUrl, selectionText, attrs.target)
    },

    executeSubMenuButtonAction (button, menu, activateOne = false) {
      // If only one button in submenu can be enabled, deactivate the rest
      if (activateOne) {
        this[menu].buttons.forEach(subMenuButton => {
          if (this.editor.isActive[subMenuButton.name]() || subMenuButton === button) {
            subMenuButton.command(this.editor.commands)
          }
        })
      } else {
        // If we just want to activate the clicked button without deactivating the other buttons in the submenu
        button.command(this.editor.commands)
      }

      this[menu].isOpen = false
    },

    toggleSubMenu (menu, isOpen) {
      this[menu].isOpen = isOpen

      if (isOpen === true) {
        const menuToClose = menu === 'tableMenu' ? 'diffMenu' : 'tableMenu'
        this[menuToClose].isOpen = false
        const closeMenu = () => {
          this[menu].isOpen = false
          document.removeEventListener('click', closeMenu)
        }
        document.addEventListener('click', closeMenu)
      }
    }
  },

  created () {
    this.currentValue = this.value
    this.prepareInitText()
  },

  mounted () {
    const extensions = [
      new History(),
      new HardBreak(),
      new Heading({ levels: this.toolbar.headings })
    ]

    if (this.toolbar.boilerPlate) {
      extensions.push(new EditorInsertAtCursorPos())
    }

    if (this.suggestions.length > 0) {
      this.suggestions.forEach(suggestionGroup => {
        extensions.push(createSuggestion(suggestionGroup, this))
      })
    }

    if (this.toolbar.headings.length > 0) {
      extensions.push(new Heading({ levels: this.toolbar.headings }))
    }

    if (this.toolbar.imageButton) {
      extensions.push(new EditorCustomImage())
    }

    if (this.toolbar.linkButton) {
      extensions.push(new Link())
      extensions.push(new EditorCustomLink())
    }

    if (this.toolbar.obscure) {
      extensions.push(new EditorObscure())
    }

    if (this.toolbar.listButtons) {
      extensions.push(new BulletList())
      extensions.push(new OrderedList())
      extensions.push(new ListItem())
    }

    if (this.toolbar.table) {
      extensions.push(new Table({
        resizable: true
      }))
      extensions.push(new TableHeader())
      extensions.push(new TableCell())
      extensions.push(new TableRow())
    }

    if (this.toolbar.insertAndDelete) {
      extensions.push(new EditorCustomDelete())
      extensions.push(new EditorCustomInsert())

      this.diffMenu.buttons = [
        {
          label: 'editor.diff.insert',
          command: (commands) => commands.insert(),
          name: 'insert'
        },
        {
          label: 'editor.diff.delete',
          command: (commands) => commands.delete(),
          name: 'delete'
        }
      ]
    }

    if (this.toolbar.mark) {
      extensions.push(new EditorCustomMark())

      this.diffMenu.buttons.unshift({
        label: 'editor.mark',
        command: (commands) => commands.mark(),
        name: 'mark'
      })
    }

    if (this.toolbar.textDecoration) {
      extensions.push(new Bold())
      extensions.push(new Italic())
      extensions.push(new Underline())
    }

    if (this.toolbar.strikethrough) {
      extensions.push(new Strike())
    }

    this.editor = new Editor({
      editable: !this.readonly,
      extensions: extensions,
      content: this.currentValue,
      disableInputRules: true,
      disablePasteRules: true,
      onUpdate: () => {
        this.setValue()
      },
      editorProps: {
        handleDrop: (view, event, slice, moved) => {
          if (!moved) {
            return true
          }
        },
        handleClick: (view, pos, event) => {
          if (event.target.tagName.toLowerCase() === 'img' && event.ctrlKey) {
            const image = event.target
            this.openUploadModal({ editAltOnly: true, currentAlt: image.getAttribute('alt') })
          }
        },
        transformPastedHTML: (slice) => {
          /*
           * Due to the strange Html format from Word clipbord, lists would not be displayed properly,
           * so we have to handle paste from word manually.
           */
          slice = handleWordPaste(slice)
          // Handle obscure tags - to handle the paste of fully obscured strings we need to overwrite the default paste behaviour and before the content is pasted we replace the obscure-styles with 'u-obscure' class
          const obscureClass = this.prefixClass('u-obscure')
          const obscureColor = getColorFromCSS(obscureClass)
          let returnContent = slice
          if (slice.includes(`span style="color: ${obscureColor}`)) {
            returnContent = slice.replace(/(?:<meta [^>]*>\s*<span [^>]*>\s*)([^<]*?)(?:\s*<\/span>)/g, '$1')
            returnContent = '<span class="' + obscureClass + '">' + returnContent + '</span>'
          }

          // Strip anchor tags if link functionality is not active
          if (this.linkButton === false) {
            returnContent = returnContent.replace(/<a[^>]*>(.*?)<\/a>/g, '$1')
          }

          // Strip img tags from pasted and dropped content
          returnContent = returnContent.replace(/<img.*?>/g, '')

          return returnContent
        }
      },
      onInit: ({ state, view }) => {
        view._props.handleScrollToSelection = customHandleScrollToSelection
      }
    })

    this.$root.$on('open-image-alt-modal', (e, id) => {
      this.editingImage = id
      this.openUploadModal({ editAltOnly: true, currentAlt: e.target.getAttribute('alt') })
    })
    /*
     * On form-reset the editor has to be cleared manually.
     * the inputs doesn't fire events in this case.
     * in the data methods its to early to get the elements
     */
    this.manuallyResetForm = (this.hiddenInput !== '' && this.$el.closest('form') !== null)
    if (this.manuallyResetForm) {
      this.$el.closest('form').addEventListener('reset', this.resetEditor)
    }
  },

  beforeDestroy () {
    if (this.editor) {
      this.editor.destroy()
      if (this.manuallyResetForm) {
        this.$el.closest('form').removeEventListener('reset', this.resetEditor)
      }
    }
  }
}

// Custom handling of scrolling after paste
function windowRect (win) {
  return {
    left: 0,
    right: win.innerWidth,
    top: 0,
    bottom: win.innerHeight
  }
}
function getSide (value, side) {
  return typeof value === 'number' ? value : value[side]
}
const parentNode = function (node) {
  const parent = node.parentNode
  return parent && parent.nodeType === 11 ? parent.host : parent
}

function customHandleScrollToSelection (view, rect = view.coordsAtPos(view.state.selection.head), startDOM = view.docView.dom) {
  const scrollThreshold = view.someProp('scrollThreshold') || 0
  const scrollMargin = view.someProp('scrollMargin') || 5
  const doc = view.dom.ownerDocument
  const win = doc.defaultView
  for (let parent = startDOM || view.dom; ; parent = parentNode(parent)) {
    if (!parent) break
    if (parent.nodeType !== 1) continue
    const parentStyle = window.getComputedStyle(parent, null)
    const atTop = (parentStyle['overflow-y'] === 'auto' || parentStyle['overflow-y'] === 'scroll' || parent.nodeType !== 1)
    const bounding = atTop ? windowRect(win) : parent.getBoundingClientRect()
    let moveX = 0
    let moveY = 0
    if (rect.top < bounding.top + getSide(scrollThreshold, 'top')) { moveY = -(bounding.top - rect.top + getSide(scrollMargin, 'top')) } else if (rect.bottom > bounding.bottom - getSide(scrollThreshold, 'bottom')) { moveY = rect.bottom - bounding.bottom + getSide(scrollMargin, 'bottom') }
    if (rect.left < bounding.left + getSide(scrollThreshold, 'left')) { moveX = -(bounding.left - rect.left + getSide(scrollMargin, 'left')) } else if (rect.right > bounding.right - getSide(scrollThreshold, 'right')) { moveX = rect.right - bounding.right + getSide(scrollMargin, 'right') }
    if (moveX || moveY) {
      if (moveY) parent.scrollTop += moveY
      if (moveX) parent.scrollLeft += moveX
    }
    if (atTop) break
  }
}

// The function below is used to get the font color of obscured elements to be able to change the HTML on copy/paste of fully-obscured strings (used above in transformPastedHTML)
function getColorFromCSS (className) {
  const body = document.getElementsByTagName('body')[0]
  const div = document.createElement('div')
  div.className = className
  div.id = 'tmpIdToGetColor'
  body.appendChild(div)
  const tmpDiv = document.getElementById('tmpIdToGetColor')
  const color = window.getComputedStyle(tmpDiv).getPropertyValue('color')

  body.removeChild(tmpDiv)
  return color
}
</script>
