<?php
/*
Copyright 2011 Montania System AB

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License. 
*/

/**
 * Google Calendar, version 2.1 API class 
 * 
 * This class implements the Google Calendar API version 2.1. To use this class you need to provide
 * your username (usually an e-mail) and password to the Google account. We're of course not saving
 * this information in any way and all requests are sent encrypted with HTTPS.
 * 
 * https://github.com/montania/Google-Calendar-API-PHP-Class
 * 
 * @author Rickard Andersson <rickard@montania.se>
 * @copyright Montania System AB
 * @version 1.0
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @package GCalendar
 */


/**
 * This class implements the Google Calendar API version 2.1. To use this class you need to provide
 * your username (usually an e-mail) and password to the Google account. We're of course not saving
 * this information in any way and all requests are sent encrypted with HTTPS.
 * 
 * @package GCalendar
 */
class GCalendar {
  
  private $email;
  private $password;
  private $source = "Montania-GCalendar-PHP";
  private $sid;
  private $lsid;
  private $auth;
  private $authenticated = false;
    
  /**
   * Class constructor to create an instance, takes email and password as arguments
   * @param string $email     Your google account email
   * @param string $password  Your google account password
   */
  function __construct($email, $password) {
    $this->email    = $email;
    $this->password = $password;
    date_default_timezone_set("Europe/Stockholm");
    
    DEFINE("DEFAULT_MAX_EVENTS", 25);
    
  }
  
  /**
   * Method to authenticate the user against Google, returns false if authentication failed.
   * @return bool
   */
  function authenticate() {
    if ($this->authenticated == true) {
      return true;
    } else if (empty($this->email) || empty($this->password)) {
      return false;
    }
    
    $ch = $this->curlPostHandle("https://www.google.com/accounts/ClientLogin", false);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, sprintf("Email=%s&Passwd=%s&source=%s&service=cl", $this->email, $this->password, $this->source));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
                
