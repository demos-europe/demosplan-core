<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div
    data-dp-validate="signLanguageVideo"
    class="space-stack-s">
    <template v-if="video.id">
      <div class="flex space-inline-m">
        <dp-video-player
          class="shadow height-fit-content width-300"
          :sources="videoSources"
          :id="`file${video.file}`"
          icon-url="/img/plyr.svg" />

        <dl class="description-list">
          <dt>{{ Translator.trans('title') }}</dt>
          <dd>{{ video.title }}</dd>
          <dt>{{ Translator.trans('video.description') }}</dt>
          <dd>{{ video.description }}</dd>
        </dl>
      </div>

      <dp-button
        :busy="isBusy"
        color="warning"
        :text="Translator.trans('delete')"
        variant="outline"
        @click="deleteVideo" />
    </template>

    <template v-else>
      <dp-input
        id="videoTitle"
        v-model="video.title"
        data-dp-validate-if="input[name='uploadedFiles[videoSrc]']!=='', #videoDescription!==''"
        :label="{
          text: Translator.trans('title')
        }"
        name="videoTitle"
        required />

      <dp-upload-files
        :allowed-file-types="['video/*']"
        :basic-auth="dplan.settings.basicAuth"
        :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
        id="videoSrc"
        :max-file-size="400 * 1024 * 1024/* 400 MiB */"
        :max-number-of-files="1"
        name="videoSrc"
        needs-hidden-input
        required
        data-dp-validate-if="#videoTitle!=='', #videoDescription!==''"
        :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '400MB' }) }"
        :tus-endpoint="dplan.paths.tusEndpoint"
        @file-remove="unsetVideoSrcId"
        @upload-success="setVideoSrcId" />

      <dp-text-area
        id="videoDescription"
        :label="Translator.trans('video.description')"
        name="videoDescription"
        required
        data-dp-validate-if="input[name='uploadedFiles[videoSrc]']!=='', #videoTitle!==''"
        v-model="video.description"
        reduced-height />

      <dp-button
        :busy="isBusy"
        :disabled="hasNoVideoInput"
        :text="Translator.trans('save')"
        variant="outline"
        @click="dpValidateAction('signLanguageVideo', createVideo, false)" />
    </template>
  </div>
</template>

<script>
import {
  dpApi,
  DpButton,
  DpInput,
  DpTextArea,
  DpUploadFiles,
  dpValidateMixin,
  getFileIdsByHash
} from '@demos-europe/demosplan-ui'
import { defineAsyncComponent } from 'vue'

export default {
  name: 'CustomerSettingsSignLanguageVideo',

  components: {
    DpButton,
    DpInput,
    DpTextArea,
    DpUploadFiles,
    DpVideoPlayer: defineAsyncComponent(async () => {
      const { DpVideoPlayer } = await import('@demos-europe/demosplan-ui')
      return DpVideoPlayer
    })
  },

  mixins: [dpValidateMixin],

  props: {
    signLanguageOverviewVideo: {
      required: false,
      type: Object,
      default: () => {
        return {
          description: '',
          file: '',
          id: null,
          mimetype: '',
          title: ''
        }
      }
    }
  },

  data () {
    return {
      isBusy: false,
      video: this.signLanguageOverviewVideo
    }
  },

  computed: {
    hasNoVideoInput () {
      return this.video.title === '' && this.video.file === '' && this.video.description === ''
    },

    videoSources () {
      return [
        {
          src: Routing.generate('core_file', { hash: this.video.file }),
          type: this.video.mimetype
        }
      ]
    }
  },

  methods: {
    createVideo () {
      this.isBusy = true
      this.saveVideo()
        .then(() => this.$emit('created'))
    },

    deleteVideo () {
      if (dpconfirm(Translator.trans('check.item.delete')) === false) {
        return
      }
      this.isBusy = true
      dpApi.delete(Routing.generate('api_resource_delete', { resourceType: 'SignLanguageOverviewVideo', resourceId: this.video.id }))
        .then(() => this.$emit('deleted'))
    },

    async saveVideo () {
      const fileIds = await getFileIdsByHash([this.video.file], Routing.generate('api_resource_list', { resourceType: 'File' }))

      const payload = {
        type: 'SignLanguageOverviewVideo',
        attributes: {
          description: this.video.description,
          title: this.video.title
        },
        relationships: {
          file: {
            data: {
              type: 'File',
              id: fileIds[0]
            }
          }
        }
      }
      return dpApi.post(Routing.generate('api_resource_create', { resourceType: 'SignLanguageOverviewVideo' }), {}, { data: payload })
    },

    setVideoSrcId (payload) {
      this.video.file = payload.hash
    },

    unsetVideoSrcId () {
      this.video.file = ''
    }
  }
}
</script>
