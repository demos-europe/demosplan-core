/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

export default {
  name: 'DpFunctional',

  functional: true,

  props: {
    value: {
      type: [String, Number],
      required: true
    },

    isEditing: {
      type: Boolean,
      required: true
    }
  },

  render: function (h, ctx) {
    let out
    if (ctx.props.isEditing) {
      out = h('textarea', [ctx.props.value])
    } else {
      // This renders a text node. It uses VUE's internal API. Rendering any html element would lead to excessive DOM size
      out = ctx._v(ctx.props.value)
    }
    return out
  }
}
