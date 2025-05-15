# Changelog

**Versioning Scheme:**
- **Minor Version**: Incremented for each release.
- **Patch Version**: Incremented for bug fixes.

## UNRELEASED
- Add Versioning of custom fields of segments

## UNRELEASED
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
