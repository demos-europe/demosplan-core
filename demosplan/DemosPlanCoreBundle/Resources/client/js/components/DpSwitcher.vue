<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<!--
This component is the generic switcher (toggle) component. It toggles the view/values between two states. The two options (states) are defined in parent with slots: option1 and option2.
If the first option is checked, the data: isFirstOptionActive = true. On click the toggle method emits the 'input' event to the parent.

For now the component is used in DpFragmentSwitcher component, which toggles the statements/fragments view in the assessment table.
-->

<template>
  <div class="o-switcher">
    <input
      type="checkbox"
      :id="inputId "
      @change="toggle">
    <label :for="inputId">
      <div
        class="o-switcher__option float--left"
        :class="{'o-switcher__option--checked': active}">
        <slot name="option1" />
      </div>
      <div
        class="o-switcher__option float--right"
        :class="{'o-switcher__option--checked': false === active}"
        data-cy="fragmentTab">
        <slot name="option2" />
      </div>
    </label>
  </div>
</template>

<script>
export default {
  name: 'DpSwitcher',

  props: {
    active: {
      required: false,
      type: Boolean,
      default: true
    },

    inputId: {
      required: true,
      type: String
    }
  },

  methods: {
    toggle () {
      this.$emit('input', (this.active === false))
    }
  }
}
</script>
