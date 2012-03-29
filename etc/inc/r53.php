<?php
/**
*
* Copyright (c) 2011, Dan Myers.
* Parts copyright (c) 2008, Donovan Schonknecht.
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without
* modification, are permitted provided that the following conditions are met:
*
* - Redistributions of source code must retain the above copyright notice,
*   this list of conditions and the following disclaimer.
* - Redistributions in binary form must reproduce the above copyright
*   notice, this list of conditions and the following disclaimer in the
*   documentation and/or other materials provided with the distribution.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
* AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
* IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
* ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
* LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
* CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
* SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
* INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
* CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
* ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
* POSSIBILITY OF SUCH DAMAGE.
*
* This is a modified BSD license (the third clause has been removed).
* The BSD license may be found here:
* http://www.opensource.org/licenses/bsd-license.php
*
* Amazon Route 53 is a trademark of Amazon.com, Inc. or its affiliates.
*
* Route53 is based on Donovan Schonknecht's Amazon S3 PHP class, found here:
* http://undesigned.org.za/2007/10/22/amazon-s3-php-class
*
*/

/**
* Amazon Route53 PHP class
*
* @link http://sourceforge.net/projects/php-r53/
* version 0.9.0
*
*/
class Route53
{
	const API_VERSION = '2010-10-01';

	protected $__accessKey; // AWS Access key
	protected $__secretKey; // AWS Secret key
	protected $__host;

	public function getAccessKey() { return $this->__accessKey; }
	public function getSecretKey() { return $this->__secretKey; }
	public function getHost() { return $this->__host; }

	protected $__verifyHost = 1;
	protected $__verifyPeer = 1;

	// verifyHost and verifyPeer determine whether curl verifies ssl certificates.
	// It may be necessary to disable these checks on certain systems.
	// These only have an effect if SSL is enabled.
	public function verifyHost() { return $this->__verifyHost; }
	public function enableVerifyHost($enable = true) { $this->__verifyHost = $enable; }

	public function verifyPeer() { return $this->__verifyPeer; }
	public function enableVerifyPeer($enable = true) { $this->__verifyPeer = $enable; }

	/**
	* Constructor
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return void
	*/
	public function __construct($accessKey = null, $secretKey = null, $host = 'route53.amazonaws.com') {
		if ($accessKey !== null && $secretKey !== null) {
			$this->setAuth($accessKey, $secretKey);
		}
		$this->__host = $host;
	}

	/**
	* Set AWS access key and secret key
	*
	* @param string $accessKey Access key
	* @param string $secretKey Secret key
	* @return void
	*/
	public function setAuth($accessKey, $secretKey) {
		$this->__accessKey = $accessKey;
		$this->__secretKey = $secretKey;
	}

	/**
	* Lists the hosted zones on the account
	*
	* @param string marker A pagination marker returned by a previous truncated call
	* @param int maxItems The maximum number of items per page.  The service uses min($maxItems, 100).
	* @return A list of hosted zones
	*/
	public function listHostedZones($marker = null, $maxItems = 100) {
		$rest = new Route53Request($this, 'hostedzone', 'GET');

		if($marker !== null) {
			$rest->setParameter('marker', $marker);
		}
		if($maxItems !== 100) {
			$rest->setParameter('maxitems', $maxItems);
		}

		$rest = $rest->getResponse();
		if($rest->error === false && $rest->code !== 200) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('listHostedZones', $rest->error);
			return false;
		}

		$response = array();
		if (!isset($rest->body))
		{
			return $response;
		}

		$zones = array();
		foreach($rest->body->HostedZones->HostedZone as $z)
		{
			$zones[] = $this->parseHostedZone($z);
		}
		$response['HostedZone'] = $zones;

		if(isset($rest->body->MaxItems)) {
			$response['MaxItems'] = (string)$rest->body->MaxItems;
		}

