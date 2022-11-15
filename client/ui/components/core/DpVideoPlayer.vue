<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div class="position--relative">
    <video
      :aria-labelledby="ariaLabelledby"
      :id="id"
      playsinline
      :data-poster="poster">
      <source
        v-for="source in sources"
        :key="source.src"
        :src="source.src"
        :type="source.type">
      <track
        v-for="track in tracks"
        :key="track.src"
        :src="track.src"
        :srclang="track.srclang"
        :label="track.label"
        :kind="track.kind"
        :default="!!track.default">
    </video>
  </div>
</template>

<script>
export default {
  name: 'DpVideoPlayer',

  props: {
    /**
     * You may pass the id of an element containing content that describes the video.
     */
    ariaLabelledby: {
      type: [String, Boolean],
      required: false,
      default: false
    },

    iconUrl: {
      type: String,
      required: true
    },

    id: {
      type: String,
      required: true
    },

    poster: {
      type: String,
      required: false,
      default: ''
    },

    sources: {
      required: false,
      validator: (value) => {
        // Check if all sources have a src and a type attribute
        return Array.isArray(value) && value.filter(source => source.src && source.type).length === value.length
      },
      default: () => ([])
    },

    tracks: {
      required: false,
      validator: (value) => {
        // Check if all tracks have their required attributes
        return Array.isArray(value) && value.filter(track => track.kind && track.srclang && track.label && track.src).length === value.length
      },
      default: () => ([])
    }
  },

  data () {
    return {
      player: {}
    }
  },

  created () {
    this.player = import('plyr/dist/plyr.polyfilled.min')
  },

  mounted () {
    this.player.then(Plyr => {
      // eslint-disable-next-line new-cap
      this.player = new Plyr.default('#' + this.id, {
        iconUrl: this.iconUrl,
        // For a full list of available controls see https://github.com/sampotts/plyr/blob/master/CONTROLS.md
        controls: [
          'play-large',
          'play',
          'progress',
          'duration',
          'mute',
          'volume',
          'captions',
          'settings',
          'download',
          'fullscreen'
        ]
      })
    })
  }
}
</script>
