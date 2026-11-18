/**
 * (c) 2010-present DEMOS plan GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { defineComponent as compatDefineComponent, configureCompat, h, useModel } from 'vue'
import { mount } from '@vue/test-utils'
import { vi } from 'vitest'
import { defineComponent as vue3ModeDefineComponent } from '@DpJs/lib/vue/vueWithVue3Mode'

// The app keeps COMPONENT_V_MODEL at its default; setup.ts enables it globally, which would override MODE 3
configureCompat({ COMPONENT_V_MODEL: undefined })

// Same shape as karten's IconButton: no emits, so a click listener must fall through to the root <button>
const createButtonWithoutEmits = (defineComponentVariant) => defineComponentVariant({
  setup () {
    return () => h('button', { type: 'button' })
  },
})

// Same shape as karten's DpsFormInput: v-model via modelValue and useModel
const createModelValueInput = (defineComponentVariant) => defineComponentVariant({
  props: {
    modelValue: { type: String, default: undefined },
    modelModifiers: { type: Object, default: () => ({}) },
  },
  emits: ['update:modelValue'],
  setup (props) {
    const model = useModel(props, 'modelValue')

    return () => h('input', {
      onInput: (event: Event) => {
        model.value = (event.target as HTMLInputElement).value
      },
    })
  },
})

const mountWithParent = (child, childProps) => mount(compatDefineComponent({
  setup () {
    return () => h(child, childProps)
  },
}))

describe('vueWithVue3Mode', () => {
  it('sets Vue 3 mode on components defined by an options object', () => {
    expect(vue3ModeDefineComponent({}).compatConfig.MODE).toBe(3)
  })

  it('sets Vue 3 mode on components defined by a setup function', () => {
    expect(vue3ModeDefineComponent(() => () => null).compatConfig.MODE).toBe(3)
  })

  it('keeps a component\'s own compatConfig keys but forces Vue 3 mode', () => {
    const component = vue3ModeDefineComponent({ compatConfig: { MODE: 2, ATTR_ENUMERATED_COERCION: false } })

    expect(component.compatConfig).toEqual({ MODE: 3, ATTR_ENUMERATED_COERCION: false })
  })

  it('passes a click listener to the root element', async () => {
    const onClick = vi.fn()
    const wrapper = mountWithParent(createButtonWithoutEmits(vue3ModeDefineComponent), { onClick })

    await wrapper.find('button').trigger('click')

    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('drops the click listener without vueWithVue3Mode (Vue 2 compat behavior)', async () => {
    const onClick = vi.fn()
    const wrapper = mountWithParent(createButtonWithoutEmits(compatDefineComponent), { onClick })

    await wrapper.find('button').trigger('click')

    expect(onClick).not.toHaveBeenCalled()
  })

  it('receives modelValue and emits update:modelValue', async () => {
    const onUpdate = vi.fn()
    const child = createModelValueInput(vue3ModeDefineComponent)
    const wrapper = mountWithParent(child, { modelValue: 'start', 'onUpdate:modelValue': onUpdate })

    expect(wrapper.findComponent(child).props('modelValue')).toBe('start')

    const input = wrapper.find('input')

    input.element.value = 'typed'
    await input.trigger('input')

    expect(onUpdate).toHaveBeenCalledWith('typed')
  })

  it('loses modelValue without vueWithVue3Mode (Vue 2 compat behavior)', async () => {
    const onUpdate = vi.fn()
    const child = createModelValueInput(compatDefineComponent)
    const wrapper = mountWithParent(child, { modelValue: 'start', 'onUpdate:modelValue': onUpdate })

    expect(wrapper.findComponent(child).props('modelValue')).toBeUndefined()

    const input = wrapper.find('input')

    input.element.value = 'typed'
    await input.trigger('input')

    expect(onUpdate).not.toHaveBeenCalled()
  })
})
