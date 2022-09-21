# Changelog
All notable changes to demosplan will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)

Categories used are Added, Changed, Deprecated, Removed, Fixed or Security
Dates are formatted as YYYY-MM-DD like 2017-08-18.

## [unreleased]
**Added**
- [T7005](https://yaits.demos-deutschland.de/T7005) include intrusion detection system

## [v3.1] planfest sprint 1.4 2017-09-19 - 2017-10-29
**Added**
- [T3742](https://yaits.demos-deutschland.de/T3742) automatic change of the status of a planning category by date 
- [T3934](https://yaits.demos-deutschland.de/T3934) check if upload of zip documents was possible and message in case of problems
- [T6230](https://yaits.demos-deutschland.de/T6230) zip upload: message if there is a pdf on the same level like the main folder of the zip because upload is not possible
- [T5659](https://yaits.demos-deutschland.de/T5659) execute transliteration at the moment of zip creation

## [Unreleased] teilhabe sprint 1.0 and 1.1, edited from 2017-09-01 to 2017-09-25
**Added**
- [T5831](https://yaits.demos-deutschland.de/T5831) Voting for statements unregistrated possible 
- [T5910](https://yaits.demos-deutschland.de/T5910) accessible statement modal
- [T5899](https://yaits.demos-deutschland.de/T5899) possibility to navigate trough public detail via keyboard
- [T5910](https://yaits.demos-deutschland.de/T5910) skip markers for navigation trough public detail via keyboard
- [T5993](https://yaits.demos-deutschland.de/T5993) planning document pager in public detail aria optimized
- [T5898](https://yaits.demos-deutschland.de/T5898) keyboard navigation overview for screendreaders
- [T5913](https://yaits.demos-deutschland.de/T5913) new security permissions: xsrf-token, honeypot, cookies, request limiting
- [T5868](https://yaits.demos-deutschland.de/T5868) statements on documents possible
- [T5816](https://yaits.demos-deutschland.de/T5816) new informations in statement modal selectable
  
**Changed**
- [T5912](https://yaits.demos-deutschland.de/T5912) feature for co-signing

## [v3.0] robob sprint 5.2 and 5.3, edited from 2017-08-25 to 2017-08-29
**Added**
- [T5556](https://yaits.demos-deutschland.de/T5556) **User-Story** possibility of compact PDF export with only statements or with statements and fragments 
- [T5861](https://yaits.demos-deutschland.de/T5861) compact PDF's added to ZIP folder 
- [T5782](https://yaits.demos-deutschland.de/T5782) **User-Story** selection of county notifications in draft statement folder possible if shortend statement workflow is set 
- [T5090](https://yaits.demos-deutschland.de/T5090) dynamic editing of reason in consideration table 
- [T464](https://yaits.demos-deutschland.de/T464), [T5724](https://yaits.demos-deutschland.de/T5724), **User-Story** Institution coordinator may choose to use shortened statement workflow (only draft and submission folder)
- [T5725](https://yaits.demos-deutschland.de/T5725) **User-Story** if shortend statement workflow is selected, statements in release folders are sent to draft statement folder of responsible members of public agency
- [T5812](https://yaits.demos-deutschland.de/T5812) **User-Story** co-signing in public statement tab in public detail possible
- [T461](https://yaits.demos-deutschland.de/T461)Possibility **User-Story** to set links in paragraphs
- [T5550](https://yaits.demos-deutschland.de/T5550) **User-Story** xlsx export of tags and reasons as well as potential areas and reasons in consideration table
- Possibility to use Logger statically anywhere via MessageBag::addMessage()
- Initial Plugin system
- Include Fragments in procedure export
- [T5843](https://yaits.demos-deutschland.de/T5843) added psr-4 compliance


**Changed**
- [T5809](https://yaits.demos-deutschland.de/T5809) blacking of anonymized statement for planners in public statement tab of public detail 
- [T5806](https://yaits.demos-deutschland.de/T5806) column size of statement and reason in compact docx export
- [T5757](https://yaits.demos-deutschland.de/T5757) check if group is assigned before deleting of the group is possible
- [T5698](https://yaits.demos-deutschland.de/T5698), [T5804](https://yaits.demos-deutschland.de/T5804) Improved performance of assessment table for long statements, fragments and considerations
- [T5541](https://yaits.demos-deutschland.de/T5541), [T5920](https://yaits.demos-deutschland.de/T5920) if a vote advice is set, no possibility to assign fragment to reviewer (planning agency and planner get different references)
- [T5869](https://yaits.demos-deutschland.de/T5869) changed wording in message for confirmation of cluster relase 
- [T5803](https://yaits.demos-deutschland.de/T5803) Update Openlayers to v4.2.0
- [T5863](https://yaits.demos-deutschland.de/T5863) first consideration in fragment consideration history is not shown
- [T5864](https://yaits.demos-deutschland.de/T5864) reviewer can select location information in filter of reviewer's fragment list
- [T5741](https://yaits.demos-deutschland.de/T5741) deactivated planning documents will not be shown in public detail under their absolute link 
- Improved selection for different export types in consideration table
- Elasticsearch sorts case insensitively using icu plugin
- [demosplanservice] Use version 0.7
 
**Fixed**
- [T5967](https://yaits.demos-deutschland.de/T5967) display names of co-signers in statement's detail view
- [T5926](https://yaits.demos-deutschland.de/T5926) attachments of statements are shown in county notification e-mails
- [T5883](https://yaits.demos-deutschland.de/T5883) chronological sorting of fragments in reviewers list
- [T5654](https://yaits.demos-deutschland.de/T5654), [T5753](https://yaits.demos-deutschland.de/T5753) optimized loading of consideration table
- [T5641](https://yaits.demos-deutschland.de/T5641) planner and reviewer in dual role sees correct consideration history of fragments
- [T5698](https://yaits.demos-deutschland.de/T5698), [T5847](https://yaits.demos-deutschland.de/T5847) [demosplanservice] Added config variable to use bigger buffersize to handle huge pdf documents 
- [T5882](https://yaits.demos-deutschland.de/T5882) layout fix for sticky filter container in consideration table for Internet Explorer
- [T5817](https://yaits.demos-deutschland.de/T5817)  fixed checkbox for representation check in detail view of statement
- [T5833](https://yaits.demos-deutschland.de/T5833) fixed sticky header in original statements list
- [T5836](https://yaits.demos-deutschland.de/T5836) fixed position of zip code in statement submit modal 
- [T5881](https://yaits.demos-deutschland.de/T5881) Display element titles in assessment table filters even if element is currently disabled
- [T5858](https://yaits.demos-deutschland.de/T5858) alphabetical sorting of submitters whithin the fields of public agencies and citizens in compact docx export 
- [T5853](https://yaits.demos-deutschland.de/T5853) planing agencies are able to see their consideration advice history 


This changelog begins at 2017-08-18
