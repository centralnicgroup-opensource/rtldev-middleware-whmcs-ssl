# WHMCS "ISPAPI" Registrar Module #

[![semantic-release](https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg)](https://github.com/semantic-release/semantic-release)
[![Build Status](https://travis-ci.org/hexonet/whmcs-ispapi-ssl.svg?branch=master)](https://travis-ci.org/hexonet/whmcs-ispapi-ssl)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![PRs welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](https://github.com/hexonet/whmcs-ispapi-ssl/blob/master/CONTRIBUTING.md)

This Repository covers the WHMCS SSL Module of HEXONET. It provides the following features in WHMCS:

## Supported Features ##

* Unified handling of different suppliers
  * Comodo SSL Certs
  * Verisign SSL Certs
  * Thawte SSL Certs
  * GeoTrust SSL Certs
* Support for testing environment

... and MORE!

## Resources ##

* [Usage Guide](https://github.com/hexonet/whmcs-ispapi-ssl/blob/master/README.md#usage-guide)
* [Release Notes](https://github.com/hexonet/whmcs-ispapi-ssl/releases)
* [Development Guide](https://github.com/hexonet/whmcs-ispapi-ssl/wiki/Development-Guide)

NOTE: We introduced sematic-release starting with v7.1.0. This is why older Release Versions do not appear in the [current changelog](https://github.com/hexonet/whmcs-ispapi-ssl/blob/master/HISTORY.md). But these versions appear in the [release overview](https://github.com/hexonet/whmcs-ispapi-ssl/releases) and in the [old changelog](https://github.com/hexonet/whmcs-ispapi-ssl/blob/master/HISTORY.old).

## Usage Guide ##

Download the ZIP archive including the latest release version [here](https://github.com/hexonet/whmcs-ispapi-ssl/raw/master/whmcs-ispapi-ssl-latest.zip).

### Installation ###

Copy all files from the *install/* subdirectory to your WHMCS installation root directory ($YOUR_WHMCS_ROOT), while keeping the folder structure.
E.g.

```text
install/modules/servers/ispapissl/ispapissl.php
=> $YOUR_WHMCS_ROOT/modules/servers/ispapissl/ispapissl.php
```

### Configuration ###

Login to the WHMCS Admin Area and navigate to `Setup > Products/Services > Products/Services` to activate.

* Create a new group e.g. "SSL Certificates"
* Create a new product, e.g.:
  * Type: Other Product/Service
  * Group: SSL Certificates
  * Name: Comodo SSL Certificate
* In details tab set a Product Description (optional).
* Ensure the field "Welcome Email" is set to "None"
* Ensure the field "Require Domain" is unchecked
* In pricing tab set the "Payment Type" to "One Time" and configure a price
* In module settings tab
  * set Module Name to "Ispapissl"
  * provide your HEXONET login credentials
  * choose your desired "Certificate Type" using dropdown list "Certificate Type"
  * choose your desired "Term" using dropdown list "Years"
  * Set "Automatically setup the product as soon as the first payment is received" to ensure the certificate is paid before registration.

## Minimum Requirements ##

For the latest WHMCS minimum system requirements, please refer to
[https://docs.whmcs.com/System_Requirements](https://docs.whmcs.com/System_Requirements)

## Contributing ##

Please read [our development guide](https://github.com/hexonet/whmcs-ispapi-ssl/wiki/Development-Guide) for details on our code of conduct, and the process for submitting pull requests to us.

## Authors ##

* **Anthony Schneider** - *development* - [AnthonySchn](https://github.com/anthonyschn)
* **Kai Schwarz** - *development* - [PapaKai](https://github.com/papakai)
* **Tulasi Seelamkurthi** - *development* - [Tulsi91](https://github.com/tulsi91)

See also the list of [contributors](https://github.com/hexonet/whmcs-ispapi-ssl/graphs/contributors) who participated in this project.

## License ##

This project is licensed under the MIT License - see the [LICENSE](https://github.com/hexonet/whmcs-ispapi-ssl/blob/master/LICENSE) file for details.

[HEXONET GmbH](https://hexonet.net)
