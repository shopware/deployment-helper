# Deployment Helper

[![codecov](https://codecov.io/gh/shopware/deployment-helper/graph/badge.svg?token=9F9GYJ3OWS)](https://codecov.io/gh/shopware/deployment-helper)
[![PHP](https://github.com/shopware/deployment-helper/actions/workflows/php.yml/badge.svg)](https://github.com/shopware/deployment-helper/actions/workflows/php.yml)

This is a helper script to install or update Shopware on the target system. 
It's independent of the Shopware version and can be used for all versions newer 6.5.

## Installation

```bash
composer require shopware/deployment-helper
```

## Usage

The idea is that you build the source code in the pipeline and then use this script on the target system to install or update the Shopware instance.

```bash
vendor/bin/deployment-helper run
```

This will detect is Shopware installed, when not will install it, otherwise will update it.

The following tasks are executed:

- Installation / Updates of Apps / Plugins
- Compile the Theme (no Webpack, should happen before in the CI pipeline)
- Check if a Shopware version changed and running Shopware upgrade scripts

