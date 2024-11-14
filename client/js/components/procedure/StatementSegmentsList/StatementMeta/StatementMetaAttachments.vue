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
            @click="fileIdSourceAttachment === localAttachments.originalAttachment.hash ? removeSourceAttachment() : markSourceAttachmentForDeletion()">
            <dp-icon
              class="ml-2"
              icon="delete" />
          </button>
        </div>

        <p
          v-if="!localAttachments.originalAttachment.hash && !editable"
          v-text="Translator.trans('none')" />

        <template  v-if="editable">
          <dp-upload
            v-if="!localAttachments.originalAttachment.hash"
            id="uploadSourceStatementAttachment"
            ref="uploadSourceStatementAttachment"
            allowed-file-types="all"
            :basic-auth="dplan.settings.basicAuth"
            :class="!editable ? 'pointer-events-none opacity-70' : 'mt-1 mb-3'"
            :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
            :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
            :max-number-of-files="1"
            name="uploadSourceStatementAttachment"
            :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
            :tus-endpoint="dplan.paths.tusEndpoint"
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
        </template>
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
              :class="{ 'line-through text-muted pointer-events-none': genericAttachmentsMarkedForDeletion.find(el => el.id === attachment.id ) }"
              :procedure-id="procedureId" />
            <button
              class="o-link--default mt-1"
              :class="genericAttachmentsMarkedForDeletion.find(el => el.id === attachment.id ) ? 'opacity-100 text-muted pointer-events-none' : 'btn--blank'"
              :disabled="genericAttachmentsMarkedForDeletion.find(el => el.id === attachment.id )"
              type="button"
              @click="fileIds.includes(attachment.hash) ? removeGenericAttachment(attachment.hash) : markGenericAttachmentForDeletion(attachment.id)">
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
          <dp-upload
            id="uploadStatementAttachment"
            ref="uploadStatementAttachment"
            allowed-file-types="all"
            :basic-auth="dplan.settings.basicAuth"
            :class="editable ? 'mt-1 mb-3' : 'pointer-events-none opacity-70'"
            :get-file-by-hash="hash => Routing.generate('core_file_procedure', { hash: hash, procedureId: procedureId })"
            :max-file-size="2 * 1024 * 1024 * 1024/* 2 GiB */"
            :max-number-of-files="1000"
            name="uploadStatementAttachment"
            :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '2GB' }) }"
            :tus-endpoint="dplan.paths.tusEndpoint"
            @upload-success="handleGenericAttachmentUploadSuccess" />

          <div class="text-right">
            <dp-button-row
              primary
              secondary
              :busy="isProcessingGenericAttachments"
              :disabled="fileIds.length === 0 && genericAttachmentsMarkedForDeletion.length === 0"
              @primary-action="saveGenericAttachments"
              @secondary-action="handleResetGenericAttachments" />
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
  DpUpload,
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
    DpUpload,
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
      // used for saving (creating) generic attachments
      fileIds: [],
      // used for saving (creating) the source attachment
      fileIdSourceAttachment: '',
      // used for deleting generic attachments
      genericAttachmentsMarkedForDeletion: [],
      isProcessingGenericAttachments: false,
      isProcessingSourceAttachment: false,
      isSourceAttachmentMarkedForDeletion: false,
      // used for displaying the source and generic attachments
      localAttachments: JSON.parse(JSON.stringify(this.initialAttachments)),
      // used for resetting the attachments
      previousGenericAttachments: [],
      // used for resetting the source attachment
      previousSourceAttachment: {},
      // used for deleting the source attachment
      sourceAttachmentMarkedForDeletion: {}
    }
  },

  watch: {
    'initialAttachments.additionalAttachments': {
      handler(newVal) {
        this.localAttachments.additionalAttachments = JSON.parse(JSON.stringify(newVal))
      },
      deep: true
    },

    'initialAttachments.originalAttachment': {
      handler(newVal) {
        this.localAttachments.originalAttachment = JSON.parse(JSON.stringify(newVal))
      },
      deep: true
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
      const attachmentToBeDeleted = { ...this.localAttachments.additionalAttachments.find(attachment => attachment.id === id) }

      return dpApi.delete(url)
        .then(checkResponse)
        .then(() => {
          const genericAttachments = this.localAttachments.additionalAttachments.filter(attachment => attachment.id !== id)

          this.setLocalGenericAttachments(genericAttachments)
          this.resetGenericAttachments()
        })
        .catch(error => {
          console.error(error)
          const restoredAttachments = [
            ...this.localAttachments.additionalAttachments,
            attachmentToBeDeleted
          ]

          this.setLocalGenericAttachments(restoredAttachments)
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
          this.setLocalOriginalAttachment(this.initialAttachments.originalAttachment)
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

    handleGenericAttachmentUploadSuccess (file) {
      this.previousGenericAttachments = [...this.localAttachments.additionalAttachments]

      const updatedGenericAttachments = [
        ...this.localAttachments.additionalAttachments,
        {
          filename: file.name,
          hash: file.fileId
        }
      ]

      this.setLocalGenericAttachments(updatedGenericAttachments)
      this.setGenericAttachmentFileId(file.fileId)
    },

    handleResetGenericAttachments () {
      this.resetGenericAttachments()

      if (this.genericAttachmentsMarkedForDeletion.length > 0) {
        this.genericAttachmentsMarkedForDeletion = []
        this.setLocalGenericAttachments(this.initialAttachments.additionalAttachments)
      } else {
        this.setLocalGenericAttachments(this.previousGenericAttachments)
      }
    },

    handleResetSourceAttachment () {
      if (this.isSourceAttachmentMarkedForDeletion) {
        this.setSourceAttachmentFileId(this.sourceAttachmentMarkedForDeletion.hash)
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

    markGenericAttachmentForDeletion (id) {
      const genericAttachmentToBeDeleted = { ...this.localAttachments.additionalAttachments.find(attachment => attachment.id === id) }
      this.genericAttachmentsMarkedForDeletion.push(genericAttachmentToBeDeleted)
    },

    markSourceAttachmentForDeletion () {
      this.sourceAttachmentMarkedForDeletion = this.localAttachments.originalAttachment
      this.isSourceAttachmentMarkedForDeletion = true
    },

    removeFileId (hash) {
      this.fileIds = this.fileIds.filter(el => el !== hash)
    },

    removeGenericAttachment (hash) {
      this.removeFileId(hash)
      this.localAttachments.additionalAttachments = this.localAttachments.additionalAttachments.filter(attachment => attachment.hash !== hash)
    },

    removeSourceAttachment () {
      this.setSourceAttachmentFileId('')
      this.localAttachments.originalAttachment = {}
    },

    resetGenericAttachments () {
      this.fileIds = []
    },

    resetSourceAttachment () {
      this.isSourceAttachmentMarkedForDeletion = false
      this.sourceAttachmentMarkedForDeletion = {}
      this.setSourceAttachmentFileId('')
    },

    saveSourceAttachment () {
      if (this.isSourceAttachmentMarkedForDeletion) {
        this.deleteSourceAttachment()
      } else {
        this.createSourceAttachment()
      }
    },

    saveGenericAttachments () {
      this.isProcessingGenericAttachments = true
      const promises = []

      if (this.genericAttachmentsMarkedForDeletion.length > 0) {
        this.genericAttachmentsMarkedForDeletion.forEach(attachment => {
          promises.push(this.deleteAttachment(attachment.id))
        })
      }

      if (this.fileIds.length > 0) {
        this.fileIds.forEach(hash => {
          const resource = this.getItemResource(hash, 'generic')
          const url = Routing.generate('api_resource_create', { resourceType: 'GenericStatementAttachment' })
          const params = {}
          const data = {
            data: resource
          }

          promises.push(dpApi.post(url, params, data))
        })
      }

      Promise.allSettled(promises)
        .then(() => {
          if (this.genericAttachmentsMarkedForDeletion.length > 0) {
            this.genericAttachmentsMarkedForDeletion = []
            dplan.notify.confirm(Translator.trans('confirm.statement.attachment.deleted', { count: this.genericAttachmentsMarkedForDeletion.length }))
          }

          if (this.fileIds.length > 0) {
            dplan.notify.confirm(Translator.trans('confirm.statement.attachment.created', { count: this.fileIds.length }))
            this.resetGenericAttachments()
          }

          this.triggerStatementRequest()
          this.isProcessingGenericAttachments = false
        })
        .catch(error => {
          console.error(error)
          this.isProcessingGenericAttachments = false
        })
    },

    setGenericAttachmentFileId (id) {
      this.fileIds.push(id)
    },

    setLocalGenericAttachments (genericAttachments) {
      this.localAttachments.additionalAttachments = genericAttachments
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
