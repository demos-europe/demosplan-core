# Changelog

**Versioning Scheme:**
- **Minor Version**: Incremented for each release.
- **Patch Version**: Incremented for bug fixes.

## UNRELEASED

### Added
- Track recommendation versions for statements and segments with full text snapshots, exposed via API and XLSX export (permission: `feature_enable_recommendation_versions`)
- Support multiple custom field types and target contexts per project

## v4.37.0 (2026-04-27)

## v4.36.0 (2026-04-24)

### Added
- Tables support draggable column reordering, persisted locally per user
- Administrators can configure a retention period for purging deleted procedures

### Changed
- Unused tags are no longer shown in the filter flyout
- Improved performance of procedure phase resolution on list and export views
- Submitter name, address and priority are hidden in the "portrait with prioritization" export

### Fixed
- Filter flyout could be hidden behind sticky table headers
- Incorrect initial filter state on statements
- Tooltips for hidden table columns were shown incorrectly
- Hover color on checked multiselect checkbox
- Overview map was shown on initial render when it should have been hidden
- Logout button now appears on the IDP error page to terminate the Keycloak session
- Designated end dates were saved with 00:00:00 instead of 23:59:59
- Elasticsearch error when removing a keyword that was in use
- Document exports could fail when CSS contained non-numeric line-height values

## v4.35.0 (2026-04-09)

## v4.34.0 (2026-04-09)

### Added
- Console commands to list and detach organisations from customers

### Fixed
- Various visual and interaction issues in the configurable segment list table
- Organisation ID was not set when manually creating statements for institutions

## v4.33.0 (2026-04-02)

### Added
- Introduce password check to forbid re-usage of passwords when trying to change passwords. This adds a new table to the DB, user_password_history
- Make custom fields available in assessment table, original statement list, my releases list, and public statement dialogs
- Improved segment list interface with configurable column layout
- Show blueprint name and organization in platform blueprint warning

### Changed
- Improved performance of procedure statistics endpoint

### Fixed
- Global GIS layers were always saved as enabled regardless of the selected setting
- "Split now" button in statement details only assigned the statement without redirecting to the split view
- Pagination in assessment table required double click to navigate
- Public paragraph list could fail when an element was missing
- Statement votes were not immediately visible after voting
- Procedure exports could fail in certain cases

## v4.32.0 (2026-03-25)
### Added
- Export original statements as ZIP file including attachments for archiving
- Display custom fields in assessment table, original statement list, and public participation dialog
- Support for users belonging to multiple organizations with organization switcher
- Warning when saving processing steps without any step marked as "completed"
- Segment list filters for assignee and step now use OR logic within the same category
- Hints for map territory features
- Protocol entries for automatic phase switch
- Rate limiting for new statements restricted to anonymous users only
- Option for addons to customize export column definitions
- Allow editing organization names in user profile
- Clear browser data on logout for improved session security

### Changed
- Renamed "Bearbeiten" to "Details" in segment list context menu and reordered menu options
- Updated pager styling
- Protocol export uses flow text instead of table layout
- Redesigned attachment entries in original statement list

### Fixed
- Redirect to split-view after claiming statement and correct unclaim behavior to prevent false assignee confirmation dialog
- Date pickers overlapping and breaking layout in procedure creation
- Statement attachments missing from procedure zip export for ToeB
- Overlapping icons in searchbar
- Support contact creation failing
- Draft statement polygon data being truncated
- Statement data being overwritten when saving additional submitters
- Publication field incorrectly shown in citizen PDF when disabled
- Flyout trigger column not staying visible when scrolling horizontally
- Organization type permission check preventing correct access
- Pagination errors when navigating statement lists
- Elasticsearch search queries returning malformed results
- Statement voting not working on MySQL 8+

## v4.31.0 (2026-02-25)
### Changed
- bump demosplan-addon version from v0.65 to v0.67

### Added
- Add custom fields to statement modal in public detail and draft list, also display custom fields in new public participation dialog

## v4.30.3 (2026-03-03)
## v4.30.2 (2026-02-24)

## v4.30.0 (2026-02-12)
### Added
- Introduce new parameters to control the parameter name used/passed within the route
  core_procedure_slug generated redirect - in addition to the existing params for the route names
- Add edit functionality for custom fields of type multiSelect with a condition that the procedure has no statements yet.
- Add drag button to GetFeatureInfo slidebar (adjust and reuse DpUnfoldToolbarControl)

### Changed
- Extract GetFeatureInfo logic for visible WMS layers out of Map.vue and into WmsGetFeatureInfo component

## v4.29.4 (2026-03-24)

### Fixed
- Custom fields column causing errors in segments list for users without segment access permission

### Added
- Explicit identity provider type configuration for SSO connections

## v4.29.3 (2026-03-18)

### Fixed
- Requests with numeric query parameter keys being incorrectly rejected as security violations
- User lookup by email or login now works case-insensitively across all authentication flows
- SSO login redirect URL missing from dynamic OAuth client configuration

