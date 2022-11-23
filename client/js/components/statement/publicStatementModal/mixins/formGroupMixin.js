/**
 * (c) 2010-present DEMOS E-Partizipation GmbH.
 *
 * This file is part of the package demosplan,
 * for more information see the license file.
 *
 * All rights reserved
 */

import { mapMutations, mapState } from 'vuex'
import { DpInput } from '@demos-europe/demosplan-ui'
import { prefixClassMixin } from '@demos-europe/demosplan-utils'

export default {
  components: {
    DpInput
  },

  mixins: [prefixClassMixin],

  props: {
    draftStatementId: {
      type: String,
      required: false,
      default: ''
    },

    disabled: {
      type: Boolean,
      required: false,
      default: false
    },

    required: {
      type: Boolean,
      required: false,
      default: true
    }
  },

  computed: {
    ...mapState('publicStatement', ['statement'])
  },
  methods: {
    ...mapMutations('publicStatement', ['updateStatement']),

    setStatementData (data) {
      this.updateStatement({ r_ident: this.draftStatementId, ...data })
    }
  }
}
