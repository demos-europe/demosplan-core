<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import DpEmailList from './DpEmailList'
import DpMultiselect from '@DpJs/components/core/form/DpMultiselect'

export default {
  name: 'DpMasterBasicSettings',

  components: {
    DpEmailList,
    DpMultiselect,
    DpTiptap: () => import('@DemosPlanCoreBundle/components/DpTiptap'),
    DpUploadFiles: () => import('@DemosPlanCoreBundle/components/DpUpload/DpUploadFiles')
  },

  props: {
    authUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    initSelectedAuthUsers: {
      required: false,
      type: Array,
      default: () => []
    },

    agencies: {
      required: false,
      type: Array,
      default: () => []
    },

    initSelectedAgencies: {
      required: false,
      type: Array,
      default: () => []
    }
  },

  data () {
    return {
      selectedAgencies: this.agencies.filter(agency => this.initSelectedAgencies.includes(agency.id)),
      selectedAuthUsers: this.authUsers.filter(user => this.initSelectedAuthUsers.includes(user.id))
    }
  },

  methods: {
    selectAllAuthUsers () {
      this.selectedAuthUsers = this.authUsers
    },

    sortSelected (type) {
      const area = `selected${type}`
      this[area].sort((a, b) => (a.name > b.name) ? 1 : ((b.name > a.name) ? -1 : 0))
    },

    unselectAllAuthUsers () {
      this.selectedAuthUsers = []
    }
  }
}
</script>
