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

  props: {
    title: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      buttonPosition: '',
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
    calculatePositionAndVisibility () {
      const fromLeft = document.getElementById('jumpContent').offsetWidth + document.getElementById('jumpContent').offsetLeft

      this.buttonPosition = `bottom: 60px; left: ${fromLeft}px; position: fixed`
      this.windowHeight = document.documentElement.clientHeight
      this.scrollPos = document.documentElement.scrollTop
    },

    scrollTop () {
      window.scrollTo(0, 0)
    }
  },

  mounted () {
    this.calculatePositionAndVisibility()

    window.addEventListener('resize', () => {
      this.calculatePositionAndVisibility()
    })

    window.addEventListener('scroll', () => {
      this.scrollPos = document.documentElement.scrollTop
    })
  }
}

</script>
