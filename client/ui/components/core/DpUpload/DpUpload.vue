<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div ref="fileInput" />
</template>

<script>
import { de } from './utils/UppyTranslations'
import DragDrop from '@uppy/drag-drop'
import { getFileTypes } from 'demosplan-utils/lib/FileInfo'
import { hasOwnProp } from 'demosplan-utils'
import ProgressBar from '@uppy/progress-bar'
import Tus from '@uppy/tus'
import Uppy from '@uppy/core'

export default {
  name: 'DpUpload',

  props: {
    /**
     * Array of mimeTypes or a defined preset as String
     * @see  '@DemosPlanCoreBundle/lib/FileInfo'
     */
    allowedFileTypes: {
      type: [Array, String],
      required: false,
      default: 'pdf'
    },

    /**
     * Warning message that will be rendered if files are added that are not allowed in `allowedFileTypes`.
     */
    allowedFileTypesWarning: {
      type: String,
      default: 'warning.filetype'
    },

    /**
     * Allow users to upload more files after uploading some
     */
    allowMultipleUploads: {
      type: Boolean,
      default: false
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
     * Maximum file size in bytes for each individual file
     */
    maxFileSize: {
      type: Number,
      default: 10000000
    },

    /**
     * Maximum number of files that can be uploaded.
     * By default its a single file upload.
     */
    maxNumberOfFiles: {
      type: Number,
      required: false,
      default: 1
    },

    translations: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },

  data () {
    return {
      currentFileHash: '',
      currentFileId: '',
      uppy: null
    }
  },

  computed: {
    allowedFileTypeArray () {
      return getFileTypes(this.allowedFileTypes)
    }
  },

  methods: {
    getCookieValue (a) {
      const b = document.cookie.match('(^|[^;]+)\\s*' + a + '\\s*=\\s*([^;]+)')
      return b ? b.pop() : ''
    },

    initialize () {
      const locale = { strings: { ...de().strings, ...this.translations } }
      this.uppy = new Uppy({
        disabled: true,
        autoProceed: true,
        allowMultipleUploads: this.allowMultipleUploads,
        restrictions: {
          allowedFileTypes: this.allowedFileTypeArray,
          maxFileSize: this.maxFileSize,
          maxNumberOfFiles: this.maxNumberOfFiles
        },
        locale: locale
      })

      this.uppy.use(DragDrop, {
        target: this.$refs.fileInput,
        width: '100%',
        note: null,
        locale: locale
      })

      this.uppy.use(ProgressBar, {
        target: this.$refs.fileInput,
        fixed: false,
        hideAfterFinish: false
      })

      let currentProcedureId = null

      if (typeof dplan !== 'undefined' && hasOwnProp(dplan, 'procedureId')) {
        currentProcedureId = dplan.procedureId
      }
      const headers = {}
      // Add current procedure id only if set
      if (currentProcedureId !== null && currentProcedureId !== '0') {
        headers['X-Demosplan-Procedure-Id'] = currentProcedureId
      }

      // If we have a basic auth, for some reason it has to be added to the header
      if (dplan.settings.basicAuth !== '') {
        headers.Authorization = dplan.settings.basicAuth
      }

      this.uppy.use(Tus, {
        endpoint: dplan.paths.uploadPost,
        chunkSize: 819200, // 800 KiB
        limit: 5,
        onAfterResponse: (_req, res) => {
          this.currentFileHash = res.getHeader('X-Demosplan-File-Hash')
          this.currentFileId = res.getHeader('X-Demosplan-File-Id')
        },
        removeFingerprintOnSuccess: true,
        headers
      })
    }
  },

  mounted () {
    this.initialize()

    this.uppy.on('complete', result => {
      setTimeout(() => {
        /*
         * Triggers uppy file-removed event (we are instead using a custom file-remove event. we do not want files to
         * be removed on resetting the uppy ui
         */
        this.uppy.cancelAll()
      }, 2000)

      this.$emit('uploads-completed', result)
    })

    this.uppy.on('upload-error', (file, error, response) => {
      console.error(error)
      dplan.notify.error(Translator.trans('error.fileupload'))
      this.$emit('file-error', { file, error, response })
    })

    this.uppy.on('file-added', file => {
      this.$emit('file-added', file)
    })

    this.uppy.on('upload', data => {
      this.$emit('upload', data)
    })

    this.uppy.on('restriction-failed', () => {
      dplan.notify.warning(Translator.trans(this.allowedFileTypesWarning))
    })

    /*
     * `upload-success` fires each time a single upload completes successfully.
     * @see https://uppy.io/docs/uppy/#upload-success
     */
    this.uppy.on('upload-success', (file) => {
      const { name, size, type } = file.data
      const newFile = {
        name: name,
        hash: this.currentFileHash,
        size: size,
        type: type,
        id: file.id, // The uppy internal file id
        fileId: this.currentFileId // The id of the file within demosplan
      }
      this.currentFileHash = ''
      this.currentFileId = ''
      this.$emit('upload-success', newFile)
    })
  },

  beforeDestroy () {
    if (this.uppy) {
      this.uppy.close()
    }
  }
}
</script>
