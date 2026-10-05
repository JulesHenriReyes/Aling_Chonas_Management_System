# Contact validation dependency

The approved plan accepts Philippine mobile and complete landline numbers using maintained number metadata. Use the core-only `giggsey/libphonenumber-for-php-lite` package, pinned to **9.0.40** in Composer and the lock file. Composer metadata reports PHP `^8.1`; the application runs PHP **8.2.12** and has mbstring enabled. The existing Symfony mbstring polyfill meets the package requirement. No geocoding/carrier/timezone features are needed.

Primary references: [maintainer installation and lite-package guidance](https://github.com/giggsey/libphonenumber-for-php/blob/master/README.md), [parser and validation API](https://github.com/giggsey/libphonenumber-for-php/blob/master/docs/PhoneNumberUtil.md), [Philippine metadata](https://raw.githubusercontent.com/giggsey/libphonenumber-for-php/master/src/data/PhoneNumberMetadata_PH.php). Composer package metadata was read before installation. Number pattern validation does not prove reachability.

Installation uses an exact package constraint, `--no-scripts` and `--no-plugins`, preserving existing application configuration and avoiding unrelated setup/migration hooks. Deployment requires `composer install` from the updated lock file. No schema migration is needed for this validation change.
