<?php

class TribeHRConnector
{
	private $version = '0.2';
	private $username;
	private $api_key;
	private $subdomain;
	private $protocol = 'https';
	
	public function __construct($subdomain, $username, $api_key) {
		$this->subdomain = $subdomain;
		$this->username = $username;
		$this->api_key = $api_key;
	}
	
	public function setProtocol($protocol) {
		$this->protocol = $protocol;
	}
	
  function sendRequest($uri, $method = 'GET', $data = '') {

    // Initialize the correct formatting for our parameters before using them
    $method = strtoupper($method);
    $data = $this->buildData($data);

    // Set all of the cURL options needed to connect to a TribeHR site
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, sprintf('%s://%s.mytribehr.com%s', $this->protocol, $this->subdomain, $uri)); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
    curl_setopt($ch, CURLOPT_HEADER, 0);                                                                           
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->api_key);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, sprintf("TribeHR PHP Connector/%s", $this->version));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/xml; charset=utf-8',));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Specify certificate handling behaviours if we're connecting over https (the default)
    if ($this->protocol == 'https') {
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);    
    }

    // Some of the different methods of submitting against the API will need different cURL options set
    switch ($method) {
      case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;

      case 'PUT':
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($data)));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        break;

      case 'GET':
        // Treat the same as the default (intentional fall-through)

      default:
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        break;
    }

    // Execute the request, set local variables to all of the response properties
    // and return the augmented connector itself.
    $this->response = curl_exec($ch);
    $this->code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $this->meta     = curl_getinfo($ch);
    $this->header   = curl_getinfo($ch , CURLINFO_CONTENT_TYPE);

    $curl_error = ($this->code > 0 ? null : curl_error($ch) . ' (' . curl_errno($ch) . ')');

    curl_close($ch);

    if ($curl_error) {
      throw new Exception('An error occurred while connecting to TribeHR: ' . $curl_error);
    }

    return $this;
  }

  /**
   * Render the given $data in a way that will be understood by cURL
   * Any object that has a multi-dimensional array will need http_build_query called.
   * Any object that has a file *must* be passed as a plan, single-dimensional array.
   * (notice that the two statements above are mutually exclusive.)
   *
   * @todo: build in compatibility for multidimensional, file inclusive $data structures
   *
   * @param mixed $data: the value of $data as passed to sendRequest
   * @return mixed: a CURLOPT_POSTFIELDS-compatible string or array, valid for the request
   */
  private function buildData($data) {

    // If $data is empty or a string, we can just return it plain
    if (empty($data) || is_string($data)) {
      return $data;
    }

    // For now, if there are files present, just submit whatever $data was, raw.
    // We can update this in the future, but for now hope it's a flat object.
    if ($this->filesSubmitted($data)) {
      return $data;
    }

    // Take our array and run http_build_query over it.
    // This will generate a cURL-compatible request that maps nicely to a multidimensional array on reception.
    return http_build_query($data);
  }

  /**
   * Determine if a given value for $data is intended to be a file submission
   * 
   * @param mixed $data: a value to be submitted against the API
   * @return bool: true if the $data value contains a signal that it is an intended file upload
   */
  private function filesSubmitted($data) {
    // Are there any files? (first character of the value is an '@')
    $filesPresent = false;

    if (is_array($data)) {
      foreach ($data as $currentProperty) {
        $filesPresent = ($filesPresent || $this->filesSubmitted($currentProperty));
      }
    } else {
      // We have a single attribute. If it is a file, it should start with '@' (cURL standard)
      $filesPresent = (substr($data, 0, 1) == '@');
    }

    return $filesPresent;
  }
}

?>