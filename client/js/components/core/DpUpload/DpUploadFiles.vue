<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset :class="prefixClass('layout')">
    <legend
      class="hide-visually"
      v-text="Translator.trans('upload.files')" />
    <dp-label
      v-if="label.text"
      class="layout__item"
      v-bind="{
        for: id,
        required: required,
        ...label
      }" />
    <dp-upload
      :allowed-file-types="allowedFileTypes"
      :chunk-size="chunkSize"
      :class="[prefixClass('layout__item u-1-of-1-palm'), prefixClass(sideBySide ? 'u-1-of-2' : 'u-1-of-1')]"
      :max-number-of-files="maxNumberOfFiles"
      :max-file-size="maxFileSize"
      :translations="translations"
      @upload-success="handleUpload" /><!--

 --><dp-uploaded-file-list
      v-if="uploadedFiles.length > 0"
      @file-remove="handleRemove"
      :class="[prefixClass('layout__item u-1-of-1-palm'), prefixClass(sideBySide ? 'u-1-of-2' : 'u-1-of-1 u-mt')]"
      :files="uploadedFiles" />

    <!--
      If the component is used in the context of a "traditional" form post, the hashes (ids)
      of the uploaded files must be included in that form post. Also, when requiring a file upload,
      the hidden input element is used by dpValidate to check for validation state.
     -->
    <input
      v-if="needsHiddenInput"
      type="hidden"
      :name="name !== 'uploadedFiles' ? `uploadedFiles[${name}]` : 'uploadedFiles'"
      :required="required"
      :data-dp-validate-if="dataDpValidateIf"
      :value="fileHashes">
  </fieldset>
</template>

<script>
import { DpLabel } from 'demosplan-ui/components'
import DpUpload from './DpUpload'
import DpUploadedFileList from './DpUploadedFileList'
import { prefixClassMixin } from 'demosplan-ui/mixins'

export default {
  name: 'DpUploadFiles',

  components: {
    DpLabel,
    DpUpload,
    DpUploadedFileList
  },

  mixins: [prefixClassMixin],

  props: {
    /**
     * Array of mimeTypes or a defined preset as String
     * @see demosplan/DemosPlanCoreBundle/Resources/client/js/lib/FileInfo.js
     */
    allowedFileTypes: {
      type: [Array, String],
      required: true,
      default: 'pdf'
    },

    /**
     * Define chunk size for huge files like PDFs
     */
    chunkSize: {
      type: Number,
      default: Infinity,
      required: false
    },

    /**
     * Use to conditionally validate a required upload field
     * "required" and "needsHiddenInput" need to be set to true in that case
     */
    dataDpValidateIf: {
      type: [Boolean, String],
      required: false,
      default: false
    },

    id: {
      type: String,
      required: false,
      default: ''
    },

    label: {
      type: Object,
      default: () => ({}),
      validator: (prop) => {
        return Object.keys(prop).every(key => ['hint', 'text', 'tooltip'].includes(key))
      }
    },

    /**
     * Sets the maximally allowed file size for a single file in bytes.
     */
    maxFileSize: {
      type: Number,
      required: false,
      default: 10000000
    },

    /**
     * Maximum number of files that can be uploaded
     * Defaults to single file upload
     */
    maxNumberOfFiles: {
      type: Number,
      required: false,
      default: 1
    },

    /**
     * Used to set the name of the hidden input field if the backend needs a specific name.
     */
    name: {
      type: String,
      required: false,
      default: 'uploadedFiles'
    },

    /**
     * Adds a hidden input with fileHash(es) as its value to the DOM.
     * Must be set to true if the uploaded file(s) are intended to be saved via form post, or if the "required"
     * prop is set to true (because dpValidate uses the input field to check the validity).
     */
    needsHiddenInput: {
      type: Boolean,
      default: false
    },

    /**
     * If set to true, dpValidate checks if at least one file has been uploaded.
     * "needsHiddenInput" must also be true in that case.
     */
    required: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Use to overwrite the default translations
     * you can find all available keys here
     * @see https://github.com/transloadit/uppy/blob/master/packages/%40uppy/locales/src/de_DE.js
     * some are already overwritten here
     * @see demosplan/DemosPlanCoreBundle/Resources/client/js/components/DpUpload/utils/UppyTranslations.js
     */
    translations: {
      type: Object,
      required: false,
      default: () => ({})
    },

    /**
     * If true the uploaded files list will be displayed next to the dropzone
     * if false it will be displayed below
     */
    sideBySide: {
      type: Boolean,
      required: false,
      default: false
    },

    /**
     * Set a storageName if you want the uploaded files to be stored
     * in the sessionStorage (i.e., be available across page reloads)
     */
    storageName: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      fileHashes: [],
      uploadedFiles: []
    }
  },

  watch: {
    storageName () {
      if (this.storageName) {
        this.updateSessionStorage()
      }
    }
  },

  methods: {
    addFile (file) {
      // To only show as many files as allowed
      if (this.maxNumberOfFiles <= this.uploadedFiles.length) {
        this.fileHashes.shift()
        this.uploadedFiles.shift()
      }
      if (this.needsHiddenInput) {
        this.fileHashes.push(file.hash)
      }

      this.uploadedFiles.push(file)
      if (this.storageName) {
        this.updateSessionStorage()
      }
    },

    clearFilesList () {
      this.uploadedFiles.forEach(file => {
        this.removeFile(file)
      })
    },

    handleRemove (file) {
      this.removeFile(file)
      this.$emit('file-remove', file)
    },

    handleUpload (file) {
      this.addFile(file)
      this.$emit('upload-success', file)

      // Let plain javascript logic also use 'upload-success' event.
      document.dispatchEvent(new CustomEvent('uploadSuccess', { detail: { file: file, fieldName: this.name } }))
    },

    removeFile (file) {
      if (this.needsHiddenInput) {
        this.fileHashes = this.fileHashes.filter(hash => hash !== file.hash)
      }

      this.uploadedFiles = this.uploadedFiles.filter(el => el.hash !== file.hash)
      if (this.storageName) {
        this.updateSessionStorage()
      }
    },

    updateSessionStorage () {
      sessionStorage.setItem(this.storageName, JSON.stringify(this.uploadedFiles))
    }
  },

  mounted () {
    if (this.storageName) {
      this.uploadedFiles = JSON.parse(sessionStorage.getItem(this.storageName)) || []
    }
  }
}
</script>
