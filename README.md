# chaching

Simple and unified object-oriented library written in PHP for e-commerce services offered by Slovak banks and financial institutions.

* [CardPay](https://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/cardpay.html) -- Tatra banka, a.s.
* [TatraPay](http://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/tatrapay.html) -- Tatra banka, a.s.
* [ePlatby VÚB](https://www.vub.sk/pre-podnikatelov/nonstop-banking/e-commerce-pre-internetovych-obchodnikov/e-platby-vub/) -- VÚB, a.s.
* [TrustCard](http://www.trustpay.eu/contact-references-payment-methods-news/dokumenty-na-stiahnutie-en-GB/) -- TrustPay, a.s.
* [SporoPay](http://www.slsp.sk/6415/sporopay-elektronicke-platby-na-internete.html) -- Slovenská sporiteľna, a.s.

The current version of the library is v0.8.1 and requires PHP 5.4 to work. Even though there are things to make better, it is already being used in production without any sort of problems.

Chaching library is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

## Setup and installation
The recommended way to install the library is to use [composer](http://getcomposer.org/) and add it as a dependency to your project's `composer.json` file.

	{
	  "require": {
	    "backbone/chaching": "0.8.0"
	  }
	}

After that, run the following command to perform the installation:

	$ composer install

## Usage
The library follows the PSR-0 convention of naming classes and after installing is available under its own namespace `\Chaching`. Basic setup includes the following:

	use Chaching\Chaching;

	$driver        = Chaching::CARDPAY;
	$authorization = [
	  'merchant_id', 'password'
	];

	$chaching = new Chaching($driver, $authorization);

As already mentioned in the introduction, currently there are four different payment methods that are supported with each having it's own driver constant: `Chaching::CARDPAY`, `Chaching::TATRAPAY`, `Chaching::TRUSTPAY` and `Chaching::EPLATBY`.

First, we need to create a request for the external service with specific information about the payment.

	$payment = $chaching->request([
		'currency'        => \Chaching\Currencies::EUR,

		'variable_symbol' => 70000000,
		'amount'          => 9.99,
		'description'     => 'My wonderful product',
		'constant_symbol' => '0308',
		'return_email'    => '...',
		'callback'        => 'http://...'
	]);

By running the `process` method in the next code block, we are getting back (when `$auto_redirect` is set to `FALSE`) the URL to redirect the user to, where the user will make the payment.

	try
	{
		$redirect_uri = $payment->process($auto_redirect = FALSE);
	}
	catch (\Chaching\Exceptions\InvalidOptionsException $e)
	{
		// Missing or incorrect value of some configuration option.
	}
	catch (\Chaching\Exceptions\InvalidRequestException $e)
	{
		// General error with authentication or the request itself.
	}

Remember the `callback` key in the request options? It is a full URL that the banking service will redirect upon completion of the payment. Chaching also has methods to help with handling the response, checking it's validity, etc.

	try
	{
		$payment = $chaching->response($_GET);

		if ($payment->status === \Chaching\Statuses::SUCCESS)
		{
			// Wohoo, we've got the money!
		}
		elseif ($payment->status === \Chaching\Statuses::TIMEOUT)
		{
			// Special type that is only returned by TatraPay service. In general
			// it's a good idea to check for it nonetheless.
		}
		else
		{
			// Failure, the user cancelled or the payment pending.
		}
	}
	catch (\Chaching\Exceptions\InvalidResponseException $e)
	{
		// General error with authentication or the response itself.
	}

TrustPay is a special case with response handling as they use notification mechanism similar to PayPal's IPN to let you know the status of the payment. In that case, in similar fashion to handling responses we use the notification method.

	try
	{
		$payment = $chaching->notification($_GET);

		// And now we can check the $payment->status and do what's appropriate.
	}
	catch (\Chaching\Exceptions\InvalidResponseException $e)
	{
		// General error with authentication or the response/notification itself.
	}

## Contributing
1. Check for open issues or open a new issue for a feature request or a bug.
2. Fork the repository and make your changes to the master branch (or branch off of it).
3. Send a pull request

## Changelog

To release v1.0 code of the library needs to have a more thorough tutorial to explain it's usage as well as complete tests.

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

---

&copy; 2014 BACKBONE, s.r.o.
