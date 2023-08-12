<?php

/**
 * A simple PHP class for working with Universally Unique Identifiers (UUIDs).
 *
 * @version 1.0.0
 * @author Jon Stovell http://jon.stovell.info
 * @copyright 2023 Jon Stovell
 * @license MIT
 *
 * Copyright (c) 2023 Jon Stovell
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Sesquipedalian;

/**
 * Generates, compresses, and expands Univerally Unique Identifers.
 *
 * This class can generate UUIDs of versions 1 through 7.
 *
 * It is also possible to create a new instance of this class from a UUID string
 * of any version via the Uuid::createFromString() method.
 *
 * This class implements the \Stringable interface, so getting the canonical
 * string representation of a UUID is as simple as casting an instance of this
 * class to string.
 *
 * Because the canonical string representation of a UUID requires 36 characters,
 * e.g. 4e917aef-2843-5b3c-8bf5-a858ee6f36bc, which can be quite cumbersome,
 * this class can also compress and expand UUIDs to and from more compact
 * representations for storage and other uses. In particular:
 *
 *  - The Uuid::getBinary() method returns the 128-bit (16 byte) raw binary
 *    representation of a UUID. This form maintains the same sort order as the
 *    full form and is the most space-efficient form possible.
 *
 *  - The Uuid::getShortForm() method returns a customized base 64 encoding of
 *    the binary form of the UUID. This form is 22 bytes long, maintains the
 *    same sort order as the full form, and is URL safe.
 *
 * For convenience, two static methods, Uuid::compress() and Uuid::expand(), are
 * available in order to simplify the process of converting an existing UUID
 * string between the full, short, or binary forms.
 *
 * For the purposes of software applications that use relational databases, the
 * most useful UUID versions are v7 and v5:
 *
 *  - UUIDv7 is ideal for generating permanently stored database keys, because
 *    these UUIDs naturally sort according to their chronological order of
 *    creation. This is the default version when generating a new UUID.
 *
 *  - UUIDv5 is ideal for situations where UUIDs need to be generated on demand
 *    from pre-existing data, but will not be stored permanently. The generation
 *    algorithm for UUIDv5 always produces the same output given the same input,
 *    so these UUIDs can be regenerated any number of times without varying.
 */
class Uuid implements \Stringable
{
	/**
	 * Default UUID version to create.
	 */
	public const DEFAULT_VERSION = 7;

	/**
	 * UUID versions that this class can generate.
	 *
	 * Versions 0 and 15 refer to the special nil and max UUIDs.
	 */
	public const SUPPORTED_VERSIONS = [0, 1, 2, 3, 4, 5, 6, 7, 15];

	/**
	 * UUID versions that use timestamps.
	 */
	public const TIME_BASED_VERSIONS = [1, 6, 7];

	/**
	 * UUID versions that hash input strings.
	 */
	public const HASH_BASED_VERSIONS = [3, 5];

	/**
	 * The special nil UUID.
	 */
	public const NIL_UUID = '00000000-0000-0000-0000-000000000000';

	/**
	 * The special max UUID.
	 */
	public const MAX_UUID = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

	/**
	 * The predefined namespace UUID for fully qualified domain names.
	 */
	public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for URLs.
	 */
	public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for ISO Object Identifiers.
	 */
	public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * The predefined namespace UUID for X.500 Distiguishing Names.
	 */
	public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

	/**
	 * Constants used to implement an alternative version of base64 encoding for
	 * compressed UUID strings.
	 */
	public const BASE64_STANDARD = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=';
	public const BASE64_SORTABLE = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz~ ';

	/*********************
	 * Internal properties
	 *********************/

	/**
	 * @var string
	 *
	 * The generated UUID.
	 */
	protected string $uuid;

	/**
	 * @var int
	 *
	 * The version of this UUID.
	 */
	protected int $version;

	/**
	 * @var float
	 *
	 * The Unix timestamp of this UUID.
	 */
	protected float $timestamp = 0.0;

	/****************************
	 * Internal static properties
	 ****************************/

