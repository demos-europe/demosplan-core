<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { h, resolveComponent } from 'vue'
import { DpInlineNotification } from '@demos-europe/demosplan-ui'

export default {
  name: 'AdministrationImportNone',

  components: {
    DpInlineNotification
  },

  render () {
    const DpInlineNotification = resolveComponent('dp-inline-notification')
    return () => h(DpInlineNotification, {
      props: {
        message: Translator.trans('import.options.none'),
        type: 'warning'
      }
    })
  }
}
</script>
