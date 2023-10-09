<license>
(c) 2010-present DEMOS plan GmbH.

This file is part of the package demosplan,
for more information see the license file.

All rights reserved
</license>

<template>
  <div id="scFaq"
       class="c-support__wrapper">
    <h2>
      {{ 'support.heading'|trans }}
    </h2>
    <p>
      {{ 'support.introduction'|trans }}<br><br>
      Sie haben eine Frage zur Technik? Dann hilft Ihnen unser technischer Support gerne weiter.
    </p>
    <div class="u-mb-0_75">
      <h3 class="font-size-h4">
        {{ 'support.content'|trans }}
      </h3>
      <div class="c-support__groupcard space-inset-m layout__item">
        Kontakt
      </div>
      <div class="c-support__groupcard space-inset-m layout__item u-ml-0_75">
        Kontakt
      </div>
      <div class="c-support__groupcard space-inset-m layout__item u-ml-0_75">
        Kontakt
      </div>
    </div>
    <h3 class="font-size-h4">
      {{ 'support.technical'|trans }}
    </h3>
    <div class="c-support__singlecard space-inset-m">
      Technischer Support
    </div>
  </div>
</template>
<script>

export default {
  name: 'DpFaqSupport'
}
</script>
