# QR Code Generator

A WordPress plugin that adds a QR code generator to any page with a shortcode. Visitors enter a URL, plain text or a contact name, pick colours and a size, and download the result as a PNG.

It runs the [QR code generator on dhirenpatel.me](https://www.dhirenpatel.me/tools/qr-code-generator/).

## What it does

- QR codes for a URL, plain text or a vCard with a contact name.
- Foreground and background colours, and a size from 100 to 1200 pixels (the image is capped at 1024 pixels).
- A preview and a download button. After a submit, focus moves to the result so keyboard and screen-reader users land on it.
- Form submissions and downloads are protected with WordPress nonces, and all input is sanitised.

## How it works

The QR code is generated on the server with [PHP QR Code](https://github.com/t0k4rt/phpqrcode) 1.1.4 and the PHP GD extension. Each image is saved to the plugin's `generated/` folder, deleted as soon as it is downloaded, and any image older than 5 minutes is removed the next time the form is used.

`phpqrcode/.htaccess` (added for this plugin) denies direct web access to the library folder on Apache and LiteSpeed. On Nginx, add an equivalent rule.

**Note:** PHP QR Code is no longer maintained, and its own README asks users to move to [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode). This plugin only passes it sanitised text to encode into a PNG.

## Requirements

- PHP with the GD extension.
- A writable plugin folder, so the plugin can create `generated/` and the library can write its `phpqrcode/cache/` files.

## Install

1. Download this repository as a ZIP (Code → Download ZIP).
2. In WordPress, go to Plugins → Add New Plugin → Upload Plugin, choose the ZIP and activate it.
3. Add the shortcode to a page:

```
[qr_generator]
```

The stylesheet loads only on pages that use the shortcode.

## Files

| File | Purpose |
|---|---|
| `qr-code-generator.php` | Plugin header, shortcode, form handling and download |
| `style.css` | Form and preview styles |
| `phpqrcode/` | PHP QR Code 1.1.4 library, with its precomputed `cache/` files |

## Licence

The plugin is GPL-2.0. See [LICENSE](LICENSE).

PHP QR Code is © Dominik Dzienia, based on libqrencode by Kentaro Fukuchi, and is bundled unchanged (apart from the added `.htaccess`) under the LGPL-3.0. See [phpqrcode/LICENSE](phpqrcode/LICENSE) and the GPL-3.0 text it builds on, [phpqrcode/COPYING-GPL-3.0.txt](phpqrcode/COPYING-GPL-3.0.txt).

## Author

[Dhiren Patel](https://www.dhirenpatel.me/), Principal Full-Stack Engineer.
