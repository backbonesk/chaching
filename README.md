# chaching

Simple and unified object-oriented library written in PHP for e-commerce services offered by Slovak banks and financial institutions.

* [CardPay](https://www.tatrabanka.sk/sk/business/ucty-platby/prijimanie-platieb/cardpay/) with optional addition for [ComfortPay](http://www.tatrabanka.sk/cardpay/CardPay_ComfortPay_technicka_prirucka.pdf) service -- Tatra banka, a.s.
* [TatraPay](https://www.tatrabanka.sk/sk/business/ucty-platby/prijimanie-platieb/tatrapay/) -- Tatra banka, a.s.

* [ePlatby VÚB](https://www.vub.sk/firmy-podnikatelia/platby/eplatby/) -- VÚB, a.s.

* [VÚB eCard](https://www.vub.sk/firmy-podnikatelia/platby/ecard/) -- VÚB, a.s.

* [SporoPay](https://www.slsp.sk/sk/biznis/prijimanie-platieb/sporopay) -- Slovenská sporiteľna, a.s.

* [iTerminal](https://www.postovabanka.sk/korporatni-klienti/eft-pos-terminal/iterminal/) -- Poštová banka, a.s.

* [GP webpay](https://gpwebpay.cz/downloads/GP_webpay_Gateway.pdf) -- Global Payments Europe, s.r.o.

* [TrustCard](https://doc.trustpay.eu/v02?ShowDirectDebits=true&ShowDirectBanking=true&ShowApiBanking=true&ShowApiBankingVibans=true#overview) -- TrustPay, a.s.

* [PayPal](http://www.paypal.com) -- PayPal (Europe) S.à r.l. et Cie, S.C.A.

* [BenefitPlus](https://www.benefit-plus.eu/sk/pre-partnerov/) -- Benefit Management s.r.o.

The current version of the library is v0.23.0 and requires PHP 5.4 or PHP 7+ to work. Even though there are things to make better, it is already being used in production without any sort of problems.

Chaching library is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

## Setup and installation
The recommended way to install the library is to use [composer](http://getcomposer.org/) and add it as a dependency to your project's `composer.json` file.

	{
	  "require": {
	    "backbone/chaching": "0.23.0"
	  }
	}

After that, run the following command to perform the installation:

	$ composer install

## Usage
The library follows the PSR-0 convention of naming classes and after installing is available under its own namespace `\Chaching`. Basic setup includes the following:

	use Chaching\Chaching;

	$driver        = Chaching::CARDPAY;
	$authorization = [ 'merchant_id', 'password' ];
	$options       = [];

	$chaching = new Chaching($driver, $authorization, $options);

As already mentioned in the introduction, currently there are eight different payment methods that are supported with each having it's own driver constant: `Chaching::CARDPAY`, `Chaching::TATRAPAY`, `Chaching::TRUSTPAY`, `Chaching::EPLATBY`, `Chaching::ECARD`, `Chaching::PAYPAL`, `Chaching::GPWEBPAY`, `Chaching::ITERMINAL`, `Chaching::ITERMINAL2` and `Chaching::BENEFITPLUS`.

In case of `Chaching::GPWEBPAY` use an associated array instead of password, so authentication information would look like this:

	$authorization = [
	  'merchant_id', [
	    'key'         => '...../gpwebpay.crt',
	    'passphrase'  => 'passphrase',
	    'certificate' => '...../gpwebpay.key'
	  ]
	];

Public and private key needs to be created according to GP webpay's documentation.

And in case of `Chaching::ITERMINAL` or `Chaching::ITERMINAL2` use an associated array as well:

	$authorization = [
	  NULL, [
	    'keystore'  => '...../iterminal.pem',
	    'password'  => 'password'
	  ]
	];

Beforementioned empty `$options` array may at the moment contain these keys:

* `ecdsa_keys_file` - Absolute file path to file with Tatra banka's ECDSA keys (used only with HMAC message signing in CardPay and TatraPay)
* `sandbox` - Would you like to use sandbox or production URLs when communicating with the bank or financial institution? Default value is `FALSE`. Due to their limitations, sandbox is available only for `Chaching::TRUSTPAY`, `Chaching::ECARD`, `Chaching::PAYPAL`, `Chaching::GPWEBPAY`, `Chaching::ITERMINAL`, `Chaching::ITERMINAL2` and `Chaching::BENEFITPLUS` (if not listed here, production URLs will be used even in sandbox mode).

Afterwards, we need to create a request for the external service with specific information about the payment.

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

(Note that with `Chaching::ITERMINAL` or `Chaching::ITERMINAL2` the `$auto_redirect` will not ever work as the transaction identifier that is provided for you is strictly needed for thereafter. Only after you succesfully create the transaction you can use the redirection URL. For more information read bank's documentation.)

	try
	{
		$redirect_url = $payment->process($auto_redirect = FALSE);
	}
	catch (\Chaching\Exceptions\InvalidOptionsException $e)
	{
		// Missing or incorrect value of some configuration option.
	}
	catch (\Chaching\Exceptions\InvalidRequestException $e)
	{
		// General error with authentication or the request itself.
	}

Remember the `callback` key in the request options? It is an absolute URL that the banking service will redirect to upon completion of the payment. Chaching also has methods to help with handling the response, checking it's validity, etc. However, if you are running with `Chaching::ITERMINAL`'s (or `Chaching::ITERMINAL2`'s) driver, you do not need to provide a callback as it is set in the merchant's centre provided by the bank.

	try
	{
		$payment = $chaching->response($_REQUEST);

		if ($payment->status === \Chaching\TransactionStatuses::SUCCESS)
		{
			// Wohoo, we've got the money!
		}
		elseif ($payment->status === \Chaching\TransactionStatuses::TIMEOUT)
		{
			// Special type that is only returned by TatraPay service. In general
			// it's a good idea to check for it nonetheless.
		}
		else
		{
			// Failure, the user cancelled, the payment got rejected or the payment pending.
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

You can also use a `notification` method with SporoPay and their mail notifications to the account own They come not at the same time as regular responses when user redirects their browser from SporoPay's interface, it is just another redundant way of checking payment status in case not everything goes according to the plan. (The same use case true for Tatra banka's services CardPay and TatraPay, but in those cases you can just use the `response`.)

When using Poštová banka's iTerminal service, Tatra banka's CardPay service or Benefit Plus service, there is an option of refunding part or full payment that has been successfully completed before. Poštová banka and Benefit Plus supports only one refund per transaction, in case of CardPay you may refund in as many steps as you'd like until sum of all refunds reaches the amount of original transaction.

	try
	{
		$payment = $chaching->refund([
			'transaction_id' => '...',
			'amount'         => 1.00,
			'currency'       => \Chaching\Currencies::EUR
		]);

		// Check $payment->status and do what's appropriate.
	}
	catch (\Chaching\Exceptions\ChachingException $e)
	{
		// General error with authentication, request or bank's response.
	}

Payment status can be one of `TransactionStatuses::REVERSED`, `TransactionStatuses::SUCCESS` or `TransactionStatuses::FAILURE`. First two mean that the payment has been successfully refunded.

Newest addition to the different services provided by supported payment institutions is the ability to check whether Tatra banka's TatraPay service is available and accepting requests.

	use \Chaching\Chaching;
	use \Chaching\ServiceStatuses;

	$authorization = [ 'merchant_id', 'password' ];
	$chaching = new Chaching(Chaching::TATRAPAY, $authorization);

	try
	{
		$status = $chaching->status();

		if ($status->status === ServiceStatuses::ONLINE)
		{
			// TatraPay is alive and well!
		}
	}
	catch (\Chaching\Exceptions\ChachingException $e)
	{
		// General error with authentication, request or bank's response.
	}

PayPal deserves an honourable mention here: Please, set the instant payment notification's (IPNs) character encoding to UTF-8. Go to your Paypal profile, click "My selling tools" in the sidebar, scroll to the bottom and click "PayPal button language encoding", click "More options" and set the encoding to UTF-8. It will save you so much pain.

## Contributing
1. Check for open issues or open a new issue for a feature request or a bug.
2. Fork the repository and make your changes to the master branch (or branch off of it).
3. Send a pull request

## TODO

To release a proper v1.0 code of the library needs to have a more thorough tutorial to explain it's usage as well as complete tests.

---

&copy; 2021 BACKBONE, s.r.o.
