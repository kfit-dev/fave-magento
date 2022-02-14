## Synopsis
An extension to add integration with Fave Payment Gateway.

## Technical feature

## Installation

Step 1: Change directory to the Magento root

Step 2: Follow the installation instructions for the extension. The Magento standard is to use composer.

composer require fave/module-payment-gateway
composer update
php bin/magento module:enable fave/module-payment-gateway
php bin/magento module:enable fave/module-payment-gateway
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean

Step 3: After installation, you can verify the extenstion is successfully installed by running the following command.

php bin/magento module:status fave/module-payment-gateway

Sample response:

`Module is enabled`

### Gateway configuration


## Contributors
Team Hephaestus (Fave)

## References
https://devdocs.magento.com/cloud/howtos/install-components.html

## License
[Open Source License](LICENSE.txt)