	/**
	 * @var string
	 *
	 * Binary form of the UUIDv5 for the executing script's URL.
	 */
	protected static string $namespace;

	/**
	 * @var int
	 *
	 * The last adjusted timestamp used.
	 */
	protected static int $last_timestamp = 0;

	/**
	 * @var string
	 *
	 * The "clock sequence" value used in UUIDv1 and UUIDv6.
	 */
	protected static string $clock_seq;

	/**
	 * @var string
	 *
	 * The "node ID" value used in UUIDv1 and UUIDv6.
	 */
	protected static string $node;

	/**
	 * @var object
	 *
	 * A \DateTimeZone object for UTC.
	 */
	protected static \DateTimeZone $utc;

	/****************
	 * Public methods
	 ****************/

	/**
	 * Constructor.
	 *
	 * Handling of the $input parameter varies depending on the $verion:
	 *  - For hash-based UUIDs, $input is a string to hash.
	 *  - For time-based UUIDs, $input can be a Unix timestamp, a date string,
	 *    or null to use 'now'.
	 *  - For UUIDv2, an array listing the domain type, local identifer, and
	 *    optional time value.
	 *  - Otherwise, $input is ignored.
	 *
	 * In general, using an arbitrary timestamp to create a time-based UUID is
	 * discouraged, because the timestamp is normally intended to refer to the
	 * moment when the UUID was generated. However, there are some situations in
	 * which UUIDs may need to be created from arbitrary timestamps in order to
	 * preserve or enforce a particular position in a sorting sequence, so the
	 * ability to do so is available.
	 *
	 * @param int $version The UUID version to create.
	 * @param mixed $input Input for the UUID generator, if applicable.
	 */
	public function __construct(?int $version = null, mixed $input = null)
	{
		// Determine the version to use.
		$this->version = $version ?? self::DEFAULT_VERSION;

		if (!in_array($this->version, self::SUPPORTED_VERSIONS)) {
			trigger_error('Unsupported UUID version requested: ' . $this->version, E_USER_WARNING);
			$this->version = self::DEFAULT_VERSION;
		}

		// Check the input.
		switch (gettype($input)) {
			// UUIDv2 wants an array, but nothing else does.
			case 'array':
				$input = $this->version !== 2 ? reset($input) : $input;
				break;

			// Expected types.
			case 'string':
			case 'integer':
			case 'double':
				break;

			// Unexpected types.
			default:
				$input = null;
				break;
		}

		if (in_array($this->version, self::HASH_BASED_VERSIONS) && !isset($input)) {
			trigger_error('UUIDv' . $this->version . ' requires string input, but none was provided.', E_USER_WARNING);
			$this->version = 0;
		}

		// For time-based formats, we need a timestamp.
		if (in_array($this->version, self::TIME_BASED_VERSIONS)) {
			$this->setTimestamp($input ?? 'now');
		}

		switch ($this->version) {
			case 1:
				$hex = $this->getHexV1();
				break;

			case 2:
				$hex = $this->getHexV2((array) $input);
				break;

			case 3:
				$hex = $this->getHexV3($input);
				break;

			case 4:
				$hex = $this->getHexV4();
				break;

			case 5:
				$hex = $this->getHexV5($input);
				break;

			case 6:
				$hex = $this->getHexV6();
				break;

			case 7:
				$hex = $this->getHexV7();
				break;

			case 15:
				$hex = 'ffffffffffffffffffffffffffffffff';
				break;

			// 0 or unknown.
			default:
				$hex = '00000000000000000000000000000000';
				break;
		}

		switch ($hex) {
			case '00000000000000000000000000000000':
				$this->version = 0;
				$this->uuid = self::NIL_UUID;
				break;

			case 'ffffffffffffffffffffffffffffffff':
				$this->version = 15;
				$this->uuid = self::MAX_UUID;
				break;

			default:
				$this->uuid = implode('-', [
					substr($hex, 0, 8),
					substr($hex, 8, 4),
					dechex($this->version) . substr($hex, 13, 3),
					dechex(hexdec(substr($hex, 16, 4)) & 0x3fff | 0x8000),
					substr($hex, 20, 12),
				]);
				break;
		}
	}