    switch ($http_code) {
      case 200:
        preg_match("/SID=([a-z0-9_-]+)/i", $response, $sid);
        preg_match("/LSID=([a-z0-9_-]+)/i", $response, $lsid);
        preg_match("/Auth=([a-z0-9_-]+)/i", $response, $auth);
        
        $this->sid = $sid[1];
        $this->lsid = $lsid[1];
        $this->auth = $auth[1];
        $this->authenticated = true;
        
        return true;
        
        break;
        
      case 403:
        return false;
        break;
        
      default:
        return false;
    } 
  }

  /**
   * Helper function to check if the user is authenticated
   * @return bool
   */
  function isAuthenticated() {
    return $this->authenticated;
  }
  
  /**
   * Method to get an array with information about all calendars assocciated with this account.
   * The array contains two keys, "handle" and "title". The handle can be used with getEvents() to retrieve events.
   * @return bool|array    Returns false on failure and array on success.
   */
  function getAllCalendars() {
    if ($this->authenticated === false) {
      return false;
    }
    
    $data = $this->getJsonRequest("https://www.google.com/calendar/feeds/default/allcalendars/full?alt=jsonc", true);
    
    if (!is_object($data)) {
      return false;
    }
    
    foreach ($data->data->items as $item) {
      $handle = str_replace(array("https://www.google.com/calendar/feeds/", "/private/full"), "", $item->eventFeedLink); 
      $calendars[] = array('title' => $item->title, 'handle' => $handle);
    }
    
    return $calendars;
  }
  
  /**
   * Method to get an array with this users own calendars.
   * The array contains two keys, "handle" and "title". The handle can be used with getEvents() to retrieve events.
   * @return bool|array    Returns false on failure and array on success.
   */
  function getOwnCalendars() {
    if ($this->authenticated === false) {
      return false;
    }
    
    $data = $this->getJsonRequest("https://www.google.com/calendar/feeds/default/owncalendars/full?alt=jsonc", true);
    
    if (!is_object($data)) {
      return false;
    }    
    
    foreach ($data->data->items as $item) {
      $handle = str_replace(array("https://www.google.com/calendar/feeds/", "/private/full"), "", $item->eventFeedLink); 
      $calendars[] = array('title' => $item->title, 'handle' => $handle);
    }
    
    return $calendars;
  }
  
  /**
   * Method to add a new calendar to the authenticated account
   * 
   * Valid colors are: 
   * #A32929     #B1365F     #7A367A     #5229A3     #29527A     #2952A3     #1B887A
   * #28754E     #0D7813     #528800     #88880E     #AB8B00     #BE6D00     #B1440E
   * #865A5A     #705770     #4E5D6C     #5A6986     #4A716C     #6E6E41     #8D6F47
   * #853104     #691426     #5C1158     #23164E     #182C57     #060D5E     #125A12
   * #2F6213     #2F6309     #5F6B02     #8C500B     #8C500B     #754916     #6B3304
   * #5B123B     #42104A     #113F47     #333333     #0F4B38     #856508
   * 
   * @param string $title     Title of the calendar
   * @param string $details   Calendar details
   * @param string $timezone  Which timezone the calendar is in
   * @param bool $hidden      If the calendar should be hidden or not
   * @param string $color     Which color should be used. See above
   * @param string $location  Location of this calendar, geographically
   * @return bool|object      Returns false on failure and object on success
   */
  function createCalendar($title, $details, $timezone, $hidden, $color, $location) {
    if ($this->authenticated === false) {
      return false;
    } else if (empty($title) || empty($timezone) || !is_bool($hidden) || empty($color) || empty($location)) {
      return false;
    }
    
    $data = array(
      "data" => array(
        "title" => $title, 
        "details" => $details,
        "timeZone" => $timezone,
        "hidden" => $hidden,
        "color" => $color,
        "location" => $location
      )
    );
    
    $headers = array('Content-type: application/json');
    
    $ch = $this->curlPostHandle("https://www.google.com/calendar/feeds/default/owncalendars/full", true, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));  
    curl_setopt($ch, CURLOPT_HEADER, true); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_headers = $this->http_parse_headers($response);
    
    curl_close($ch);
    unset($ch);
    
    if ($http_code == 302) {
      
      $url = $response_headers['Location'];
     
      $ch = $this->curlPostHandle($url, true, $headers);
      
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  
      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      
      if ($http_code == 201) {
        return json_decode($response);
      } else {      
        return false;
      }
      
    } else if ($http_code == 201) {
      return json_decode($response);
    } else {      
      return false;
    }   
  }

  /**  
   * Function to delete a calendar
   * @param string $handle    E-mail or handle to identify the calendar
   * @return bool             Whether or not the calendar was deleted successfully
   */
  public function deleteCalendar($handle) {
    
    $url = "https://www.google.com/calendar/feeds/default/owncalendars/full/$handle";
    $ch = $this->curlDeleteHandle($url, true, array());

    $response = curl_exec($ch);
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
 
    if($http_code==200) {
        return true;
    } else {
        return false;
    }    
  }


  /**
   * Method to retrieve events from a specific calendar.
   * @param string $handle    E-mail or handle to identify the calendar
   * @param integer $max      Max amount of events to get. (optional, default = 25)
   * @param string $from      A date string where the selection should start (optional, default all events)
   * @param string $to        A date string where the selection should end (optional, default all events)
   * @return bool|object      Returns false on failure and object on success.
   */
  function getEvents($handle, $max = DEFAULT_MAX_EVENTS, $from = null, $to = null) {
    if ($this->authenticated === false) {
      return false;
    } else if (empty($handle)) {
      return false;
    }
    if (empty($handle)) {
      $handle = "default";
    }
    if (!is_numeric($max)) {
      $max = DEFAULT_MAX_EVENTS;
    }

    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full?alt=jsonc&max-results=%s", $handle, $max);
    
    if ($from != null) { 
      $from = urlencode(date("c", strtotime($from)));
      $url .= "&start-min=" . $from;
    }
    if ($to != null) {
      $to   = urlencode(date("c", strtotime($to)));
      $url .= "&start-max=" . $to;
    }
    
    $ch = $this->curlGetHandle($url, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code == "200") {
      return json_decode($response);
    } else {
      return false;
    } 
  }
  
  /**
   * Method to check if an event has been updated. 
   * @param string $handle    E-mail or handle to identify the calendar
   * @param string $id        The ID sent by Google Calendar
   * @param string $etag      The ETag property sent by Google Calendar
   * @return bool|object      Returns false on failure, true if event is up to date and object if event has been changed
   */
  function getEvent($handle, $id, $etag) {
    if ($this->authenticated === false) {
      return false;
    } else if (empty($id) || empty($etag)) {
      return false;
    }
    if (empty($handle)) {
      $handle = "default";
    }
    if (substr($etag, 0, 1) != '"') {
      $etag = '"' . $etag;
    }
    if (substr($etag, -1, 1) != '"') {
      $etag .= '"';
    }

    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full/%s?alt=jsonc", $handle, $id);
    $ch = $this->curlGetHandle($url, true, array('If-None-Match: ' . $etag));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code == 200) {
      return json_decode($response);
    } else if ($http_code == 304 || $http_code == 412) {
      return true;
    } else {
      return false;
    }    
  }

  /**
   * Get an event by its entryID
   * @param string $handle    E-mail or handle to identify the calendar
   * @return bool|object      Returns false on failure and object on success
   */
  function getEventByID($handle, $event_id) {
    if ($this->authenticated === false) {
      return false;
    } else if (empty($handle)) {
      return false;
    }
    // GET https://www.google.com/calendar/feeds/default/private/full/entryID
    $url = "https://www.google.com/calendar/feeds/$handle/private/full/$event_id?alt=jsonc";
    
    $ch = $this->curlGetHandle($url, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code == "200") {
      $event = json_decode($response);
      if (!empty($event)) { 
        return $event;
      } else {
        return array();
      }
    } else {
      return false;
    }
  }

  
  /**
   * Method to search for an event.
   * @param string $handle    E-mail or handle to identify the calendar
   * @param string $query     The search query to perform
   * @param integer $max      Max amount of events to get. (optional, default = 25)
   * @return bool|object      Returns false on failure and object on success
   */
  function findEvent($handle, $query, $max = DEFAULT_MAX_EVENTS) {
    if ($this->authenticated === false) {
      return false;
    } else if (empty($query)) {
      return false;
    }
    
    if (empty($handle)) {
      $handle = "default";
    }
    if (!is_numeric($max)) {
      $max = DEFAULT_MAX_EVENTS;
    }    
    
    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full?q=%s&alt=jsonc&max-results=%s", $handle, urlencode($query), $max);
    $ch = $this->curlGetHandle($url, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code == 200) {
      return json_decode($response);
    } else {
      return false;
    }
  }
  
  /**
   * Method to create an event in a specific calendar.
   * @param string $handle          E-mail or handle to identify the calendar
   * @param bool $quick             If quick is set to true, only the details argument is needed.
   * @param string $details         Details of the event
   * @param string $title           Title of the event (optional in quick mode)
   * @param string $transparency    Transparency (optional in quick mode) 
   * @param string $status          Status of the event (optional in quick mode)
   * @param string $location        Location of the event (optional in quick mode)
   * @param string $start           Time when the event starts (optional in quick mode)
   * @param string $end             Time when the event ends (optional in quick mode)
   * @return bool|object            Returns false on failure and object on success
   */
  function createEvent($handle, $quick = false, $details, $title = null, $transparency = null, $status = null, $location = null, $start = null, $end = null) {
    if ($this->authenticated === false) {
      return false; 
    } else if ($quick === false && (empty($title) || empty($transparency) || empty($status) || empty($location) || empty($start) || empty($end))) {
      return false;
    } else if ($quick === true && empty($details)) {
      return false;
    }
    
    if (empty($handle)) {
      $handle = "default";
    }    

    if ($quick === true) {
      $data = array("data" => array(
          "details" => $details,
          "quickAdd" => true
        )
      );
      $data = json_encode($data);
    } else {
      $data = sprintf('{
  "data": {
    "title": "%s",
    "details": "%s",
    "transparency": "%s",
    "status": "%s",
    "location": "%s",
    "when": [
      {
        "start": "%s",
        "end": "%s"
      }
    ]
  }
}', $title, $details, $transparency, $status, $location, date("c", strtotime($start)), date("c", strtotime($end)));

    }

    $headers = array('Content-Type: application/json');
    
    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full", $handle);
    $ch = $this->curlPostHandle($url, true, $headers);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);  
    curl_setopt($ch, CURLOPT_HEADER, true); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_headers = $this->http_parse_headers($response);
    
    curl_close($ch);
    unset($ch);
    
    if ($http_code == 302) {
      
      $url = $response_headers['Location'];
     
      $ch = $this->curlPostHandle($url, true, $headers);
      
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  
      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
      if ($http_code == 201) {
        return json_decode($response);
      } else {      
        return false;
      }
      
    } else if ($http_code == 201) {
      return json_decode($response);
    } else {      
      return false;
    }    
  }

  /**
   * Method to remove an event from the calendar. If $etag is submitted it won't delete your event if it has been updated since you last retreived it
   * @param string $handle    E-mail or handle to identify the calendar
   * @param string $id        The id of the event
   * @param string $etag      The e-tag of the event (optional)
   * @return bool             Returns false on failure and true on success
   */
  function deleteEvent($handle, $id, $etag = null) {
    if ($this->authenticated === false) {
      return false; 
    } else if (empty($handle) || empty($id)) {
      return false;
    }
    
    if (empty($handle)) {
      $handle = "default";
    }
    
    if (!empty($etag)) {
        
      if (substr($etag, 0, 1) != '"') {
        $etag = '"' . $etag;
      }
      if (substr($etag, -1, 1) != '"') {
        $etag .= '"';
      }
            
      $headers = array('If-Match: ' . $etag);
    } else {
      $headers = array('If-Match: *');
    }
    
    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full/%s", $handle, $id);    
    $ch = $this->curlDeleteHandle($url, true, $headers);
    
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    return ($http_code == 200);
  }
  
  /**
   * Method to update an event in the calendar. If $etag is submitted it won't update your event if it has been updated since you last retreived it
   * @param string $handle    E-mail or handle to identify the calendar
   * @param string $id        The id of the event
   * @param string $etag      The e-tag of the event (optional)
   * @param string $json      The complete json code from the event that you've retrieved with the changes that you want
   * @return bool|object      Returns false on failure and object on success
   */  
  function updateEvent($handle, $id, $etag, $json) {
    if ($this->authenticated === false) {
      return false; 
    } else if (empty($handle) || empty($id) || empty($json)) {
      return false;
    } else if (!is_object(json_decode($json))) {
      return false;
    } else {
      $json = json_encode(json_decode($json));
    }
    
    if (empty($handle)) {
      $handle = "default";
    }
    
    $headers = array('Content-type: application/json');
    
    
    if (!empty($etag)) {
        
      if (substr($etag, 0, 1) != '"') {
        $etag = '"' . $etag;
      }
      if (substr($etag, -1, 1) != '"') {
        $etag .= '"';
      }
         
      $headers[] = 'If-Match: ' . $etag;
    } else {
      $headers[] = 'If-Match: *';
    }    

    $url = sprintf("https://www.google.com/calendar/feeds/%s/private/full/%s", $handle, $id);    
    $ch = $this->curlPutHandle($url, true, $headers);
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);  
    curl_setopt($ch, CURLOPT_HEADER, true); 

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_headers = $this->http_parse_headers($response);
        
    curl_close($ch);
    unset($ch);
    
    if ($http_code == 302) {
      
      $url = $response_headers['Location'];
     
      $ch = $this->curlPutHandle($url, true, $headers);
      
      curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
  
      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                 
      if ($http_code == 200) {
        return json_decode($response);
      } else {      
        return false;
      }
      
    } else if ($http_code == 200) {
      return json_decode($response);
    } else {      
      return false;
    }        
  }  
  
  /**
   * Private helper function to send a HTTP GET request and return the json decoded data
   * @param string $url
   * @param bool $authenticated   If the request should contain authentication information
   * @return bool|object          Returns false on failure and object on success.
   */
  private function getJsonRequest($url, $authenticated = false) {
    if (empty($url)) {
      return false;
    }
      
    $ch = $this->curlGetHandle($url, $authenticated);
    
    if ($ch === false) {
      return false;
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
      return json_decode($response);
    } else {
      return false;
    }    
  }
  
  /**
   * Private helper function to get a cURL handle with the correct options, authentication included. The user has to be successfully authenticated with authenticate() first
   * @param string $url           The URL where the http request should go
   * @param bool $authenticated   If the request should contain authentication information
   * @param array $headers        An array of headers to be sent with the request
   * @return bool|curl handle     Returns false on failure and a curl handle on success
   */
  private function curlGetHandle($url, $authenticated = false, $headers = array()) {
    if ($authenticated === true && $this->authenticated === false) {
      return false;
    } else if (empty($url)) {
      return false;
    }

    $headers[] = 'GData-Version: 2.1';    
    
    if ($authenticated === true) {
      $headers[] = 'Authorization: GoogleLogin auth='. $this->auth;
    }    
        
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        
    return $ch;    
  }
  
  /**
   * Private helper function to get a cURL handle for POST actions with the correct options. The user has to be successfully authenticated with authenticate() first. 
   * @param string $url           The URL where the http request should go
   * @param bool $authenticated   If the request should contain authentication information
   * @param array $headers        An array of headers to be sent with the request
   * @return bool|curl handle     Returns false on failure and a curl handle on success
   */
  private function curlPostHandle($url, $authenticated = false, $headers = array()) {
    if ($authenticated === true && $this->authenticated === false) {
      return false;
    } else if (empty($url)) {
      return false;
    }
    
    $headers[] = 'GData-Version: 2.1';
    
    if ($authenticated === true) {
      $headers[] = 'Authorization: GoogleLogin auth='. $this->auth;
    }
        
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    return $ch;
  }
  

  /**
   * Internal method to create custom method cURL handles
   * @param string $url           The URL where the HTTP request should go
   * @param string $method        Can be any of DELETE, PUT, etc.
   * @param bool $authenticated   If the request should contain authentication information (optional, default = false)
   * @param array $headers        An array of headers to be sent with the request (optional)
   * @param bool $return          If cURL should return data instead of printing it (optional, default = true)
   * @param bool $follow          If cURL should follow redirects (optional, default = true)
   * @return bool|curl handle     Returns false in failure and a curl handle on success
   */
  private function curlCustomHandle($url, $method, $authenticated = false, $headers = array(), $return = true, $follow = true) {
    if ($authenticated === true && $this->authenticated === false) {
      return false;
    } else if (empty($url) || empty($method)) {
      return false;
    }
    
    $headers[] = 'GData-Version: 2.1';
    
    if ($authenticated === true) {
      $headers[] = 'Authorization: GoogleLogin auth='. $this->auth;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow);
    
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    return $ch;
  }

  /**
   * Private helper function to get a cURL handle for DELETE actions with the correct options. The user has to be successfully authenticated with authenticate() first. 
   * @param string $url           The URL where the http request should go
   * @param bool $authenticated   If the request should contain authentication information
   * @param array $headers        An array of headers to be sent with the request
   * @return bool|curl handle     Returns false on failure and a curl handle on success
   */    
  private function curlDeleteHandle($url, $authenticated = false, $headers = array()) {
    return $this->curlCustomHandle($url, "DELETE", $authenticated, $headers); 
  }

  /**
   * Private helper function to get a cURL handle for PUT actions with the correct options. The user has to be successfully authenticated with authenticate() first. 
   * @param string $url           The URL where the http request should go
   * @param bool $authenticated   If the request should contain authentication information
   * @param array $headers        An array of headers to be sent with the request
   * @return bool|curl handle     Returns false on failure and a curl handle on success
   */     
  private function curlPutHandle($url, $authenticated = false, $headers = array()) {
    return $this->curlCustomHandle($url, "PUT", $authenticated, $headers, true, false);
  }

  /**
   * Adds user(s) to the Access Control List
   * @param string $handle        E-mail or handle to identify the calendar
   * @param string $scope         A person or set of people ( e-mail address / domain name / null )
   * @param string $scope_type    The type of scope ( user / domain / default )
   * @param string $role          The access level ( root / owner / editor / freebusy / read / none )
   */
  function addUserToACL($handle = "default", $role = "read", $scope = null, $scopeType = "default") {
    // POST /calendar/feeds/liz@gmail.com/acl/full
    $url = sprintf("https://www.google.com/calendar/feeds/%s/acl/full/", $handle);    
    $data = array(
      'data' => array(
        'scopeType' => $scopeType,
        'role' => $role
      )
    );
    if (!empty($scope)) {
      $data['data']['scope'] = $scope;
    }
    $json = json_encode($data);

    $headers = array('Content-type: application/json');

    $ch = $this->curlPostHandle($url, true, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_headers = $this->http_parse_headers($response);

    curl_close($ch);
    unset($ch);

    if ($http_code == 302) {

      $url = $response_headers['Location'];

      $ch = $this->curlPostHandle($url, true, $headers);

      curl_setopt($ch, CURLOPT_POSTFIELDS, ($json));

      $response = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      if ($http_code == 201) {
        return json_decode($response);
      } else {      
        return false;
      }

    } else if ($http_code == 201) {
      return json_decode($response);
    } else {      
      return false;
    }   
  }

  /**
   * Creates the http_parse_headers function if pecl_http is not installed
   */
  function http_parse_headers($header) {
    if(!function_exists('http_parse_headers')) {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach( $fields as $field ) {
          if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
            $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
            if( isset($retVal[$match[1]]) ) {
              $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
            } else {
              $retVal[$match[1]] = trim($match[2]);
            }
          }
        }
        return $retVal;
    } else {
        return http_parse_headers($header);
    }
  }
}
