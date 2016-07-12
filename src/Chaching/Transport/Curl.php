<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2016 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching\Transport;

use Chaching\Chaching;
use Chaching\Exceptions\ChachingException;
use Chaching\Exceptions\MissingDependencyException;


class Curl
{
	const METHOD_DELETE 	= 'DELETE';
	const METHOD_GET 		= 'GET';
	const METHOD_POST 		= 'POST';
	const METHOD_PUT 		= 'PUT';

	private $http_code 		= 0;
	private $headers 		= [];
	private $content 		= NULL;
	private $raw_content 	= NULL;

	private $command 		= '';

	public function __construct($method, $url, $data, $custom_options = [])
	{
		if (!function_exists('curl_init'))
			throw new MissingDependencyException('Curl is required');

		$options = [
			CURLOPT_URL 			=> $url,
			CURLOPT_CONNECTTIMEOUT 	=> 30,
			CURLOPT_TIMEOUT 		=> 30,
			CURLOPT_RETURNTRANSFER 	=> TRUE,
			CURLOPT_USERAGENT 		=> 'chaching-php-' . Chaching::VERSION
		] + $custom_options;

		$ch = curl_init();

		switch (strtoupper($method))
		{
			case self::METHOD_POST:
				$options[ CURLOPT_CUSTOMREQUEST ] 	= self::METHOD_POST;
				$options[ CURLOPT_POSTFIELDS ] 		= $data;
				break;

			case self::METHOD_PUT:
				$options[ CURLOPT_CUSTOMREQUEST ] 	= self::METHOD_PUT;
				$options[ CURLOPT_POSTFIELDS ] 		= $data;
				break;

			case self::METHOD_DELETE:
				$options[ CURLOPT_CUSTOMREQUEST ] 	= self::METHOD_DELETE;
				$data = '';
				break;

			case 'GET':
				$data = '';
				break;
		}

		$custom_headers = [];

		if (!empty($data))
		{
			$custom_headers[] = 'Content-Length: ' . strlen($data);
		}

		// Discard 'Expect: 100-continue' behavior forcing cURL to wait
		// for two seconds if the server does not understand it.
		$options[ CURLOPT_HTTPHEADER ] = array_merge($custom_headers, [
			'Expect:'
		]);

		$this->command = 'curl -X '.$method;

		foreach ($options[ CURLOPT_HTTPHEADER ] as $header)
		{
			$this->command .= " --header '" . $header . "'";
		}

		if ($data)
		{
			$this->command .= " --data '" . $data . "'";
		}

		$this->command .= ' ' . $url;

		curl_setopt_array($ch, $options);

		$this->content = curl_exec($ch);

		// With dual stacked DNS responses, it's possible for a server to
		// have IPv6 enabled but not have IPv6 connectivity.  If this is
		// the case, curl will try IPv4 first and if that fails, then it will
		// fall back to IPv6 and the error EHOSTUNREACH is returned by the
		// operating system.
		if ($this->content === FALSE AND empty($this->curl_opts[ CURLOPT_IPRESOLVE ]))
		{
			$matches 	= [];
			$regex 		= '/Failed to connect to ([^:].*): Network is unreachable/';

			if (preg_match($regex, curl_error($ch), $matches))
			{
				if (strlen(@inet_pton($matches[ 1 ])) === 16)
				{
					$this->curl_opts[ CURLOPT_IPRESOLVE ] = CURL_IPRESOLVE_V4;
					curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
					$result = curl_exec($ch);
				}
			}
		}

		$this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($this->content === FALSE)
		{
			$exception_code 	= curl_errno($ch);
			$exception_message 	= curl_error($ch);

			curl_close($ch);

			throw new ChachingException(sprintf(
				'cURL error %d: %s', $exception_code, $exception_message
			));
		}
	}

	public function content()
	{
		return !empty($this->content)
			? $this->content
			: NULL;
	}

	public function headers()
	{
		return !empty($this->headers) ? $this->headers : [];
	}

	public function http_code()
	{
		return $this->http_code;
	}
}
