<?php

/*
 * This file is part of Chaching.
 *
 * (c) 2026 BACKBONE, s.r.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Chaching;

/**
 * Minimal array to XML converter.
 *
 * Replaces the `spatie/array-to-xml` dependency (which requires PHP 8+) with
 * a small implementation that works on PHP 7.4 as well as PHP 8. It supports
 * the subset of features the library actually uses:
 *
 *  - nested associative arrays are converted to nested elements,
 *  - a `_attributes` key holds an associative array of element attributes,
 *  - a `_value` key sets the text content of an element that also has
 *    attributes,
 *  - sequential (list) arrays produce repeated elements with the parent key,
 *  - scalar leaves are converted to text nodes (NULL and FALSE produce an
 *    empty element, TRUE produces "1").
 */
class ArrayToXml
{
	/**
	 * @param 	array 	$array 			data to convert
	 * @param 	string 	$root_element 	name of the root element
	 * @param 	string 	$xml_version 	XML declaration version
	 * @param 	string 	$xml_encoding 	XML declaration encoding (empty for none)
	 * @return 	string
	 * @throws 	\InvalidArgumentException
	 */
	public static function convert(array $array, $root_element = 'root', $xml_version = '1.0', $xml_encoding = '')
	{
		$document = new \DOMDocument($xml_version, $xml_encoding);

		$root = $document->createElement(
			self::validate_name($root_element)
		);

		$document->appendChild($root);

		self::convert_element($document, $root, $array);

		return $document->saveXML();
	}

	/**
	 * @param 	\DOMDocument 	$document
	 * @param 	\DOMElement 	$element
	 * @param 	mixed 			$value
	 * @return 	void
	 */
	private static function convert_element(\DOMDocument $document, \DOMElement $element, $value)
	{
		if (!is_array($value))
		{
			$element->appendChild(
				$document->createTextNode(self::stringify($value))
			);

			return;
		}

		foreach ($value as $key => $data)
		{
			if ($key === '_attributes')
			{
				self::add_attributes($element, $data);
			}
			elseif ($key === '_value')
			{
				$element->appendChild(
					$document->createTextNode(self::stringify($data))
				);
			}
			elseif (is_int($key))
			{
				// Sequential array: the parent element itself is repeated by
				// the caller (see `add_node`), so just fill in its content.
				self::convert_element($document, $element, $data);
			}
			else
			{
				self::add_node($document, $element, $key, $data);
			}
		}
	}

	/**
	 * @param 	\DOMDocument 	$document
	 * @param 	\DOMElement 	$parent
	 * @param 	string 			$key
	 * @param 	mixed 			$value
	 * @return 	void
	 */
	private static function add_node(\DOMDocument $document, \DOMElement $parent, $key, $value)
	{
		$name = self::validate_name($key);

		if (is_array($value) AND self::is_sequential($value))
		{
			foreach ($value as $item)
			{
				$child = $document->createElement($name);
				$parent->appendChild($child);

				self::convert_element($document, $child, $item);
			}

			return;
		}

		$child = $document->createElement($name);
		$parent->appendChild($child);

		self::convert_element($document, $child, $value);
	}

	/**
	 * @param 	\DOMElement 	$element
	 * @param 	mixed 			$attributes
	 * @return 	void
	 */
	private static function add_attributes(\DOMElement $element, $attributes)
	{
		if (!is_array($attributes))
			return;

		foreach ($attributes as $name => $value)
		{
			$element->setAttribute(
				self::validate_name($name), self::stringify($value)
			);
		}
	}

	/**
	 * @param 	array 	$value
	 * @return 	bool
	 */
	private static function is_sequential(array $value)
	{
		if (empty($value))
			return FALSE;

		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @param 	mixed 	$value
	 * @return 	string
	 */
	private static function stringify($value)
	{
		if ($value === NULL OR $value === FALSE)
			return '';

		if ($value === TRUE)
			return '1';

		// Strip control characters that are not allowed in XML 1.0 documents.
		return preg_replace(
			'/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value
		);
	}

	/**
	 * @param 	mixed 	$name
	 * @return 	string
	 * @throws 	\InvalidArgumentException
	 */
	private static function validate_name($name)
	{
		$name = (string) $name;

		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.\-:]*$/', $name))
			throw new \InvalidArgumentException(sprintf(
				"'%s' is not a valid XML element or attribute name.", $name
			));

		return $name;
	}
}