	/**
	 * Returns the version of this UUID.
	 *
	 * @return int The version of this UUID.
	 */
	public function getVersion(): int
	{
		return $this->version;
	}

	/**
	 * Returns a binary representation of the UUID.
	 *
	 * @return string 16-byte binary string.
	 */
	public function getBinary(): string
	{
		return hex2bin(str_replace('-', '', $this->uuid));
	}

	/**
	 * Compresses $this->uuid to a 22-character string.
	 *
	 * This short form is URL-safe and maintains the same ASCII sort order as
	 * the original UUID string.
	 *
	 * @return string The short form of the UUID.
	 */
	public function getShortForm(): string
	{
		return rtrim(strtr(base64_encode($this->getBinary()), self::BASE64_STANDARD, self::BASE64_SORTABLE));
	}

	/**
	 * Returns the string representation of the generated UUID.
	 *
	 * @return string The UUID.
	 */
	public function __toString(): string
	{
		return $this->uuid;
	}

	/***********************
	 * Public static methods
	 ***********************/

	/**
	 * Creates a new instance of this class.
	 *
	 * This is just syntactical sugar to simplify method chaining and procedural
	 * coding styles, much like `date_create()` does for `new \DateTime()`.
	 *
	 * @param int $version The UUID version to create.
	 * @param mixed $input Input for the UUID generator, if applicable.
	 * @return object A new Uuid object.
	 */
	public static function create(?int $version = null, mixed $input = null): object
	{
		return new self($version, $input);
	}

	/**
	 * Creates a instance of this class from an existing UUID string.
	 *
	 * If the input UUID string is invalid, behaviour depends on the $strict
	 * parameter:
	 *  - If $strict is false, a warning error will be triggered and an
	 *    instance of this class for the nil UUID will be created.
	 *  - If $strict is true, a fatal error will be triggered.
	 *
	 * @param string $input A UUID string. May be compressed or uncompressed.
	 * @param bool $strict If set to true, invalid input causes a fatal error.
	 * @return object A Uuid object.
	 */
	public static function createFromString(string $input, bool $strict = false): object
	{
		// Binary format is 16 bytes long.
		// Short format is 22 bytes long.
		// Full format is 32 bytes long, once extraneous characters are removed.
		if (strlen($input) === 16) {
			$hex = bin2hex($input);
		} elseif (strlen($input) === 22 && strspn($input, self::BASE64_SORTABLE) === 22) {
			$hex = bin2hex(base64_decode(strtr($input, self::BASE64_SORTABLE, self::BASE64_STANDARD), true));
		} elseif (strspn(str_replace(['{', '-', '}'], '', $input), '0123456789ABCDEFabcdef') === 32) {
			$hex = strtolower(str_replace(['{', '-', '}'], '', $input));
		} else {
			trigger_error("Invalid UUID string supplied: {$input}", $strict ? E_USER_ERROR : E_USER_WARNING);

			$hex = '00000000000000000000000000000000';
		}

		$obj = new self();
		$obj->version = hexdec(substr($hex, 12, 1));
		$obj->uuid = implode('-', [
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12),
		]);

