<?php

namespace drsdre\WordpressApi;

use yii\helpers\Json;
use yii\authclient\OAuthToken;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\httpclient\Request;
use yii\httpclient\Response;
use yii\httpclient\Client as HttpClient;

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
class Client extends BaseObject {

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
	 * @var array API access token
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
	 * @var Request $request
	 */
	protected $request;
	/**
	 * @var Response $response
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

			$this->client = new HttpClient( [
				'baseUrl'        => $this->endpoint,
				'requestConfig'  => [
					'format' => HttpClient::FORMAT_JSON,
				],
				'responseConfig' => [
					'format' => HttpClient::FORMAT_JSON,
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
	 * @param int|null $page_number
	 * @param int $page_length
	 * @param array $request_data
	 *
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
	 */
	public function getData(
		$entity_url,
		$context = 'view',
		$page_number = null,
		$page_length = 10,
		array $request_data = []
	) {
		// Set query data
		$request_data['context']  = $context;
		$request_data['per_page'] = $page_length;

		if ( ! is_null( $page_number ) ) {
			$request_data['page'] = $page_number;
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
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
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
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
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

	// API Data response methods

	/**
	 * Post data with entity url
	 *
	 * @param string $entity_url
	 * @param string $context view or edit
	 * @param array $update_data
	 *
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
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
	 * @param bool $force
	 *
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
	 */
	public function deleteData(
		string $entity_url,
		bool $force = true
	) {
		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'delete' )
			     ->setUrl(
				     str_replace( $this->endpoint . '/', '', $entity_url ) .
				     ( $force ? '?force=true' : '' )
			     ) // Strip endpoint url from url param
		;

		$this->executeRequest();

		return $this;
	}

	/**
	 * Upload file to entity url
	 *
	 * @param $entity_url
	 * @param $file_name
	 * @param $file_content_type
	 * @param $file_data
	 *
	 * @return $this
	 * @throws Exception
	 * @throws \Exception
	 * @throws \yii\httpclient\Exception
	 */
	public function uploadFile(
		$entity_url,
		$file_name,
		$file_content_type,
		& $file_data
	) {
		$this->request =
			$this->createAuthenticatedRequest()
			     ->setMethod( 'post' )
			     ->setUrl( str_replace( $this->endpoint . '/', '', $entity_url ) )// Strip endpoint url from url param
			     ->setContent( $file_data )
			     ->addHeaders( [
				     'content-disposition' => 'attachment; filename=' . $file_name,
				     'content-type'        => $file_content_type,
			     ] )
		;

		$this->executeRequest();

		return $this;
	}

	// API Data response methods

	/**
	 * Return content as array
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function asArray() {
		if ( isset( $this->response->content ) ) {
			return Json::decode( $this->response->content, true );
		}

		return [];
	}

	/**
	 * Return content as object
	 *
	 * @return \stdClass
	 * @throws \Exception
	 */
	public function asObject() {
		if ( isset( $this->response->content ) ) {
			return Json::decode( $this->response->content, false );
		}

		return null;
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

		return null;
	}

	/**
	 * Get the request content from the last request
	 *
	 * @return string
	 */
	public function getLastRequestContent() {
		if ( isset( $this->request->content ) ) {
			return $this->request->content;
		}

		return null;
	}

	/**
	 * Create authenticated request
	 *
	 * @return Request
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
	 * @throws \Exception
	 */
	protected function executeRequest() {

		$this->retries = 0;

		do {
			try {
				// Execute the request
				$this->response = $this->request->send();

				// Test the result
				$result_content = Json::decode( $this->response->content, false );

				try {

					// Check if response is valid
					if ( ! $this->response->isOk ) {

						$error_data = (isset($result_content->code)?' Code: ' . $result_content->code . ' ':'') .
									' URL: ' . $this->request->getFullUrl();

						// Error handling
						switch ( $this->response->statusCode ) {
							case 304:
								throw new Exception( 'Not Modified.', $this->response->statusCode );
							case 400:
								// Collect the request parameters
								$parameter_errors = [];
								if ( isset( $result_content->data->params ) ) {
									foreach ( $result_content->data->params as $param_error ) {
										$parameter_errors[] = $param_error;
									}
								}

								throw new Exception(
									'Bad Request ' . (isset($result_content->message)?$result_content->message:'unknown') .
									' Params: ' .implode( ' | ', $parameter_errors ) .
									$error_data,
									$this->response->statusCode
								);
							case 401:
								if ( isset( $result_content->code ) && $result_content->code == 'json_oauth1_nonce_already_used' ) {
									// Oauth1 nonce already used, can be retried

									// Map to status code 432 (unassigned)
									throw new Exception(
										( isset( $result_content->message ) ? $result_content->message : $this->response->content ) .
										$error_data,
										432
									);
								}

								// Generic 401 error
								throw new Exception(
									'Unauthorized: ' .
									( isset( $result_content->message ) ? $result_content->message : $this->response->content ) .
									$error_data,
									$this->response->statusCode
								);
							case 403:
								throw new Exception(
									'Forbidden: request not allowed.' .
									$error_data,
									$this->response->statusCode
								);
							case 404:
								throw new Exception(
									'Not found: URL does not exist.' .
									$error_data,
									$this->response->statusCode
								);
							case 405:
								throw new Exception(
									'Method Not Allowed: incorrect HTTP method ' . $this->request->getMethod() . ' provided.' .
									$error_data,
									$this->response->statusCode
								);
							case 410:
								throw new Exception(
									'Gone: URL has moved.' .
									$error_data,
									$this->response->statusCode
								);
							case 415:
								throw new Exception(
									'Unsupported Media Type (incorrect HTTP method ' . $this->request->getMethod() . ' provided).' .
									$error_data,
									$this->response->statusCode
								);
							case 429:
								throw new Exception(
									'Too many requests: client is rate limited.' .
									$error_data,
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
								                     ( isset( $result_content->code ) ?
									                     $result_content->code . ' => ' :
									                     $this->response->content ) .
								                     ( isset( $result_content->message ) ?
									                     $result_content->message :
									                     '' ) .
								                     ( isset( $result_content->data ) ?
									                     ' (' . print_r( $result_content->data, true ) . ')' :
									                     '' ),
									$this->response->statusCode );
							case 501:
								throw new Exception( 'Not Implemented.' . $error_data,
									$this->response->statusCode );
							case 502:
								throw new Exception( 'Bad Gateway: server has an issue.' . $error_data,
									$this->response->statusCode );
							default:
								throw new Exception( 'Status code ' . $this->response->statusCode . ' returned.' .
								                     $error_data,
									$this->response->statusCode );
						}
					}
					$request_success = true;

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

			} catch ( InvalidParamException $e ) {
				// Handle JSON parsing error
				// Map to status code 512 (unassigned, used for 'illegal response')
				throw new Exception( 'Invalid JSON data returned (' . $e->getMessage() . "): " . $this->response->content,
					512 );

			} catch ( \yii\httpclient\Exception $e ) {
				// HTTPClient connection layer errors
				// Check if call can be retried and max retries is not hit
				if ( in_array( $e->getCode(), [ 2, 7, 28, 55, 56 ] ) && $this->retries < $this->max_retry_attempts ) {
					// Retry the request
					$request_success = false;
					$this->retries ++;
				} else {
					// Can not be retried or too many retries
					// Throw Wordpress API Exception
					throw new Exception(
						"HttpClient error (retried {$this->retries}): " . $e->getMessage(),
						$e->getCode()
					);
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