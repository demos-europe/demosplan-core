<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpInlineNotification } from '@demos-europe/demosplan-ui'

export default {
  name: 'AdministrationImportNone',

  functional: true,

  components: {
    DpInlineNotification
  },

  render: function (createElement) {
    return createElement(DpInlineNotification, {
      props: {
        message: Translator.trans('import.options.none'),
        type: 'warning'
      }
    })
  }
}
</script>
