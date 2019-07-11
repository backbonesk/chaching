## Changelog

### v0.19.2: 2019/07/11

Fixed incorrect handling of "empty" characters such as number zero in generating signatures for `VUBeCard` service drvier.

### v0.19.1: 2019/05/29

Again fixes incorrect variable naming in verifying `PemKeys` signature that resurfaced in abf1b89 (see PR #2).

### v0.19.0: 2018/12/31

Updated VUB eCard service driver with proper testing URLs, fixed validating results of payment not exactly according to the documentation ([igor-kamil](https://github.com/igor-kamil)) and added support for newer version of generating signatures (enabled by default).

Removed usage of deprecated `FILTER_FLAG_HOST_REQUIRED` when checking for valid URL via `filter_var` in PHP 7.3 and other minor code style improvements.


### v0.18.0: 2018/07/21

Added `notification` method to `SLSPSporoPay` driver to allow to validate mail notifications (signatures) coming from the SporoPay service.

Removed dependency on `mcrypt` extension that is to be removed from the core into PECL in PHP 7.2; calls were replaced by OpenSSL that was also a dependency before. Removed `MissingDependencyException`.

### v0.17.5: 2018/07/09

iTerminal service returns transaction ID as `base64` so encoding the parameter as part of the URL for redirection is required for their servers to process the transaction correctly.

### v0.17.4: 2018/06/18

In some circustances VUB's eCard service might return month of card expiration as a three-letter string, eg. "080" instead of "08". Thus, work explicitly only with the first two.

### v0.17.3: 2017/01/31

Process further fields from PayPal's responses like buyer's name, account etc. which might be useful for further analysis. Also tries to sort out the encoding issues if IPNs do not come in UTF-8.

### v0.17.2: 2017/01/08

Fixes refusal to accept non-ASCII character payer names when creating Tatra banka's CardPay request. The bank allows only basic characters to appear in name so other characters were omitted. In case there were no charactes left, the request would be refused and exception thrown. Now a placeholder "unknown" value is provided to successfully process the request and return a redirection URL.

### v0.17.1: 2016/12/10

Fixes incorrect variable handling when TatraPay's monitor fails due to invalid bank response.

### v0.17.0: 2016/12/09

Added support for checking online status of Tatra banka's TatraPay service.

### v0.16.5: 2016/09/07

Fixes refunding in sandbox / production mode for Poštová banka's iTerminal service.

### v0.16.4: 2016/09/04

Fixes sandbox URLs for Poštová banka's iTerminal service.

### v0.16.3: 2016/08/22

Fixes reading and processing Tatra banka's ECDSA keys file that uses CRLF line endings.

### v0.16.2: 2016/08/15

Fixes SLSP's SporoPay signature handling on response.

### v0.16.1: 2016/08/05

Fixes sandbox URLs for Poštová banka's iTerminal service.

### v0.16.0: 2016/08/05

Added the ability to switch between sandbox and production environment when communicating with banks and financial institutions.

### v0.15.1: 2016/07/27

Added support for CardPay's ability to refund payments.

### v0.15.0: 2016/05/31

Added support for Poštová banka's iTerminal serice for accepting payments as well as refunding them.
Adds default saving of PayPal's transaction identifier to `transaction_id` on notification object.

### v0.14.4: 2016/05/11

Fixes inability to verify CardPay's signatures when set to receive `RC` attribute.

### v0.14.3: 2016/05/06

Adds ability to read card expiration date when using VÚB eCard and bank's transaction numbers with eCard and CardPay services.

### v0.14.2: 2016/04/22

Fixes problem with older CardPay's encryption Aes256 and Des methods, when the response from bank does not conatin `TID` (external transaction idenfitifer).

### v0.14.1: 2016/04/19

Fixes signature verification in TatraPay's transactions that include constant symbol.

### v0.14.0: 2016/03/31

Added support for HMAC encoding required from all new clients to Tatra banka's CardPay and TatraPay services along with ECDSA message signing (for the signing to work correctly, `openssl` PHP extension is required).

### v0.13.1: 2015/09/09

Fixes minor issue for handling empty "merchant data" field with GP webpay payment method.

### v0.13.0: 2015/09/07

Added support for GP webpay. The difference is that it does not use a shared_key, but set of private / public keys generated according to their documentation, so an associated array with `certificate`, `key` and `passphrase` keys needs to be provided as the second argument to the constructor of `Chaching` object.

### v0.12.1: 2015/08/31

Send automatically optional `encoding` attribute to VÚB eCard service to fix for default turkish encoding on payment gateway.

### v0.12.0: 2015/08/24

Added support for PayPal through "Buy now" payment buttons along with Instant Payment Notification checking.

### v0.11.2: 2015/03/27

If client requests mail notification after a payment when using ComfortPay, always request also the longer notification with `CID` (card identifier). Just as well, if `REM` (return mail) attribute is present with a valid value `TEM` will be set automatically.

### v0.11.1: 2015/03/26

Fixes support for Tatra banka's ComfortPay registration.

### v0.11.0: 2015/02/22

Added support for AES256 message hashing for Tatra banka's TatraPay and CardPay service. Missing `mcrypt` support in PHP throws `MissingDependencyException`.

### v0.10.0: 2015/01/28

Added support for VÚB eCard service (VÚB, a.s.).

### v0.9.1: 2015/01/27

Minor changes concerning input of the bank response.

### v0.9.0: 2014/12/24

Adds support for ComfortPay transactions using CardPay driver.

Shorten the name accroding to the specification when using CardPay driver.

### v0.8.2: 2014/10/16

Small fixes for incorrect initialization of ePlatby payment service and their specific symbol policy that depends on the contract with the bank.

### v0.8.1: 2014/10/14

Small fixes for currency compatibility problems with TatraPay service.

### v0.8.0: 2014/10/14

Added initial support for SporoPay – online service (Slovenská sporiteľňa, a.s.).

Fail silently when passing arguments that are not supported by particular payment method without throwing `InvalidOptionsException`.

### v0.7.0: 2014/08/11

Further documentation and some minor fixes.

### v0.6.0: 2013/19/12

Added support for TrustCard service (TrustPay) according to [Merchant Integration Manual v2.0](http://www.trustpay.eu/assets/Uploads/Merchant-API-integration-v2.0.pdf).

Fixes in this release:

- Fixed diacritics handling of CardPay services.
- TatraPay does not require `NAME` and `SS` field when creating a payment request anymore
- Code documentation rewritten to English.

### v0.5.0: 2013/09/18

First version of the Chaching library with support for CardPay and TatraPay services (Tatra banka, a.s.) and ePlatby VÚB (VÚB, a.s.).