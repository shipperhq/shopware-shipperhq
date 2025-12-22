# 🚀 ShipperHQ Plugin for Shopware 6

This Shopware plugin integrates ShipperHQ's rating engine, allowing Shopware stores to fetch live shipping rates from the ShipperHQ API during checkout.

---

## 📦 Features

- 🔄 Integration with ShipperHQ rate calculation services
- 📡 Real-time shipping rate calculations
- 🚚 Support for multiple carriers and shipping methods
- 🧩 Custom rate rules and conditions

---

## ✅ Requirements

- 🧱 Shopware **6.6.10** or higher
- 🐘 PHP **8.1** or higher
- 🔐 Valid ShipperHQ API credentials

---

## 🛠️ Installation Instructions

### 📁 1. Upload the Plugin

1. Unzip the provided archive
2. Rename the extracted folder to `SHQRateProvider` (if not already)
3. Upload the folder to your Shopware installation at:
   ```
   custom/plugins/SHQRateProvider/
   ```
4. cd into the plugin directory:
   ```bash
   cd custom/plugins/SHQRateProvider/
   ```
5. Run composer install to install the dependencies:
   ```bash
   composer install
   ```
6. Ensure the plugin shows up in the Shopware plugin list:
   ```bash
   bin/console plugin:refresh
   ```
7. Install and activate the plugin:
   ```bash
    bin/console plugin:install --activate SHQRateProvider
    ```
8. Clear the cache: 
   ```bash
   bin/console cache:clear
   ```
---

## ⚙️ Configuration

1. Navigate to **Extensions > My Extensions**
2. Find and click on **"ShipperHQ Shipping Rates"** in the extension list
3. Configure your **ShipperHQ API credentials and settings**
4. Save the configuration
5. Click "Reload Shipping Methods" to fetch available shipping methods from ShipperHQ
6. Clear the Shopware cache again:
   ```bash
   bin/console cache:clear
   ```

---

## 🛠️ Troubleshooting

### All Shipping Methods Are Shown at Checkout (Including Unavailable Ones)

**Issue:**
At checkout, every shipping method appears, even those that should not be available. When selecting an unavailable method, you may see an error such as:

```
USPS Priority Mail is blocked for your current shopping cart
```

**Solution:**
1. Go to the ShipperHQ app settings in Shopware.
2. Click the **Reload Shipping Methods** button.
3. Clear the Shopware cache:
   ```bash
   bin/console cache:clear
   ```

This will refresh the list of available shipping methods and resolve the error.

---

## 🧰 Support

For assistance, please visit our  
📖 [Help Center](https://docs.shipperhq.com/)  
✉️ Or contact ShipperHQ support: [support@shipperhq.com](mailto:support@shipperhq.com)  
📞 For other options, visit our [Contact Us](https://shipperhq.com/contact/) page

---

## 🤝 Contributing

We welcome contributions!  
The best way to contribute is to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

---

## 📄 License

See included license files.

---

## ©️ Copyright

© 2025 Zowta LLC — [ShipperHQ.com](http://www.ShipperHQ.com)

---
