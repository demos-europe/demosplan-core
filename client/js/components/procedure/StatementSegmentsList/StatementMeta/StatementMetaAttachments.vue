<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <fieldset>
    <legend
      id="attachments"
      class="mb-3 color-text-muted font-normal">
      {{ Translator.trans('attachments') }}
    </legend>
    <div class="space-stack-m">
      <!-- Statement as attachment -->
      <div>
        <p
          class="weight--bold u-m-0"
          v-text="Translator.trans('attachment.original')" />
        <statement-meta-attachments-link
          v-if="attachments.originalAttachment.hash"
          :attachment="attachments.originalAttachment"
          class="block mt-2 mb-3"
          :procedure-id="procedureId" />
        <p
          v-if="!attachments.originalAttachment.hash && !editable"
          v-text="Translator.trans('none')" />

        <dp-upload-files
          v-if="editable"
          id="uploadSourceStatementAttachment"
          ref="uploadSourceStatementAttachment"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :class="editable ? 'mt-1' : 'pointer-events-none opacity-70'"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="1"
          name="uploadSourceStatementAttachment"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="removeSourceStatementFileId"
          @upload-success="setSourceStatementFileId" />
      </div>

      <!-- Other attachments -->
      <div>
        <dp-label
          :text="Translator.trans('more.attachments')"
          for="uploadStatementAttachment" />

        <!-- List of existing attachments -->
        <ul
          v-if="attachments.additionalAttachments.length > 0"
          class="space-y-2 mb-3">
          <li
            v-for="attachment in attachments.additionalAttachments"
            :key="attachment.hash">
            <statement-meta-attachments-link
              :attachment="attachment"
              class="block mt-2"
              :procedure-id="procedureId" />
          </li>
        </ul>
        <p
          v-if="attachments.additionalAttachments.length === 0 && !editable"
          v-text="Translator.trans('none')" />

        <!-- File upload -->
        <template v-if="editable">
          <dp-upload-files
            id="uploadStatementAttachment"
            ref="uploadStatementAttachment"
            allowed-file-types="all"
            :basic-auth="dplan.settings.basicAuth"
            :class="editable ? 'mt-1' : 'pointer-events-none opacity-70'"
            :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
            :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
            :max-number-of-files="1000"
            name="uploadStatementAttachment"
            :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
            :tus-endpoint="dplan.paths.tusEndpoint"
            @file-remove="removeFileId""
            @upload-success="setFileId" />

          <div class="text-right">
            <dp-button-row
              class="mt-4"
              primary
              secondary
              :disabled="fileIds.length === 0 && fileIdsSourceStatement.length === 0"
              @primary-action="save"
              @secondary-action="reset" />
          </div>
        </template>
      </div>
    </div>
  </fieldset>
</template>

<script>
import {
  dpApi,
  DpButton,
  DpButtonRow,
  DpLabel,
  DpUploadFiles
} from '@demos-europe/demosplan-ui'
import StatementMetaAttachmentsLink from './StatementMetaAttachmentsLink'

export default {
  name: 'StatementMetaAttachments',

  components: {
    DpButton,
    DpButtonRow,
    DpLabel,
    DpUploadFiles,
    StatementMetaAttachmentsLink
  },

  props: {
    attachments: {
      type: Object,
      required: true
    },

    /**
     * Editable can be used to disable DpUploadFiles on css level
     * but keep the uploaded files list accessible at the same time.
     */
    editable: {
      type: Boolean,
      required: false,
      default: false
    },

    procedureId: {
      type: String,
      required: true
    },

    statementId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      fileIds: [],
      fileIdsSourceStatement: [],
      isEditingSourceStatement: false,
      isProcessing: false
    }
  },

  methods: {
    getItemResource (fileHash, attachmentType) {
      return {
        type: 'StatementAttachment',
        attributes: {
          attachmentType: attachmentType
        },
        relationships: {
          statement: {
            data: {
              id: this.statementId,
              type: 'Statement'
            }
          },
          file: {
            data: {
              id: fileHash,
              type: 'File'
            }
          }
        }
      }
    },

    removeFileId (file) {
      const fileIdx = this.fileIds.findIndex(el => el === file.hash)
      this.fileIds.splice(fileIdx, 1)
    },

    removeSourceStatementFileId (file) {
      const fileIdx = this.fileIdsSourceStatement.findIndex(el => el === file.hash)
      this.fileIdsSourceStatement.splice(fileIdx, 1)
    },

    reset () {
      this.resetSourceStatementAttachment()
      this.resetAttachments()
    },

    resetAttachments () {
      this.fileIds = []
      this.$refs.uploadStatementAttachment.clearFilesList()
    },

    resetSourceStatementAttachment () {
      this.isEditingSourceStatement = false
      this.fileIdsSourceStatement = []
      this.$refs.uploadSourceStatementAttachment.clearFilesList()
    },

    save () {
      const areAttachmentsAdded = this.fileIds.length > 0
      const isSourceAttachmentAdded = this.fileIdsSourceStatement.length > 0
      const propertyNames = {
        generic: {
          fileIdsField: 'fileIds',
        },
        source_statement: {
          fileIdsField: 'fileIdsSourceStatement',
        }
      }

      const attachmentTypes = []

      if (areAttachmentsAdded) {
        attachmentTypes.push('generic')
      }

      if (isSourceAttachmentAdded) {
        attachmentTypes.push('source_statement')
      }

      let uploadPromises = []

      attachmentTypes.forEach(type => {
        const {
          fileIdsField
        } = propertyNames[type]

        if (this[fileIdsField].length === 0 && !dpconfirm(Translator.trans('files.empty'))) {
          return
        }

        if (type === 'source_statement' && this.attachments.originalAttachment.hash && !dpconfirm(Translator.trans('check.statement.replace_source_attachment'))) {
          return
        }

        this.isProcessing = true

        uploadPromises = [
          ...uploadPromises,
          ...this[fileIdsField].map(hash => {
          const resource = this.getItemResource(hash, type)
          const url = Routing.generate('api_resource_create', { resourceType: 'StatementAttachment' })
          const params = {}
          const data = {
            data: resource
          }

          return dpApi.post(url, params, data)
        })]
      })

      Promise.allSettled(uploadPromises)
        .then(() => {
          this.reset()
          this.triggerStatementRequest()
          this.isProcessing = false
        })
        .catch(error => {
          this.isProcessing = false
        })
    },

    setFileId (file) {
      this.fileIds.push(file.fileId)
    },

    setSourceStatementFileId (file) {
      this.fileIdsSourceStatement.push(file.fileId)
    },

    triggerStatementRequest () {
      this.$root.$emit('statement-attachments-added')
    }
  }
}
</script>
