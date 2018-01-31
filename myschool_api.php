<?php 
//This library should allow programatic access to a MySchool interface through web scraping using parent login.
//Only tested on the system of my school (European Schools - sms.eursc.eu)
//No support provided. Requires the Simple HTML Dom library.
require("lib/simple_html_dom.php");

class MySchoolAPI {

	public $browser_headers = [
			    'Cache-Control: max-age=0',
			    'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0, MySchool_API) Gecko/20100101 Firefox/28.0',
			    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			    'Accept-Encoding: identity',
			    'Accept-Language: en-US,en;q=0.5',
			    'Host: demo.msm.io',
			    'Referer: https://demo.msm.io/login.php', 
			    'Origin: https://demo.msm.io'
	];

	public $host = "https://demo.msm.io";

	public function setHost($host) {
		 $this->host = $host; 
		 $this->browser_headers = [
		 		'Cache-Control: max-age=0',
			    'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux i686; rv:28.0, MySchool_API) Gecko/20100101 Firefox/28.0',
			    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
			    'Accept-Encoding: identity',
			    'Accept-Language: en-US,en;q=0.5',
			    'Host: '.str_replace('https://', '', $host).'', //put your host here
			    'Referer: '.$host.'/login.php', 
			    'Origin: '.$host.''
		 ];

	}

