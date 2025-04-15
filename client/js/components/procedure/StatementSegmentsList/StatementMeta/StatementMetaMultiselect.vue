<template>
  <div>
    <dp-label
      class="my-1"
      :for="name"
      :text="label" />
    <dp-multiselect
      v-if="editable"
      v-model="selectedItems"
      :id="name"
      class="w-full inline-block"
      label="name"
      multiple
      :options="options"
      track-by="id"
      @input="(changedValue) => $emit('change', changedValue, name)">
      <template v-slot:option="{ props }">
        {{ props.option.name }}
      </template>
      <template v-slot:tag="{ props }">
        <span class="multiselect__tag">
          {{ props.option.name }}
          <i
            aria-hidden="true"
            @click="props.remove(props.option)"
            tabindex="1"
            class="multiselect__tag-icon" />
          <input
            type="hidden"
            :value="props.option.id"
            :name="name">
        </span>
      </template>
    </dp-multiselect>
    <ul
      v-else
      :id="name"
      class="o-list o-list--csv color--grey">
      <template v-if="filteredByOptionsValue.length > 0">
        <li
          v-for="value in filteredByOptionsValue"
          :key="`${name}-${value.name}`"
          class="o-list__item">
          {{ value.name }}
        </li>
      </template>
      <li v-else>
        -
      </li>
    </ul>
  </div>
</template>

<script>
import {
  DpLabel,
  DpMultiselect
} from '@demos-europe/demosplan-ui'

export default {
  name: 'StatementMetaMultiselect',

  components: {
    DpLabel,
    DpMultiselect
  },

  props: {
    editable: {
      type: Boolean,
      required: false,
      default: false
    },

    label: {
      type: String,
      required: false,
      default: ''
    },

    name: {
      type: String,
      required: false,
      default: ''
    },

    options: {
      type: Array,
      required: false,
      default: () => []
    },

    value: {
      type: Array,
      required: false,
      default: () => []
    }
  },

  emits: [
    'change'
  ],

  data () {
    return {
      selectedItems: this.value
    }
  },

  computed: {
    filteredByOptionsValue () {
      // Filters the value array based on whether each item's name exists in the options array
      return this.value.filter(item => {
        return this.options.some(option => option.name === item.name)
      })
    }
  }
}
</script>
