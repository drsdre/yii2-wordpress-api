<?php

namespace drsdre\WordpressApi;

/**
 * Wordpress API Exception class for Wordpress Client errors
 *
 * Error codes handling retry and other specific conditions
 *
 * @author Andre Schuurman <andre.schuurman+yii2-wordpress-api@gmail.com>
 * @since 2.0
 */
class Exception extends yii\base\Exception {

	const FAIL = 0;
	const RETRY = 1;
	const WAIT_RETRY = 2;
	const ITEM_EXISTS = 3;

	/**
	 * @return string the user-friendly name of this exception
	 */
	public function getName()
	{
		return 'API response failed, retry';
	}
}