	public function loginSMS($username, $password) {

		  	$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->host);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "user_email=".urlencode($username)."&user_password=".urlencode($password));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->browser_headers);

			$output = curl_exec($ch);
			$cookies = $this->getCookiesFromCURL($output);

			//add another request to preserve the session
			$ch = $this->getGeneralCurlObjectForSMS($this->host, $cookies);
			$output = curl_exec($ch);

			return $cookies;
	}

	public function logoutSMS($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host."/login.php?m=1", $cookies);
		$output = curl_exec($ch);

		return true;


	}

	public function returnCookieStr($cookies) {

			$php_session_id = $cookies["PHPSESSID"];

			$str_cookie = 'PHPSESSID=' . $php_session_id . '; password_remembered=; page_requested=page_requested=%2Fcontent%2Fcommon%2Fdashboard.php';

			return $str_cookie;
	}

	public function getLoginStatus($cookies) {

			$ch = $this->getGeneralCurlObjectForSMS($this->host, $cookies);
			$output = curl_exec($ch);

			if (strpos($output, 'Logged in as') !== false) {
		   		return true; //user is logged in
			} else {
				return false; //user is not logged in

			}
	}

	public function fetchCurrentStudent($cookies) {

		//we are fetching from the student info page because if we were to fetch from the homepage it would reset the studenet to the default one
		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/guardian/student_info.php", $cookies);
		$output = curl_exec($ch);

		//simple regex is enough as there is only one h2 and content is known
		preg_match('|<h[^>]+>(.*)</h[^>]+>|iU', $output, $data);
		
		//remove one with <h2>
		unset($data[0]);
		$data = array_values($data);

		//convert array to string
		$data = $data[0];

		//in order to match with schedule name to an ID from the homepage drop down menu, we have to swap last name with family name and add ','
		//that is reverse name order and add ','
		//supports up to three names (hardcoded)

		//explode by whitespace
		$data = explode(" ", $data);
		//get value of last element
		$final_element = array_slice($data, -1)[0];
		//cut off last element so it doesn't get repeated if the name is only composed of two parts
		$data = array_slice($data, 0, -1);
		//make name with correct structure

		//tri-name (we don't want whitespace trouble)
		if (isset($data[1])) {
			$data = $final_element.", ".$data[0]." ".$data[1];
		} else {
			$data = $final_element.", ".$data[0];
		}
		
		return $data;

	}

	//get NAME => ID
	public function matchNameWithID($current_student, $multistudent) {
		return array($current_student => $multistudent[$current_student]);
	}

	//fetch stuff such as class, class teacher
	public function fetchStudentInfo($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/guardian/student_info.php", $cookies);
		$output = curl_exec($ch);

		$html = new simple_html_dom();
		$html -> load($output);

		$data = array();

		foreach ($html->find('td') as $e) {
			$data[] = $e->plaintext;
		}

		//first 6 contains the stuff we need
		$data = array_slice($data, 0, 6);
		return $data;
	}

	public function fetchAllStudents($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host, $cookies);
		$output = curl_exec($ch);

		$html = new simple_html_dom();
		$html -> load($output);

		$data = array();

		foreach ($html->find('select option') as $e) {
			$data[$e->plaintext] = $e->value;
		}

		return $data;

	}

	public function switchCurrentStudent($new_student_id, $cookies) {

		  	$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $this->host);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "student_select=".$new_student_id);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->browser_headers);
			curl_setopt($ch, CURLOPT_COOKIE, $this->returnCookieStr($cookies));

			$output = curl_exec($ch);

			return true;

	}

	public function fetchAccountInformation($cookies) {

			$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/common/account_edit.php", $cookies);
			$output = curl_exec($ch);

			// Retrieve the DOM from string
			$html = new simple_html_dom();
			$html -> load($output);

			$data = array();

			//Parse it
			foreach($html->find('td') as $e) {
					$data[] = $e->plaintext;
			}

			//We will have USER ID, 10000, EMAIL, john@whatever.com, etc
			$data = array_chunk($data, 2);
			
			//only the first 4 are needed: name, user id, email, membership. Strip out password stuff
			unset($data[4]);
			unset($data[5]);
			unset($data[6]);
			unset($data[7]);
			unset($data[8]);	

			return $data;

	}

	public function downloadToArray($data_linked, $cookies) {

		$data = array();

		foreach ($data_linked as $name => $url) {
			$url = $this->host.$url;
			$ch = $this->getGeneralCurlObjectForSMS($url, $cookies);
			$output = curl_exec($ch);

			$data[$name] = base64_encode($output);
		}


		return $data;

	}

	public function fetchScheduleURL($cookies) {
		
		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/common/calendar_for_parents.php", $cookies);
		$output = curl_exec($ch);

		$html = new simple_html_dom();
		$html -> load($output);

		$values = array();

		foreach ($html->find('input') as $e) {
			$values[] = $e->value;
		}

		//remove noise
		unset($values[0]);
		unset($values[4]);
		unset($values[5]);
		$values = array_values($values);

		$schedule_url = $this->constructSchedulePrintURL($values[0], $values[1], $values[2]);

		return $schedule_url;
	}

	public function fetchSchedule($schedule_url, $cookies) {

		//start parsing schedule
		$ch = $this->getGeneralCurlObjectForSMS($schedule_url, $cookies);
		$output = curl_exec($ch);

		//replace text so that the free period doesn't get treated as whitespace
		$output = str_replace("&nbsp;", "FREE", $output);

		$html = new simple_html_dom();
		$html -> load($output);

		$schedule = array();

		foreach ($html->find('td[class=top]') as $e) {
			 $schedule[] = strip_tags($e->innertext);
		}


		//split array by tabs
		$schedule = $this->splitArrayByTabs($schedule);

		//throw out all empty values
		$schedule = $this->throwEmptyArrayValuesIntoTheTrashTwoDimensional($schedule);


		//at this point, it goes vertically: monday first, tuesday first, wednesday first, etc. Make it monday first, monday second, monday third, etc

		//first it chunks array into 5 periods (vertically 5 periods)
		//then, into 5 arrays: for each day, 11 sub-arrays: for 11 periods
		$schedule = $this->transformScheduleIntoHorizontal($schedule);


		//remove 10th, 11th periods
		$schedule = $this->removeUnnecesaryPeriodsFromSchedule($schedule);

		//adapt free periods to allow easier parsing. Since the free periods only have one subarray value, we need to add all the three (class, room, teacher all need to equal FREE)
		$schedule = $this->adaptFreePeriodsFromSchedule($schedule);


		return $schedule;
	}

	public function adaptFreePeriodsFromSchedule($array) {

		foreach ($array as $key => $value) {
			foreach ($value as $key_deeper => $value_deeper) {
				if ($value_deeper[0] == "FREE") {
					$array[$key][$key_deeper][16] = "FREE";
					$array[$key][$key_deeper][32] = "FREE";
				}
			}
		}

		return $array;
	}

	public function removeUnnecesaryPeriodsFromSchedule($array) {

		foreach ($array as $key => $value) { 
			unset($array[$key][9]);    
	    	unset($array[$key][10]);      
		}

		return $array;

	}

	public function transformScheduleIntoHorizontal($array) {
		
		$array = array_chunk($array, 5);

		foreach ($array as $key => $value) {
			$mon[] = $value[0];
			$tue[] = $value[1];
			$wed[] = $value[2];
			$thu[] = $value[3];
			$fri[] = $value[4];
		}

		$array = array_chunk(array_merge($mon, $tue, $wed, $thu, $fri), 11);
		return $array;
	}

	public function splitArrayByTabs($array) {
		
		foreach ($array as $key => $value) {
			$array[$key] = preg_split('/[\t]/', $value);
		}

		return $array;

	}

	public function throwEmptyArrayValuesIntoTheTrashTwoDimensional($array) {

		foreach ($array as $key => $value) {
			foreach ($value as $key_deeper => $value_deeper) {
				if ($value_deeper === '') {
					unset($array[$key][$key_deeper]);
				}
			}	
		}

		return $array;

	}

	public function constructSchedulePrintURL($courses, $name, $class) {
		return $this->host."/data/common_handler.php?action=Course_Schedule::AJAX_U_PrintTimetable&active_course_list=".urlencode($courses)."&header_description=".urlencode($name).urlencode(" - ").urlencode($class);
	}

	public function getGeneralCurlObjectForSMS($url, $cookies) {

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_COOKIE, $this->returnCookieStr($cookies));		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->browser_headers);

		return $ch;
	}

	public function fetchTermReports($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/guardian/term_reports.php", $cookies);
		$output = curl_exec($ch);

		// Retrieve the DOM from string
		$html = new simple_html_dom();
		$html -> load($output);

		$links = array();
		$names = array();

		foreach ($html->find('td a') as $e) {
			$links[] = $e->plaintext;
			$names[] = $e->href;
		}

		$data_linked = array();
		$data_linked = array_combine($links, $names);
		
		$data = $this->downloadToArray($data_linked, $cookies);

		return $data;
	}

	public function fetchTeacherInformation($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/guardian/student_info.php", $cookies);
		$output = curl_exec($ch);

		// Retrieve the DOM from string
		$html = new simple_html_dom();
		$html -> load($output);

		$teachers = array();

		//Parse teachers
		//to only classes, no teachers: [class=th top]
		foreach($html->find('td[!class]') as $e) {

				//only keep teachers from selection of td's with no classes
				if (preg_match("/@/", $e->plaintext)) {
					$teachers[] = $e->plaintext;
				}
				
		}

		//remove class teacher
		unset($teachers[0]);
		$teachers = array_values($teachers);

		$classes = array();

		//parse classes
		foreach($html->find('td[class=th top]') as $e) {
					$classes[] = $e->plaintext;
		}

		$data = array_combine($teachers, $classes);

		return $data;

	}

	public function fetchContactDetails($cookies) {

		$ch = $this->getGeneralCurlObjectForSMS($this->host."/content/user/my_contact_details.php", $cookies);
		$output = curl_exec($ch);

		$output = str_replace("&nbsp;", "EMPTY", $output);

		// Retrieve the DOM from string
		$html = new simple_html_dom();
		$html -> load($output);

		$data = array();

		//Parse it
		foreach($html->find('td') as $e) {
				$data[] = $e->plaintext;
		}

		//we need to remove all two consequent blank values (ex: &nbsp;&nbsp;)
		$data = $this->removeConsequentBlankValues($data);
		//data as on the MySchool system: in multidimensional array [x]->0->City=>1->Bruxelles etc
		$data = array_chunk($data, 2);

		return $data;

	}

	public function removeConsequentBlankValues($array) {
		foreach ($array as $key => $value) {
			if ($array[$key] == "EMPTY" and $array[$key + 1] == "EMPTY") {
				unset($array[$key + 1]);
			}
		}

		return $array;
	}

	public function getCookiesFromCURL($output) {

		preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $output, $matches);
		$cookies = array();
		foreach($matches[1] as $item) {
		    parse_str($item, $cookie);
		    $cookies = array_merge($cookies, $cookie);
		}

		return $cookies;
	}


}

//Example, using parent login. This will fetch pairs of teacher => subject for a student:
$api = new MySchoolAPI();
$api->setHost("https://my.host.com");
$cookies = $api->loginSMS("email", "password");
print_r($api->fetchTeacherInformation($cookies));


?>