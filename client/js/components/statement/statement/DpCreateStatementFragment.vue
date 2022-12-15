<license>
  (c) 2010-present DEMOS E-Partizipation GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
import { mapActions, mapGetters } from 'vuex'
import { DpCopyPasteButton, DpEditor, DpMultiselect } from '@demos-europe/demosplan-ui'
import DpSelectDocument from './../fragment/SelectDocument'

export default {

  name: 'DpCreateStatementFragment',

  components: {
    DpCopyPasteButton,
    DpEditor,
    DpMultiselect,
    DpSelectDocument
  },

  props: {
    initTags: {
      required: false,
      type: Array,
      default: () => []
    },

    initCounties: {
      required: false,
      type: Array,
      default: () => []
    },

    initMunicipalities: {
      required: false,
      type: Array,
      default: () => []
    },

    initPriorityAreas: {
      required: false,
      type: Array,
      default: () => []
    },

    initFragmentText: {
      required: false,
      type: String,
      default: ''
    },

    procedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      counties: [],
      municipalities: [],
      priorityAreas: [],
      tags: [],
      department: '',
      fragmentText: ''
    }
  },

  computed: {
    ...mapGetters('assessmentTable', ['assessmentBaseLoaded'])
  },

  methods: {
    ...mapActions('assessmentTable', ['applyBaseData']),

    resetSelectMenu (field) {
      this[field] = []
    },

    setFragmentText (value) {
      this.fragmentText = value
    }
  },

  mounted () {
    this.applyBaseData([this.procedureId])
      .then(() => {
        const tagsFromStore = this.$store.getters['assessmentTable/tags']
        const selectedTags = []
        Object.values(tagsFromStore).forEach(group => {
          this.initTags.forEach(tag => {
            const foundTag = group.tags.find(tagInGroup => tagInGroup.id === tag)
            if (foundTag) {
              selectedTags.push({ id: foundTag.id, title: foundTag.name })
            }
          })
        })
        this.tags = selectedTags
      })

    this.counties = this.initCounties
    this.municipalities = this.initMunicipalities
    this.priorityAreas = this.initPriorityAreas
    this.fragmentText = this.initFragmentText
  }
}
</script>