		return $obj;
	}

	/**
	 * Convenience method to get the binary or short form of a UUID string.
	 *
	 * @param string $input A UUID string. May be compressed or uncompressed.
	 * @param bool $to_base64 If true, compress to short form. Default: false.
	 * @return string The short form of the UUID string.
	 */
	public static function compress(string $input, bool $to_base64 = false): string
	{
		$uuid = self::createFromString($input);

		return $to_base64 ? $uuid->getShortForm() : $uuid->getBinary();
	}

	/**
	 * Convenience method to get the full form of a UUID string.
	 *
	 * @param string $input A UUID string. May be compressed or uncompressed.
	 * @return string The full form of the input.
	 */
	public static function expand(string $input): string
	{
		return self::createFromString($input)->uuid;
	}

	/**
	 * Returns the fully expanded value of self::$namespace.
	 *
	 * @return string A UUID string.
	 */
	public static function getNamespace(): string
	{
		self::setNamespace();

		$hex = bin2hex(self::$namespace);

		return implode('-', [
			substr($hex, 0, 8),
			substr($hex, 8, 4),
			substr($hex, 12, 4),
			substr($hex, 16, 4),
			substr($hex, 20, 12),
		]);
	}

	/**
	 * Sets self::$namespace to the binary form of a UUID.
	 *
	 * If $ns is false and self::$namespace has not yet been set, a default
	 * namespace UUID will be generated automatically.
	 *
	 * If $ns is a valid UUID string, that string will be used as the namespace
	 * UUID. A fatal error will be triggered if the string isn't a valid UUID.
	 *
	 * If $ns is true, any existing value of self::$namespace will be replaced
	 * with the default value. This is helpful if you need to reset the value of
	 * self::$namespace after temporarily using a custom namespace.
	 *
	 * See RFC 4122, section 4.3.
	 *
	 * @param bool|string $ns Either a valid UUID string, true to forcibly
	 *    reset to the automatically generated default value, or false to use
	 *    the current value (which will be set to the default if undefined).
	 *    Default: false.
	 */
	public static function setNamespace(bool|string $ns = false): void
	{
		// Manually supplied namespace.
		if (is_string($ns)) {
			self::$namespace = self::createFromString($ns, true)->getBinary();
			return;
		}

		// It's already set and we aren't resetting, so we're done.
		if (isset(self::$namespace) && !$ns) {
			return;
		}

		// Scheme.
		$scheme = strtolower($_SERVER['REQUEST_SCHEME'] ?? (isset($_SERVER['SERVER_PROTOCOL']) ? substr($_SERVER['SERVER_PROTOCOL'], 0, strcspn($_SERVER['SERVER_PROTOCOL'], '/')) : 'file'));

		if ($scheme === 'http' && !empty($_SERVER['HTTPS'])) {
			$scheme .= 's';
		}

		// Host.
		$host = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['HOST_NAME'] ?? php_uname('n');

		// Path.
		$path = $_SERVER['SCRIPT_NAME'] ?? (isset($_SERVER['REQUEST_URI']) ? substr($_SERVER['REQUEST_URI'], -strlen(($_SERVER['QUERY_STRING'] ?? '') . ($_SERVER['PATH_INFO'] ?? ''))) : null);

		if (!isset($path)) {
			$path = explode('/', $_SERVER['PHP_SELF']);

			if (isset($_SERVER['SCRIPT_FILENAME'])) {
				$basename = basename($_SERVER['SCRIPT_FILENAME']);
			}

			$found_file = false;

			foreach ($path as $key => $part) {
				if ($found_file) {
					unset($path[$key]);
				} else {
					$found_file = isset($basename) ? $part === $basename : strtolower(substr($part, -4)) === '.php';
				}
			}

			$path = implode('/', $path);
		}

		$scripturl = $scheme . '://' . trim($host, '/') . '/' . trim($path, '/');

		// Temporarily set self::$namespace to the binary form of the predefined
		// namespace UUID for URLs. (See RFC 4122, appendix C.)
		self::$namespace = hex2bin(str_replace('-', '', self::NAMESPACE_URL));

		// Set self::$namespace to the binary UUIDv5 for $scripturl.
		self::$namespace = self::create(5, $scripturl)->getBinary();
	}

	/******************
	 * Internal methods
	 ******************/

	/**
	 * UUIDv1: Time-based (but not time-sortable) UUID version.
	 *
	 * The 60-bit timestamp counts 100-nanosecond intervals since Oct 15, 1582,
	 * at 0:00:00 UTC (the date when the Gregorian calendar went into effect).
	 * The maximum date is Jun 18, 5623, at 21:21:00.6846975 UTC. (Note: In the
	 * introduction section of RFC 4122, the maximum date is stated to be
	 * "around A.D. 3400" but this appears to be errata. It would be true if the
	 * timestamp were a signed integer, but in fact the timestamp is unsigned.)
	 *
	 * Uniqueness is ensured by appending a "clock sequence" and a "node ID" to
	 * the timestamp. The clock sequence is a randomly initialized value that
	 * can be incremented or re-randomized whenever necessary. The node ID can
	 * either be a value that is already guaranteed to be unique (typically the
	 * network card's MAC address) or a random value. In this implementation,
	 * both values are initialized with random values each time the script runs.
	 *
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV1(): string
	{
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 1) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		return $parts['time_low'] . $parts['time_mid'] . '0' . $parts['time_high'] . $parts['clock_seq'] . $parts['node'];
	}

	/**
	 * UUIDv2: DCE security version. Suitable only for specific purposes and is
	 * rarely used.
	 *
	 * RFC 4122 does not describe this version. It just reserves UUIDv2 for
	 * "DCE Security version." Instead the specification for UUIDv2 can be found
	 * in the DCE 1.1 Authentication and Security Services specification.
	 * https://pubs.opengroup.org/onlinepubs/9696989899/chap5.htm#tagcjh_08_02_01_01
	 *
	 * The purpose of UUIDv2 is to embed information not only about where and
	 * when the UUID was created (via the node ID and timestamp), but also by
	 * whom it was created. This is accomplished by including a user, group, or
	 * organization ID and a type indicator (via a local domain ID and a local
	 * domain type indicator). This ability to know who created a UUID was
	 * apparently helpful for situations where a system needed to perform
	 * security checks related to the UUID value. For general purposes, however,
	 * this ability is not useful, and the trade-offs required to enable it
	 * are highly problematic.
	 *
	 * The most significant problem with UUIDv2 is its extremely high collision
	 * rate. For any given combination of node, local domain type, and local
	 * domain identifier, it can only produce 64 UUIDs every 7 minutes.
	 *
	 * This implementation uses random node IDs rather than real MAC addresses.
	 * This reduces the risk of UUIDv2 collisions occurring at a single site.
	 * Nevertheless, the collision risk remains high in global space.
	 *
	 * Another problem is that DEC 1.1's specifications only describe the case
	 * where a UUIDv2 is generated on a POSIX system, and do not give guidance
	 * about what to do on non-POSIX systems. In particular, UUIDv2 tries to
	 * encode the user ID and/or group ID of the user who created the UUID, but
	 * these concepts may not be defined or available on non-POSIX systems.
	 * Instead, the meaning of all local domain types and local domain IDs is
	 * left undefined by the specification for non-POSIX systems.
	 *
	 * If $input['id'] is set, it will be used as the local domain ID. If it is
	 * not set, the local domain ID will be determined based on the value of
	 * $input['domain']:
	 *
	 *  - If 'domain' is 0, the ID will be the current user's ID number.
	 *  - If 'domain' is 1, the ID will be the current user's group ID number.
	 *  - If 'domain' is 2, the ID will be an organization ID. In this
	 *    implementation, the organization ID is derived from self::$namespace.
	 *
	 * If cross-platform support is desirable, then scripts generating UUIDv2s
	 * should always provide a value in $input['id'] rather than relying on
	 * automatically determined values. ... Or better yet, don't use UUIDv2.
	 *
	 * @param array $input array
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV2(array $input): string
	{
		// No string keys?
		if (array_is_list($input)) {
			list($domain, $id, $timestamp) = array_pad($input, 3, null);
		} else {
			$domain = $input['domain'] ?? 0;
			$id = $input['id'] ?? null;
			$timestamp = $input['timestamp'] ?? null;
		}

		if ($domain < 0) {
			$this->version = 0;
			return str_replace('-', '', self::NIL_UUID);
		}

		$this->setTimestamp($timestamp ?? 'now');
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 2) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		// Try to find the $id. Only fully supported on POSIX systems.
		if (!isset($id)) {
			switch ($domain) {
				// Told to use the user ID.
				case 0:
					// On POSIX systems, use ID of the user executing the script.
					// On non-POSIX systems, use ID of the user that owns the script.
					$id = function_exists('posix_getuid') ? posix_getuid() : getmyuid();
					break;

				// Told to use the primary group ID.
				case 1:
					if (function_exists('posix_getgid')) {
						// POSIX systems can actually do this.
						$id = posix_getgid();
					} else {
						// On non-POSIX systems, fall back to user ID because
						// getmygid() returns nothing useful on non-POSIX systems.
						trigger_error('Automatic group domain is unsupported for UUIDv2 on non-POSIX systems. Falling back to user domain.', E_USER_NOTICE);

						$id = getmyuid();
						$domain = 0;
					}
					break;

				// Told to use organization ID.
				case 2:
					// This site's namespace UUID is suitable here.
					$id = hexdec(substr(self::getNamespace(), 0, 8));
					break;

				// Unknown domain.
				default:
					trigger_error("Cannot generate automatic UUIDv2 for unknown domain: {$domain}", E_USER_ERROR);
					break;
			}
		}

		$id = sprintf('%08x', $id);
		$domain = sprintf('%02x', $domain);

		// Re-randomize the node every time we generate a UUIDv2.
		self::$node = sprintf('%012x', hexdec(bin2hex(random_bytes(6))) | 0x10000000000);

		return $id . $parts['time_mid'] . '0' . $parts['time_high'] . substr($parts['clock_seq'], 0, 2) . $domain . $parts['node'];
	}

	/**
	 * UUIDv3: Creates a UUID for a name within a namespace using an MD5 hash.
	 *
	 * @param string $input The input string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV3(string $input): string
	{
		// Ensure self::$namespace is set.
		self::setNamespace();

		// Concat binary namespace UUID with $input, then get the MD5 hash.
		return md5(self::$namespace . $input);
	}

	/**
	 * UUIDv4: Creates a UUID from random data.
	 *
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV4(): string
	{
		return bin2hex(random_bytes(16));
	}

	/**
	 * UUIDv5: Creates a UUID for a name within a namespace using an SHA-1 hash.
	 *
	 * @param string $input The input string.
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV5(string $input): string
	{
		// Ensure self::$namespace is set.
		self::setNamespace();

		// Concat binary namespace UUID with $input, then get the SHA-1 hash.
		return substr(sha1(self::$namespace . $input), 0, 32);
	}

	/**
	 * UUIDv6: Time-sortable UUID version.
	 *
	 * The timestamp component is monotonic and puts the most significant bit
	 * first, so sorting these UUIDs lexically also sorts them chronologically.
	 *
	 * The 60-bit timestamp counts 100-nanosecond intervals since Oct 15, 1582,
	 * at 0:00:00 UTC (the date when the Gregorian calendar went into effect).
	 * The maximum date is Jun 18, 5623, at 21:21:00.6846975 UTC.
	 *
	 * Uniqueness is ensured by appending a "clock sequence" and a "node ID" to
	 * the timestamp. The clock sequence is a randomly initialized value that
	 * can be incremented or re-randomized whenever necessary. The node ID can
	 * either be a value that is already guaranteed to be unique (typically the
	 * network card's MAC address) or a random value. In this implementation,
	 * both values are initialized with random values each time the script runs.
	 *
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV6(): string
	{
		$parts = $this->getGregTimeParts();

		// Date out of range? Bail out.
		if ($this->version != 6) {
			return str_replace('-', '', $this->version === 15 ? self::MAX_UUID : self::NIL_UUID);
		}

		return $parts['time_high'] . $parts['time_mid'] . substr($parts['time_low'], 0, -3) . '0' . substr($parts['time_low'], -3) . $parts['clock_seq'] . $parts['node'];
	}

	/**
	 * UUIDv7: Improved time-sortable UUID version.
	 *
	 * The timestamp component is monotonic and puts the most significant bit
	 * first, so sorting these UUIDs lexically also sorts them chronologically.
	 *
	 * The 48-bit timestamp measures milliseconds since the Unix epoch. The
	 * maximum date is Aug 01, 10889, at 05:31:50.655 UTC.
	 *
	 * Uniqueness is ensured by appending 74 random bits to the timestamp.
	 *
	 * @return string 32 hexadecimal digits.
	 */
	protected function getHexV7(): string
	{
		$timestamp = $this->adjustTimestamp();

		// Date out of range? Bail out.
		if ($timestamp < 0) {
			$this->version = 0;
			return str_replace('-', '', self::NIL_UUID);
		}

		if ($timestamp > 281474976710655) {
			$this->version = 15;
			return str_replace('-', '', self::MAX_UUID);
		}

		return sprintf('%012x', $timestamp) . bin2hex(random_bytes(10));
	}

	/**
	 * Helper method for getHexV1 and getHexV6.
	 *
	 * @return array Components for the UUID.
	 */
	protected function getGregTimeParts(): array
	{
		$timestamp = $this->adjustTimestamp();

		// We can't track the clock sequence between executions, so initialize
		// it to a random value each time. See RFC 4122, section 4.1.5.
		if (!isset(self::$clock_seq)) {
			self::$clock_seq = bin2hex(random_bytes(2));
		}

		// We don't have direct access to the MAC address in PHP, but the spec
		// allows using random data instead, provided that we set the least
		// significant bit of its first octet to 1. See RFC 4122, section 4.5.
		if (!isset(self::$node)) {
			self::$node = sprintf('%012x', hexdec(bin2hex(random_bytes(6))) | 0x10000000000);
		}

		// If necessary, increment $clock_seq.
		if (isset(self::$last_timestamp) && self::$last_timestamp >= $timestamp) {
			self::$clock_seq = hexdec(self::$clock_seq);
			self::$clock_seq++;
			self::$clock_seq %= 0x10000;
			self::$clock_seq = sprintf('%04x', self::$clock_seq);
		}

		self::$last_timestamp = max($timestamp, self::$last_timestamp);

		// Date out of range? Bail out.
		if ($timestamp < 0) {
			$this->version = 0;
			return [];
		}

		if ($timestamp > 1152921504606846975) {
			$this->version = 15;
			return [];
		}

		$time_hex = sprintf('%015x', $timestamp);

		return [
			'time_high' => substr($time_hex, 0, 3),
			'time_mid' => substr($time_hex, 3, 4),
			'time_low' => substr($time_hex, 7, 8),
			'clock_seq' => self::$clock_seq,
			'node' => self::$node,
		];
	}

	/**
	 * Sets $this->timestamp to a microsecond-precision Unix timestamp.
	 *
	 * @param string|float $input A timestamp or date string. Default: 'now'.
	 */
	protected function setTimestamp(string|float $input = 'now'): void
	{
		if ($input === 'now') {
			$this->timestamp = (float) microtime(true);
		} else {
			$date = @date_create((is_numeric($input) ? '@' : '') . $input);

			if ($date === false) {
				$date = date_create();
			}

			if (!isset(self::$utc)) {
				self::$utc = new \DateTimeZone('UTC');
			}

			$date->setTimezone(self::$utc);

			$this->timestamp = (float) $date->format('U.u');

			unset($date);
		}
	}

	/**
	 * Adjusts a Unix timestamp to meet the needs of the this UUID version.
	 *
	 * @return int A timestamp value appropriate for this UUID version.
	 */
	protected function adjustTimestamp(): int
	{
		$timestamp = $this->timestamp ?? (float) microtime(true);

		switch ($this->version) {
			// For v1, v2, & v6, use epoch of Oct 15, 1582, at midnight UTC, and
			// use 100-nanosecond precision. Since PHP only offers microsecond
			// precision, the last digit will always be 0, but that's fine.
			case 1:
			case 2:
			case 6:
				$timestamp += 12219292800;
				$timestamp *= 10000000;
				break;

			// For v7, use millisecond precision.
			case 7:
				$timestamp *= 1000;
				break;

			default:
				trigger_error("Unsupported UUID version requested: {$this->version}", E_USER_WARNING);
				return $timestamp;
		}

		$timestamp = (int) $timestamp;

		if ($timestamp < 0) {
			trigger_error("Timestamp out of range for UUIDv{$this->version}", E_USER_WARNING);
		}

		return $timestamp;
	}
}