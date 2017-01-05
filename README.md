Yii2 Wordpress API
=================

Yii2 client for Wordpress Rest API (part of core as of Wordpress 4.7)

Full API Documentation here: [http://v2.wp-api.org/](http://v2.wp-api.org/)

Requirements:
=================

PHP5 with CURL extensions.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
composer require --prefer-dist drsdre/yii2-wordpress-api "*"
```

or add

```json
"drsdre/yii2-wordpress-api": "*"
```

to the `require` section of your `composer.json` file.

Authorisation Setup
====

Setup a connection using either using
- oAuth1 (requires https://wordpress.org/plugins/rest-api-oauth1/)
- Basic Authentication (requires https://github.com/WP-API/Basic-Auth Warning: NOT recommended for production)

oAuth1
---

The example below uses a model to store Wordpress authorisation data. The fields required are:
- site_url [string]
- client_key [string]
- client_secret [string]
- access_token [Json string]

You need a web action to authorize the oAuth1 access. 

```php
    /**
	 * Execute oAuth verification
	 *
	 * @param $id of wordpress_site record
	 * @param null $oauth_token
	 *
	 * @return yii\web\Response
	 */
	public function actionVerifyAccess($id, $oauth_token = null) {
		$this->findModel( $id );

		// Open Wordpress Auth API
		$oauthClient = new WordpressAuth([
			'apiBaseUrl' => $this->site_url, // https://www.yoursite.com/ (without API directory)
			'consumerKey' => $this->model->client_key,
			'consumerSecret' => $this->model->client_secret,
		]);

		try {
			if (is_null($oauth_token)) {
				// If no authorisation token, start authorization web flow
				
				// Must set return URL without parameter to prevent 'OAuth signature does not match' error
				$oauthClient->setReturnUrl(
					yii::$app->getRequest()->getHostInfo().'/'.
					yii::$app->getRequest()->getPathInfo().'?id='.$id);
					
                // Get request token
				$oauth_token = $oauthClient->fetchRequestToken();
				
				// Get authorization URL
				$url         = $oauthClient->buildAuthUrl($oauth_token);
				 
				// Redirect to authorization URL
				return $this->redirect($url); 
			}

			// After user returns at our site:
			$access_token = $oauthClient->fetchAccessToken($oauth_token);
			
			// Upgrade to access token
			$this->model->access_token = yii\helpers\Json::encode($access_token->params);
			
			// Save token to record
			$result = $this->model->save();
			
		} catch (yii\base\Exception $e) {
			yii::$app->session->setFlash( 'alert', [
				'body'    => yii::t( 'app', 'Verification failed. Error: ' ).$e->getMessage(),
				'options' => [ 'class' => 'alert-danger' ],
			] );
		}

        // Redirect to main overview
		return $this->redirect('/wordpress_site/'); 
	}
```

With the access token, the Wordpress API can be initialised like this:

```php
$wordpress_credentials = [ 
   'endpoint'      => $WordpressSite->site_url,
   'client_key'    => $WordpressSite->client_key,
   'client_secret' => $WordpressSite->client_secret,
   'access_token'  => Json::decode( $WordpressSite->access_token ),
];

$WordpressApiClient = new drsdre\WordpressApi\Client( $wordpress_credentials );
```

Basic Authentication
---

```php
$wordpress_credentials = [ 
   'endpoint' => $WordpressSite->site_url,
   'username' => $WordpressSite->username,
   'password' => $WordpressSite->password
];

$WordpressApiClient = new drsdre\WordpressApi\Client( $wordpress_credentials );
```

Use the API
====

Once the Wordpress API Client is authorized, request can be made to the API.

Retrieve paged data:

```php
$api_post_page        = 1;

do {
    $ApiResult = $WordpressApiClient->getData(
        '',
        'edit',
        $api_post_page,
        $WebsiteWp->get_page_size
    );
    $data = $ApiResult->asArray();
    < do something with the data >
} while ( $api_post_page <= $ApiResult->result_total_pages );
```

See 


That's all!
-----------