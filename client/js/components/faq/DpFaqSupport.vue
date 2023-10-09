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
      {{ Translator.trans('support.heading') }}
    </h2>
    <section class="u-mb-0_75">
      <p class="u-mb-0_75">{{ Translator.trans('support.introduction') }}</p>
      <p>Sie haben eine Frage zur Technik? Dann hilft Ihnen unser technischer Support gerne weiter.</p>
        <h3 class="font-size-h4">
          {{ Translator.trans('support.content') }}
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
    </section>
    <h3 class="font-size-h4">
      {{ Translator.trans('support.technical') }}
    </h3>
    <div class="c-support__singlecard space-inset-m">
      <p class="c-support__singlecard__content">
        <svg
          class="u-mr-0_125"
          height="18px"
          width="20px"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 28.314 28.323"
          style="enable-background:new 0 0 28.314 28.323"
          xml:space="preserve">
        <path d="m27.728 20.384-4.242-4.242a1.982 1.982 0 0 0-1.413-.586h-.002c-.534 0-1.036.209-1.413.586L17.83 18.97l-8.485-8.485 2.828-2.828c.78-.78.78-2.05-.001-2.83L7.929.585A1.986 1.986 0 0 0 6.516 0h-.001C5.98 0 5.478.209 5.101.587L.858 4.83C.729 4.958-.389 6.168.142 8.827c.626 3.129 3.246 7.019 7.787 11.56 6.499 6.499 10.598 7.937 12.953 7.937 1.63 0 2.426-.689 2.604-.867l4.242-4.242c.378-.378.587-.881.586-1.416 0-.534-.208-1.037-.586-1.415zm-5.656 5.658c-.028.028-3.409 2.249-12.729-7.07C-.178 9.452 2.276 6.243 2.272 6.244L6.515 2l4.243 4.244-3.535 3.535a.999.999 0 0 0 0 1.414l9.899 9.899a.999.999 0 0 0 1.414 0l3.535-3.536 4.243 4.244-4.242 4.242z"/>
      </svg>(+49) 40 428 46 2694</p>
      <div class="c-support__singlecard__content__fontsize">
        <h5>Servicezeiten</h5>
        <p>Montag bis Freitag: 6.30 - 18 Uhr<br>
          Freitag: 6.30 - 17Uhr
        </p>
      </div>
    </div>
  </div>
</template>
<script>

export default {
  name: 'DpFaqSupport'
}
</script>
