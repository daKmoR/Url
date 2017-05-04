<?php
	if (!defined('BOWER_PATH')) {
		define('BOWER_PATH', __DIR__ . '/..');
	}

	require_once(BOWER_PATH . '/Is/Is.php');

	/**
	 * Url Helper
	 *
	 **/
	Class Url {

		/**
		 * Pings an url but does not wait for response.
		 *
		 * Example:
		 *   Url::ping('http://domain.com/generateCache.php');
		 *   => generateCache.php will be triggered but my current request won't be slowed
		 *
		 * @param $url
		 */
		public static function ping($url)	{
			$parts = parse_url($url);
			$port = isset($parts['port']) ? $parts['port'] : 80;
			$getUrl = isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '' ? $parts['path'] . '?' . $parts['query'] : $parts['path'];
			$fp = fsockopen($parts['host'], $port);
			$out = "GET " . $getUrl . " HTTP/1.1\r\n";
			$out .= "Host: ". $parts['host'] . "\r\n";
			$out .= "Content-Length: 0" . "\r\n";
			$out .= "Connection: Close\r\n\r\n";
			fwrite($fp, $out);
			fclose($fp);
		}

		/**
		 * Returns the Url of the Live System.
		 * Kills Page if not defined.
		 *
		 * Example:
		 *   Url::getLive();
		 *   => http://project.com
		 *
		 * @return array|mixed|null
		 */
		public static function getLive() {
			global $_SETTINGS;
			$liveUrl = $_SETTINGS->get('live.url');
			if (!isset($liveUrl) || Is::emptyString($liveUrl)) {
				Debug::add('You have to define live.url in your .htsettings file.');
				Debug::render();
				die();
			}
			return $_SETTINGS->get('live.url');
		}


		/**
		 * Replaces all System Urls with Live Urls. Works with big textes as well.
		 *
		 * Example:
		 *   Url::toLive('http://localhost/ProjectCom/about-us');
		 *   => http://project.com/about-us
		 *
		 * @param $text
		 * @return mixed
		 */
		public static function fromSystemToLive($text) {
			return str_replace(ROOT_URL, static::getLive(), $text);
		}

		/**
		 * Replaces all Live Urls with System Urls. Works with big textes as well.
		 *
		 * Example:
		 *   Url::fromLiveToSystem('http://project.com/about-us');
		 *   => http://localhost/ProjectCom/about-us
		 *
		 * @param $text
		 * @return mixed
		 */
		public static function fromLiveToSystem($text) {
			return str_replace(static::getLive(), ROOT_URL, $text);
		}

		/**
		 * Takes the Path and generates the Url for the Frontend.
		 *
		 * Example:
		 *   Url::fromPath('/path/to/html/Content/uploads/a.jpg');
		 *   => http://domain.com/Content/uploads/a.jpg
		 *
		 * @param $path
		 * @return mixed
		 */
		public static function fromPath($path) {
			return str_replace(ROOT_DIR, ROOT_URL, $path);
		}

		/**
		 * Takes an Url and returns the Path to the file
		 *
		 * Example:
		 *   Url::toPath('http://domain.com/Content/uploads/a.jpg');
		 *   => /path/to/html/Content/uploads/a.jpg
		 *
		 * @param $url
		 * @return mixed
		 */
		public static function toPath($url) {
			return str_replace(ROOT_URL, ROOT_DIR, $url);
		}

		/**
		 * Checks if a given url is within the installation (ROOT_URL)
		 *
		 * Examples:
		 *   Url::isExternal('http://google.com');
		 *   => true
		 *   Url::isExternal('http://domain.com/mypage/')
		 *   => false
		 *   Url::isExternal('#something')
		 *   => false
		 *
		 * @param $url
		 * @param $base
		 * @return bool
		 */
		public static function isExternal($url, $base = '') {
			if (stripos($url, '#') === 0) {
				return false;
			}
			$base = $base !== '' ? $base : ROOT_URL;
			$part = substr($url, 0, strlen($base));
			return $part !== $base;
		}

		/**
		 * @param       $url
		 * @param array $documentTypes
		 * @return bool
		 */
		public static function isDocument($url, $documentTypes = array('.pdf', '.doc')) {
			return static::isFile($url, $documentTypes);
		}

		/**
		 * Checks if a given url ends with certain endings
		 *
		 * @param       $url
		 * @param array $fileTypes
		 * @return bool
		 */
		public static function isFile($url, $fileTypes = array('.pdf', '.doc', '.zip', '.doc', '.docx', '.xls', '.xlsx', '.jpg', '.png', '.gif', '.txt')) {
			$url = strtolower($url);
			foreach($fileTypes as $documentType) {
				if (substr($url, strlen($documentType)*-1) === $documentType) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Checks if a given url should be opened in a new window
		 *
		 * @see static::isExternal()
		 * @see static::isDocument()
		 *
		 * @param $url
		 * @return bool
		 */
		public static function isNewWindow($url) {
			return static::isExternal($url) || static::isDocument($url);
		}

		/**
		 * Returns the permalink respecting the current language if wpml is loaded. If no post is found it will link back
		 * to the homepage.
		 *
		 * Example:
		 *   echo Url::getPermalink(); // url to current page
		 *   echo Url::getPermalink(12); // url to page with id
		 *   echo Url::getPermalink($page); // url to $page
		 *
		 * @param $post
		 * @return string
		 */
		public static function getPermalink($post = false) {
			if (Lang::isActive()) {
				$postId = is_object($post) && isset($post->ID) ? $post->ID : $post;
				$postIdInLanguage = wpml_object_id_filter($postId , 'page', true, Lang::getCode());
				return ($postIdInLanguage != 0) ? get_permalink($postIdInLanguage) : apply_filters('wpml_home_url', '');
			}
			return function_exists('get_permalink') ? get_permalink($post) : static::getCurrent();
		}

		/**
		 * Returns the current Urls with all request parameters
		 *
		 * Example:
		 *   Url::getCurrent();
		 *
		 * Results:
		 *   http://domain.com/test/?param=b&me=you
		 *
		 * @return string
		 */
		public static function getCurrent() {
			$ssl = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? true:false;
			$protocolRaw = strtolower($_SERVER['SERVER_PROTOCOL']);
			$protocol = substr($protocolRaw, 0, strpos($protocolRaw, '/')) . (($ssl) ? 's' : '');
			return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		/**
		 * Example:
		 *   Url::getRelative();
		 *
		 * Results:
		 *   test/?param=b&me=you
		 *
		 * @param null $url
		 * @return mixed
		 */
		public static function getRelative($url = null) {
			$url = $url ? $url : static::getCurrent();
			return str_replace(ROOT_URL . '/', '', $url);
		}

		/**
		 * Return the directory of the current url. e.g. removing queries.
		 *
		 * Example:
		 *   Url::getDirectory(); // current Url: http://domain.com/test/?param=b&me=you
		 *   => http://domain.com/test/
		 *
		 * @param bool $url
		 * @return bool|string
		 */
		public static function getDirectory($url = false) {
			$url = $url ? $url : static::getCurrent();
			if (strpos($url, '?') !== false) {
				list($url, $urlParams) = explode('?', $url);
			}
			return $url;
		}

		/**
		 * Helper function for Url::modifyUrl
		 *
		 * @param      $paramsToAdd
		 * @param bool $url
		 * @return string
		 */
		public static function addParameter($paramsToAdd, $url = false) {
			return static::modify($paramsToAdd, array(), $url);
		}

		/**
		 * Modifies a given url to add (and overwrite) parameters or to remove them. If there is no url given explicitly it
		 * will read the current url.
		 *
		 * Example:
		 *   Url::modifyUrl(array('test' => 'me')); // current Url http://domain.com/contact/?go=there&test=you
		 *   Url::modifyUrl(array('test' => 'me')); // current Url http://domain.com/
		 *   Url::modifyUrl(array(), array('test')); // current Url http://domain.com/contact/?go=there&test=you
		 *
		 * Results:
		 *   http://domain.com/contact/?go=there&test=me
		 *   http://domain.com/?test=me
		 *   http://domain.com/contact/?go=there
		 *
		 * @param array $paramsToAdd
		 * @param array $paramsToRemove
		 * @param bool  $_currentUrl
		 * @return string
		 */
		public static function modify($paramsToAdd = array(), $paramsToRemove = array(), $_currentUrl = false) {
			$currentUrl = $_currentUrl ? $_currentUrl : static::getCurrent();

			$parameters = array();
			$urlStart = $currentUrl;
			if (strpos($currentUrl, '?') !== false) {
				list($urlStart, $parametersString) = explode('?', $currentUrl);
				parse_str($parametersString, $parameters);
			}
			foreach($paramsToAdd as $paramKeyToAdd => $paramToAdd) {
				$parameters[$paramKeyToAdd] = $paramToAdd;
			}
			foreach($paramsToRemove as $paramKeyToRemove => $paramToRemove) {
				unset($parameters[$paramToRemove]);
			}
			$query = http_build_query($parameters);
			$url = $urlStart;
			$url .= $query !== '' ? '?' . $query : ''; // Rebuild the url
			return $url;
		}

		/**
		 * Always returns an absolute full url. Allows you to use relative urls across servers
		 *
		 * Examples:
		 *   Url::relativeToFullUrl('user/login');
		 *   => http://domain.com/user/login
		 *   => http://domain.com/subfolder/user/login // if installation is in a subfolder
		 *   Url::relativeToFullUrl('http://google.com');
		 *   => http://google.com
		 *   Url::relativeToFullUrl('#something');
		 *   => #something
		 *
		 * @param $url
		 * @return string
		 */
		public static function relativeToFullUrl($url) {
			return stripos($url, 'http') === 0 || stripos($url, '#') === 0 ? $url : ROOT_URL . '/' . $url;
		}

		/**
		 * Adds protocol to a link if it has none
		 *
		 * Example:
		 *   Url::addProtocol('test.me');
		 *   // http://test.me
		 *   Url::addProtocol('https://test.me');
		 *   // https://test.me - stays the same
		 *   Url::addProtocol('test.me', 'https');
		 *   // https://test.me
		 *
		 * @param  string $url
		 * @param  string $protocol
		 * @return string
		 */
		public static function addProtocol($url, $protocol = 'http://') {
			if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
				$url = $protocol . $url;
			}
			return $url;
		}

	} // end Helper