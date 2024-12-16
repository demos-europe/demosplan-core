<template>
  <div>
    <div>
      {{ publicVerifiedTranslation }}
    </div>

    <div class="flex mt-1">
      <div
        class="mt-1 mr-1 w-2 h-2 rounded-full"
        :class="publicVerified === 'publication_approved' ? 'bg-status-complete' : 'bg-color--grey-light-1'" />
      <span v-text="votingStatusTranslation" />
    </div>
  </div>
</template>

<script>
export default {
  name: 'VotingStatus',

  props: {
    publicVerified: {
      type: String,
      default: ''
    }
  },

  computed: {
    publicVerifiedTranslation () {
      switch (this.publicVerified) {
        case 'publication_approved':
          return Translator.trans('enabled')
        case 'publication_pending':
          return Translator.trans('publication.pending')
        default:
          return Translator.trans('not.enabled')
      }
    },

    votingStatusTranslation () {
      return this.publicVerified === 'publication_approved'
        ? Translator.trans('voting.activated')
        : Translator.trans('voting.deactivated')
    }
  }
}
</script>
