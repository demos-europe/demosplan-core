<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<script>
export default {
  name: 'DpSendBeacon',

  props: {
    url: {
      type: String,
      required: true
    }
  },

  methods: {
    sendBeacon () {
      navigator.sendBeacon(this.url)
    }
  },

  mounted () {
    window.onbeforeunload = this.sendBeacon
  },

  render () {
    return null
  }
}
</script>
