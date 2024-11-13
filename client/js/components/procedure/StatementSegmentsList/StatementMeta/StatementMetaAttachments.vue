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
        <div
          v-if="localAttachments.originalAttachment.hash"
          class="flex mb-2">
          <statement-meta-attachments-link
            :attachment="localAttachments.originalAttachment"
            :aria-disabled="isSourceAttachmentMarkedForDeletion ? 'true' : 'false'"
            class="block mt-1 mb-1 text-ellipsis overflow-hidden whitespace-nowrap"
            :class="{ 'line-through text-muted pointer-events-none': isSourceAttachmentMarkedForDeletion }"
            :procedure-id="procedureId"
            :tabindex="isSourceAttachmentMarkedForDeletion ? -1 : 0" />
          <button
            class="o-link--default"
            :class="isSourceAttachmentMarkedForDeletion ? 'opacity-100 text-muted pointer-events-none' : 'btn--blank'"
            :disabled="isSourceAttachmentMarkedForDeletion"
            type="button"
            @click="markSourceAttachmentForDeletion">
            <dp-icon
              class="ml-2"
              icon="delete" />
          </button>
        </div>

        <p
          v-if="!localAttachments.originalAttachment.hash && !editable"
          v-text="Translator.trans('none')" />

        <dp-upload-files
          v-if="!localAttachments.originalAttachment.hash && editable"
          id="uploadSourceStatementAttachment"
          ref="uploadSourceStatementAttachment"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :class="!editable ? 'pointer-events-none opacity-70' : 'mt-1'"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="1"
          name="uploadSourceStatementAttachment"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="setSourceAttachmentFileId('')"
          @upload-success="handleSourceAttachmentUploadSuccess" />

        <div class="text-right">
          <dp-button-row
            primary
            secondary
            :busy="isProcessingSourceAttachment"
            :disabled="fileIdSourceAttachment === '' && isSourceAttachmentMarkedForDeletion === false"
            @primary-action="saveSourceAttachment"
            @secondary-action="handleResetSourceAttachment" />
        </div>
      </div>

      <!-- Other attachments -->
      <div>
        <dp-label
          :text="Translator.trans('more.attachments')"
          for="uploadStatementAttachment" />

        <!-- List of existing attachments -->
        <ul
          v-if="localAttachments.additionalAttachments.length > 0"
          class="mb-3">
          <li
            v-for="attachment in localAttachments.additionalAttachments"
            class="flex"
            :key="attachment.hash">
            <statement-meta-attachments-link
              :attachment="attachment"
              class="block mt-1 text-ellipsis overflow-hidden whitespace-nowrap"
              :procedure-id="procedureId" />
            <button
              class="btn--blank o-link--default mt-1"
              type="button"
              @click="deleteAttachment(attachment.id)">
              <dp-icon
                class="ml-2"
                icon="delete" />
            </button>
          </li>
        </ul>
        <p
          v-if="localAttachments.additionalAttachments.length === 0 && !editable"
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
            @file-remove="removeFileId"
            @upload-success="setFileId" />

          <div class="text-right">
            <dp-button-row
              primary
              secondary
              :busy="isProcessingGenericAttachments"
              :disabled="fileIds.length === 0"
              @primary-action="saveGenericAttachments"
              @secondary-action="resetGenericAttachments" />
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
  DpIcon,
  DpLabel,
  DpUploadFiles,
  checkResponse
} from '@demos-europe/demosplan-ui'
import StatementMetaAttachmentsLink from './StatementMetaAttachmentsLink'

