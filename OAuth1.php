<?php

namespace drsdre\WordpressApi;

/**
 * Allows authentication through Wordpress oAuth 1.
 *
 * In order to use Wordpress OAuth you must register your application in your Wordpress site with
 * the WP REST API Oauth plugin.
 *
 * Example application configuration:
 *
 * ```php
 * 'components' => [
 *     'authClientCollection' => [
 *         'class' => 'yii\authclient\Collection',
 *         'clients' => [
 *             'wordpress' => [
 *                 'class' => 'drsdre\WordpressApi\Auth',
 *                 'consumerKey' => 'wordpress_client_key',
 *                 'consumerSecret' => 'wordpress_client_secret',
 *             ],
 *         ],
 *     ]
 *     ...
 * ]
 * ```
 *
 * @see    https://github.com/WP-API/OAuth1
 * @see    https://wordpress.org/plugins/rest-api-oauth1/
 *
 * @author Andre Schuurman <andre.schuurman+yii2-wordpress-api@gmail.com>
 */
class OAuth1 extends \yii\authclient\OAuth1 {

	/**
	 * @inheritdoc
	 */
	public $authUrl = 'oauth1/authorize';

	/**
	 * @inheritdoc
	 */
	public $requestTokenUrl = 'oauth1/request';

	/**
	 * @inheritdoc
	 */
	public $requestTokenMethod = 'POST';

	/**
	 * @inheritdoc
	 */
	public $accessTokenUrl = 'oauth1/access';

	/**
	 * @inheritdoc
	 */
	public $accessTokenMethod = 'POST';

	/**
	 * @inheritdoc
	 */
	public $authorizationHeaderMethods = [ 'POST', 'PATCH', 'PUT', 'DELETE' ];

	/**
	 * var $apiBaseUrl is Wordpress site url (json slug is auto added)
	 */
	public $apiBaseUrl;

	/**
	 * @inheritdoc
	 */
	public function __construct( array $config ) {
		parent::__construct( $config );

		// Add apiBaseUrl to auth, requestToken and accessToken URL's
		$this->authUrl         = $this->apiBaseUrl . '/' . $this->authUrl;
		$this->requestTokenUrl = $this->apiBaseUrl . '/' . $this->requestTokenUrl;
		$this->accessTokenUrl  = $this->apiBaseUrl . '/' . $this->accessTokenUrl;

		// Set apiBaseUrl to Wordpress Rest API base slug
		$this->apiBaseUrl = $this->apiBaseUrl . '/wp-json';
	}

	/**
	 * @inheritdoc
	 */
	protected function initUserAttributes() {
		return $this->api( 'account/verify_credentials.json', 'GET', $this->attributeParams );
	}

	/**
	 * @inheritdoc
	 */
	protected function defaultName() {
		return 'wordpress';
	}

	/**
	 * @inheritdoc
	 */
	protected function defaultTitle() {
		return 'Wordpress';
	}
}