		if(isset($rest->body->IsTruncated)) {
			$response['IsTruncated'] = (string)$rest->body->IsTruncated;
			if($response['IsTruncated'] == 'true') {
				$response['NextMarker'] = (string)$rest->body->NextMarker;
			}
		}

		return $response;
	}

	/**
	* Retrieves information on a specified hosted zone
	*
	* @param string zoneId The id of the hosted zone, as returned by CreateHostedZoneResponse or ListHostedZoneResponse
	*                      In other words, if ListHostedZoneResponse shows the zone's Id as '/hostedzone/Z1PA6795UKMFR9',
	*                      then that full value should be passed here, including the '/hostedzone/' prefix.
	* @return A data structure containing information about the specified zone
	*/
	public function getHostedZone($zoneId) {
		// we'll strip off the leading forward slash, so we can use it as the action directly.
		$zoneId = trim($zoneId, '/');

		$rest = new Route53Request($this, $zoneId, 'GET');

		$rest = $rest->getResponse();
		if($rest->error === false && $rest->code !== 200) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('getHostedZone', $rest->error);
			return false;
		}

		$response = array();
		if (!isset($rest->body))
		{
			return $response;
		}

		$response['HostedZone'] = $this->parseHostedZone($rest->body->HostedZone);
		$response['NameServers'] = $this->parseDelegationSet($rest->body->DelegationSet);

		return $response;
	}

	/**
	* Creates a new hosted zone
	*
	* @param string name The name of the hosted zone (e.g. "example.com.")
	* @param string reference A user-specified unique reference for this request
	* @param string comment An optional user-specified comment to attach to the zone
	* @return A data structure containing information about the newly created zone
	*/
	public function createHostedZone($name, $reference, $comment = '') {
		// hosted zone names must end with a period, but people will forget this a lot...
		if(strrpos($name, '.') != (strlen($name) - 1)) {
			$name .= '.';
		}

		$data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$data .= '<CreateHostedZoneRequest xmlns="https://route53.amazonaws.com/doc/'.Route53::API_VERSION."/\">\n";
		$data .= '<Name>'.$name."</Name>\n";
		$data .= '<CallerReference>'.$reference."</CallerReference>\n";
		if(strlen($comment) > 0) {
			$data .= "<HostedZoneConfig>\n";
			$data .= '<Comment>'.$comment."</Comment>\n";
			$data .= "</HostedZoneConfig>\n";
		}
		$data .= "</CreateHostedZoneRequest>\n";

		$rest = new Route53Request($this, 'hostedzone', 'POST', $data);

		$rest = $rest->getResponse();

		if($rest->error === false && !in_array($rest->code, array(200, 201, 202, 204)) ) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('createHostedZone', $rest->error);
			return false;
		}

		$response = array();
		if (!isset($rest->body))
		{
			return $response;
		}

		$response['HostedZone'] = $this->parseHostedZone($rest->body->HostedZone);
		$response['ChangeInfo'] = $this->parseChangeInfo($rest->body->ChangeInfo);
		$response['NameServers'] = $this->parseDelegationSet($rest->body->DelegationSet);

		return $response;
	}

	/**
	* Retrieves information on a specified hosted zone
	*
	* @param string zoneId The id of the hosted zone, as returned by CreateHostedZoneResponse or ListHostedZoneResponse
	*                      In other words, if ListHostedZoneResponse shows the zone's Id as '/hostedzone/Z1PA6795UKMFR9',
	*                      then that full value should be passed here, including the '/hostedzone/' prefix.
	* @return The change request data corresponding to this delete
	*/
	public function deleteHostedZone($zoneId) {
		// we'll strip off the leading forward slash, so we can use it as the action directly.
		$zoneId = trim($zoneId, '/');

		$rest = new Route53Request($this, $zoneId, 'DELETE');

		$rest = $rest->getResponse();
		if($rest->error === false && $rest->code !== 200) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('deleteHostedZone', $rest->error);
			return false;
		}

		if (!isset($rest->body))
		{
			return array();
		}

		return $this->parseChangeInfo($rest->body->ChangeInfo);
	}

	/**
	* Retrieves a list of resource record sets for a given zone
	*
	* @param string zoneId The id of the hosted zone, as returned by CreateHostedZoneResponse or ListHostedZoneResponse
	*                      In other words, if ListHostedZoneResponse shows the zone's Id as '/hostedzone/Z1PA6795UKMFR9',
	*                      then that full value should be passed here, including the '/hostedzone/' prefix.
	* @param string type The type of resource record set to begin listing from. If this is specified, $name must also be specified.
	*                    Must be one of: A, AAAA, CNAME, MX, NS, PTR, SOA, SPF, SRV, TXT
	* @param string name The name at which to begin listing resource records (in the lexographic order of records).
	* @param int maxItems The maximum number of results to return.  The service uses min($maxItems, 100).
	* @return The list of matching resource record sets
	*/
	public function listResourceRecordSets($zoneId, $type = '', $name = '', $maxItems = 100) {
		// we'll strip off the leading forward slash, so we can use it as the action directly.
		$zoneId = trim($zoneId, '/');

		$rest = new Route53Request($this, $zoneId.'/rrset', 'GET');

		if(strlen($type) > 0) {
			$rest->setParameter('type', $type);
		}
		if(strlen($name) > 0) {
			$rest->setParameter('name', $name);
		}
		if($maxItems != 100) {
			$rest->setParameter('maxitems', $maxItems);
		}

		$rest = $rest->getResponse();
		if($rest->error === false && $rest->code !== 200) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('listResourceRecordSets', $rest->error);
			return false;
		}

		$response = array();
		if (!isset($rest->body))
		{
			return $response;
		}

		$recordSets = array();
		foreach($rest->body->ResourceRecordSets->ResourceRecordSet as $set) {
			$recordSets[] = $this->parseResourceRecordSet($set);
		}

		$response['ResourceRecordSets'] = $recordSets;

		if(isset($rest->body->MaxItems)) {
			$response['MaxItems'] = (string)$rest->body->MaxItems;
		}

		if(isset($rest->body->IsTruncated)) {
			$response['IsTruncated'] = (string)$rest->body->IsTruncated;
			if($response['IsTruncated'] == 'true') {
				$response['NextRecordName'] = (string)$rest->body->NextRecordName;
				$response['NextRecordType'] = (string)$rest->body->NextRecordType;
			}
		}

		return $response;
	}

	/**
	* Makes the specified resource record set changes (create or delete).
	*
	* @param string zoneId The id of the hosted zone, as returned by CreateHostedZoneResponse or ListHostedZoneResponse
	*                      In other words, if ListHostedZoneResponse shows the zone's Id as '/hostedzone/Z1PA6795UKMFR9',
	*                      then that full value should be passed here, including the '/hostedzone/' prefix.
	* @param array changes An array of change objects, as they are returned by the prepareChange utility method.
	*                      You may also pass a single change object.
	* @param string comment An optional comment to attach to the change request
	* @return The status of the change request
	*/
	public function changeResourceRecordSets($zoneId, $changes, $comment = '') {
		// we'll strip off the leading forward slash, so we can use it as the action directly.
		$zoneId = trim($zoneId, '/');

		$data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$data .= '<ChangeResourceRecordSetsRequest xmlns="https://route53.amazonaws.com/doc/'.Route53::API_VERSION."/\">\n";
		$data .= "<ChangeBatch>\n";

		if(strlen($comment) > 0) {
			$data .= '<Comment>'.$comment."</Comment>\n";
		}

                if(!is_array($changes)) {
			$changes = array($changes);
		}

		$data .= "<Changes>\n";
		foreach($changes as $change) {
			$data .= $change;
		}
		$data .= "</Changes>\n";

		$data .= "</ChangeBatch>\n";
		$data .= "</ChangeResourceRecordSetsRequest>\n";

		$rest = new Route53Request($this, $zoneId.'/rrset', 'POST', $data);

		$rest = $rest->getResponse();
		if($rest->error === false && !in_array($rest->code, array(200, 201, 202, 204))) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('changeResourceRecordSets', $rest->error);
			return false;
		}

		if (!isset($rest->body))
		{
			return array();
		}

		return $this->parseChangeInfo($rest->body->ChangeInfo);
	}

	/**
	* Retrieves information on a specified change request
	*
	* @param string changeId The id of the change, as returned by CreateHostedZoneResponse or ChangeResourceRecordSets
	*                      In other words, if CreateHostedZoneResponse showed the change's Id as '/change/C2682N5HXP0BZ4',
	*                      then that full value should be passed here, including the '/change/' prefix.
	* @return The status of the change request
	*/
	public function getChange($changeId) {
		// we'll strip off the leading forward slash, so we can use it as the action directly.
		$zoneId = trim($changeId, '/');

		$rest = new Route53Request($this, $changeId, 'GET');

		$rest = $rest->getResponse();
		if($rest->error === false && $rest->code !== 200) {
			$rest->error = array('code' => $rest->code, 'message' => 'Unexpected HTTP status');
		}
		if($rest->error !== false) {
			$this->__triggerError('getChange', $rest->error);
			return false;
		}

		if (!isset($rest->body))
		{
			return array();
		}

		return $this->parseChangeInfo($rest->body->ChangeInfo);
	}



	/**
	* Utility function to parse a HostedZone tag structure
	*/
	private function parseHostedZone($tag) {
		$zone = array();
		$zone['Id'] = (string)$tag->Id;
		$zone['Name'] = (string)$tag->Name;
		$zone['CallerReference'] = (string)$tag->CallerReference;

		// these might always be set, but check just in case, since
		// their values are option on CreateHostedZone requests
		if(isset($tag->Config) && isset($tag->Config->Comment)) {
			$zone['Config'] = array('Comment' => (string)$tag->Config->Comment);
		}

		return $zone;
	}

	/**
	* Utility function to parse a ChangeInfo tag structure
	*/
	private function parseChangeInfo($tag) {
		$info = array();
		$info['Id'] = (string)$tag->Id;
		$info['Status'] = (string)$tag->Status;
		$info['SubmittedAt'] = (string)$tag->SubmittedAt;
		return $info;
	}

	/**
	* Utility function to parse a DelegationSet tag structure
	*/
	private function parseDelegationSet($tag) {
		$servers = array();
		foreach($tag->NameServers->NameServer as $ns) {
			$servers[] = (string)$ns;
		}
		return $servers;
	}

	/**
	* Utility function to parse a ResourceRecordSet tag structure
	*/
	private function parseResourceRecordSet($tag) {
		$rrs = array();
		$rrs['Name'] = (string)$tag->Name;
		$rrs['Type'] = (string)$tag->Type;
		$rrs['TTL'] = (string)$tag->TTL;
		$rrs['ResourceRecords'] = array();
		foreach($tag->ResourceRecords->ResourceRecord as $rr) {
			$rrs['ResourceRecords'][] = (string)$rr->Value;
		}
		return $rrs;
	}

	/**
	* Utility function to prepare a Change object for ChangeResourceRecordSets requests.
	* All fields are required.
	*
	* @param string action The action to perform.  One of: CREATE, DELETE
	* @param string name The name to perform the action on.
	*                    If it does not end with '.', then AWS treats the name as relative to the zone root.
	* @param string type The type of record being modified.
	*                    Must be one of: A, AAAA, CNAME, MX, NS, PTR, SOA, SPF, SRV, TXT
	* @param int ttl The time-to-live value for this record, in seconds.
	* @param array records An array of resource records to attach to this change.
	*                      Each member of this array can either be a string, or an array of strings.
	*                      Passing an array of strings will attach multiple values to a single resource record.
	*                      If a single string is passed as $records instead of an array,
	*                      it will be treated as a single-member array.
	* @return object An opaque object containing the change request.
	*                Do not write code that depends on the contents of this object, as it may change at any time.
	*/
	public function prepareChange($action, $name, $type, $ttl, $records) {
		$change = "<Change>\n";
		$change .= '<Action>'.$action."</Action>\n";
		$change .= "<ResourceRecordSet>\n";
		$change .= '<Name>'.$name."</Name>\n";
		$change .= '<Type>'.$type."</Type>\n";
		$change .= '<TTL>'.$ttl."</TTL>\n";
		$change .= "<ResourceRecords>\n";

		if(!is_array($records)) {
			$records = array($records);
		}

		foreach($records as $record) {
			$change .= "<ResourceRecord>\n";
			if(is_array($record)) {
				foreach($record as $value) {
					$change .= '<Value>'.$value."</Value>\n";
				}
			}
			else {
				$change .= '<Value>'.$record."</Value>\n";
			}
			$change .= "</ResourceRecord>\n";
		}

		$change .= "</ResourceRecords>\n";
		$change .= "</ResourceRecordSet>\n";
		$change .= "</Change>\n";

		return $change;
	}

	/**
	* Trigger an error message
	*
	* @internal Used by member functions to output errors
	* @param array $error Array containing error information
	* @return string
	*/
	public function __triggerError($functionname, $error)
	{
		if($error == false) {
			trigger_error(sprintf("Route53::%s(): Encountered an error, but no description given", $functionname), E_USER_WARNING);
		}
		else if(isset($error['curl']) && $error['curl'])
		{
			trigger_error(sprintf("Route53::%s(): %s %s", $functionname, $error['code'], $error['message']), E_USER_WARNING);
		}
		else if(isset($error['Error']))
		{
			$e = $error['Error'];
			$message = sprintf("Route53::%s(): %s - %s: %s\nRequest Id: %s\n", $functionname, $e['Type'], $e['Code'], $e['Message'], $error['RequestId']);
			trigger_error($message, E_USER_WARNING);
		}
	}

	/**
	* Callback handler for 503 retries.
	*
	* @internal Used by SimpleDBRequest to call the user-specified callback, if set
	* @param $attempt The number of failed attempts so far
	* @return The retry delay in microseconds, or 0 to stop retrying.
	*/
	public function __executeServiceTemporarilyUnavailableRetryDelay($attempt)
	{
		if(is_callable($this->__serviceUnavailableRetryDelayCallback)) {
			$callback = $this->__serviceUnavailableRetryDelayCallback;
			return $callback($attempt);
		}
		return 0;
	}
}

