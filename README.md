# chaching

Jednoduché a (v rámci možností) unifikované objektovo-orientované rozhranie pre nesledovné bankové produkty pre e-commerce poskytované najmä slovenskými bankami, napísané v jazyku PHP:

* [CardPay](https://www.tatrabanka.sk/sk/business/ucty-platby-karty/elektronicke-bankovnictvo/cardpay.html) -- Tatra banka, a.s.
* [ePlatby VÚB](https://www.vub.sk/pre-podnikatelov/nonstop-banking/e-commerce-pre-internetovych-obchodnikov/e-platby-vub/) -- VÚB, a.s.

Aktuálna verzia kódu je 0.0.3.

## Príprava a inštalácia
Odporúčaný spôsob inštalácie je požiť [composer](http://getcomposer.org/) a zapísať rozhranie ako závislosť projektu s priamou cestou k repozitáru dostupného v rámci firemnej VPN:

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

Následne treba vykonať samotnú inštaláciu:

	$ composer install

Pre správnu funkcionalitu knižnice je nevyhnutné mať skompilované minimálne PHP 5.4.

## Použitie
Keďže rozhranie nasleduje PSR-0 konvencie pomenovávania tried, po nainštalovaní bude dostupné pod vlastným menným priestorom (angl. namespace) `Chaching`.

## Autori
* Návrh a programovanie: Pavol Sopko

---

&copy; 2013 BACKBONE, s.r.o.
