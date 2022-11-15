<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <dp-modal
    ref="uploadModal"
    content-classes="u-2-of-3-lap-up u-1-of-2-desk-up">
    <template>
      <h3
        v-if="editAltTextOnly"
        class="u-mb">
        {{ Translator.trans('image.edit') }}
      </h3>
      <h3
        v-else
        class="u-mb">
        {{ Translator.trans('image.insert') }}
      </h3>
      <div v-show="editAltTextOnly === false">
        <dp-upload-files
          allowed-file-types="img"
          id="imageFile"
          :max-file-size="20 * 1024 * 1024/* 20 MiB */"
          :max-number-of-files="1"
          ref="uploader"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.image', { browse: '{browse}', maxUploadSize: '20MB' }) }"
          @upload-success="setFile" />
      </div>
      <dp-input
        id="altText"
        v-model="altText"
        class="u-mb"
        :label="{
          hint: Translator.trans('image.alt.explanation'),
          text: Translator.trans('alternative.text')
        }" />
      <div class="u-mt text--right width-100p space-inline-s">
        <button
          class="btn btn--primary"
          type="button"
          @click="emitAndClose()">
          {{ Translator.trans('insert') }}
        </button>
        <button
          class="btn btn--secondary"
          type="button"
          @click="resetAndClose()">
          {{ Translator.trans('abort') }}
        </button>
      </div>
    </template>
  </dp-modal>
</template>

<script>
import { DpInput } from 'demosplan-ui/components'
import DpModal from '../DpModal'
import DpUploadFiles from '../DpUpload/DpUploadFiles'

export default {
  name: 'DpUploadModal',

  components: {
    DpInput,
    DpModal,
    DpUploadFiles
  },

  data () {
    return {
      fileUrl: '',
      altText: '',
      editAltTextOnly: false
    }
  },

  methods: {
    emitAndClose () {
      if (this.editAltTextOnly) {
        this.$emit('add-alt', this.altText)
      } else if (this.fileUrl) {
        this.$emit('insert-image', this.fileUrl, this.altText)
      }
      this.resetAndClose()
    },

    resetAndClose () {
      this.altText = ''
      this.fileUrl = ''
      this.editAltTextOnly = false
      this.$emit('close')
      this.toggleModal()
    },

    setFile ({ hash }) {
      this.fileUrl = Routing.generate('core_file', { hash: hash })
      // Force-update the component so that DpModal updates and therefore check for new focusable elements
      this.$forceUpdate()
    },

    toggleModal (data) {
      const willCloseModal = this.$refs.uploadModal.isOpenModal === true

      if (willCloseModal) {
        this.$refs.uploader.clearFilesList()
      } else if (data) {
        this.editAltTextOnly = data.editAltOnly
        this.altText = data.currentAlt
      }

      this.$refs.uploadModal.toggle()
    }
  }
}
</script>
