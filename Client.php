<?php

namespace drsdre\WordpressApi;

use yii\helpers\Json;
use yii\authclient\OAuthToken;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

/**
 * Client for communicating with a Wordpress Rest API interface (standard included from Wordpress 4.7 on).
 *
 * Authentication can either be done using:
 * - oAuth1 plugin, see drsdre\WordpressApi\OAuth1.php
 * - Basic Authentication with user/password (not recommended for live) see https://github.com/WP-API/Basic-Auth.
 * See http://v2.wp-api.org/guide/authentication/
 *
 * @see    http://v2.wp-api.org/
 *
 * @author Andre Schuurman <andre.schuurman+yii2-wordpress-api@gmail.com>
 */
class Client extends \yii\base\Object {

	/**
	 * @var string API url endpoint
	 */
	public $endpoint = '';

	/**
	 * @var string API client_key
	 */
	public $client_key;

	/**
	 * @var string API client_secret
	 */
	public $client_secret;

	/**
	 * @var string API access token
	 */
	public $access_token;

	// For development only

	/**
	 * @var string email
	 */
	public $username;

	/**
	 * @var string email
	 */
	public $password;

	/**
	 * @var integer $result_total_records
	 */
	public $result_total_records;

	/**
	 * @var integer $result_total_pages
	 */
	public $result_total_pages;

	/**
	 * @var array $result_allow_methods
	 */
	public $result_allow_methods;

	/**
	 * @var int $max_retry_attempts retry attempts if possible
	 */
	public $max_retry_attempts = 5;

	/**
	 * @var int $retry_attempts retry attempts if possible
	 */
	public $retries = 0;
	/**
	 * @var yii\httpclient\Request $request
	 */
	protected $request;
	/**
	 * @var yii\httpclient\Response $response
	 */
	protected $response;
	/**
	 * @var OAuth1 $client
	 */
	private $client;

	/**
	 * Initialize object
	 *
	 * @throws InvalidConfigException
	 */
	public function init() {
		if ( empty( $this->endpoint ) ) {
			throw new InvalidConfigException( 'Specify valid endpoint.' );
		}

		if ( empty( $this->client_key ) && empty( $this->client_secret ) || empty( $this->access_token ) ) {
			if ( empty( $this->username ) || empty( $this->password ) ) {
				throw new InvalidConfigException(
					'Either specify client_key, client_secret & access_token for OAuth1 [production] ' .
					'or username and password for basic auth [development only].' );
			}

			$this->client = new yii\httpclient\Client( [
				'baseUrl'        => $this->endpoint,
				'requestConfig'  => [
					'format' => yii\httpclient\Client::FORMAT_JSON,
				],
				'responseConfig' => [
					'format' => yii\httpclient\Client::FORMAT_JSON,
				],
			] );
		} else {
			// Create your OAuthToken
			$token = new OAuthToken();
			$token->setParams( $this->access_token );

			// Start a WordpressAuth session
			$this->client = new OAuth1( [
				'accessToken'    => $token,
				'consumerKey'    => $this->client_key,
				'consumerSecret' => $this->client_secret,
				'apiBaseUrl'     => $this->endpoint,
			] );

			// Use the client apiBaseUrl as endpoint
			$this->endpoint = $this->client->apiBaseUrl;
		}
	}

	// API Interface Methods