## v4.29.2 (2026-03-13)

### Added
- Per-customer SSO login configuration

### Fixed
- Additional submitters added to a statement were lost after page reload
- Flyout menu ("...") in sections view not visible with wide columns
- Error when finishing segment assignment ("Aufteilen abschließen") for certain tag configurations
- Newly created procedures not appearing in procedure list
- Boilerplate texts disappearing when individual boilerplates were removed from a category
- Data input organization dropdown showing organizations from other customers
- Organizations not receiving correct access rights after approval

## v4.29.0 (2026-02-06)

## v4.28.1 (2026-02-04)

## v4.28.0 (2026-01-28)
### Added
- Enable GetFeatureInfo requests for visible WMS layers in the map
- Add `FileService::saveBinaryFileContent()` method to save binary file content directly without manual temporary file handling
  - Accepts filename, binary content, and optional filename prefix
  - Automatically handles temporary file creation and cleanup using Symfony Filesystem (`dumpFile()` and `deleteLocalFile()`)
  - Supports virus checking and procedure/user association
  - Useful for saving already-decoded base64 content from external sources
  - Validates filename is not empty
  - Sanitizes filename using existing `sanitizeFileName()` method
- Add `FileWriteException` for dedicated file write error handling

### Changed
- Submit basic settings form automatically through the warning modal if user clicks on 'activate' (cleans up redundant code needed for scrolling the interface section into focus)
- bump demosplan-addon version from v0.64 to v0.65
- Update `FileService::saveTemporaryLocalFile()` documentation to clarify it uses configured storage backend (S3, local, or other adapters based on FILES_SOURCE environment variable)

### Fixed
- Check correct interface-checkbox state: 'checked' instead of 'disabled' (check whether interface has been activated, not whether procedure has been transmitted)

## v4.27.0 (2026-01-16)

### Added
- Add support for Abwägungsvorschlag (vote advice) dropdown functionality for statements, including display in PDF and DOCX exports.
- StatementExportModal: Adds a tags filter to the export modal, allowing statements to be filtered by tags during export

- Add permission check for agency email fields
- Move Maillane-specific database migrations to demosplan-addon-maillane
    - Remove maillane_connection and maillane_allowed_sender_email_address table creation from Version20200106150455
    - Remove maillane_connection_id field and index from _procedure table in Version20200106150455
    - Delete Version20220928083055 (procedure_id restructuring) - moved to addon
    - Maillane table management is now handled entirely by the addon migrations
- Add extra Info-WorkSheet to xksx exports by TagFilter
  Add docx Title to "Teilexport ..." if a TagFilter was applied
- Add tag-based filtering for segments of Statement exports (DOCX, XLSX, ZIP)
  that filters segments within statements
  by tag ID, tag title, tag topic ID, or tag topic title.
  Statements without matching segments are excluded from export.
  No applied tag-filter will still export all statements unchanged

- Add similar submitters to the Submitter List
- Add segment text to the boilerplate modal, if the segment and its text is available
- Attribute isPrivatePerson is used during keycloak login to recognize a private person. As a fallback Groups may still be used.
- Add a back to segments list button to the segment edit and recommendation dialog, that keeps former set filters for segments list
- Fix missing form fields in procedure basic settings
- Add anonymous voters column to statement XLSX export

## v4.25.0 (2025-11-06)

## v4.24.1 (2025-12-24)

### Features
- Add warning modal in procedure settings on form-submit when public participation phase is set and interface is not activated
- Attribute isPrivatePerson is used during keycloak login to recognize a private person. As a fallback Groups may still be used.

### Further changes
- Add separate view permission for procedure pictogram (`field_procedure_pictogram_view`)
- Move addon interface fields to public participation phase section in procedure settings
- Rename addon hook from `addon.additional.field` to `interface.fields.to.transmit`
- Make pictogram fields optional in procedure settings

## v4.24.0 (2025-11-06)
- Detect Company Department from OzgKeycloak token and assign it to user

## v4.23.0 (2025-10-22)
## v4.21.0 (2025-10-22)

## v4.18.1 (2025-10-16)
## v4.18.0 (2025-10-13)

## v4.16.3 (2026-02-05)

## v4.16.1 (2025-10-16)
- Fix addon asset build during docker build

## v4.16.0 (2025-09-30)
- Allow project specific CSS
- allow sessions to be stored in redis

- Add checkbox in procedure settings to expand procedure description in public view on page load
- Use external Geocoder API as service for address auto-suggestions

- Turn projects into yarn workspaces

### Features
- Add possibility to delete custom fields and their options

## v4.15.4 (2026-03-06)
- Fix statement vote on mysql8+, immediately show vote
- Rate limit new statements only for anonymous users
- Set TUS resumable upload cache TTL to a week by default

## v4.15.3 (2025-12-02)

## v4.15.2 (2025-10-24)
- fix zip download for older uploads

