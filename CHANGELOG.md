# Changelog

## 0.9.30
- 08a898c (HEAD -> release/0.9.30, origin/develop, develop) fix: Update test signature to support new class (#42)
- 0506e07 test: AU-1407 request tests (#41)

## 0.9.29
- ded8eb4 fix: AU-2518: Change getUserId signature as this may be null in some cases
- 24f6571 fix: Revert "fix: AU-1934: Modify json output (#34)"

## 0.9.28
- 8767894 fix: AU-488: Remove service from document (#38)

## 0.9.27
- abf9e30 fix: AU-1934: Modify json output (#34)
- fa47e5e test: AU-1407 ATVDocument tests (#36)
- af6b653 PHPCS
- 366586c PHPCS
- 096f468 PHPCS
- 8e9f4e9 PHPCS
- e2f6cfa PHPCS
- 0e9d9a8 Fix testing env setup

## 0.9.26
- f0c28a8 Update version number in composer.json
- c12ee85 test: AU-1929 Header tests (#31)
- 256da46 Release 0.9.24
- f370109 Version bump
- 085c44f feat: AU-1984: Tunnistamo to version 3 (#30)
- 202ff75 update PR-template, add documentation and translations section (#29)
- 95c8d13 Pump up version numbero
- 89c296a AU-2123: Update version constrains for D10, run PHPCS fixes. (#28)
- d13b2cf fix:  AU-2131: Drupal 10 compatibility (#27)

## 0.9.19
- e28f5d1: fix: AU-2214: Set X-Api-Key headers in doRequest if using the api key flag
## 0.9.18
- 5e9ec07: feat: AU-1921 new property getters & setters 
## 0.9.17
- 87bbf92 feat: AU-710: Log normal requests
## 0.9.16
- 05b6ffe fix: AU-1928: Reintroduce setAuthHeaders to AtvService to fix profile uploads

## 0.9.15
- fe67ab1 feat: Module improvements

## 0.9.14
- 76652d8 fix: Use refetch parameter in searchDocuments method

## 0.9.13
- f4c1877 fix: AU-1821: Add slash to documents endpoint (#16)

## 0.9.11
- ab05189 feat: AU-1407 extend tests (#15)
- c36daf2 test: AU-1407 Add initial unit tests (#14)

## 0.9.10
- c36daf2 test: AU-1407 Add initial unit tests (#14)

## 0.9.9
- c4c8bc4 feat: AU-828: Add method to check document existance by transaction id (#11)

## 0.9.8
- c4c8bc4 feat: AU-828: Add method to check document existance by transaction id (#11)

## 0.9.7
- 374c825 fix: AU-579: Fix file upload prepended data (#10)

## 0.9.6
- d377b62 fix: GDPR delete apikey

## 0.9.5
- 26d25a5 fix: Change ATV auth in GDPR calls to use apikey (#9)

## 0.9.4
- fix: Change ATV auth in GDPR calls to use apikey (#9)

## 0.9.4

- fix: Remove version constraint from HP module
- feat: LOM-435: Add module exception event and subscriber for audit logging (#8)
- fix: AU-791: Add delete method for deleting via ATV href. (#7)
- fix: LOM-445: Filter user input before sending to service (#6)

## 0.9.3
- Add max pagecount variable to ATV service class.
- Add env variables to README