export default {
  name: 'StatementMetaAttachments',

  components: {
    DpButton,
    DpButtonRow,
    DpIcon,
    DpLabel,
    DpUploadFiles,
    StatementMetaAttachmentsLink
  },

  props: {
    initialAttachments: {
      type: Object,
      required: true,
      validator(value) {
        return value.hasOwnProperty('originalAttachment') && value.hasOwnProperty('additionalAttachments')
      }
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
      attachmentsMarkedForDeletion: [],
      fileIds: [],
      fileIdSourceAttachment: '',
      isProcessingGenericAttachments: false,
      isProcessingSourceAttachment: false,
      isSourceAttachmentMarkedForDeletion: false,
      localAttachments: JSON.parse(JSON.stringify(this.initialAttachments)),
      sourceAttachmentMarkedForDeletion: {},
      previousSourceAttachment: {}
    }
  },

  methods: {
    createSourceAttachment () {
      this.isProcessingSourceAttachment = true

      const resource = this.getItemResource(this.fileIdSourceAttachment, 'source_statement')
      const url = Routing.generate('api_resource_create', { resourceType: 'SourceStatementAttachment' })
      const params = {}
      const data = {
        data: resource
      }

      return dpApi.post(url, params, data)
        .then(checkResponse)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.statement.source.attachment.created'))
          this.resetSourceAttachment()
          this.triggerStatementRequest()
          this.isProcessingSourceAttachment = false
        })
        .catch(error => {
          console.error(error)
          this.isProcessingSourceAttachment = false
        })
    },

    deleteAttachment (id) {
      const url = Routing.generate('api_resource_delete', { resourceType: 'GenericStatementAttachment', resourceId: id })

      return dpApi.delete(url)
        .then(checkResponse)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.statement.attachment.deleted'))
          this.resetGenericAttachments()
        })
        .catch(error => {
          console.error(error)
          dplan.notify.error(Translator.trans('error.statement.attachment.delete'))
        })
    },

    deleteSourceAttachment () {
      this.localAttachments.originalAttachment = {}

      const url = Routing.generate('api_resource_delete', { resourceType: 'SourceStatementAttachment', resourceId: this.initialAttachments.originalAttachment.id })

      return dpApi.delete(url)
        .then(checkResponse)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.statement.source.attachment.deleted'))
          this.resetSourceAttachment()
          this.setLocalOriginalAttachment({})
        })
        .catch(error => {
          console.error(error)
          this.localAttachments.originalAttachment = this.initialAttachments.originalAttachment
        })
    },

    getItemResource (fileHash, attachmentType) {
      if (attachmentType === 'generic') {
        return {
          type: 'GenericStatementAttachment',
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
      }

      if (attachmentType === 'source_statement') {
        return {
          type: 'SourceStatementAttachment',
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
      }
    },

    handleResetSourceAttachment () {
      if (this.isSourceAttachmentMarkedForDeletion) {
        this.setSourceAttachmentFileId(this.sourceAttachmentMarkedForDeletion.hash)
        this.setLocalOriginalAttachment({
          filename: this.sourceAttachmentMarkedForDeletion.filename,
          hash: this.sourceAttachmentMarkedForDeletion.hash
        })
        this.isSourceAttachmentMarkedForDeletion = false
      } else {
        this.resetSourceAttachment()
        this.setLocalOriginalAttachment(this.previousSourceAttachment)
      }
    },

    handleSourceAttachmentUploadSuccess (file) {
      this.setSourceAttachmentFileId(file.fileId)
      this.previousSourceAttachment = { ...this.localAttachments.originalAttachment }

      this.setLocalOriginalAttachment({
        filename: file.name,
        hash: file.fileId
      })
    },

    markAttachmentsForDeletion (ids) {
      this.attachmentsMarkedForDeletion.push(...ids)
    },

    markSourceAttachmentForDeletion () {
      this.sourceAttachmentMarkedForDeletion = this.localAttachments.originalAttachment
      this.isSourceAttachmentMarkedForDeletion = true
    },

    removeFileId (file) {
      const fileIdx = this.fileIds.findIndex(el => el === file.hash)
      this.fileIds.splice(fileIdx, 1)
    },

    resetGenericAttachments () {
      this.fileIds = []
      this.$refs.uploadStatementAttachment.clearFilesList()
    },

    resetSourceAttachment () {
      this.isSourceAttachmentMarkedForDeletion = false
      this.sourceAttachmentMarkedForDeletion = {}
      this.setSourceAttachmentFileId('')

      if (this.$refs.uploadSourceStatementAttachment) {
        this.$refs.uploadSourceStatementAttachment.clearFilesList()
      }
    },

    saveSourceAttachment () {
      if (this.isSourceAttachmentMarkedForDeletion) {
        this.deleteSourceAttachment()
      } else {
        this.createSourceAttachment()
      }
    },

    saveGenericAttachments () {
        if (this.fileIds.length === 0 && !dpconfirm(Translator.trans('files.empty'))) {
          return
        }

        this.isProcessingGenericAttachments = true

        const uploadPromises = this.fileIds.map(hash => {
          const resource = this.getItemResource(hash, 'generic')
          const url = Routing.generate('api_resource_create', { resourceType: 'GenericStatementAttachment' })
          const params = {}
          const data = {
            data: resource
          }

          return dpApi.post(url, params, data)
        })

      Promise.allSettled(uploadPromises)
        .then(() => {
          dplan.notify.confirm(Translator.trans('confirm.statement.attachment.created'))
          this.resetGenericAttachments()
          this.triggerStatementRequest()
          this.isProcessingGenericAttachments = false
        })
        .catch(error => {
          console.error(error)
          dplan.notify.error(Translator.trans('error.statement.source.attachment.create'))
          this.isProcessingGenericAttachments = false
        })
    },

    setFileId (file) {
      this.fileIds.push(file.fileId)
    },

    setLocalOriginalAttachment (originalAttachment) {
      this.localAttachments.originalAttachment = originalAttachment
    },

    setSourceAttachmentFileId (id) {
      this.fileIdSourceAttachment = id
    },

    triggerStatementRequest () {
      this.$root.$emit('statement-attachments-added')
    }
  }
}
</script>
