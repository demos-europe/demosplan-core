<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="linkModal"
    content-classes="u-1-of-3"
    data-dp-validate="linkModal"
    @modal:toggled="setVisible">
    <template>
      <h3>
        {{ Translator.trans(hasLink ? 'editor.link.edit' : 'editor.link.insert') }}
      </h3>
      <div class="space-stack-m">
        <dp-input
          id="link_text"
          v-model="text"
          :label="{
            text: Translator.trans('link.text')
          }"
          :required="isVisible" />
        <dp-input
          id="link_url"
          v-model="url"
          :label="{
            hint: Translator.trans('editor.link.url.formatHint'),
            text: Translator.trans('url')
          }"
          :pattern="isVisible === true ? '(^https?://.*|^//.*)' : null"
          :required="isVisible"
          type="url" />
        <dp-checkbox
          id="newTab"
          v-model="newTab"
          :label="{
            text: Translator.trans('open.in.new.tab')
          }" />
        <dp-button-row
          class="u-mt"
          primary
          :primary-text="Translator.trans('insert')"
          secondary
          :secondary-text="Translator.trans('remove')"
          @primary-action="dpValidateAction('linkModal', () => emitAndClose('insert'), false)"
          @secondary-action="emitAndClose('remove')" />
      </div>
    </template>
  </dp-modal>
</template>

<script>
import DpButtonRow from '../DpButtonRow'
import DpCheckbox from '../form/DpCheckbox'
import { DpInput } from 'demosplan-ui/components'
import DpModal from '../DpModal'
import { dpValidateMixin } from 'demosplan-utils/mixins'

export default {
  name: 'DpLinkModal',

  components: {
    DpButtonRow,
    DpCheckbox,
    DpInput,
    DpModal
  },

  mixins: [dpValidateMixin],

  data () {
    return {
      initUrl: '',
      isVisible: false,
      newTab: false,
      text: '',
      url: ''
    }
  },

  computed: {
    hasLink () {
      return this.initUrl !== ''
    }
  },

  methods: {
    emitAndClose (value) {
      this.$emit('insert', (value === 'insert' ? this.url : null), this.newTab, this.text)
      this.toggleModal()
    },

    setVisible (isOpenModal) {
      this.isVisible = isOpenModal
    },

    toggleModal (initUrl, textSelection, newTab) {
      this.$refs.linkModal.toggle()
      if (this.isVisible) {
        this.initUrl = initUrl
        this.url = initUrl
        this.text = textSelection
        this.newTab = newTab === '_blank'
      } else {
        this.url = ''
        this.text = ''
        this.newTab = false
      }
    }
  }
}
</script>
