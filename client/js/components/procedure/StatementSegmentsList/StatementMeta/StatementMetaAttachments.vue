<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="space-stack-s">
    <div class="space-stack-s">
      <p
        class="weight--bold u-m-0"
        v-text="Translator.trans('attachment.original')" />
      <statement-meta-attachments-link
        v-if="attachments.originalAttachment.hash"
        :attachment="attachments.originalAttachment"
        :procedure-id="procedureId" />
      <p
        v-else
        class="color--grey"
        v-text="Translator.trans('none')" />
    </div>
    <div class="space-stack-s">
      <dp-label
        :text="Translator.trans('more.attachments')"
        for="uploadStatementAttachment" />
      <ul v-if="attachments.additionalAttachments.length > 0">
        <li
          v-for="attachment in attachments.additionalAttachments"
          :key="attachment.hash">
          <statement-meta-attachments-link
            :attachment="attachment"
            :procedure-id="procedureId" />
        </li>
      </ul>
      <template v-if="editable">
        <dp-upload-files
          id="uploadStatementAttachment"
          ref="uploadStatementAttachment"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :class="editable ? '' : 'pointer-events-none opacity-70'"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="1000"
          name="uploadStatementAttachment"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="removeFileId"
          @upload-success="setFileId" />
        <dp-button
          :busy="isProcessing"
          class="float-right"
          :disabled="fileIds.length === 0"
          :text="Translator.trans('save')"
          @click="save('generic')" />
      </template>
    </div>
  </div>
</template>

<script>
import { dpApi, DpButton, DpLabel, DpUploadFiles } from '@demos-europe/demosplan-ui'
import StatementMetaAttachmentsLink from './StatementMetaAttachmentsLink'

export default {
  name: 'StatementMetaAttachments',

  components: {
    DpButton,
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
      isProcessing: false,
      isProcessingSourceStatement: false
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

    removeFileIdSource (file) {
      const fileIdx = this.fileIdsSourceStatement.findIndex(el => el === file.hash)
      this.fileIdsSourceStatement.splice(fileIdx, 1)
    },

    resetSourceStatement () {
      this.isEditingSourceStatement = false
      this.fileIdsSourceStatement = []
      this.$refs.uploadSourceStatementAttachment.clearFilesList()
    },

    save (attachmentType) {
      const { fileIdsField, uploadRef, processing } = {
        generic: {
          fileIdsField: 'fileIds',
          processing: 'isProcessing',
          uploadRef: 'uploadStatementAttachment'
        },
        source_statement: {
          fileIdsField: 'fileIdsSourceStatement',
          processing: 'isProcessingSourceStatement',
          uploadRef: 'uploadSourceStatementAttachment'
        }
      }[attachmentType]

      if (this[fileIdsField].length === 0 && !dpconfirm(Translator.trans('files.empty'))) {
        return
      }
      if (attachmentType === 'source_statement' && !dpconfirm(Translator.trans('check.statement.replace_source_attachment'))) {
        return
      }

      this[processing] = true

      const uploadPromises = this[fileIdsField].map((hash) => {
        const resource = this.getItemResource(hash, attachmentType)

        return dpApi.post(Routing.generate('api_resource_create', { resourceType: 'StatementAttachment' }), {}, { data: resource })
      })

      Promise.allSettled(uploadPromises)
        .then(() => {
          this[fileIdsField] = []
          this.$refs[uploadRef].clearFilesList()
          this.$root.$emit('statement-attachments-added')
          this[processing] = false

          if (attachmentType === 'source_statement') {
            this.isEditingSourceStatement = false
          }
        })
    },

    setFileId (file) {
      this.fileIds.push(file.fileId)
    },

    setFileIdSource (file) {
      this.fileIdsSourceStatement.push(file.fileId)
    }
  }
}
</script>
