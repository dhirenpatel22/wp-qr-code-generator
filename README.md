# QR Code Generator

A WordPress plugin that adds a QR code generator to any page with a shortcode. Visitors enter a URL, plain text or a contact name, pick colours and a size, and download the result as a PNG.

It runs the [QR code generator on dhirenpatel.me](https://www.dhirenpatel.me/tools/qr-code-generator/).

## What it does

- QR codes for a URL, plain text or a vCard with a contact name.
- Foreground and background colours, and a size from 100 to 1200 pixels (the image is capped at 1024 pixels).
- A preview and a download button. After a submit, focus moves to the result so keyboard and screen-reader users land on it.
- Form submissions are protected with a WordPress nonce, and all input is sanitised.

## How it works

The QR code is generated on the server with [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) 6.0.1 and the PHP GD extension. The PNG is built in memory and sent back in the page as a data URI, so no image is ever saved on the server, and the download comes from the same data.

## Requirements

- PHP 8.2 or later, with the GD and mbstring extensions.

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
| `qr-code-generator.php` | Plugin header, shortcode, form handling and QR generation |
| `style.css` | Form and preview styles |
| `vendor/` | chillerlan/php-qrcode and its dependency, installed with Composer and committed so the ZIP works as is |
| `composer.json`, `composer.lock` | To rebuild `vendor/`: `composer install --no-dev` |

The library is loaded with its normal namespace (`chillerlan\QRCode`). If another active plugin bundles a different version of the same library, whichever loads first is used.

## Licence

The plugin is GPL-2.0-or-later. See [LICENSE](LICENSE).

[chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) is © Smiley, dual-licensed MIT or Apache-2.0, and [chillerlan/php-settings-container](https://github.com/chillerlan/php-settings-container) is MIT. Their licence files are in `vendor/chillerlan/`.

## Changelog

- **2.0**: replaced the unmaintained PHP QR Code 1.1.4 library with chillerlan/php-qrcode 6.0.1; QR codes are generated in memory instead of saved to a public folder; size is checked on the server; licence is now GPL-2.0-or-later; requires PHP 8.2.
- **1.4**: version running on dhirenpatel.me when this repository was created.

## Author

[Dhiren Patel](https://www.dhirenpatel.me/), Principal Full-Stack Engineer.
