<?php

namespace drsdre\WordpressApi;

/**
 * Wordpress API Exception class for Wordpress Client errors
 *
 * Error codes handling retry and other specific conditions
 * Curl error codes range from 1 to 92, http status codes from 100 to 599
 *
 * @author Andre Schuurman <andre.schuurman+yii2-wordpress-api@gmail.com>
 */
class Exception extends \yii\base\Exception {

	const HANDLE_AS_FAIL = 0;
	const HANDLE_AS_RETRY = 1;
	const HANDLE_AS_WAIT_RETRY = 2;
	const HANDLE_AS_ITEM_EXISTS = 3;
	const HANDLE_AS_ITEM_NOT_FOUND = 4;
	const HANDLE_AS_ILLEGAL_RESPONSE = 4;

	static $handle_names = [
		self::HANDLE_AS_FAIL => 'unrecoverable',
		self::HANDLE_AS_RETRY => 'can be retried',
		self::HANDLE_AS_WAIT_RETRY => 'can be retried after wait time',
		self::HANDLE_AS_ITEM_NOT_FOUND => 'item is not found',
		self::HANDLE_AS_ITEM_EXISTS => 'item already exists',
		self::HANDLE_AS_ILLEGAL_RESPONSE => 'illegal JSON response',
	];

	static $code_handle_mappings = [
		// Curl timeout errors
		2 => self::HANDLE_AS_RETRY,
		7 => self::HANDLE_AS_RETRY,
		28 => self::HANDLE_AS_RETRY,
		55 => self::HANDLE_AS_RETRY,
		56 => self::HANDLE_AS_RETRY,
		// HTTP status errors
		404 => self::HANDLE_AS_ITEM_NOT_FOUND,
		410 => self::HANDLE_AS_ITEM_NOT_FOUND,
		429 => self::HANDLE_AS_WAIT_RETRY,
		432 => self::HANDLE_AS_RETRY,
		433 => self::HANDLE_AS_ITEM_EXISTS,
		502 => self::HANDLE_AS_RETRY,
		512 => self::HANDLE_AS_ILLEGAL_RESPONSE,
	];

	/**
	 * @return string the user-friendly name of this exception
	 */
	public function getName()
	{
		return "API request failed (#{$this->getCode()}): {$this->getMessage()}. To be handled as {$this->getHandleText()}";
	}

	/**
	 * @return int
	 */
	public function getHandleCode() {
		return isset(self::$code_handle_mappings[$this->getCode()])?self::$code_handle_mappings[$this->getCode()]:self::HANDLE_AS_FAIL;
	}

	/**
	 * @return string
	 */
	public function getHandleText() {
		return self::$handle_names[$this->getHandleCode()];
	}
}