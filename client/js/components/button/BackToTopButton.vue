<template>
  <dp-button
    :aria-label="Translator.trans('backToTop')"
    :title="Translator.trans('backToTop')"
    variant="outline"
    color="primary"
    :class="{ 'hide-visually': hide }"
    hide-text
    rounded
    icon="arrow-up"
    icon-size="large"
    :style="buttonPosition"
    @click="scrollTop">
  </dp-button>
</template>

<script>
import { DpButton } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpBackToTop',

  components: {
    DpButton
  },

  data () {
    return {
      buttonPosition: '',
      contentHeight: 0,
      footerHeight: 0,
      positionFromLeft: 0,
      scrollPos: 0,
      windowHeight: 0
    }
  },

  computed: {
    hide () {
      return this.scrollPos < this.windowHeight
    }
  },

  methods: {
    calculateSizes () {
      this.positionFromLeft = document.getElementById('jumpContent').offsetWidth + document.getElementById('jumpContent').offsetLeft
      this.contentHeight = document.documentElement.scrollHeight - document.documentElement.offsetHeight
      this.footerheight = document.querySelector('#app footer').offsetHeight
      this.windowHeight = document.documentElement.clientHeight
    },

    calculatePosition () {
      this.scrollPos = document.documentElement.scrollTop
      const fromBottom = (this.contentHeight - 10 - this.footerheight > this.scrollPos) ? 10 : -this.contentHeight + 10 + this.footerheight + this.scrollPos

      this.buttonPosition = `bottom: ${fromBottom}px; left: ${this.positionFromLeft}px; position: fixed`
    },

    scrollTop () {
      window.scrollTo(0, 0)
    }
  },

  mounted () {
    this.calculateSizes()
    this.calculatePosition()

    window.addEventListener('resize', () => {
      this.calculateSizes()
      this.calculatePosition()
    })

    window.addEventListener('scroll', () => {
      this.calculatePosition()
    })
  }
}

</script>
