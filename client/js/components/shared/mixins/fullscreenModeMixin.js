/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */
export default {
  data () {
    return {
      isFullscreen: false
    }
  },

  methods: {
    handleFullscreenMode () {
      this.isFullscreen = !this.isFullscreen

      if (this.isFullscreen) {
        document.querySelector('html').setAttribute('style', 'overflow: hidden')
      } else {
        document.querySelector('html').removeAttribute('style')
      }
    }
  }
}
