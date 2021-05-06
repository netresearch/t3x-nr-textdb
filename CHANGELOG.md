# 0.4.2

## MISC

- FIX-1: Cast translations to string. So they are correctly importet. 6d15c4f
- ADKP-823: Fix import. 967ff2e
- ADKP-823: Add services.yml c930b39
- master: Import labels for all languages with same typoe3language 0d4d073
- Added missing extension-key in composer.json feaf3bf

# 0.4.1

## MISC

- OPS-0: Fix import d5f6d5e

# 0.4.0

## MISC

- OPS-0: Add small imporvement to import controller. 6f3150a
- ui: Implement UI Improvements cfa84e5
- ATU-42: Remove unused templates. Remove f:be.container from layout otherwise the html tag is rendered twice. d544859
- ATU-42: Fixed viewhelper 84e43e0
- ATU-42: Fixed method return type 0752032
- ATU-42: Fixed validation annotations fb9c831
- ATU-42: make typo3 10 compatibility 2378da1
- ATU-42: raise compatible version to 10 7193f68

# 0.3.1

## MISC

- OPS-0: Fix import command to prevent that helhum/console does not work as expected d1660a9
- Add LICENSE 6a774bc

# 0.3.0

## MISC

- MFAG-476: store module config in BE user data 6e6c43f
- MFAG-476: add search for values to be module 16bbe3a

# 0.2.1

## MISC

- MFAG-355: Increase limit of value field in database. 286e66a

# 0.2.0

## MISC

- NRTEXTDB-0: Implement Import action. 4e88f36
- MFAG-310: Add overwride option to import command. Remove debugcode. 718b189

# 0.1.0

## MISC

- TYPO-305: add possibility to filter in BE module 71f1616

# 0.0.6

## MISC

- MFAG-315: Add ui improvement 6cfed90
- MFAG-315: Refactor repository so it uses joins for getting trasnlation. 9ec0e0c
- MFAG-315: Add new Indices. 4f8d5fc

# 0.0.5

## MISC

- MFAG-315: Cleanup comments and DocBlocks 2848ab9
- MFAG-315: Add local caches to all repositories 7a2b46b
- MFAG-315: Implement local cache e651548

# 0.0.4

## MISC

- MFAG-300: Implement Translation and Editing via Backendmodule f20f717

# 0.0.3

## MISC

- MFAG-225: Add import command to import textdb files into database via cli. f13f229
- MFAG-147: add correct handling for hidden fields 7b70ad5
- MFAG-147: remove pid from ts configuration for textdb records fe27c73
- MFAG-147: make language only editable if it is a new record a92f749
- MFAG-0: Add new Backend module icons 736c97f
- MFAG-195: add pagination for list view b668014
- MFAG-195: Set default value in viewhelper 8aaa6c5
- MFAG-196: Implement ViewHelper and Translation Service. bc4fe2f
- master: Remove unwanted folder 12eb2c5
- master: Fix use statements 7ada76e
- MFAG-147: fix single view of translations 9c1ee15
- Revert "MFAG-147: use correct type for value" fcebb3d
- MFAG-147: use correct type for value c55c399
- MFAG-14: remove translation from type, component and environment 0bf5904
- MFAG-147: add delete link ee84411
- MFAG-147: fix relations 6e0e314
- MFAG-147: fix list tempalte ba5912f
- MFAG-147: fix templates and relations b08334b
- initial commit 27b3d36

