<template>
  <div>
    <dp-label
      :text="label"
      :for="name" />
    <ul
      v-if="editable"
      :id="name"
      class="o-list o-list--csv color--grey">
      <template v-if="options.filter(opt => value.contains(opt))">
        <li
          v-for="option in options.filter(opt => value.contains(opt))"
          :key="`county-${option.name}`"
          class="o-list__item">
          {{ option.name }}
        </li>
      </template>
      <li v-else>
        -
      </li>
    </ul>
    <dp-multiselect
      v-else
      :value="value"
      @input="(changedValue) => $emit('change', changedValue)"
      :id="name"
      class="layout__item u-1-of-1 inline-block"
      label="name"
      multiple
      :options="options"
      track-by="id">
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
            :name="name" />
        </span>
      </template>
    </dp-multiselect>
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

    name: {
      type: String,
      required: false,
      default: ''
    },

    label: {
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
      type: String,
      required: false,
      default: ''
    }
  }
}
</script>