	/**
	 * Get data using entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param int|null $page
	 * @param int $page_length
	 *
	 * @return self
	 */
	public function getData(
		$entity_url,
		$context = 'view',
		$page = null,
		$page_length = 10
	) {
		// Set query data
		$data = [
			'context'  => $context,
			'per_page' => $page_length,
		];

		if ( ! is_null( $page ) ) {
			$data['page'] = $page;
		}

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'get' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Put data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $data
	 *
	 * @return self
	 */
	public function putData(
		$entity_url,
		$context = 'edit',
		array $data
	) {
		// Set Set query data
		$data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'put' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Patch with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $data
	 *
	 * @return self
	 */
	public function patchData(
		$entity_url,
		$context = 'edit',
		array $data
	) {
		// Set Set query data
		$data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'patch' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Post data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $data
	 *
	 * @return self
	 */
	public function postData(
		$entity_url,
		$context = 'view',
		array $data
	) {
		// Set context
		$data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'post' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Delete data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context
	 * @param array $data
	 *
	 * @return self
	 */
	public function deleteData(
		$entity_url,
		$context = 'edit',
		array $data
	) {
		// Set context
		$data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'delete' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) ) // Strip endpoint url from url param
			     ->setData( $data )
		;

		$this->executeRequest();

		return $this;
	}

	// API Data response methods

	/**
	 * Return content as array
	 *
	 * @return array
	 * @throws Exception
	 */
	public function asArray() {
		if ( isset( $this->response->content ) ) {
			return Json::decode( $this->response->content, true );
		}
	}

	/**
	 * Return content as object
	 *
	 * @return \stdClass
	 * @throws Exception
	 */
	public function asObject() {
		if ( isset( $this->response->content ) ) {
			return Json::decode( $this->response->content, false );
		}
	}

	/**
	 * Get the raw content object
	 *
	 * @return yii\httpclient\Response
	 */
	public function asRaw() {
		return $this->response->content;
	}

	/**
	 * Get the request content from the last request
	 */
	public function getLastRequestContent() {
		return $this->request->content;
	}

	/**
	 * Create authenticated request
	 *
	 * @return yii\httpclient\Request
	 */
	protected function createAuthenticatedRequest() {
		if ( is_a( $this->client, 'drsdre\WordpressApi\OAuth1' ) ) {
			// oAuth1 request
			$request = $this->client
				->createApiRequest();
		} else {
			// Basic authentication request
			$request = $this->client
				->createRequest();

			// Use Basic Authentication
			$request->setHeaders( [
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			] );
			/*->addHeaders([
					'X-Auth-Key' => $this->key,
					'X-Auth-Email' => $this->email,
					'content-type' => 'application/json'
				]);
			*/
		}

		return $request;
	}

	/**
	 * Parse API response
	 *
	 * @throws Exception on failure
	 * @throws \yii\httpclient\Exception
	 * @throws \Exception
	 * @return \stdClass
	 */
	protected function executeRequest() {

		$this->retries = 0;

		do {
			try {
				// Execute request
				$this->response = $this->request->send();

				// Test result
				$result_content = Json::decode( $this->response->content, false );

				// Check for response status code
				if ( ! $this->response->isOk ) {

					switch ( $this->response->statusCode ) {
						case 304:
							throw new Exception(
								'Not Modified.'
							);
						case 400:
							$parameter_errors = [];
							foreach($result_content->data->params as $param_error) {
								$parameter_errors[] = $param_error;
							}
							throw new Exception(
								'Bad Request: '.implode(' | ', $parameter_errors).' (request ' . $this->request->getFullUrl() . ').',
								Exception::FAIL
							);
						case 401:
							// Nonce used can be retried, other 401 can not
							if ( isset( $result_content->code ) && $result_content->code == 'json_oauth1_nonce_already_used' ) {
								throw new Exception(
									( isset( $result_content->message ) ? $result_content->message : $this->response->content ) .
									' ' . $this->request->getFullUrl(),
									Exception::RETRY
								);
							} else {
								throw new Exception(
									'Unauthorized: '.$result_content->message.' (request ' . $this->request->getFullUrl() . ').',
									Exception::FAIL
								);
							}
						case 403:
							throw new Exception(
								'Forbidden: request not authenticated accessing ' . $this->request->getFullUrl(),
								Exception::FAIL
							);
						case 404:
							throw new Exception(
								'No data found: route ' . $this->request->getFullUrl() . 'does not exist.',
								Exception::FAIL
							);
						case 405:
							throw new Exception(
								'Method Not Allowed: incorrect HTTP method ' . $this->request->getMethod() . ' provided.',
								Exception::FAIL
							);
						case 415:
							throw new Exception(
								'Unsupported Media Type (incorrect HTTP method ' . $this->request->getMethod() . ' provided).',
								Exception::FAIL
							);
						case 429:
							throw new Exception(
								'Too many requests: client is rate limited.',
								Exception::WAIT_RETRY
							);
						case 500:
							$content = $this->asArray();
							// Check if specific error code have been returned
							if ( isset( $content['code'] ) && $content['code'] == 'term_exists' ) {
								throw new Exception(
									isset( $content['message'] ) ? $content['message'] : 'Internal server error.',
									Exception::ITEM_EXISTS
								);
							} else {
								throw new Exception(
									'Internal server error.',
									Exception::FAIL
								);
							}

						case 502:
							throw new Exception(
								'Bad Gateway error: server has an issue.',
								Exception::RETRY
							);
						default:
							throw new Exception(
								'Unknown code ' . $this->response->statusCode . ' for URL ' . $this->request->getFullUrl()
							);
					}
				}
				$request_success = true;
			} catch (InvalidParamException $e) {
				throw new Exception(
					'Invalid JSON data returned: ' . $e->getMessage(),
					Exception::FAIL
				);

			} catch ( \yii\httpclient\Exception $e ) {
				// Check if call can be retried and max retries is not hit
				// Should this be code 7 CURLE_COULDNT_CONNECT ?
				if ( $e->getCode() == 2 && $this->retries < $this->max_retry_attempts ) {
					// Retry the request
					$request_success = false;
					$this->retries ++;
				} else {
					// Too many retries, retrow Exception
					throw new Exception(
						'Curl connection error: ' . $e->getMessage(),
						Exception::FAIL
					);
				}

			} catch ( Exception $e ) {
				// Retry if exception can be retried
				if ( $e->getCode() == Exception::RETRY && $this->retries < $this->max_retry_attempts ) {
					// Retry the request
					$request_success = false;
					$this->retries ++;
				} else {
					// Too many retries, retrow Exception
					throw $e;
				}
			}

			// Retry until request is successful or max attempts has been reached
		} while ( $request_success === false && $this->retries <= $this->max_retry_attempts );

		// Update result information (for paging)
		$this->result_total_records = $this->response->getHeaders()['X-WP-Total'];
		$this->result_total_pages   = $this->response->getHeaders()['X-WP-TotalPages'];
		$this->result_allow_methods = $this->response->getHeaders()['allow'];
	}
}