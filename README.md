## Synopsis
An extension to add integration with Fave Payment Gateway. 

## Technical feature

## Installation

**Step 1: Change directory to the Magento root.**

**Step 2: Follow the installation instructions for the extension. The Magento standard is to use composer.**

```
composer require favemy/module-payment-gateway
composer update
php bin/magento module:enable fave/module-payment-gateway
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
```

**Step 3: After installation, you can verify the extenstion is successfully installed by running the following command.**

`php bin/magento module:status fave/module-payment-gateway`

Sample response:

`Module is enabled`

### Gateway configuration

**Step 1: Log in to magento admin dashboard.**

**Step 2: Follow the following instructions to configure FavePay as payment option.**
```
STORES -> Configuration -> Payment Methods -> Other Payment Methods -> FavePay
```
![image](https://user-images.githubusercontent.com/62248677/153821351-d9b97ece-70f8-49fd-a4dd-75d714c738a9.png)


**Step 3: Enter the credentials as provided by Fave.**

Merchant Gateway Key(App ID), Private api key and Outlet ID will be provided by Fave. 

![image](https://user-images.githubusercontent.com/62248677/153821466-62438adf-733e-4faf-ab05-42db2a987890.png)

**Step 4: Save config.**

## Contributors
Team Hephaestus (Fave)

## References
https://devdocs.magento.com/cloud/howtos/install-components.html

## License
[Open Source License](LICENSE.txt)
