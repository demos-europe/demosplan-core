# Changelog

**Versioning Scheme:**
- **Minor Version**: Incremented for each release.
- **Patch Version**: Incremented for bug fixes.

## UNRELEASED

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
## v4.16.1 (2025-10-16)
- Fix addon asset build during docker build

## v4.16.3 (2026-02-05)
## v4.16.0 (2025-09-30)
- Allow project specific CSS
- allow sessions to be stored in redis

- Add checkbox in procedure settings to expand procedure description in public view on page load
- Use external Geocoder API as service for address auto-suggestions

- Turn projects into yarn workspaces

### Features
- Add possibility to delete custom fields and their options

## v4.15.3 (2025-12-02)
## v4.15.2 (2025-10-24)
- fix zip download for older uploads

## v4.15.0 (2025-09-15)
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
## v4.6.0 (2025-07-18)
- Allow to configure procedures to accept or not anonymous statements
- Allow filtering of institution tags in AdminstrationMemberList / refactor twig
- Add configurable feedback control for public participation statements 


## v4.5.0 (2025-06-25)
- Export Original Statements as docx in the Statement List
- Allow filtering of institution tags in DpAddOrganizationList

## v4.4.0 (2025-06-13)

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

## v2.27.1 (2025-04-09)
- Fix zip import encoding and recursion

## v3.0.0 (2025-04-09)
- create deletion report entry when procedure is deleted
- Migrate to Vue 3
- Implement VirusCheckSocket to directly check files for viruses via remote sockets
- Enhance security by sanitizing HTTP headers to prevent injection attacks

## v2.26.5 (2025-03-28)
- new parameter proxy_no_proxy to allow to exclude local services from the proxy

## v2.26.4 (2025-03-26)
- new parameter cafile to set the path to the CA file for the symfony http client

## v2.26.3 (2025-03-24)
- Use less strict samesite cookie policy for session cookies to allow login via keycloak

## v2.26.2 (2025-03-14)
- Create report entries on create, update, deletion of an element, paragraph , singleDocument, mapDrawing or mapDrawing-explanation

## v2.27.0 (2025-03-12)
- Enable to send Statement final notice using RpcRequest and Vue.js
- Add the possibility to export synopsis without personal data.


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
