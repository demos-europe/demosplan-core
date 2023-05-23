<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { DpModal, DpMultiselect } from '@demos-europe/demosplan-ui'

export default {
  name: 'DpFragmentListFilterModal',
  components: {
    DpModal,
    DpMultiselect
  },
  props: {
    filters: {
      type: Array,
      required: false,
      default: () => []
    },
    appliedFilters: {
      type: Array,
      required: false,
      default: () => []
    },
    permissionFields: {
      type: Object,
      required: false,
      default: () => ({})
    }
  },
  data () {
    return {
      test: '',
      userSelection: {
        procedureName: [],
        voteAdvice: [],
        priorityAreaKeys: [],
        municipalityNames: [],
        countyNames: [],
        tagNames: [],
        elementId: [],
        paragraphId: []
      }
    }
  },
  computed: {
    filterGroups () {
      const groups = {
        submissionFilters: {
          groupLabel: 'submission',
          values: this.filters.filter(el => el.name !== 'elementId' && el.name !== 'paragraphId')
        },
        documentFilters: {
          groupLabel: 'plandocument',
          values: this.filters.filter(el => el.name === 'elementId' || el.name === 'paragraphId')
        }
      }

      // Set correct permissions for some filter fields
      Object.values(groups).forEach(group => group.values.forEach(el => {
        const permissionsToCheck = this.permissionFields[el.name]
        el.hasPermission = permissionsToCheck ? permissionsToCheck.every(permission => hasPermission(permission.replace(/([-_]\w)/g, g => g[1].toUpperCase()))) : true
      }))

      return groups
    }
  },

  methods: {
    stripRaw (string) {
      return string.split('.raw').join('')
    }
  },

  mounted () {
    // On mounted set initially selected filters by taking applied filters and finding the correct multiselect option in all options
    this.appliedFilters.forEach(filter => {
      const foundFilterInAllOptions = this.filters.find(el => el.name === filter.field)
      if (foundFilterInAllOptions) {
        const foundFilterValues = foundFilterInAllOptions.values
        let initialFilters
        if (Array.isArray(foundFilterValues)) {
          initialFilters = foundFilterValues.filter(val => filter.value.includes(val.value)) || filter.value
        } else if (typeof foundFilterValues === 'object') {
          initialFilters = Object.values(foundFilterValues).filter(val => filter.value.includes(val.value)) || filter.value
        } else if (typeof foundFilterValues === 'string') {
          initialFilters = foundFilterValues
        }

        this.userSelection[this.stripRaw(filter.field)] = initialFilters
      }
    })
  }
}
</script>
