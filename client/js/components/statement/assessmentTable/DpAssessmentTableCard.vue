<license>
  (c) 2010-present DEMOS plan GmbH.

  This file is part of the package demosplan,
  for more information see the license file.

  All rights reserved
</license>

<documentation>
  <!--
    the TabelCard is One Element of the A-Table
    It holds the statement-Tab and the fragment-Tab
    the Fragment-Tab is a child-component, where the statement-tab lives in here.
  -->
  <usage>
    <dp-assessment-table-card
      statement-id="statementId"
      current-user-id="userId"
      current-user-name="username"
      view-mode="viewMode"
      :procedure-statement-priority-area="true/false"
      :public-participation-publication-enabled="true/false"
      init-statement="statement-object"/>
  </usage>
</documentation>

<template>
  <li
    :id="'itemdisplay_' + statement.id"
    :data-cy="dataCy"
    class="c-at-item"
    v-cloak>
    <!--  item header  -->
    <div v-if="statement.movedToProcedureId === ''">
      <div
        class="c-at-item__header layout--flush u-pt-0_25 u-ph-0_5 flow-root"
        data-add-animation>
        <!--  id, date created, assignment  -->
        <div class="c-at-item__row-icon layout__item">
          <input
            type="checkbox"
            name="statement[]"
            data-cy="checkStatement"
            :id="`checkStatement:${displayedCheckboxId}`"
            :value="displayedCheckboxId"
            :disabled="hasPermission('area_statements_fragment') && Object.keys(selectedFragments).length > 0"
            :title="hasPermission('area_statements_fragment') && Object.keys(selectedFragments).length > 0 ? Translator.trans('unselect.entity.first', {entity: Translator.trans('statement')}) : false"
            :checked="isSelected"
            @change="toggleSelection">
          <br>
          <!-- Claim -->
          <dp-claim
            class="c-at-item__row-icon inline-block"
            entity-type="statement"
            :assigned-id="(statement.assignee.id || '')"
            :assigned-name="(statement.assignee.name || '')"
            :assigned-organisation="(statement.assignee.orgaName || '')"
            :current-user-id="currentUserId"
            :current-user-name="currentUserName"
            :is-loading="updatingClaimState"
            v-if="hasPermission('feature_statement_assignment')"
            @click="updateClaim" />
        </div><!--
       --><div class="layout--flush layout__item c-at-item__row">
          <label
            :for="`checkStatement:${displayedCheckboxId}`"
            class="layout__item u-1-of-6 u-mb-0 u-pb-0_25">
            <v-popover
              placement="top"
              trigger="hover focus">
              <i
                v-if="statement.isCluster && hasPermission('feature_statement_cluster')"
                class="fa fa-object-group"
                aria-hidden="true" />
              <span data-cy="statementExtID">{{ extid }}</span>
              <!-- Display icon anyways when moved from/to another procedure, otherwise display it when frontend state changes  -->
              <i
                v-if="!!statement.movedFromProcedureName"
                class="fa fa-exchange"
                aria-hidden="true" />

              <span class="weight--normal block">
                {{ statementDate(statement.submitDate) }}
              </span>

              <template v-slot:popover>
                <template v-if="statement.authoredDate > 0">
                  {{ Translator.trans('statement.date.authored') }}: {{ statementDate(statement.authoredDate) }}<br>
                </template>

                {{ Translator.trans('statement.date.submitted') }}: {{ statementDate(statement.submitDate) }}<br>
                {{ Translator.trans('phase') }}: {{ statement.phase }}

                <template v-if="statement.movedFromProcedureId !== ''">
                  <br>
                  {{ Translator.trans('movedFrom') }}: {{ statement.movedFromProcedureName }}
                  <br>
                  {{ Translator.trans('formerExternId') }}: {{ statement.formerExternId }}
                </template>
              </template>
            </v-popover>
          </label><!--

       --><div
            class="layout__item u-3-of-6 o-hellip">
            <!--  author  -->
            <div
              v-if="false === statement.isCluster"
              class="u-1-of-1 u-pb-0_25">
              <div class="o-hellip--nowrap u-1-of-1">
                <v-popover
                  class="o-hellip--nowrap"
                  placement="top"
                  trigger="hover focus">
                  <!-- Findings when refactoring this template part:
                  - manual statements will have (`isSubmittedByCitizen === true`)
                    when selected r_role == 0, and initialOrganisationName == '' when selected r_role == 1 (#1)
                  - every platform visitor is assigned an orga + department
                  - anonymous users (not logged in) will have `isSubmittedByCitizen === true` and
                    orgaDepartmentName: 'anonym' (#2)
                  - `anonymous` will be set to `true` for not logged-in users that choose to make an anonymous
                    statement (#3)
                    see https://yaits.demos-deutschland.de/w/demosplan/functions/statement/ for details
                   -->

                  <template v-if="hasOwnProp(statement, 'initialOrganisationName')">
                    <!--  Initially visible content  -->
                    <!--  see (#1)  -->
                    <template v-if="statement.initialOrganisationName == ''">
                      <template v-if="hasPermission('field_statement_user_organisation') && !!statement.userOrganisation">
                        {{ statement.userOrganisation }}
                      </template>
                      <template v-else>
                        {{ Translator.trans('institution') }}
                      </template>
                    </template>
                    <template v-else>
                      {{ statement.initialOrganisationName }}
                    </template>

                    <!--  department / citizen name -->
                    <template v-if="hasOwnProp(statement,'initialOrganisationDepartmentName') && statement.initialOrganisationDepartmentName !== ''">
                      <template v-if="statement.initialOrganisationDepartmentName === 'anonym'">
                        <!-- display authorName if this is a non-anonymous statement of a user who is not logged in -->
                        <br>
                        <template v-if="!statement.anonymous">
                           {{ statement.authorName }}
                        </template>
                        <!-- display 'anonymous' if it is an anonymous statement of a user who is not logged in #}-->
                        <template v-else>
                          {{ Translator.trans('anonymous') }}
                        </template>
                      </template>
                      <template v-else>
                        <!--  display department, if this is a statement of a loggedin user  -->
                        <br>{{ statement.initialOrganisationDepartmentName }}
                      </template>
                    </template>
                  </template>

                  <!--  Popover content  -->
                  <template
                    v-if="hasOwnProp(statement, 'initialOrganisationName')"
                    v-slot:popover>
                    <!--  see (#1)  -->
                    <template
                      v-if="!statement.isSubmittedByCitizen && (hasPermission('field_statement_user_organisation') === false && !statement.userOrganisation)">
                      {{ Translator.trans('organisation') }}: {{ statement.initialOrganisationName !== '' ? statement.initialOrganisationName : Translator.trans('institution') }} <br>
                      <!--  see (#2)  -->
                      <template v-if="!!statement.initialOrganisationDepartmentName && statement.initialOrganisationDepartmentName !== ''">
                        {{ Translator.trans('department') }}: {{ statement.initialOrganisationDepartmentName }}<br>
                      </template>
                    </template>

                    <!--  see (#3)  -->
                    <!-- if
                     - non-anonymous institution (submitName === given name) or
                     - non-anonymous citizen (manual statement) (submitName === given name) or
                     - anonymized citizen/institution (submitName === 'anonymisiert')
                     display submitName -->
                    <template v-if="statement.submitName !== ''">
                      {{ Translator.trans('submitted.author') }}: {{ statement.submitName }}
                    </template>

                    <!-- if non-anonymous (registered or unregistered) citizen, including manual statement -->
                    <template v-else-if="statement.submitName === '' && !statement.anonymous && statement.authorName !== '' && statement.isSubmittedByCitizen">
                      {{ Translator.trans('submitted.author') }}: {{ statement.authorName }}
                    </template>

                    <!-- if anonymous citizen (unregistered or manual statement) -->
                    <template v-else-if="statement.submitName === '' && (statement.authorName === '' || statement.anonymous) && statement.isSubmittedByCitizen">
                      {{ Translator.trans('submitted.author') }}: {{ Translator.trans('citizen.anonymous') }}
                    </template>

                    <!--  additional user fields: userState, userGroup, userOrganisation, userPosition  -->
                    <template v-if="hasPermission('field_statement_user_state') && !!statement.userState">
                      <br>{{ Translator.trans('state') }}: {{ statement.userState }}
                    </template>

                    <template v-if="hasPermission('field_statement_user_group') && !!statement.userGroup">
                      <br>{{ Translator.trans('group') }}: {{ statement.userGroup }}
                    </template>

                    <template v-if="hasPermission('field_statement_user_organisation') && !!statement.userOrganisation">
                      <br>{{ Translator.trans('organisation') }}: {{ statement.userOrganisation }}
                    </template>

                    <template v-if="hasPermission('field_statement_user_position') && !!statement.userPosition">
                      <br>{{ Translator.trans('position') }}: {{ statement.userPosition }}
                    </template>
                  </template>
                  <template v-else>
                    {{ Translator.trans('notspecified') }}
                  </template>
                </v-popover>
              </div>
            </div>
            <div
              v-else-if="true === statement.isCluster && statement.clusterName !== ''"
              class="u-1-of-1 u-pb-0_25">
              <div class="o-hellip--nowrap u-1-of-1">
                {{ Translator.trans('statement.cluster.name') }}: {{ statement.clusterName }}
              </div>
            </div>
          </div><!--

       --><div
            class="inline-block u-pt-0_25 text-right float-right">
            <!-- Votes -->
            <span
              v-tooltip="`${Translator.trans('voted.by')}: ${statement.votesNum}`"
              class="c-at-item__badge-icon"
              v-if="hasPermission('feature_statements_vote') && statement.votesNum > 0">
              <i
                class="fa fa-comment-o u-mr-0_125"
                aria-hidden="true" />{{ statement.votesNum }}
            </span>

            <!-- Likes -->
            <span
              v-tooltip="`${Translator.trans('liked.by')}: ${statement.likesNum}`"
              class="c-at-item__badge-icon"
              v-if="hasPermission('feature_statements_like') && statement.likesNum > 0">
              <i
                class="fa fa-chevron-circle-up u-mr-0_125"
                aria-hidden="true" />{{ statement.likesNum }}
            </span>

            <!-- Visibility for public -->
            <i
              v-tooltip="`${Translator.trans('publish.on.platform')}: ${Translator.trans(statement.publicVerifiedTranslation)}`"
              v-if="hasPermission('field_statement_public_allowed') && publicParticipationPublicationEnabled"
              class="c-at-item__badge-icon fa"
              :class="publicVerifiedKeyIcon"
              aria-hidden="true" />

            <!-- Navigation for toOriginal, detail, createFragment -->
            <table-card-flyout-menu
              entity="statement"
              :entity-id="statement.id"
              :extern-id="extid"
              :statement-detail-path="statementDetailPath"
              :statement-original-id="statement.originalId"
              :editable="isClaimed"
              :statement-procedure-id="statementProcedureId"
              :is-cluster="statement.isCluster"
              @statement:copy="openCopyStatementModal(statement.id)"
              @statement:move="moveStatement(statement.id)" />

            <!-- Toggle expanded/collapsed -->
            <button
              type="button"
              data-cy="toggleViewExpandedCollapsed"
              class="u-pr-0_5 u-pl-0_25 btn--blank o-link--default"
              @click="toggleView(expanded ? 'collapsed' : 'expanded')">
              <i
                class="fa"
                :class="{'fa-angle-down': !expanded, 'fa-angle-up': expanded}"
                style="font-size: 1.8rem; line-height: 1.2em;" />
            </button>
          </div>
          </div>
      </div>

      <!--  item content - hidden with table-cards:toggle-view 'collapsed' (List view)  -->
      <div
        data-cy="statementCardDetail"
        v-show="expanded">
        <dp-item-row
          v-if="hasPermission('area_statements_fragment')"
          class="u-mt-0_25">
          <dp-fragments-switcher
            :statement-id="statement.id"
            :statement-fragments-total="statement.fragmentsTotal"
            :statement-fragments-length="statement.filteredFragmentsCount"
            :is-filtered="isFiltered"
            :statement-tab-visible="tab === 'statement'"
            @toggletabs="toggleTab"
            @fragments:showall="showAllFragments" />
        </dp-item-row>

        <!--  statement tab  -->
        <div
          v-show="tab === 'statement'"
          class="bg-color-light">
          <!--  status / priorities  -->
          <dp-item-row
            v-if="hasPermission('field_statement_status') || hasPermission('field_statement_priority')"
            title="status"
            class="u-pb-0">
            <dl class="layout--flush layout__item c-at-item__row u-1-of-1">
              <dd
                v-if="hasPermission('field_statement_status')"
                class="layout--flush layout__item"
                :class="hasPermission('field_statement_priority') ? 'border--right u-3-of-6' : 'u-1-of-1'">
                <dp-edit-field-single-select
                  label="Status"
                  field-key="status"
                  :entity-id="statement.id"
                  :options="status"
                  :value="statement.status"
                  @field:update="updateStatement"
                  @field:save="data => saveStatement(data, 'attribute', 'status')"
                  ref="status"
                  :editable="isClaimed"
                  :label-grid-cols="4" />
              </dd><!--
                Priorities
             --><dd
                v-if="hasPermission('field_statement_status')"
                class="layout__item"
                :class="hasPermission('field_statement_priority') ? 'u-pl-0_5 u-3-of-6' : 'u-1-of-1'">
                  <dp-edit-field-single-select
                    label="priority"
                    :entity-id="statement.id"
                    field-key="priority"
                    :value="statement.priority"
                    :options="priorities"
                    :editable="isClaimed"
                    :label-grid-cols="4"
                    ref="priority"
                    @field:update="updateStatement"
                    @field:save="data => saveStatement(data, 'attribute', 'priority')" />
                </dd>
            </dl>
          </dp-item-row>

          <!--  VoteAdvice (VotePla / VoteStk)  -->
          <dp-item-row
            v-if="hasPermission('field_statement_vote_stk') || hasPermission('field_statement_vote_pla') || hasPermission('feature_statements_fragment_vote')"
            title="fragment.voteAdvice"
            class="u-pb-0">
            <dl class="layout--flush layout__item c-at-item__row u-1-of-1">
              <dd
                v-if="hasPermission('field_statement_vote_stk') && (hasPermission('feature_statements_fragment_advice') || hasPermission('feature_statements_fragment_vote'))"
                class="layout__item"
                :class="[hasPermission('field_statement_vote_pla') ? 'border--right u-3-of-6 u-pr-0_5' : 'u-1-of-1']">
                <dp-edit-field-single-select
                  :label="Translator.trans('fragment.voteAdvice.short')"
                  field-key="voteStk"
                  :label-grid-cols="(hasPermission('field_statement_vote_pla')) ? 4 : 2"
                  :entity-id="statement.id"
                  :options="adviceValues"
                  :value="statement.voteStk"
                  @field:update="updateStatement"
                  @field:save="data => saveStatement(data, 'attribute', 'voteStk')"
                  ref="voteStk"
                  :editable="isClaimed" />
              </dd><!--
             --><dd
                  v-if="hasPermission('field_statement_vote_pla') && hasPermission('feature_statements_fragment_vote')"
                  class="layout__item"
                  :class="(hasPermission('field_statement_vote_stk')) ? 'u-3-of-6 u-pl-0_5' : 'u-1-of-1'">
                  <dp-edit-field-single-select
                    :label="Translator.trans('fragment.vote.short')"
                    field-key="votePla"
                    :label-grid-cols="4"
                    :entity-id="statement.id"
                    :options="adviceValues"
                    :value="statement.votePla || ''"
                    @field:update="updateStatement"
                    @field:save="data => saveStatement(data, 'attribute', 'votePla')"
                    ref="votePla"
                    :editable="isClaimed" />
                </dd>
            </dl>
          </dp-item-row>

          <!--  Location.
                  If there is any location data (counties, priority areas, municipalities or map drawing), it is displayed.
                  dplan.procedureStatementPriorityArea is set in base.html.twig.
                  @TODO the use of dplan.procedureStatementPriorityArea is not quite clear.
          -->
          <dp-item-row
            v-if="showLocationRow"
            title="statement.map.reference"
            class="u-pb-0">
            <dp-edit-field-multi-select
              v-if="hasPermission('field_statement_county')"
              class="relative"
              label="counties"
              :entity-id="statement.id"
              field-key="counties"
              :value="statement.counties"
              :options="counties"
              :editable="isClaimed"
              ref="counties"
              @field:update="updateStatement"
              @field:save="data => saveStatement(data, 'relationship', 'counties')" />

            <dp-edit-field-multi-select
              v-if="hasPermission('field_statement_municipality') && statementFormDefinitions.mapAndCountyReference.enabled"
              class="relative"
              label="municipalities"
              :entity-id="statement.id"
              field-key="municipalities"
              :value="statement.municipalities"
              :options="municipalities"
              :editable="isClaimed"
              ref="municipalities"
              @field:update="updateStatement"
              @field:save="data => saveStatement(data, 'relationship', 'municipalities')" />

            <dp-edit-field-multi-select
              v-if="dplan.procedureStatementPriorityArea && statementFormDefinitions.mapAndCountyReference.enabled"
              class="relative"
              label="priorityAreas.all"
              :entity-id="statement.id"
              field-key="priorityAreas"
              :value="statement.priorityAreas"
              :options="priorityAreas"
              :editable="isClaimed"
              ref="priorityAreas"
              @field:update="updateStatement"
              @field:save="data => saveStatement(data, 'relationship', 'priorityAreas')" />

            <template v-if="statement.polygon !== '' && statementFormDefinitions.mapAndCountyReference.enabled">
              <dt class="layout__item u-pb-0_25 u-pt-0_25 u-1-of-6 weight--bold">
                {{ Translator.trans('statement.map.drawing') }}:
              </dt><!--
                --><dd class="layout__item u-1-of-6 u-pt-0_25">
                  <a
                    class="u-5-of-6 relative"
                    @click.prevent.stop="toggleMapModal(JSON.parse(statement.polygon))"
                    href="#"
                    rel="noopener">
                    {{ Translator.trans('see') }}
                  </a>
                </dd>
            </template>
          </dp-item-row>

          <!--  elements  -->
          <dp-item-row
            title="elements.assigned"
            v-if="hasPermission('field_procedure_elements')"
            class="u-pb-0">
            <dp-edit-field-single-select
              class="relative"
              label="document"
              :entity-id="statement.id"
              field-key="elements"
              :value="statement.elementId"
              :options="elements"
              :editable="isClaimed"
              ref="elementId"
              @field:save="data => saveStatement(data, 'relationship', 'elements')" />

            <!-- paragraphParent, i.e. original version of the paragraph -->
            <dp-edit-field-single-select
              v-if="elementHasParagraphs"
              class="relative"
              label="paragraph"
              :entity-id="statement.id"
              field-key="paragraph"
              :value="statement.paragraphParentId"
              :options="selectedElementParagraph"
              :editable="isClaimed"
              ref="paragraph"
              @field:save="data => saveStatement(data, 'relationship', 'paragraph')" />

            <!-- documentParent -->
            <dp-edit-field-single-select
              v-if="elementHasDocuments"
              class="relative"
              label="file"
              :entity-id="statement.id"
              field-key="document"
              :value="statement.documentParentId"
              :options="selectedElementDocuments"
              :editable="isClaimed"
              ref="document"
              @field:save="data => saveStatement(data, 'relationship', 'document')" />

            <dl v-if="hasPermission('field_procedure_elements') && hasPermission('area_statements_fragment') && statement.fragmentsElements.length > 0">
              <dt class="layout__item u-1-of-6 weight--bold">
                {{ Translator.trans('fragments.elements') }}:
              </dt><!--
                --><dd class="layout__item u-5-of-6">
                  <ul class="u-mb-0">
                    <li
                      v-for="fragmentElement in statement.fragmentsElements"
                      :key="fragmentElement.id">
                      {{ fragmentElement.elementTitle }}
                      <template v-if="hasOwnProp(fragmentElement,'paragraphTitle') && fragmentElement.paragraphTitle !== null">
                        - {{ fragmentElement.paragraphTitle }}
                      </template>
                    </li>
                  </ul>
                </dd>
            </dl>
          </dp-item-row>

          <!--  tags  -->
          <dp-item-row
            title="tags"
            v-if="hasPermission('feature_statements_tag')"
            class="u-pb-0">
            <dp-edit-field-multi-select
              class="relative"
              label="tags"
              :entity-id="statement.id"
              field-key="tags"
              :value="statement.tags"
              :options="tags"
              group-values="tags"
              data-cy="statementTag"
              group-label="title"
              :is-group-select="true"
              :editable="isClaimed"
              ref="tags"
              @field:update="updateStatement"
              @field:save="data => saveStatement(data, 'relationship', 'tags')" />
          </dp-item-row>

          <!-- Statement / Recommendation Text -->
          <dp-item-row
            title="statement.text"
            class="u-pb-0 u-pt-0"
            is-fullscreen-row
            :border-bottom="(statement.files.length > 0)">
            <template>
              <dp-claim
                v-if="hasPermission('feature_statement_assignment')"
                class="c-at-item__row-icon inline-block fullscreen-claim"
                :assigned-id="(statement.assignee.id || '')"
                :assigned-name="(statement.assignee.name || '')"
                :assigned-organisation="(statement.assignee.orgaName || '')"
                :current-user-id="currentUserId"
                :current-user-name="currentUserName"
                entity-type="statement"
                :is-loading="updatingClaimState"
                @click="updateClaim" />
              <dl class="flex">
                <!--
                    Statement text
                    With tiptap we can set obscure as prop always when the obscure button should be visible in the field,
                    because the permission check (featureObscureText) takes place in tiptap
                    -->
                <editable-text
                  class="u-pb-0_5 u-pr-0_5 u-pt-0_25 u-1-of-2 border--right"
                  title="statement"
                  :procedure-id="procedureId"
                  :initial-text="initText"
                  :entity-id="statement.id"
                  :editor-id="statement.id + '_statementText'"
                  :initial-is-shortened="statement.textIsTruncated || false"
                  full-text-fetch-route="dm_plan_assessment_get_statement_ajax"
                  field-key="text"
                  :editable="isClaimed"
                  edit-label="statement.edit"
                  mark
                  :obscure="hasPermission('feature_obscure_text')"
                  strikethrough
                  height-limit-element-label="statement"
                  @field:save="data => saveStatement(data, 'attribute', 'text')"
                  ref="text" />
                <!--
                  Recommendation text
               -->
                <editable-text
                  class="u-pb-0_25 u-pl-0_5 u-pt-0_25 u-1-of-2"
                  title="recommendation"
                  :procedure-id="procedureId"
                  :initial-text="recommendationText"
                  :entity-id="statement.id"
                  :editor-id="statement.id + '_recommendation'"
                  :initial-is-shortened="statement.recommendationIsTruncated || false"
                  full-text-fetch-route="dm_plan_assessment_get_recommendation_ajax"
                  field-key="recommendation"
                  link-button
                  :editable="isClaimed"
                  edit-label="recommendation.of.statement.edit"
                  height-limit-element-label="fragment"
                  @field:save="data => saveStatement(data, 'attribute', 'recommendation')"
                  ref="recommendation"
                  :boiler-plate="hasPermission('area_admin_boilerplates')">
                  <template
                    v-slot:hint
                    v-if="recommendationPubliclyVisible">
                    {{ Translator.trans('recommendation.publicly.visible.short') }}
                    <dp-contextual-help
                      class="float-right u-mt-0_125"
                      :text="Translator.trans('recommendation.publicly.visible')" />
                  </template>
                </editable-text>
              </dl>
            </template>
          </dp-item-row><!--
         --><div
              v-if="statement.files.length > 0 || statement.sourceAttachment !== '' && statement.sourceAttachment?.filename"
              class="layout--flush u-pv-0_25 u-ph-0_5">
              <div
                class="layout__item c-at-item__row-icon color--grey"
                :title="Translator.trans('statement.files.uploaded')">
                <i
                  class="fa fa-paperclip"
                  aria-hidden="true" />
              </div><!--

           --><div class="layout--flush layout__item c-at-item__row break-words">
                <a
                  v-if="statement.sourceAttachment?.filename && hasPermission('feature_read_source_statement_via_api')"
                  class="u-pr-0_5 o-hellip border--right u-mr-0_5"
                  :href="Routing.generate('core_file_procedure', { hash: statement.sourceAttachment.hash, procedureId: procedureId })"
                  rel="noopener"
                  target="_blank"
                  :title="Translator.trans('attachment.original')">
                  {{ statement.sourceAttachment.filename }}
                </a>
                <!-- Attached files -->
                <a
                  v-for="file in statement.files"
                  :key="file.hash"
                  class="u-pr-0_5 o-hellip"
                  :href="Routing.generate('core_file_procedure', { hash: file.hash, procedureId: procedureId })"
                  rel="noopener"
                  target="_blank"
                  :title="Translator.trans('attachments')">
                  {{ file.filename }}
                </a>
              </div>
            </div>
        </div>

        <!-- Fragments Tab -->
        <div
          v-if="hasPermission('area_statements_fragment')"
          class="bg-color-light"
          v-show="tab==='fragments'">
          <div class="layout--flush u-p-0_5 u-pt-0_25 border--top u-nojs-show--block">
            <div class="layout__item c-at-item__row-icon color--grey" /><!--
           --><div class="layout__item c-at-item__row weight--bold">
                {{ Translator.trans('fragments') }}:
              </div>
          </div>

          <dp-fragment-list
            :csrf-token="csrfToken"
            :procedure-id="procedureId"
            :statement-id="statement.id"
            :current-user-id="currentUserId"
            :current-user-name="currentUserName"
            :initial-total-fragments-count="statement.fragmentsCount"
            :initial-filtered-fragments-count="statement.initialFilteredFragmentsCount"
            :is-filtered="isFiltered"
            ref="fragmentList"
            :fragments-loading="fragmentsLoading" />
        </div>
      </div>
    </div>

    <!-- Item that has been moved to procedure. minimal item header  -->
    <div
      v-else-if="statement.movedToProcedureId !== ''"
      class="c-at-item__header layout--flush u-pt-0_25 u-ph-0_5 flow-root"
      data-add-animation>
      <!--  id, date created, assignment  -->
      <div class="c-at-item__row-icon layout__item">
        <input
          type="checkbox"
          :value="statement.id"
          :checked="isSelected"
          @change="toggleSelection"
          :disabled="Object.keys(selectedFragments).length > 0">
      </div><!--

      --><div class="layout--flush layout__item c-at-item__row">
          <label
            :for="statement.id + ':item_check[]'"
            class="layout__item u-1-of-6 u-mb-0 u-pb-0_25">
            <v-popover
              class="inline-block"
              placement="top">
              {{ extid }}

              <i
                class="fa fa-exchange"
                aria-hidden="true" />

              <span class="weight--normal block">
                {{ statementDate(statement.submitDate) }}
              </span>

              <template v-slot:popover>
                <span
                  class="hidden"
                  :class="{'inline-block': assessmentBaseLoaded}">
                  <template v-if="statement.authoredDate > 0">
                    <!-- remove comment when in vue to show the date -->
                    {{ Translator.trans('statement.date.authored') }}: {{ statementDate(statement.authoredDate) }} <br>
                  </template>
                  {{ Translator.trans('statement.date.submitted') }}: {{ statementDate(statement.submitDate) }} <br>
                  {{ Translator.trans('phase') }}: {{ statement.phase }}

                  <template v-if="statement.movedFromProcedureId !== ''">
                    <br>
                    {{ Translator.trans('movedFrom') }}: {{ statement.movedFromProcedureName }}
                    <br>
                    {{ Translator.trans('formerExternId') }}: {{ statement.formerExternId }}
                  </template>
                </span>
              </template>
            </v-popover>
          </label>
          <div
            v-if="accessibleProcedureIds.findIndex(el => el === statement.movedToProcedureId) >= 0"
            class="float-right u-mt-0_5 u-mb-0_75 u-mr"
            v-tooltip="Translator.trans('statement.moved', {name: statement.movedToProcedureName})">
            <a
              :href="Routing.generate('dm_plan_assessment_single_view', { statement: statement.movedStatementId, procedureId: statement.movedToProcedureId })"
              rel="noopener">
              {{ Translator.trans('movedTo') }}: {{ statement.movedToProcedureName.slice(0,70) }}{{ (statement.movedToProcedureName.length > 70) ? '...' : '' }}
            </a>
          </div>
          <div
            v-else
            class="float-right u-mt-0_5 u-mb-0_75 u-mr"
            v-tooltip="Translator.trans('statement.moved', {name: statement.movedToProcedureName})">
            {{ Translator.trans('movedTo') }}: {{ statement.movedToProcedureName.slice(0,55) }}{{ (statement.movedToProcedureName.length > 55) ? '...' : '' }} ({{ Translator.trans('inaccessible') }})
          </div>
        </div>
    </div>
  </li>
</template>

<script>
import { dpApi, DpContextualHelp, formatDate, hasOwnProp, VPopover } from '@demos-europe/demosplan-ui'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { Base64 } from 'js-base64'
import DpClaim from '../DpClaim'
import DpEditFieldMultiSelect from './DpEditFieldMultiSelect'
import DpEditFieldSingleSelect from './DpEditFieldSingleSelect'
import DpItemRow from './ItemRow'
import EditableText from './EditableText'
import TableCardFlyoutMenu from '@DpJs/components/statement/assessmentTable/TableCardFlyoutMenu'

export default {
  name: 'DpAssessmentTableCard',

  components: {
    DpContextualHelp,
    DpClaim,
    DpEditFieldMultiSelect,
    DpEditFieldSingleSelect,
    DpFragmentList: () => import(/* webpackChunkName: "dp-fragment-list" */ './DpFragmentList'),
    DpFragmentsSwitcher: () => import(/* webpackChunkName: "dp-fragments-switcher" */ './DpFragmentsSwitcher'),
    DpItemRow,
    EditableText,
    TableCardFlyoutMenu,
    VPopover
  },

  props: {
    csrfToken: {
      type: String,
      required: true
    },

    dataCy: {
      type: String,
      required: false,
      default: 'statementCard'
    },

    isSelected: {
      required: true,
      type: Boolean
    },

    statementId: {
      required: true,
      type: String
    },

    /*
     * Needed for statement history view
     * we don't want to use current procedure id but the procedureId of the statement
     */
    statementProcedureId: {
      required: true,
      type: String
    }
  },

  data () {
    return {
      // We have to use $store.state... notation because at that moment the maps for state/getters are not yet initialized
      expanded: this.$store.state.AssessmentTable.currentTableView === 'statement' || this.$store.state.AssessmentTable.currentTableView === 'fragments',
      tab: this.$store.state.AssessmentTable.currentTableView === 'fragments' ? 'fragments' : 'statement',
      updatingClaimState: false,
      fragmentsLoading: false,
      placeholderStatementId: null
    }
  },

  computed: {
    // Get the Statement from the Store (if not Present there use initial data)
    ...mapState('Statement', { statement (state) { return state.statements[this.statementId] } }),
    ...mapGetters('AssessmentTable', [
      'adviceValues',
      'assessmentBase',
      'assessmentBaseLoaded',
      'counties',
      'documents',
      'elements',
      'municipalities',
      'paragraph',
      'priorities',
      'priorityAreas',
      'procedureId',
      'status',
      'tags'
    ]),
    ...mapGetters('Fragment', ['selectedFragments', 'fragmentsByStatement']),
    ...mapState('Statement', ['selectedElements', 'statements', 'isFiltered']),

    ...mapState('AssessmentTable',
      [
        'accessibleProcedureIds',
        'currentUserId',
        'currentUserName',
        'procedureStatementPriorityArea',
        'publicParticipationPublicationEnabled',
        'statements',
        'viewMode',
        'statementFormDefinitions'
      ]
    ),

    /*
     *  When moving a stn to another procedure, the checkbox has to get the id of the newly generated
     *  placeholderStatement to make it appear in the exports. To be refactored when AT is all-vue.
     */
    displayedCheckboxId () {
      return this.placeholderStatementId ? this.placeholderStatementId : this.statementId
    },

    elementHasDocuments () {
      return this.statement.elementId && Array.isArray(this.documents[this.statement.elementId])
    },

    elementHasParagraphs () {
      return this.statement.elementId && Array.isArray(this.paragraph[this.statement.elementId])
    },

    /*
     * The Label for the external Id
     * Needed for forms and to display "copy" for the user before the id-number
     */
    extid () {
      // @improve T12645
      return (hasOwnProp(this.statement, 'parentId') && hasOwnProp(this.statement, 'originalId') && this.statement.originalId !== this.statement.parentId) ? Translator.trans('copyof') + ' ' + this.statement.externId : this.statement.externId
    },

    initText () {
      return Base64.encode(this.statement.text)
    },

    isClaimed () {
      return hasPermission('feature_statement_assignment') ? this.statement.assignee.id === this.currentUserId : true
    },

    publicVerifiedKeyIcon () {
      let icon

      if (this.statement.publicVerified !== 'no_check_since_not_allowed' && this.statement.publicVerified !== 'no_check_permission_disabled') {
        switch (this.statement.publicVerified) {
          case 'publication_pending':
            icon = 'fa-exclamation-circle color-message-severe-fill'
            break
          case 'publication_approved':
            icon = 'fa-eye'
            break
          case 'publication_rejected':
            icon = 'fa-ban'
            break
          default:
            icon = 'fa-eye-slash color--grey'
        }
      }

      return icon
    },

    recommendationPubliclyVisible () {
      return this.statement.publicVerified === 'publication_approved' && hasPermission('feature_statements_public_statement_recommendation_visible')
    },

    recommendationText () {
      return Base64.encode(this.statement.recommendation)
    },

    selectedElementDocuments () {
      return this.documents[this.statement.elementId] || []
    },

    selectedElementParagraph () {
      return this.paragraph[this.statement.elementId] || []
    },

    showLocationRow () {
      return this.statementFormDefinitions.countyReference.enabled || this.statementFormDefinitions.mapAndCountyReference.enabled ? (this.statement.polygon !== '' || dplan.procedureStatementPriorityArea || hasPermission('field_statement_county') || hasPermission('field_statement_municipality') || hasPermission('field_statement_priority_area')) : false
    },

    statementDetailPath () {
      return (this.statement.isCluster)
        ? Routing.generate('DemosPlan_cluster_view', { statement: this.statementId, procedureId: this.procedureId, isCluster: true })
        : Routing.generate('dm_plan_assessment_single_view', { statement: this.statementId, procedureId: this.procedureId })
    }

  },

  methods: {
    ...mapActions('Fragment', [
      'removeFragmentFromSelectionAction',
      'loadFragments',
      'setSelectedFragmentsAction',
      'resetSelection'
    ]),

    ...mapActions('Statement', [
      'updateStatementAction',
      'addToSelectionAction',
      'removeFromSelectionAction',
      'setAssigneeAction'
    ]),

    ...mapMutations('AssessmentTable', [
      'setModalProperty',
      'setProperty'
    ]),

    ...mapMutations('Statement', [
      'addStatement',
      'updateStatement',
      'replaceStatement'
    ]),

    fetchStatementFragments () {
      if (Object.values(this.fragmentsByStatement(this.statementId).fragments).length === 0 && this.statement.fragmentsTotal !== 0) {
        this.fragmentsLoading = true
        this.loadFragments({ procedureId: this.procedureId, statementId: this.statementId })
          .then(() => {
            // Mark fragments as selected (checkbox state)
            this.setSelectedFragmentsAction()
            this.fragmentsLoading = false
            if (this.intersectionObserver) {
              this.intersectionObserver.disconnect()
              this.intersectionObserver = null
            }
          })
      }
    },

    handleMoveToProcedure ({ movedToProcedureId, statementId, movedStatementId, placeholderStatementId, movedToAccessibleProcedure, movedToProcedureName }) {
      if (statementId === this.statementId) {
        this.updateStatement({ id: statementId, movedToProcedureId: movedToProcedureId, movedToProcedureName: movedToProcedureName, movedStatementId: movedStatementId })
        this.placeholderStatementId = placeholderStatementId
        if (JSON.parse(sessionStorage.getItem('selectedElements')) !== null) {
          this.removeFromSelectionAction(this.statementId)
        }

        // Handle checked fragments of the moved statement (remove them from selection)
        const fragmentsInSessionStorage = JSON.parse(sessionStorage.getItem('selectedFragments'))

        if (fragmentsInSessionStorage && hasOwnProp(fragmentsInSessionStorage, this.procedureId)) {
          const selectedFragmentsOfMovedStatement = Object.values(fragmentsInSessionStorage[this.procedureId]).filter(fragment => fragment.statementId === this.statementId)
          if (selectedFragmentsOfMovedStatement.length > 0) {
            selectedFragmentsOfMovedStatement.forEach(frag => this.removeFragmentFromSelectionAction(frag.id))
          }
        }
        this.$nextTick(() => {
          const statementElem = document.getElementById('itemdisplay_' + statementId)
          statementElem.focus()
          const header = statementElem.querySelector('[data-add-animation]')
          if (header.classList.contains('animation--bg-highlight-grey--light-1')) {
            header.classList.remove('animation--bg-highlight-grey--light-1')
          }
          header.classList.add('animation--bg-highlight-grey--light-1')
        })
      }
    },

    hasOwnProp (obj, prop) {
      return hasOwnProp(obj, prop)
    },

    moveStatement (id) {
      if ((hasPermission('feature_statement_assignment') ? (this.currentUserId === this.statement.assignee.id) : true) === false) {
        return false
      }
      //  MoveStatement() is triggered from statement footer button
      this.$root.$emit('moveStatement:toggle', id)
    },

    openCopyStatementModal (id) {
      if ((hasPermission('feature_statement_assignment') ? (this.currentUserId === this.statement.assignee.id) : true) === false) {
        return false
      }

      this.setModalProperty({ prop: 'copyStatementModal', val: { show: true, statementId: id } })
    },

    preparePayload (data, propType, fieldName) {
      let payload = {
        id: data.id,
        type: 'Statement'
      }

      if (propType === 'attribute') {
        payload = {
          ...payload,
          attributes: {
            [fieldName]: data[fieldName]
          }
        }
      }

      if (propType === 'relationship') {
        /**
         *  When element is saved, reset paragraph (which holds the currently selected paragraph) and document
         */
        if (fieldName === 'elements') {
          payload = {
            ...payload,
            relationships: {
              elements: {
                data: {
                  id: data.elements,
                  type: 'Elements'
                }
              },
              paragraph: {
                data: null
              },
              document: {
                data: null
              }
            }
          }

          this.resetRelatedFields()
        }

        if (fieldName !== 'elements') {
          const type = fieldName === 'paragraph' ? 'ParagraphVersion' : `${fieldName.charAt(0).toUpperCase()}${fieldName.slice(1)}`

          if (Array.isArray(data[fieldName])) {
            payload = {
              ...payload,
              relationships: {
                [fieldName]: {
                  data: data[fieldName].map(el => {
                    return {
                      id: el,
                      type: type
                    }
                  })
                }
              }
            }
          }

          if (!Array.isArray(data[fieldName])) {
            payload = {
              ...payload,
              relationships: {
                [fieldName]: {
                  data: {
                    id: data[fieldName],
                    type: type
                  }
                }
              }
            }
          }
        }
      }

      return payload
    },

    resetRelatedFields () {
      if (this.elementHasParagraphs && this.$refs.paragraph) {
        this.resetSelectedParagraph()
      }

      if (this.elementHasDocuments && this.$refs.document) {
        this.resetSelectedDocument()
      }
    },

    resetSelectedDocument () {
      this.$refs.document.selected = ''
      this.$refs.document.selectedBefore = ''
    },

    resetSelectedParagraph () {
      this.$refs.paragraph.selected = ''
      this.$refs.paragraph.selectedBefore = ''
    },

    /**
     *
     * @param data {Object}
     * @param propType {String} - can be either 'attribute' or 'relationship'
     * @param fieldName {String} - the name of the property as sent to BE
     */
    saveStatement (data, propType, fieldName) {
      const payload = this.preparePayload(data, propType, fieldName)
      this.$emit('statement:updated')
      //  ##### Fire store action #####
      this.updateStatementAction({ data: payload }).then(updated => {
        let updatedField = ''
        //  Unset loading state of saved field
        for (const field in updated) {
          // If TAGS are changed, we have to add the tag content to consideration text field
          if (field === 'tags') {
            // This string will concatenate all tags' texts we want to add to recommendation
            let textToBeAdded = ''
            const fieldToUpdate = this.$refs.recommendation

            // We return an array with promises to be able to wait until all requests are finished and then add the text at once
            const tags = Object.values(updated.tags).map(tag => {
              return dpApi.post(Routing.generate('dm_plan_assessment_get_boilerplates_ajax', {
                tag: tag.id,
                procedure: this.procedureId
              }))
                .then(data => {
                  if (data.data.code === 100 && data.data.success) {
                    // If the tag's text is already in recommendation, we don't want to add it again
                    if (fieldToUpdate.$data.fullText.includes(data.data.body) || (fieldToUpdate.$refs.editor && fieldToUpdate.$refs.editor.$data.editor.getHTML().includes(data.data.body))) {
                      return false
                    } else {
                      textToBeAdded += '<p>' + data.data.body + '</p>'
                      return Promise.resolve(true)
                    }
                  }
                })
            })

            // After all requests are completed we can add the tag texts to Begründungsfeld
            Promise.all(tags).then(() => {
              if (textToBeAdded !== '') {
                // If there is no input, we want to overwrite the 'k.A.' default string
                fieldToUpdate.$data.fullText !== 'k.A.'
                  ? fieldToUpdate.$data.fullText += textToBeAdded
                  : fieldToUpdate.$data.fullText = textToBeAdded

                fieldToUpdate.$data.isEditing = true
                dplan.notify.notify('info', Translator.trans('info.tag.text.added'))
              }
            })
          }

          if (this.$refs[field]) {
            updatedField = field
            //  Handle components that use <dp-edit-field>
            const editFieldComponent = this.$refs[field].$children.find(child => child.$options.name === 'DpEditField')
            if (editFieldComponent) {
              editFieldComponent.$data.loading = false
              editFieldComponent.$data.editingEnabled = false
            }
            //  Handle components that have a loading state by themselves
            if (hasOwnProp(this.$refs[field].$data, 'loading')) {
              this.$refs[field].$data.loading = false
            }
            if (hasOwnProp(this.$refs[field].$data, 'editingEnabled')) {
              this.$refs[field].$data.editingEnabled = false
            }
          }
        }

        // Used in DpVersionHistory to update items in version history sidebar
        this.$root.$emit('entity:updated', this.statementId, 'statement')

        return updatedField
      }).then(updatedField => {
        this.$root.$emit('entityTextSaved:' + this.statementId, { entityId: this.statementId, field: updatedField }) // Used in EditableText.vue to update short and full texts
      })
    },

    showAllFragments (checked) {
      if (this.tab === 'statement') {
        this.toggleTab(false)
      }
      this.$refs.fragmentList.showAll = checked
    },

    statementDate (d) {
      return formatDate(d)
    },

    toggleMapModal (drawingData) {
      this.$root.$emit('toggleMapModal', drawingData)
    },

    toggleSelection () {
      if (this.isSelected === false) {
        const statement = {
          id: this.statementId,
          extid: this.extid,
          movedToProcedure: (this.statement.movedToProcedureId !== ''),
          assignee: this.statement.assignee,
          isCluster: this.statement.isCluster
        }
        // Make sure that no fragments are checked if we check statement (fragments may not be loaded at this point so the checkbox in TableCard will not be disabled)
        this.resetSelection()
        this.$emit('statement:addToSelection', statement)
      }

      if (this.isSelected === true) {
        this.$emit('statement:removeFromSelection', this.statementId)
      }
    },

    // ShowStatementTab is true if statements should be shown and false if we want to show fragments
    toggleTab (showStatementTab) {
      //  Fragment tab is only toggled when fragments are enabled
      if (showStatementTab === false) {
        this.tab = 'fragments'

        // Load fragments if not yet loaded
        this.fetchStatementFragments()
      } else if (showStatementTab === true) {
        this.tab = 'statement'
      } else {
        //  Fallback
        this.tab = 'statement'
      }
    },

    toggleView (view) { // Possible values for view: collapsed, expanded, statement, fragments
      //  Disable toggle actions on moved items
      if (this.statement.placeholderStatementId) {
        return
      }

      if (view === 'collapsed') {
        this.expanded = false
      } else {
        this.expanded = true
        if (view !== 'expanded') { // If it is not collapsed or expanded it has to be statement or fragments
          this.toggleTab(view === 'statement')
        }
      }
    },

    updateClaim () {
      this.updatingClaimState = true
      this.setAssigneeAction({ statementId: this.statementId, assigneeId: (hasOwnProp(this.statement.assignee, 'id') && this.currentUserId === this.statement.assignee.id ? '' : this.currentUserId) })
        .then(response => {
          this.updatingClaimState = false
          this.$root.$emit('entity:updated', this.statementId, 'statement')
        })
    }
  },

  mounted () {
    this.$root.$on('statement:moveToProcedure', (moveToProcedureParams) => this.handleMoveToProcedure(moveToProcedureParams))

    // If the tab change has been triggered by filter modal (all filters applied on fragments) we have to lazy load fragments. We set an observer to observe current element scroll position. If element is in viewport or 50px below, the fragments will be fetched (the fetchStatementsFragments function will be triggered) and observer will be disconnected from this element.
    if (this.tab === 'fragments') {
      this.intersectionObserver = new IntersectionObserver((e) => {
        if (e[0].isIntersecting) {
          this.fetchStatementFragments()
        }
      }, { root: null, rootMargin: '50px' })
      this.intersectionObserver.observe(this.$el)
    }

    // Update card view if table view has been change (per Ansicht Button)
    this.$store.subscribe(mutation => {
      if (mutation.type === 'assessmentTable/setProperty' && mutation.payload.prop === 'currentTableView') {
        this.toggleView(mutation.payload.val)
      }
    })
  }
}
</script>
