<template>
  <dp-modal
    ref="imgModal"
    content-classes="w-fit"
    :data-cy="dataCy">
    <img
      :alt="this.clickedImg.alt"
      :src="this.clickedImg.src">
  </dp-modal>
</template>
<script>
import { DpModal } from '@demos-europe/demosplan-ui'

export default {
  name: 'ImageModal',

  components: {
    DpModal
  },

  props: {
    dataCy: {
      type: String,
      required: false,
      default: 'imageModal'
    }
  },

  data () {
    return {
      clickedImg: {
        alt: '',
        src: ''
      }
    }
  },

  methods: {
    addClickListener (images) {
      images.forEach(img => {
        img.addEventListener('click', this.imageClicked)
      })
    },

    imageClicked ({ target }) {
      this.clickedImg.src = target.src
      this.clickedImg.alt = target.alt
      this.$refs.imgModal.toggle()
    }
  }
}
</script>
