<template>
  <dp-button
    class="sticky z-above-zero"
    :class="{ 'sr-only border-none p-0': hide }"
    hide-text
    icon="arrow-up"
    icon-size="large"
    rounded
    :style="buttonPosition"
    :text="Translator.trans('back.to.top')"
    data-cy="backToTop"
    variant="outline"
    @click="scrollTop" />
</template>

<script>
import { DpButton } from '@demos-europe/demosplan-ui'

export default {
  name: 'BackToTop',

  components: {
    DpButton
  },

  data () {
    return {
      containerElement: null,
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
      return this.scrollPos < this.windowHeight * 0.7
    }
  },

  methods: {
    calculateSizes () {
      this.positionFromLeft = this.containerElement.offsetWidth + this.containerElement.offsetLeft
      this.contentHeight = document.documentElement.scrollHeight - document.documentElement.offsetHeight
      this.footerheight = document.querySelector('#app footer').offsetHeight
      this.windowHeight = document.documentElement.clientHeight
    },

    calculatePosition () {
      this.scrollPos = document.documentElement.scrollTop
      const fromBottom = (this.contentHeight - 10 - this.footerheight > this.scrollPos) ? 10 : -this.contentHeight + 10 + this.footerheight + this.scrollPos

      this.buttonPosition = `bottom: ${fromBottom}px; left: ${this.positionFromLeft}px`
    },

    scrollTop () {
      window.scrollTo(0, 0)
    }
  },

  mounted () {
    this.containerElement = document.getElementById('jumpContent')
    this.calculateSizes()
    this.calculatePosition()

    window.addEventListener('scroll', () => {
      this.calculatePosition()
    }, { passive: true })

    new ResizeObserver(() => {
      this.calculateSizes()
      this.calculatePosition()
    })
      .observe(document.getElementById('app'))
  }
}

</script>
