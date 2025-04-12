# SHQRateProvider Plugin for Shopware 6

This plugin provides rate calculation functionality for shipping rates in Shopware 6.

## Features

- Integration with ShipperHQ rate calculation services
- Real-time shipping rate calculations
- Support for multiple carriers and shipping methods
- Custom rate rules and conditions

## Requirements

- Shopware 6.5.x or higher
- PHP 8.1 or higher
- Valid ShipperHQ API credentials

## Installation

1. Upload the plugin files to `custom/plugins/SHQRateProvider/`
2. Install the plugin through Shopware Admin:
   ```bash
   bin/console plugin:install --activate SHQRateProvider
   ```
3. Clear the cache:
   ```bash
   bin/console cache:clear
   ```

## Configuration

1. Navigate to Settings > System > Plugins
2. Find and click on "SHQRateProvider" in the plugin list
3. Configure your ShipperHQ API credentials and settings

## Development

### Database Connection

```bash
mysql -u root -proot -h shopware-mysql.jo-macbook.svc.cluster.local
```

### Building the Administration

We want to build the administration after changes 

```bash
bin/build-administration.sh 
 bin/console cache:clear && bin/console assets:install && bin/console theme:compile
```


```bash
# Install dependencies
npm install

# Build for production
npm run build

# Watch for changes during development and reload the admin
./bin/watch-administration.sh
```

### Plugin Commands

```bash
# Build the plugin
bin/console plugin:build SHQRateProvider

# Refresh the plugin
bin/console plugin:refresh

# Update the plugin
bin/console plugin:update SHQRateProvider
```

### Running Shopware



## Support

For support inquiries, please contact:
- Email: support@shipperhq.com
- Documentation: [ShipperHQ Documentation](https://docs.shipperhq.com)

## License

Copyright © ShipperHQ. All rights reserved.


## Composer Install

``composer clear-cache
rm -rf vendor/
composer update --prefer-source``

## Useful SQL Commands

``SELECT sm.id, sm.technical_name, sm.active, smt.name, smt.description FROM shipping_method sm LEFT JOIN shipping_method_translation smt ON sm.id = smt.shipping_method_id WHERE smt.language_id = (SELECT id FROM language WHERE locale_id = (SELECT id FROM locale WHERE code = 'en-GB'));``

Will output something like:

+------------------------------------+----------------------------------+--------+---------------------------------+--------------------------------------------+
| id                                 | technical_name                   | active | name                            | description                                |
+------------------------------------+----------------------------------+--------+---------------------------------+--------------------------------------------+
| 0x01957BCAD7FD729EBE577F58637465BF | shipping_standard                |      1 | Standard                        | NULL                                       |
| 0x01957BCAD7FD729EBE577F5863BE54D9 | shipping_express                 |      1 | Express                         | NULL                                       |
| 0x01957BD1A281717888B5447BCDD34557 | NULL                             |      0 | Service Point Delivery          | Please select a service point.             |
| 0x019582F5726471FD8B9B22A3BDC15AF6 | shqfedex-FEDEX_GROUND            |      1 | FedEx - Ground                  | ShipperHQ: FedEx - Ground                  |
| 0x019582F5759673BAB84D016D448AB38A | shqfedex-FEDEX_2_DAY             |      1 | FedEx - 2nd Day                 | ShipperHQ: FedEx - 2nd Day                 |
| 0x019582F5785571918172B810A7695245 | shqfedex-GROUND_HOME_DELIVERY    |      1 | FedEx - Home Delivery           | ShipperHQ: FedEx - Home Delivery           |
| 0x019582F57B477360BEC3518508B241BF | shqfedex-FIRST_OVERNIGHT         |      1 | FedEx - First Overnight         | ShipperHQ: FedEx - First Overnight         |
| 0x019582F57EF771E1AF6121DD5F8E1A8C | shqfedex-PRIORITY_OVERNIGHT      |      1 | FedEx - Priority Overnight      | ShipperHQ: FedEx - Priority Overnight      |
| 0x019582F582BD733A9167A4CC022057B0 | shqfedex-INTERNATIONAL_ECONOMY   |      1 | FedEx - International Economy   | ShipperHQ: FedEx - International Economy   |
| 0x019582F5861A72BAB46084508099AB18 | shqfedex-INTERNATIONAL_GROUND    |      1 | FedEx - International Ground    | ShipperHQ: FedEx - International Ground    |
| 0x019582F5893D73BCA37E0DBB1750F41C | shqfedex-INTERNATIONAL_PRIORITY  |      1 | FedEx - International Priority  | ShipperHQ: FedEx - International Priority  |
| 0x019582F58C0E70D8B2670DFA887ED015 | shqups-1DA                       |      1 | UPS - UPS Next Day Air®         | ShipperHQ: UPS - UPS Next Day Air®         |
| 0x019582F58FB870A894DDFEDEE68CC82F | shqups-2DA                       |      1 | UPS - UPS 2nd Day Air®          | ShipperHQ: UPS - UPS 2nd Day Air®          |
| 0x019582F5933171ABAD322CE2F657866C | shqups-STD                       |      1 | UPS - UPS® Standard             | ShipperHQ: UPS - UPS® Standard             |
| 0x019582F59642722FB17C143BF69055BC | shqups-3DS                       |      1 | UPS - UPS 3 Day Select®         | ShipperHQ: UPS - UPS 3 Day Select®         |
| 0x019582F598FE700CBD680284B85FA071 | shqups-XPR                       |      1 | UPS - UPS Worldwide Express®    | ShipperHQ: UPS - UPS Worldwide Express®    |
| 0x019582F59C5570348A481C310377ED98 | shqups-XPD                       |      1 | UPS - UPS Worldwide Expedited®  | ShipperHQ: UPS - UPS Worldwide Expedited®  |
| 0x019582F5A02E71D6AF35DD6D517BC31C | shqups-65                        |      1 | UPS - UPS Worldwide Saver®      | ShipperHQ: UPS - UPS Worldwide Saver®      |
| 0x019582F5A2E673FEABE526C87EC177AF | shqups-GND                       |      1 | UPS - UPS® Ground               | ShipperHQ: UPS - UPS® Ground               |
| 0x019582F5A66C7037BD6AE49B563B8A92 | shqcerasisfreight-cerasisfreight |      1 | Cerasis - LTL                   | ShipperHQ: Cerasis - LTL                   |
| 0x019582F5A9DC73CA9F175E74696E9FB6 | shqcerasisfreight-CNWY           |      1 | Cerasis - Con-Way Freight       | ShipperHQ: Cerasis - Con-Way Freight       |
| 0x019582F5AD7272D39549B095A2868F99 | shqcerasisfreight-UPGF           |      1 | Cerasis - TForce Freight        | ShipperHQ: Cerasis - TForce Freight        |
| 0x019582F5B072728E838543BEC17C9037 | shqsurepost-USG                  |      1 | UPS SurePost - UPS SurePost®    | ShipperHQ: UPS SurePost - UPS SurePost®    |
| 0x019582F5B42570DD8E5ECAE2B01FFE6B | shqcustom-test                   |      1 | Custom Carrier - test           | ShipperHQ: Custom Carrier - test           |
| 0x019582F5B6F67335BDFDE2EE6FEEA0BA | shqflat-fixed                    |      1 | Fixed Rate SHQ - Fixed          | ShipperHQ: Fixed Rate SHQ - Fixed          |
+------------------------------------+----------------------------------+--------+---------------------------------+--------------------------------------------+
25 rows in set (0.01 sec)
