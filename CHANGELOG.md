# Changelog

To release v1.0 code of the library needs to have tutorial to explain it's usage as well as be fully documented.

### v0.6.0: 2013/19/12

Added support for TrustCard service (TrustPay) according to [Merchant Integration Manual v2.0](http://www.trustpay.eu/assets/Uploads/Merchant-API-integration-v2.0.pdf).

Fixes in this release:

- Fixed diacritics handling of CardPay services.
- TatraPay does not require `NAME` and `SS` field when creating a payment request anymore
- Code documentation rewritten to English.

### v0.5.0: 2013/09/18

First version of the Chaching library with support for [CardPay](http://www.tatrabanka.sk/cardpay/CardPay_technicka_prirucka.pdf) and [TatraPay](http://www.tatrabanka.sk/tatrapay/TatraPay_technicka_prirucka.pdf) services (Tatra banka, a.s.) and [ePlatby VÚB](https://www.vub.sk/files/pre-podnikatelov/nonstop-banking/e-commerce-pre-internetovych-obchodnikov/e-platby-vub/eplatby_priloha2.pdf) (VÚB, a.s.).