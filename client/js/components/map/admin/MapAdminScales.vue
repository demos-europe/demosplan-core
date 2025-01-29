<template>
  <div class="space-stack-xs">
    <label
      for="r_scales"
      class="mb-0"
      v-text="Translator.trans('map.scales')" />
    <dp-multiselect
      v-model="scales"
      label="label"
      data-cy="map:mapAdminScales"
      multiple
      :options="availableScales"
      track-by="value"
      @input="updateScales">
      <template v-slot:option="{ props }">
        {{ props.option.label }}
      </template>
      <template v-slot:tag="{ props }">
        <span class="multiselect__tag">
          {{ props.option.label }}
          <i
            aria-hidden="true"
            @click="props.remove(props.option)"
            tabindex="1"
            class="multiselect__tag-icon" />
          <input
            type="hidden"
            :value="props.option.value"
            name="r_scales[]">
        </span>
      </template>
    </dp-multiselect>
    <p class="lbl__hint">
      {{ Translator.trans('map.scales.select.hint') }}
    </p>
    <dp-inline-notification
      v-if="!areScalesSuitable"
      class="mt-3 mb-2"
      :message="Translator.trans('map.scales.select.error')"
      type="error" />
  </div>
</template>

<script>
import { DpInlineNotification, DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'MapAdminScales',

  components: {
    DpInlineNotification,
    DpMultiselect
  },

  props: {
    availableScales: {
      type: Array,
      required: false,
      default: () => []
    },

    selectedScales: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  data () {
    return {
      areScalesSuitable: false,
      scales: this.selectedScales
    }
  },

  watch: {
    selectedScales (newVal) {
      this.scales = newVal
    }
  },

  methods: {
    /**
     * To allow users to pick scales individually but prevent a combination
     * of scales that will cause performance issues (which is triggered by
     * scale jumps that are too big), a simple heuristic is applied here:
     * a scale must not be bigger than 50x the preceding scale.
     * @return {boolean}
     */
    checkIfScalesAreSuitable () {
      if (this.scales.length < 2) {
        return true
      }

      let scaleRatio
      for (let i = 0; i < this.scales.length - 1; i++) {
        scaleRatio = +this.scales[i + 1].value / +this.scales[i].value

        if (scaleRatio > 50) {
          return false
        }
      }

      return true
    },

    sortSelected () {
      this.scales.sort((a, b) => parseInt(a.value) - parseInt(b.value))
    },

    updateScales (scales) {
      this.areScalesSuitable = this.checkIfScalesAreSuitable()
      this.sortSelected()
      this.$emit('suitableScalesChange', this.areScalesSuitable)
      this.$emit('update', scales)
    }
  },

  mounted () {
    this.areScalesSuitable = this.checkIfScalesAreSuitable()
    this.sortSelected()
    this.$emit('suitableScalesChange', this.areScalesSuitable)
  }
}
</script>
