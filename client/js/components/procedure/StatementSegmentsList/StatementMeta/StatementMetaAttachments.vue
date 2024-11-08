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
          v-if="attachments.originalAttachment.hash"
          class="flex mb-2">
          <statement-meta-attachments-link
            :attachment="attachments.originalAttachment"
            class="block mt-1 mb-1 text-ellipsis overflow-hidden whitespace-nowrap"
            :procedure-id="procedureId" />
          <button
            class="btn--blank o-link--default"
            type="button"
            @click="deleteSourceAttachment">
            <dp-icon
              class="ml-2"
              icon="delete" />
          </button>
        </div>

        <p
          v-if="!attachments.originalAttachment.hash && !editable"
          v-text="Translator.trans('none')" />

        <dp-upload-files
          v-if="!attachments.originalAttachment.hash && editable"
          id="uploadSourceStatementAttachment"
          ref="uploadSourceStatementAttachment"
          allowed-file-types="all"
          :basic-auth="dplan.settings.basicAuth"
          :class="!editable ? 'pointer-events-none opacity-70' : ''"
          :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
          :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
          :max-number-of-files="1"
          name="uploadSourceStatementAttachment"
          :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
          :tus-endpoint="dplan.paths.tusEndpoint"
          @file-remove="removeSourceAttachmentFileId"
          @upload-success="setSourceStatementFileId" />

        <div class="text-right">
          <dp-button-row
            primary
            secondary
            :busy="isProcessingSourceAttachment"
            :disabled="fileIdSourceAttachment === ''"
            @primary-action="saveSourceAttachment"
            @secondary-action="resetSourceAttachment" />
        </div>
      </div>

      <!-- Other attachments -->
      <div>
        <dp-label
          :text="Translator.trans('more.attachments')"
          for="uploadStatementAttachment" />

        <!-- List of existing attachments -->
        <ul
          v-if="attachments.additionalAttachments.length > 0"
          class="mb-3">
          <li
            v-for="attachment in attachments.additionalAttachments"
            class="flex"
            :key="attachment.hash">
            <statement-meta-attachments-link
              :attachment="attachment"
              class="block mt-1 text-ellipsis overflow-hidden whitespace-nowrap"
              :procedure-id="procedureId" />
            <button
              class="btn--blank o-link--default mt-1"
              type="button"
              @click="deleteAttachment(attachment.hash)">
              <dp-icon
                class="ml-2"
                icon="delete" />
            </button>
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
            @file-remove="removeFileId"
            @upload-success="setFileId" />

          <div class="text-right">
            <dp-button-row
              primary
              secondary
              :busy="isProcessingOtherAttachments"
              :disabled="fileIds.length === 0"
              @primary-action="saveOtherAttachments"
              @secondary-action="resetOtherAttachments" />
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
  DpUploadFiles
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
      fileIdSourceAttachment: '',
      isEditingSourceAttachment: false,
      isProcessingOtherAttachments: false,
      isProcessingSourceAttachment: false
    }
  },

  methods: {
    createSourceAttachment () {
      const resource = this.getItemResource(this.fileIdSourceAttachment, 'source_statement')
      const url = Routing.generate('api_resource_create', { resourceType: 'StatementAttachment' })
      const params = {}
      const data = {
        data: resource
      }

      return dpApi.post(url, params, data)
        .then(() => {
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
        .then(() => {
          this.resetOtherAttachments()
        })
    },

    deleteSourceAttachment () {
      const url = Routing.generate('api_resource_delete', { resourceType: 'SourceAttachment', resourceId: this.attachments.originalAttachment.id })
      return dpApi.delete(url)
        .then(() => {
          this.resetSourceAttachment()
        })
    },

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

    removeSourceAttachmentFileId () {
      this.fileIdSourceAttachment = ''
    },

    resetOtherAttachments () {
      this.fileIds = []
      this.$refs.uploadStatementAttachment.clearFilesList()
    },

    resetSourceAttachment () {
      this.isEditingSourceAttachment = false
      this.fileIdSourceAttachment = ''
      this.$refs.uploadSourceStatementAttachment.clearFilesList()
    },

    saveSourceAttachment () {
      const statementHasSourceAttachment = this.attachments.originalAttachment.hash

      if (statementHasSourceAttachment && !dpconfirm(Translator.trans('check.statement.replace_source_attachment'))) {
        return
      }

      this.isProcessingSourceAttachment = true
      this.createSourceAttachment()
    },

    saveOtherAttachments () {
        if (this.fileIds.length === 0 && !dpconfirm(Translator.trans('files.empty'))) {
          return
        }

        this.isProcessingOtherAttachments = true

        const uploadPromises = this.fileIds.map(hash => {
          const resource = this.getItemResource(hash, 'generic')
          const url = Routing.generate('api_resource_create', { resourceType: 'StatementAttachment' })
          const params = {}
          const data = {
            data: resource
          }

          return dpApi.post(url, params, data)
        })

      Promise.allSettled(uploadPromises)
        .then(() => {
          this.resetOtherAttachments()
          this.triggerStatementRequest()
          this.isProcessingOtherAttachments = false
        })
        .catch(error => {
          this.isProcessingOtherAttachments = false
        })
    },

    setFileId (file) {
      this.fileIds.push(file.fileId)
    },

    setSourceStatementFileId (file) {
      this.fileIdSourceAttachment = file.fileId
    },

    triggerStatementRequest () {
      this.$root.$emit('statement-attachments-added')
    }
  }
}
</script>
