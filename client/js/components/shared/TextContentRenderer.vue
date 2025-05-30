<script>
import { h, resolveComponent } from 'vue'
import DomPurify from 'dompurify'
import { DpLoading } from '@demos-europe/demosplan-ui'

export default {
  name: 'TextContentRenderer',

  components: {
    DpLoading
  },

  props: {
    text: {
      type: String,
      default: ''
    },

    dataText: {
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
   * changing the text property on a <text-content-renderer>-Instance will
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
  render () {
    const sanitizedText = DomPurify.sanitize(this.text, { ADD_TAGS: ['dp-obscure'] })

    const immediateComponent = {
      template: `<div class='text-wrapper w-fit' data-cy='textWrapper'>${sanitizedText}</div>`,
      data () {
        return this.dataText || {}
      }
    }

    const DpLoading = resolveComponent('dp-loading')

    return (this.text)
      ? h(immediateComponent)
      : h(DpLoading, { props: { isLoading: true } })
  }
}
</script>
