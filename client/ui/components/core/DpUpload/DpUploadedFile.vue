<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <li>
    <span
      aria-hidden="true"
      v-if="file.mimeType === 'txt'">
      <i
        :class="fileIcon" />
    </span>
    <span v-if="isImage">
      <img
        :src="Routing.generate('core_file', { hash: file.hash })"
        :aria-label="Translator.trans('image.preview')"
        width="50px">
    </span>
    <span
      :class="prefixClass('display--inline-block u-pl-0_5')"
      style="width: calc(100% - 62px);">
      {{ file.name }}
      ({{ file.size }})
      <button
        type="button"
        :class="prefixClass('btn-icns u-m-0')"
        :aria-label="Translator.trans('file.remove')"
        @click="removeFile">
        <i
          :class="prefixClass('fa fa-trash')"
          aria-hidden="true" />
      </button>
    </span>
  </li>
</template>

<script>
import { getFileInfo } from 'demosplan-utils/lib/FileInfo'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpUploadedFile',

  mixins: [prefixClassMixin],

  props: {
    fileString: {
      type: String,
      required: true
    }
  },

  computed: {
    file () {
      return getFileInfo(this.fileString)
    },

    fileIcon () {
      const icon = this.file.mimeType === 'txt' ? 'fa-file-text-o' : 'fa-folder-o'
      return this.prefixClass('fa ' + icon)
    },

    isImage () {
      const imageTypes = ['png', 'jpg', 'gif', 'bmp', 'ico', 'tiff', 'svg']
      return typeof imageTypes.find(type => type === this.file.mimeType) !== 'undefined'
    }
  },

  methods: {
    removeFile () {
      this.$emit('file-remove', this.file)
    }
  }
}
</script>
