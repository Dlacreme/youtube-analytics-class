<?php

/* 		YoutubeAnalytics structure : 
**
**	Attributes
**	CTor
**	Public Methods
**	Private Methods
**	Get|Set
*/

use \DateTime;

class YoutubeAnalytics {

	/*****************
	**	Attributes  **
	*/

	/*
	** String - Id Client
	** Used for authentication
	*/
	private $clientId;

	/* 
	** String - Secret Client
	** Used for authentication
	*/
	private $clientSecret;

	/*
	** String - Redirect Url
	** Url send during OAuth query. This url is called after the user authenticatin
	*/
	private $redirect;

	/*
	** Scope - define the type of request
	**
	*/
	private $scope;

	/*
	** String Access token
	** Used to request information
	*/
	private $access_token;

	/*
	** String - Token Type
	** Define the type of access token
	*/
	private $token_type;

	/*
	** Int - Expire
	** Time of access token is valide
	*/
	private $expire;

	/*
	** String - Channel
	** Define the channel used for query
	*/
	private $channel;

	/*
	** Bool - Print Error
	** If it's true, error will be print
	*/
	private $printError;

	/*
	** String - url Query
	** Url of the API
	*/
	private $url_query = 'https://www.googleapis.com/youtube/analytics/v1/reports?';


	// CTor
	public function __construct($clientId, $clientSecret, $redirect = null, $printError = false)
	{
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->channel = 'MINE';
		$this->scope = 'https://www.googleapis.com/auth/userinfo.profile+https://www.googleapis.com/auth/yt-analytics.readonly+https://www.googleapis.com/auth/yt-analytics-monetary.readonly';	
		$this->redirect = $redirect ? $redirect : 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

		// If it's a callback from startAuthentication
		if (isset($_GET['code']))
			$this->tradeToken($_GET['code']);

		// If access_token already available
		$this->access_token = isset($_SESSION['google_access_token']) ? $_SESSION['google_access_token'] : 0;
		$this->token_type = isset($_SESSION['google_token_type']) ? $_SESSION['google_token_type'] : 0;
		$this->expire = isset($_SESSION['google_token_expire']) ? $_SESSION['google_token_expire'] : 0;

		// Do you want to print error ?
		$this->printError = $printError;
	}

	/*******************************
	**	Public Methods - Queries  **
	*/

	public function listVideos() {
		if (!$this->isTokenValid())
			$this->startAuthentication();
/*
		$host = 'https://www.googleapis.com';
		$authorization = $this->access_token;
		$uri = '/youtube/analytics/v1/reports
		?ids=channel%3D%3DMINE
		&start-date=2013-03-01
		&end-date=2013-03-31
		&metrics=views
		&dimensions=day
		&sort=day';
		
		$http = 'HTTP/1.1';

		$url = $host . $uri;
		var_dump(header('Location:' . filter_var($url, FILTER_SANITIZE_URL) . 'authorization= ' . $authorization));
*/

		$now = new DateTime('now');
		$now = $now->format('Y-m-d');

		$data = array(
			'ids' => 'channel==' . $this->channel,
			'start-date' => '2013-03-01',
			'end-date' => $now,
			'metrics' => 'views'
		);

		return ($this->execQuery($data, 'listVideos'));
	}

	public function geographicInfo($country = 'Europe') {
		if (!$this->isTokenValid())
			$this->startAuthentication();

		$ret = array();

		$now = new DateTime('now');
		$now = $now->format('Y-m-d');

		/* Country-specific watch time metrics for a channel's videos
		** This query retrieves country-specific viewcounts, watch time metrics, and subscription figures for a channel's videos. The report returns one row of data for each country where the channel's videos were watched. Rows are sorted in descending order of number of minutes watched.
		** &&
		** This request retrieves country-specific viewcounts, annotation click-through rates, annotation close rates, and annotation impressions for the channel's videos. Results are sorted by annotation click-through rate in descending order, which means that the country with the highest annotation click-through rate will be listed first.
		**
		** dimensions=country
		** metrics=views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,subscribersGained
		** sort=-estimatedMinutesWatched
		*/
		$data = array(
			'ids' => 'channel==' . $this->channel,
			'start-date' => '2013-03-01',
			'end-date' => $now,
			'dimensions' => 'country',
			'metrics' => 'views,estimatedMinutesWatched,averageViewDuration,averageViewPercentage,subscribersGained,likes,annotationClickThroughRate,annotationCloseRate,annotationImpressions',
			'sort' => '-estimatedMinutesWatched'
		);
		array_push($ret, $this->execQuery($data));

		/* Top 10 – Most viewed videos in a specific country or Europe
		** This query retrieves the channel's 10 most viewed videos, as measured by number of views during the specified date range, in a specified country. Results are sorted by viewcount in descending order.
		**
		** dimensions=video
		** metrics=views,estimatedMinutesWatched,likes,subscribersGained
		** filters=country==France
		** max-results=10
		** sort=-views
		*/

		if ($country == 'Europe') {
			$data = array(
				'ids' => 'channel==' . $this->channel,
				'start-date' => '2013-03-01',
				'end-date' => $now,
				'dimension' => 'video',
				'metrics' => 'views,estimatedMinutesWatched,likes,subscribersGained',
				'filters' => 'continent==150',
				'max-results' => '10',
				'sort' => '-views'
			);
		} else {
			$data = array(
				'ids' => 'channel==' . $this->channel,
				'start-date' => '2013-03-01',
				'end-date' => $now,
				'dimension' => 'video',
				'metrics' => 'views,estimatedMinutesWatched,likes,subscribersGained',
				'filters' => 'country==' . $country,
				'max-results' => '10',
				'sort' => '-views'
			);
		}
		array_push($ret, $this->execQuery($data));

		return ($ret);
	}



