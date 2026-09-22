<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<template>
  <div>
    <slot
      :counties="counties"
      :department="department"
      :fragment-text="fragmentText"
      :municipalities="municipalities"
      :priority-areas="priorityAreas"
      :reset-select-menu="resetSelectMenu"
      :set-fragment-text="setFragmentText"
      :tags="tags"
      :update-field="updateField"
    />
  </div>
</template>

<script>
import { mapActions, mapGetters } from 'vuex'

export default {

  name: 'DpCreateStatementFragment',

  props: {
    initTags: {
      required: false,
      type: Array,
      default: () => [],
    },

    initCounties: {
      required: false,
      type: Array,
      default: () => [],
    },

    initMunicipalities: {
      required: false,
      type: Array,
      default: () => [],
    },

    initPriorityAreas: {
      required: false,
      type: Array,
      default: () => [],
    },

    initFragmentText: {
      required: false,
      type: String,
      default: '',
    },

    procedureId: {
      required: true,
      type: String,
    },

    statementText: {
      required: false,
      type: String,
      default: '',
    },
  },

  data () {
    return {
      counties: [],
      municipalities: [],
      priorityAreas: [],
      tags: [],
      department: '',
      fragmentText: '',
    }
  },

  computed: {
    ...mapGetters('AssessmentTable', ['assessmentBaseLoaded']),
  },

  methods: {
    ...mapActions('AssessmentTable', ['applyBaseData']),

    resetSelectMenu (field) {
      this[field] = []
    },

    setFragmentText () {
      this.fragmentText = this.statementText
    },

    updateField (field, value) {
      this[field] = value
    },
  },

  mounted () {
    this.applyBaseData([this.procedureId])
      .then(() => {
        const tagsFromStore = this.$store.getters['AssessmentTable/tags']
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
  },
}
</script>
