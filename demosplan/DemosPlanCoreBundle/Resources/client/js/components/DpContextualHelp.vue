<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--

  The DpContextualHelp component renders an icon indicating contextual help,
  which shows a tooltip able to handle text or html content.

  -->
  <usage>
    <dp-contextual-help :text="Translator.trans('statements.import.hint')" />
  </usage>
</documentation>

<script>
export default {
  name: 'DpContextualHelp',

  functional: true,

  props: {
    /**
     * A translation key representing the actual tooltip content.
     * May actually be any string including html.
     */
    text: {
      type: String,
      required: true
    }
  },

  render: function (h, { props, data }) {
    return h('i', {
      attrs: {
        class: `fa fa-question-circle ${typeof data.staticClass === 'undefined' ? '' : data.staticClass}`,
        'aria-label': Translator.trans('contextual.help')
      },
      directives: [
        {
          name: 'tooltip',
          value: props.text
        }
      ]
    })
  }
}
</script>
