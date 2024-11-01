<?php

if(!function_exists("Viralogy_LoadTracker")) {

	function Viralogy_getPage($url,$user=null,$password=null) {
		if(function_exists("curl_init")) {
			$ch = curl_init();
			curl_setopt($ch,CURLOPT_USERAGENT,"Mozilla/4.0");
			curl_setopt($ch, CURLOPT_URL,$url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);	
			if($user !=null && $password != null) {
				curl_setopt($ch,CURLOPT_USERPWD,$user . ":" . $password);
			}
			$data = utf8_encode(curl_exec ($ch));
			curl_close ($ch);
		}else {
			$data = getPageWithoutCUrl($url,$user,$password);
		}
		return $data;
	}

	function Viralogy_getPageWithoutCUrl($url, $user, $password) {
		$params = array('http' => array(
					  'method' => 'GET'
				   ));
		if($user !=null && $password != null) {
			$cred = base64_encode($user.":".$password);
			$params['http']['Authorization'] = "Basic: $cred";
		}	
		$ctx = stream_context_create($params);
		$fp = @fopen($url, 'rb', false, $ctx);
		$response = @stream_get_contents($fp);
		return $response;
	}


	function Viralogy_replaceTimestampsWithAge($str) {
		preg_match_all("/\"created_at\":\"(.+?)\"/",$str,$matches);
		$now = time();
		foreach($matches[1] as $match) {
			$secondsDiff = $now - strtotime($match);
			if($secondsDiff > 60) {
				$minutesDiff = floor($secondsDiff/60);
				if($minutesDiff > 60) {
					$hoursDiff = floor($minutesDiff/60);
					if($hoursDiff > 24) {
						$daysDiff = floor($hoursDiff/24);
						if($daysDiff > 15) {
							$timeStr = "a long time ago";
						} else {
							$timeStr = "about $daysDiff day" . ($daysDiff > 1 ? 's' : '') . " ago";
						}
					} else {
						$timeStr = "about $hoursDiff hour" . ($hoursDiff > 1 ? 's' : '') . " ago";
					}
				} else {
					$timeStr = "about $minutesDiff minute" . ($minutesDiff > 1 ? 's' : '') . " ago";
				}
			} else {
				$timeStr = "about $secondsDiff second" . ($secondsDiff > 1 ? 's' : '') . " ago";
			}
			$str = str_replace($match,$timeStr,$str);
		}
		return $str;
	}


	function Viralogy_UpgradeCheck($FULLPATH) {
		
		if(get_option('Viralogy_W1_can_update') === "no") {
			return;
		}
		
		$lastUpdate = (int)(get_option('Viralogy_W1_last_update'));
		if($lastUpdate && $lastUpdate > (time() - (60*24))) {
			//update once a day
			return;
		}

		//every now and then check for an upgrade
		update_option('Viralogy_W1_last_update', time());

		$filePath = substr($FULLPATH,0,strrpos($FULLPATH,"/"));
		$filePath = substr($filePath,0,strrpos($filePath,"/")+1);
		$fileName = substr($FULLPATH,strrpos($FULLPATH,"/")+1);
		$pluginName = substr($fileName,0,strpos($fileName,"."));

		$data = file_get_contents($FULLPATH);
		preg_match("/Version: ([\d\.]+)/",$data,$matches);
		if($matches && $matches[1]) {
			$currentVersion = $matches[1];
		}			

		$www = urlencode($_SERVER['HTTP_HOST']);	
		$newestVersion = @file_get_contents("http://www.viralogy.com/packageManager/version/$pluginName");
				
		if($newestVersion && $currentVersion != $newestVersion) {
			//do the update
			$zipFile = @file_get_contents("http://www.viralogy.com/packageManager/download/$pluginName/$www");
			if(@file_put_contents($filePath . $pluginName . '.zip', $zipFile) === false) {
				update_option('Viralogy_W1_can_update', "no" );
			}else {
				@shell_exec("unzip -o $filePath" . "$pluginName -d $filePath/$pluginName");
				//unlink($filePath . $pluginName . '.zip');	
				update_option('Viralogy_W1_can_update', "yes" );
			}					
		}
	}

	$Viralogy_Tracker_Loaded = false;
	function Viralogy_LoadTracker() { 
		global $Viralogy_Tracker_Loaded;
		if($Viralogy_Tracker_Loaded)
			return;
		$Viralogy_Tracker_Loaded = true;
		?>
		<script language="javascript" type="text/javascript">
		if (typeof ViralogyDI=="undefined") {
		 var protocol = document.location.toString().indexOf( "https://" ) != -1 ? "https" : "http";
		 document.write(unescape("%3Cscript type='text/javascript' src='" + protocol + "://www.viralogy.com/javascript/vdi.js'%3E%3C/script%3E"));
		}
		</script>
	<?php } 
}

?>
