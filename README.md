# chaching

Simple and unified object-oriented library written in PHP for the following e-commerce services offered by Slovak banks and financial institutions.

* [CardPay](https://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/cardpay.html) -- Tatra banka, a.s.
* [TatraPay](http://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/tatrapay.html) -- Tatra banka, a.s.
* [ePlatby VÚB](https://www.vub.sk/pre-podnikatelov/nonstop-banking/e-commerce-pre-internetovych-obchodnikov/e-platby-vub/) -- VÚB, a.s.
* [CardPay](https://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/cardpay.html) -- TrustPay, a.s.

The current version of the library is v0.6.0.

## Setup and installation
The recommended way to install the library is to use  [composer](http://getcomposer.org/) and add it as a dependency to your project's `composer.json` file. The library is currentyl accessible from within BACKBONE's VPN.

	{
	  "repositories": [
	    {
	      "type": "git",
	      "url": "git@dev01.backbone.intra:chaching.git"
	    }
	  ],
	  "require": {
	    "backbone/chaching": "*"
	  }
	}

After that, run the following command to perform the installation:

	$ composer install

For proper functionality make sure you have a web server with PHP 5.4.

## Usage
The library follows the PSR-0 convention of naming classes and library is available after installing under its own namespace `\Chaching`.

---

&copy; 2013 BACKBONE, s.r.o.
