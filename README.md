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

## Support

For support inquiries, please contact:
- Email: support@shipperhq.com
- Documentation: [ShipperHQ Documentation](https://docs.shipperhq.com)

## License

Copyright © ShipperHQ. All rights reserved.
