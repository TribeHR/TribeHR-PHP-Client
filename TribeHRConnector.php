<?php

class TribeHRConnector
{
	private $version = '0.2';
	private $username;
	private $api_key;
	private $subdomain;
	private $protocol = 'https';
	
  /** 
   * Constructor for a new TribeHRConnector instance
   * 
   * @param string $subdomain: The subdomain where your TribeHR site can be found.
   *                           If your site is normally found at "https://foo.mytribehr.com" this would be "foo"
   * @param string $username: The username of an account on your TribeHR site that you wish to use to complete the 
   *                          requests you will make through the connector
   * @param string $api_key: The API key belonging to the same TribeHR user as the $username value
   */
	public function __construct($subdomain, $username, $api_key) {
		$this->subdomain = $subdomain;
		$this->username = $username;
		$this->api_key = $api_key;
	}
	
  /** 
   * Setter for the $protocol value if you wish to override the 'https' default protocol.
   * It is strongly recommended to leave this as https
   *
   * @param string $protocol: The request protocol to use (only the alpha part: do not include '://')
   *                          Only current expected values are 'http' or 'https'
   */
	public function setProtocol($protocol) {
		$this->protocol = $protocol;
	}
	
  /**
   * Execute the given request/submission against the given TribeHR endpoint
   *
   * @param uri $uri: the API endpoint path to submit against (eg /users.xml)
   * @param string $method: (optional) The request method to use (GET, POST, PUT). Default = GET
   * @param mixed $data: The values to submit against the endpoint for create/edit actions, expected as an array
   *                     These should be formatted as defined in the documentation including nested sub-objects
   *                     For any values that represent a file upload: these *must* be a path to the file on your
   *                      local system, prepended by '@' (this is consistent with cURL standards)
   *
   * @throws exception: This untyped exception contains a string with the given curl_error value
   * 
   * @return TribeHRConnector: Returns an instance of the connector with the following properties now set:
   *                           response, code, meta, header
   */
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
   * @todo: Build in compatibility for multidimensional, file inclusive $data structures
   *
   * @param mixed $data: The value of $data as passed to sendRequest
   * @return mixed: A CURLOPT_POSTFIELDS-compatible string or array, valid for the request
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
   * @param mixed $data: A value to be submitted against the API
   * @return bool: True if the $data value contains a signal that it is an intended file upload
   */
  private function filesSubmitted($data) {
    // Are there any files? (first character of the value is an '@': see comments on sendRequest)

    if (is_array($data)) {
      foreach ($data as $currentProperty) {
        if ($this->filesSubmitted($currentProperty)) {
          return true;
        }
      }

      // Went through each element of the array and found no files
      return false;
    }

    // We have a single attribute. If it is a file, it should start with '@' (cURL standard)
    return (substr($data, 0, 1) == '@');
  }
}

?>