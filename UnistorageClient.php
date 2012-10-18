<?php
require_once __DIR__ . '/models/BaseFile.php';
require_once __DIR__ . '/models/ImageFile.php';

class UnistorageClient
{
	protected static $instance;

	public $token;
	public $host;
	public $debug = true;
	private $result;

	private $curlInfo = array();

	private static $_mime2Class = array(
		'image/jpeg' => 'ImageFile',
		'image/gif' => 'ImageFile',
		'image/png' => 'ImageFile',
		'image/tiff' => 'ImageFile',
		'image/bmp' => 'ImageFile',
	);

	public static function app() {
		if ( is_null(self::$instance) ) {
			self::$instance = new UnistorageClient(UC_HOST,UC_TOKEN);
		}
		return self::$instance;
	}
	/**
	 * @param string $host without trailing slash and protocol
	 * @param string $token
	 */
	function __construct($host, $token)
	{
		$this->host = $host;
		$this->token = $token;
	}


	public function uploadFile($fs_path, $type_id = null, $headers = null)
	{
		is_file($fs_path);

		$fields = array('file' => "@{$fs_path}") + (($type_id) ? array('type_id' => (string)$type_id) : array());

		$curl_opts = array(
			'CURLOPT_POST' => true,
			'CURLOPT_POSTFIELDS' => $fields
		);

		if (is_null($headers))
			$headers = array();

		$headers['Token'] = $this->token;

		$request = array();
		$request['resource'] = '/';

		return $this->sendRequest($request, $headers, $curl_opts);
	}

	public function getFile($uid,$request = null)
	{
		if(!$request) {
			$request = array();
			$request['resource'] = '/' . $uid . '/';
		} else {
			$request['resource'] = '/' . $uid . '/' . $request['resource'];
		}

		$this->sendRequest($request);

		$class = null;
		if (isset($this->result->information))
			$class = $this->getFileClass($this->result->information->mimetype);

		if (!$class || !class_exists($class)) {
			$class = 'BaseFile';
		}
		return new $class(isset($this->result->id) ? $this->result->id : $uid, isset($this->result->information) ? $this->result->information : null, isset($this->result->ttl) ? $this->result->ttl : null);
	}

	public function action($uid,$action,$params = null) {
		if($params && !is_array($params))
			throw new Exception ('Incorrect action params');

		$request = array();
		$paramsEncoded = array();
		foreach ($params as $key => $value) {
			$paramsEncoded[] = "$key=$value";
		}
		$request['resource'] = "?action=$action" . (!empty($paramsEncoded) ? '&' . implode('&',$paramsEncoded) : '');
		return $this->getFile($uid,$request);
	}

	private function sendRequest($request, $headers = null, $curl_opts = null)
	{
		if (is_null($headers))
			$headers = array();

		$headers['Token'] = $this->token;

		foreach ($headers as $k => $v)
			$headers[$k] = "$k: $v";

		$uri = 'http://' . $this->host . $request['resource'];
		if($this->debug) echo "---------- $uri ---------\n";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//if ($this->debug) curl_setopt($ch, CURLOPT_VERBOSE, true);

		if (is_array($curl_opts)) {
			foreach ($curl_opts as $k => $v) {
				curl_setopt($ch, constant($k), $v);
			}
		}

		$result = curl_exec($ch);
		$this->curlInfo = curl_getinfo($ch);
		curl_close($ch);
		$this->result = json_decode($result);
		if($this->debug) var_dump($this->result);

		return $this->curlInfo['http_code'] == '200';
	}

	/**
	 * @return string
	 */
	public function getResult()
	{
		return $this->result;
	}

	/**
	 * @param $mimeType string
	 * @return mixed
	 */
	public function getFileClass($mimeType)
	{
		if (!isset(self::$_mime2Class[$mimeType])) return false;
		else return self::$_mime2Class[$mimeType];
	}
}