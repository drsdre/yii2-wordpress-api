<?php

namespace drsdre\WordpressApi;

/**
 * Wordpress API Exception class for Wordpress Client errors
 *
 * Error codes handling retry and other specific conditions
 *
 * @author Andre Schuurman <andre.schuurman+yii2-wordpress-api@gmail.com>
 */
class Exception extends \yii\base\Exception {

	const FAIL = 0;
	const RETRY = 1;
	const WAIT_RETRY = 2;
	const ITEM_EXISTS = 3;
	const ILLEGAL_RESPONSE = 4;

	static $code_names = [
		self::FAIL => ' unrecoverable',
		self::RETRY => ' and can be retried',
		self::WAIT_RETRY => ' and can be retried after wait time',
		self::ITEM_EXISTS => ' because item already exists',
		self::ILLEGAL_RESPONSE => ' with an illegal JSON response',
	];

	/**
	 * @return string the user-friendly name of this exception
	 */
	public function getName()
	{
		return 'API request failed'.$this->getCodeName();
	}

	/**
	 * @return mixed|string
	 */
	public function getCodeName() {
		return isset(self::$code_names[$this->getCode()])?self::$code_names[$this->getCode()]:'';
	}
}