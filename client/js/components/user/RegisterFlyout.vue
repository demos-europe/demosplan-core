<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <dp-flyout
    align="left"
    class="u-ml-0_5">
    <template v-slot:trigger>
      <div
        class="c-header__nav-item-link"
        data-cy="headerNav:registerAction">
        {{ Translator.trans('register.action') }}
        <i
          class="fa fa-angle-down"
          aria-hidden="true" />
      </div>
    </template>

    <div class="space-stack-s">
      <a
        class="block"
        data-cy="headerNav:register"
        :href="Routing.generate('DemosPlan_citizen_registration_form')"
        v-text="Translator.trans('register')" />
      <a
        class="block"
        data-cy="headerNav:registerOrga"
        :href="Routing.generate('DemosPlan_orga_register_form')"
        v-text="Translator.trans('register.orga')" />
    </div>
  </dp-flyout>
</template>

<script>
import { DpFlyout } from '@demos-europe/demosplan-ui'

export default {
  name: 'RegisterFlyout',

  components: {
    DpFlyout
  },

  mounted () {
    /*
     * The placeholder element is shown until all javascript has been loaded
     * and parsed to prevent a flash of missing content.
     */
    const placeholder = document.getElementById('registerFlyoutPlaceholder')
    if (placeholder) {
      placeholder.remove()
    }
  }
}
</script>
