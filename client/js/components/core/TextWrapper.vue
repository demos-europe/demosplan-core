<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpLoading } from 'demosplan-ui/components'

export default {
  name: 'DpTextWrapper',

  functional: true,

  props: {
    text: {
      type: String,
      default: ''
    },

    data: {
      type: Object,
      default: () => ({})
    }
  },
  /**
   * Rendering dynamic elements is done by tricking the renderer into
   * rendering the content of a made up template.
   *
   * This works because vue recreates components on the fly if they are
   * not globally registered. Thus, creating the minicomponent `immediateComponent`
   * causes its template (the passed text) to be reinterpreted every time.
   *
   * Since any component will rerender if any of its props is changed,
   * changing the text property on a <dp-text-wrapper>-Instance will
   * cause it to re-evaluate its contents.
   *
   * CAVEATS:
   *
   * While this is technically able to re-evaluate any html code inside text
   * and on that way instantiate any Vue component inside, this will - as it
   * is set up right now - only work for components which are registered to
   * the global Vue object or to the currently calling parent component.
   *
   * @param h
   * @param context
   * @return {*}
   */
  render (h, context) {
    const immediateComponent = {
      template: `<div class='text-wrapper width-fit-content' data-cy='textWrapper'>${context.props.text}</div>`,
      data () {
        return context.props.data
      }
    }

    return (context.props.text)
      ? h(immediateComponent)
      : h(DpLoading, { props: { isLoading: true } })
  }
}
</script>
