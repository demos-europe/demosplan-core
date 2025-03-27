<template>
  <div class="border">
    <component
      v-for="addon in loadedAddons"
      :is="addon.component"
      :key="`addon:${addon.name}`"
      :data-cy="`addon:${addon.name}`"
      :ref="`${addon.name}${refComponent}`"
      v-bind="addonProps"
      @addonEvent:emit="(event) => $emit(event.name, event.payload)" />
  </div>
</template>

<script>
import { defineAsyncComponent, defineComponent, getCurrentInstance, reactive, resolveComponent, shallowRef } from 'vue'
import loadAddonComponents from '@DpJs/lib/addon/loadAddonComponents'
import { DpButton } from '@demos-europe/demosplan-ui'

export default {
  name: 'AddonWrapper',

  components: {
    DpButton
  },

  props: {
    /**
     * The addonProps prop will be bound to the addon components to add props dynamically.
     */
    addonProps: {
      type: Object,
      required: false,
      default: () => {}
    },

    /**
     * The hookName prop will be used to load an addon via the generic rpc route.
     */
    hookName: {
      type: String,
      required: true,
      default: ''
    },

    refComponent: {
      type: String,
      required: false,
      default: 'Addon'
    }
  },

  data () {
    return {
      loadedAddons: reactive([])
    }
  },

  async mounted () {
    const app = getCurrentInstance().appContext.app

    console.log('test')
    const addons = await loadAddonComponents(this.hookName)
    // addons.forEach(addon => {
    //   if (!this.$.appContext.components.hasOwnProperty(addon.name)) {
    //     // app.component(addon.name, window[addon.name].default)
    //   }
    // })

    this.$nextTick(() => {
      addons.forEach((addon, idx) => {
        this.loadedAddons.push({
          component: window[addon.name].default,
          name: addon.name
        })
      })
    })

    // const asyncComponent = defineAsyncComponent(() => new Promise((resolve, reject) => {
    //   return resolve({
    //     template: '<div>I am async!</div>'
    //   })
    // }))
    //
    // console.log(asyncComponent)
    // this.loadedAddons.push({
    //   component: asyncComponent,
    //   name: 'testme'
    // })
    // app.component('testme', asyncComponent)

    this.$emit('addons:loaded', this.loadedAddons.map(addon => addon.name))
  }
}
</script>
