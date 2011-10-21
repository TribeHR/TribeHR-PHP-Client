<?php

class TribeHRConnector
{
	private $version = '0.1';
	private $username;
	private $api_key;
	private $subdomain;
	private $protocol = 'https';
	
	public function __construct($subdomain, $username, $api_key)
	{
		$this->subdomain = $subdomain;
		$this->username = $username;
		$this->api_key = $api_key;
	}
	
	public function setProtocol($protocol)
	{
		$this->protocol = $protocol;
	}
	
  function sendRequest($uri, $method = 'GET', $data = '')
  {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL,
      sprintf('%s://%s.mytribehr.com%s',
      $this->protocol, $this->subdomain, $uri
    )); 
    
    // Convert data to a string, or at least to an array of scalar data
    if(!empty($data) && !is_string($data))
    {
//	    $data = http_build_query($data);
    }
    
    // https
	if($this->protocol == 'https')
	{
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);		
	}
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
	curl_setopt($ch, CURLOPT_HEADER, 0);                                                                           
	curl_setopt($ch, CURLOPT_USERPWD, $this->username . ":" . $this->api_key);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, sprintf("TribeHR PHP Connector/%s", $this->version));

      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Accept: text/xml; charset=utf-8',
      ));

    $method = strtoupper($method);

    if ($method === 'POST')
    {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    else if ($method === 'PUT')
    {
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	    if(!empty($data) && !is_array($data))
	    {
		    $data = http_build_query($data);
	    }
      	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($data)));
     	curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    else if ($method !== 'GET')
    {
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $this->response = curl_exec($ch);
    $this->code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $this->meta     = curl_getinfo($ch);
    $this->header   = curl_getinfo($ch , CURLINFO_CONTENT_TYPE);

    $curl_error = ($this->code > 0 ? null : curl_error($ch) . ' (' . curl_errno($ch) . ')');

    curl_close($ch);

    if ($curl_error)
    {
      throw new Exception('An error occurred while connecting to TribeHR: ' . $curl_error);
    }

    return $this;
  }
}

?>