	/*********************
	** PRIVATE METHODS  **
	*/

	/*
	** Start authentication.
	** Redirect on google service to get token
	*/
	private function startAuthentication() {
		/* GET QUERY - Ex of URL
			https://accounts.google.com/o/oauth2/auth
			?response_type=code
			&redirect_uri=http%3A%2F%2Flocalhost%2Fbmm%2Fyoutube.php
			&client_id=178268439102.apps.googleusercontent.com
			&scope=https%3A%2F%2Fwww.googleapis.com%2Fauth%2Fyt-analytics.readonly
			&access_type=online
			&approval_prompt=auto
		*/

		$url_query = 'https://accounts.google.com/o/oauth2/auth';
		$response_type = 'code';
		$redirect_uri = urlencode($this->redirect);
		$client_id = $this->clientId;
		$client_secret = $this->clientSecret;
		$scope = $this->scope;
		$access_type = 'online';
		$approval_prompt = 'auto';

		$url = $url_query . '?response_type=' . $response_type . '&redirect_uri=' . $redirect_uri . '&client_id=' . $client_id . '&scope=' . $scope . '&access_type=' . $access_type . '&approval_prompt=' . $approval_prompt;
		header('Location:' . filter_var($url, FILTER_SANITIZE_URL));
	}


	/*
	**	Trade token for access_token
	*/
	private function tradeToken($code) {

		/* POST QUERY - Infos
			url = 'https://accounts.google.com/o/oauth2/token'
			
			code = token get with startAuthentication
			redirect_uri = redirect uri
			client_id = client id
			client_secret = client secret
			grant_type = 'authorization_code'
		*/

		$data = array(
				'code' => $code,
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'redirect_uri' => $this->redirect,
				'grant_type' => 'authorization_code'
			);

		$dataStr = trim(http_build_query($data));

		if (!($re = curl_init()))
			return ($this->printError('TradeToken - Failed curl_init'));

		$conf = array(
			CURLOPT_URL => 'https://accounts.google.com/o/oauth2/token',
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/x-www-form-urlencoded',
				'Content-Length: ' . trim(strlen($dataStr))
			),
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => $dataStr
		);
		
		if (!curl_setopt_array($re, $conf))
			return ($this->printError('TradeToken - Failed curl_setopt_array'));
		if (!($res = curl_exec($re)))
			return ($this->printError('TradeToken - Failed curl_exec'));
		$res = json_decode($res);
		curl_close($re);

		if (isset($res->access_token)) {
			$this->access_token = $_SESSION['google_access_token'] = $res->access_token;
			$this->token_type = $_SESSION['google_token_type'] = $res->token_type;
			$this->expire = $_SESSION['google_token_expire'] = $res->expires_in;
		}
		return (true);
	}

	/*
	**	Query builder - return the result as json file
	*/
	private function execQuery($data, $error = '') {

		$dataStr = http_build_query($data);
		$url_query = $this->url_query . $dataStr;
		$conf = array(
			CURLOPT_URL => $url_query,
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_HEADER => 0,
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/x-www-form-urlencoded',
				'Authorization: Bearer ' . $this->access_token
			),
		);

		if (!($re = curl_init()))
			return ($this->printError($error . ' - Fail curl_init'));

		if (!curl_setopt_array($re, $conf))
			return ($this->printError($error . ' - Fail curl_setopt_array'));

		if (!($res = curl_exec($re)))
			return ($this->printError($error . ' - Fail curl_exec'));

		curl_close($re);
		return ($res);
	}


	/*
	**	Check if token is valid and still alive
	*/
	private function isTokenValid() {
		if (!$this->access_token || !($exp = new DateTime($this->expire)) || new DateTime("now") > $exp)
			return (false);
		return (true);
	}

	private function printError($s) {
		if ($this->printError)
			echo $s;
		return (false);
	}


	/****************
	**  GET | SET  **
	*/

	public function clientSecret($s = null) {
		if ($s) {
			$this->clientSecret = $s;
		}
		return ($this->clientSecret);
	}

	public function clientId($s = null) {
		if ($s) {
			$this->clientId = $s;
		}
		return ($this->clientId);
	}

	public function redirect($s = null) {
		if ($s) {
			$this->redirect = $s;
		}
		return ($this->redirect);
	}

	public function channel($s = null) {
		if ($s)
			$this->channel = $s;
		return ($s);
	}

	public function scope($s) {
		if ($s)
			$this->scope = $s;
		return ($this->scope);
	}

	public function addScope($s) {
		$this->scope .= '+' . $s;
	}


}

?>