## v4.15.0 (2025-09-15)

## v4.14.3 (2026-02-06)
## v4.14.2 (2025-12-02)
## v4.14.0 (2025-09-15)
- Add html paragraph import from odt files

## v4.12.0 (2025-09-10)
- Add ODT export functionality for assessment tables
- Add checkbox in procedure settings to expand procedure description in public view on page load
- Use external Geocoder API as service for address auto-suggestions
- Update demosplan-addon from v0.59 to v0.60

## v4.11.0 (2025-08-27)
- Allow to edit custom field type singleSelect
- Mark outdated map layers in the map settings

## v4.10.1 (2025-08-13)
- Display Keycloak logout countdown warning in the header and logout automatically

## v4.10.0 (2025-07-30)

## v4.9.1 (2025-08-07)
- Fix time based procedure phase switch

## v4.9.0 (2025-07-30)
- Allow filtering of institution tags in AdminstrationMemberList / refactor twig
- Add configurable feedback control for public participation statements
- Migrate to Tailwind CSS v4

## v4.7.0 (2025-07-18)

## v4.6.3 (2026-02-18)
- Allow to configure procedures to accept or not anonymous statements
- Export Original Statements as docx in the Statement List
- Allow filtering of institution tags in DpAddOrganizationList
- Allow filtering of institution tags in AdminstrationMemberList / refactor twig

## v4.6.0 (2025-07-18)
- Allow to configure procedures to accept or not anonymous statements
- Allow filtering of institution tags in AdminstrationMemberList / refactor twig
- Add configurable feedback control for public participation statements

## v4.5.0 (2025-06-25)
- Export Original Statements as docx in the Statement List
- Allow filtering of institution tags in DpAddOrganizationList

## v4.4.0 (2025-06-13)

## v4.3.8 (2026-03-13)
- fix DS-505: prevent StatementMeta children from overwriting unrelated statement data on save
- fix DPLAN-17389: make column with flyout trigger sticky

## v4.3.7 (2026-02-17)

## v4.3.5 (2025-11-24)

## 4.3.4 (2025-11-14)
- implement option to import additional submitters via statement ID in statement imports via xlsx
- adjust example statement import xlsx files

## v4.3.3 (2025-11-04)
- smart pagination for segment navigation

## v4.3.1-ewm (2025-09-25)
- allow sessions to be stored in redis

## v4.3.0 (2025-06-13)
- Add Versioning of custom fields of segments
- Update Elasticsearch to version 8
- Export Original Statements as csv in the Statement List

## v4.1.0 (2025-05-21)
## v4.0.0 (2025-05-21)
- Update to symfony 6.4

## v3.3.0 (2025-05-13)
- restore deleted logger entry

## v3.2.0 (2025-05-13)
- Enable Custom Field feature on segments: Allow users to add/edit custom fields to their segments     based on the custom fields defined in the procedure
- Add Custom Field feature: Allow users to add custom fields to their procedures

## v3.0.0 (2025-04-09)
- create deletion report entry when procedure is deleted
- Migrate to Vue 3
- Implement VirusCheckSocket to directly check files for viruses via remote sockets
- Enhance security by sanitizing HTTP headers to prevent injection attacks

## v2.27.1 (2025-04-09)
- Fix zip import encoding and recursion

## v2.27.0 (2025-03-12)
- Enable to send Statement final notice using RpcRequest and Vue.js
- Add the possibility to export synopsis without personal data.

## v2.26.5 (2025-03-28)
- new parameter proxy_no_proxy to allow to exclude local services from the proxy

## v2.26.4 (2025-03-26)
- new parameter cafile to set the path to the CA file for the symfony http client

## v2.26.3 (2025-03-24)
- Use less strict samesite cookie policy for session cookies to allow login via keycloak

## v2.26.2 (2025-03-14)
- Create report entries on create, update, deletion of an element, paragraph , singleDocument, mapDrawing or mapDrawing-explanation

## v2.26.0 (2025-02-25)

### Features
- Extend safelist for purge css to include all plyr classes
- Allow flag on external links to indicate this URL should only be shown for user(roles) with a specific permission
- Institution tag management: Add search field and filters to institution list
- Procedure basic settings: Move procedure location up under the "internal" section
- TagsListEditForm: add a confirmation message before deleting the Tag or Topic

### Further changes
- Segments list: Use DpSearchField for custom search
- DpInlineNotification: Set margin from outside the component (instead of inside)
- Rework Tags-Lists: Now works with APi2.0 and is extendable by addons.
- Enable the DELETE method for the AdministratableUser resource type
- TagsList: trigger an update request only when the title is modified
- DpCreateTag: Ensure the new tag retains its relationship to the Topic after creation


## 2.20.0

### Features
- Admin institution list: Institutions can now be tagged filtered by categories

### Fixes
- Several bug fixes

### Further changes
- Addons can now be installed automatically, when listed in the `addons.yml` file
- Several major dependencies have been updated
