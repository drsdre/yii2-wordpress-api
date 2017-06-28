<?php

namespace drsdre\WordpressApi;

use yii\helpers\Json;
use yii\authclient\OAuthToken;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\httpclient\Response;

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
	 * @param array $request_data
	 *
	 * @return self
	 */
	public function getData(
		$entity_url,
		$context = 'view',
		$page = null,
		$page_length = 10,
		array $request_data = []
	) {
		// Set query data
		$request_data['context'] = $context;
		$request_data['per_page'] = $page_length;

		if ( ! is_null( $page ) ) {
			$request_data['page'] = $page;
		}

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'get' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $request_data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Put data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $update_data
	 *
	 * @return self
	 */
	public function putData(
		$entity_url,
		$context = 'edit',
		array $update_data
	) {
		// Set Set query data
		$update_data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'put' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $update_data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Patch with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $update_data
	 *
	 * @return self
	 */
	public function patchData(
		$entity_url,
		$context = 'edit',
		array $update_data
	) {
		// Set Set query data
		$update_data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'patch' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $update_data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Post data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $update_data
	 *
	 * @return self
	 */
	public function postData(
		$entity_url,
		$context = 'view',
		array $update_data
	) {
		// Set context
		$update_data['context'] = $context;

		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'post' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setData( $update_data )
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Delete data with entity url
	 *
	 * @param string $entity_url
	 *
	 * @return self
	 */
	public function deleteData(
		$entity_url
	) {
		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'delete' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) ) // Strip endpoint url from url param
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
	 * @return string
	 */
	public function asRaw() {
		if ( isset( $this->response->content ) ) {
			return $this->response->content;
		}
	}

	/**
	 * Get the request content from the last request
	 */
	public function getLastRequestContent() {
		if (isset($this->request->content)) {
			return $this->request->content;
		}
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
				// Execute the request
				$this->response = $this->request->send();

				// Test the result
				$result_content = Json::decode( $this->response->content, false );

				// Check if response is valid
				if ( ! $this->response->isOk ) {

					// Error handling
					switch ( $this->response->statusCode ) {
						case 304:
							throw new Exception( 'Not Modified.', $this->response->statusCode );
						case 400:
							// Collect the request parameters
							$parameter_errors = [];
							foreach ( $result_content->data->params as $param_error ) {
								$parameter_errors[] = $param_error;
							}

							throw new Exception(
								'Bad Request: ' . implode( ' | ',
									$parameter_errors ) . ' (request ' . $this->request->getFullUrl() . ').',
								$this->response->statusCode
							);
						case 401:
							if ( isset( $result_content->code ) && $result_content->code == 'json_oauth1_nonce_already_used' ) {
								// Oauth1 nonce already used, can be retried

								// Map to status code 432 (unassigned)
								throw new Exception(
									( isset( $result_content->message ) ? $result_content->message : $this->response->content ) .
									' ' . $this->request->getFullUrl(),
									432
								);
							}

							// Generic 401 error
							throw new Exception(
								'Unauthorized: ' . $result_content->message . ' (request ' . $this->request->getFullUrl() . ').',
								$this->response->statusCode
							);
						case 403:
							throw new Exception(
								'Forbidden: request not allowed accessing ' . $this->request->getFullUrl(),
								$this->response->statusCode
							);
						case 404:
							throw new Exception(
								'Not found: ' . $this->request->getFullUrl() . 'does not exist.',
								$this->response->statusCode
							);
						case 405:
							throw new Exception(
								'Method Not Allowed: incorrect HTTP method ' . $this->request->getMethod() . ' provided.',
								$this->response->statusCode
							);
						case 405:
							throw new Exception(
								'Gone: resource ' . $this->request->getFullUrl() . ' has moved.',
								$this->response->statusCode
							);
						case 415:
							throw new Exception(
								'Unsupported Media Type (incorrect HTTP method ' . $this->request->getMethod() . ' provided).',
								$this->response->statusCode
							);
						case 429:
							throw new Exception(
								'Too many requests: client is rate limited.',
								$this->response->statusCode
							);
						case 500:
							$content = $this->asArray();
							// Check if specific error code have been returned
							if ( isset( $content['code'] ) && $content['code'] == 'term_exists' ) {
								// Map to status code 433 (unassigned, used for 'item exists' error type)
								throw new Exception(
									isset( $content['message'] ) ? $content['message'] : 'Internal server error.',
									433
								);
							}

							throw new Exception( 'Internal server error: ' .
							                     ( isset($result_content->message) ?
								                     $result_content->message :
								                     $this->response->content ),
								$this->response->statusCode );
						case 501:
							throw new Exception( 'Not Implemented: ' . $this->request->getFullUrl() . '.',
								$this->response->statusCode );
						case 502:
							throw new Exception( 'Bad Gateway: server has an issue.',
								$this->response->statusCode );
						default:
							throw new Exception( 'Status code ' . $this->response->statusCode . ' returned for URL ' . $this->request->getFullUrl(),
								$this->response->statusCode );
					}
				}
				$request_success = true;
			} catch ( InvalidParamException $e ) {
				// Handle JSON parsing error
				// Map to status code 512 (unassigned, used for 'illegal response')
				throw new Exception( 'Invalid JSON data returned (' . $e->getMessage() . "): " . $this->response->content, 512 );

			} catch ( \yii\httpclient\Exception $e ) {
				// Check if call can be retried and max retries is not hit
				// Should this be code 7 CURLE_COULDNT_CONNECT ?
				if ( $e->getCode() == 2 && $this->retries < $this->max_retry_attempts ) {
					// Retry the request
					$request_success = false;
					$this->retries ++;
				} else {
					// Too many retries, retrow Exception
					throw $e;
				}

			} catch ( Exception $e ) {
				// Retry if exception can be retried
				if ( $e->getHandleCode() == Exception::HANDLE_AS_RETRY && $this->retries < $this->max_retry_attempts ) {
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