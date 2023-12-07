# drupal-module-helfi-atv

## Environment variables
- ATV_BASE_URL -> base url for ATV api calls
- ATV_VERSION -> ATV version to be used in url
- ATV_USE_CACHE -> boolean to set cache usage, with this set, responses will be cached for single request.
- ATV_API_KEY -> api key to access services
- ATV_USE_TOKEN_AUTH -> true / false. If true, use user JWT token for authentication.
- ATV_MAX_PAGES -> maximum pages to fetch with single request. Defaults to 10.
- ATV_PAGE_SIZE -> page size in single search request. Defaults to 20.
- ATV_SERVICE -> ATV servicename for token based auth


### Variables from other modules or platform
- APP_ENV -> must be set from parent
- DEBUG -> set to true to print debugging

note: When ATV properly supports Tunnistamo authentication, api key will be obsolete and users are authorized via tunnistamo.

## Tests

This module has unit and kernel tests. You need to have this module inside a working drupal installation.

Command to run the tests: ```vendor/bin/phpunit public/modules/contrib/helfi_atv```