final class Route53Request
{
	private $r53, $action, $verb, $data, $parameters = array();
	public $response;

	/**
	* Constructor
	*
	* @param string $r53 The Route53 object making this request
	* @param string $action SimpleDB action
	* @param string $verb HTTP verb
	* @param string $data For POST requests, the data being posted (optional)
	* @return mixed
	*/
	function __construct($r53, $action, $verb, $data = '') {
		$this->r53 = $r53;
		$this->action = $action;
		$this->verb = $verb;
		$this->data = $data;
		$this->response = new STDClass;
		$this->response->error = false;
	}

	/**
	* Set request parameter
	*
	* @param string  $key Key
	* @param string  $value Value
	* @param boolean $replace Whether to replace the key if it already exists (default true)
	* @return void
	*/
	public function setParameter($key, $value, $replace = true) {
		if(!$replace && isset($this->parameters[$key]))
		{
			$temp = (array)($this->parameters[$key]);
			$temp[] = $value;
			$this->parameters[$key] = $temp;
		}
		else
		{
			$this->parameters[$key] = $value;
		}
	}

	/**
	* Get the response
	*
	* @return object | false
	*/
	public function getResponse() {

		$params = array();
		foreach ($this->parameters as $var => $value)
		{
			if(is_array($value))
			{
				foreach($value as $v)
				{
					$params[] = $var.'='.$this->__customUrlEncode($v);
				}
			}
			else
			{
				$params[] = $var.'='.$this->__customUrlEncode($value);
			}
		}

		sort($params, SORT_STRING);

		$query = implode('&', $params);

		// must be in format 'Sun, 06 Nov 1994 08:49:37 GMT'
		$date = gmdate('D, d M Y H:i:s e');

		$headers = array();
		$headers[] = 'Date: '.$date;
		$headers[] = 'Host: '.$this->r53->getHost();

		$auth = 'AWS3-HTTPS AWSAccessKeyId='.$this->r53->getAccessKey();
		$auth .= ',Algorithm=HmacSHA256,Signature='.$this->__getSignature($date);
		$headers[] = 'X-Amzn-Authorization: '.$auth;

		$url = 'https://'.$this->r53->getHost().'/'.Route53::API_VERSION.'/'.$this->action.'?'.$query;

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'Route53/php');

		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, ($this->r53->verifyHost() ? 1 : 0));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, ($this->r53->verifyPeer() ? 1 : 0));

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, array(&$this, '__responseWriteCallback'));
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Request types
		switch ($this->verb) {
			case 'GET': break;
			case 'POST':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
				if(strlen($this->data) > 0) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
					$headers[] = 'Content-Type: text/plain';
					$headers[] = 'Content-Length: '.strlen($this->data);
				}
			break;
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
			break;
			default: break;
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);

		// Execute, grab errors
		if (curl_exec($curl)) {
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		} else {
			$this->response->error = array(
				'curl' => true,
				'code' => curl_errno($curl),
				'message' => curl_error($curl),
				'resource' => $this->resource
			);
		}

		@curl_close($curl);

		// Parse body into XML
		if ($this->response->error === false && isset($this->response->body)) {
			$this->response->body = simplexml_load_string($this->response->body);

			// Grab Route53 errors
			if (!in_array($this->response->code, array(200, 201, 202, 204))
				&& isset($this->response->body->Error)) {
				$error = $this->response->body->Error;
				$output = array();
				$output['curl'] = false;
				$output['Error'] = array();
				$output['Error']['Type'] = (string)$error->Type;
				$output['Error']['Code'] = (string)$error->Code;
				$output['Error']['Message'] = (string)$error->Message;
				$output['RequestId'] = (string)$this->response->body->RequestId;

				$this->response->error = $output;
				unset($this->response->body);
			}
		}

		return $this->response;
	}

	/**
	* CURL write callback
	*
	* @param resource &$curl CURL resource
	* @param string &$data Data
	* @return integer
	*/
	private function __responseWriteCallback(&$curl, &$data) {
		$this->response->body .= $data;
		return strlen($data);
	}

	/**
	* Contributed by afx114
	* URL encode the parameters as per http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/index.html?Query_QueryAuth.html
	* PHP's rawurlencode() follows RFC 1738, not RFC 3986 as required by Amazon. The only difference is the tilde (~), so convert it back after rawurlencode
	* See: http://www.morganney.com/blog/API/AWS-Product-Advertising-API-Requires-a-Signed-Request.php
	*
	* @param string $var String to encode
	* @return string
	*/
	private function __customUrlEncode($var) {
		return str_replace('%7E', '~', rawurlencode($var));
	}

	/**
	* Generate the auth string using Hmac-SHA256
	*
	* @internal Used by SimpleDBRequest::getResponse()
	* @param string $string String to sign
	* @return string
	*/
	private function __getSignature($string) {
		return base64_encode(hash_hmac('sha256', $string, $this->r53->getSecretKey(), true));
	}
}
