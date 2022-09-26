<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <h4 :class="prefixClass('u-mb-0_25')">
      {{ Translator.trans('uploaded.files') }}
    </h4>
    <ul :class="prefixClass('o-list space-stack-xs')">
      <dp-uploaded-file
        v-for="(fileString, idx) in fileStrings"
        :file-string="fileString"
        @file-remove="file => $emit('file-remove', file)"
        :key="idx" />
    </ul>
  </div>
</template>

<script>
import DpUploadedFile from './DpUploadedFile'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpUploadedFileList',

  components: {
    DpUploadedFile
  },

  mixins: [prefixClassMixin],

  props: {
    files: {
      type: Array,
      required: false,
      default: () => ([])
    }
  },

  computed: {
    fileStrings () {
      return this.files.map(file => {
        let str = Object.values(file).toString()
        str = str.replace(/,/g, ':')
        return str
      })
    }
  }
}
</script>
