<?php
/*
Plugin Name: Viralogy Twitter Sidebar
Plugin URI: http://www.viralogy.com
Description: Viralogy's Twitter Sidebar shows your visitors you most recent tweets and current tweets around tags you're interested in.
Version: 1.3.7
Author: Viralogy, Inc.
Author URI: http://www.viralogy.com
PackageManager: Viralogy
*/

define("VIRALOGY_W1_NAME","Viralogy Twitter Sidebar");
define("VIRALOGY_W1_URL","http://wordpress.org/extend/plugins/viralogy-twitter-widget/");
define("VIRALOGY_W1_BASE_URL",get_option('siteurl') . '/wp-content/plugins/viralogy-twitter-widget/');
$filePath = substr(__FILE__,0,strrpos(__FILE__,"/"));
require_once($filePath . "/utilities.php");

//////////////* Hooks *//////////////////
add_action("admin_menu","Viralogy_W1_ShowAdminMenu");
add_action("plugins_loaded", "Viralogy_W1_Init");
add_action("init","Viralogy_W1_Request_Handler");
register_activation_hook( __FILE__, 'Viralogy_W1_Activate' );
register_deactivation_hook( __FILE__, 'Viralogy_W1_Deactivate' );
add_filter('plugin_action_links', 'Viralogy_W1_plugin_action_links', 10, 2);

function Viralogy_W1_Activate() { 
	global $wpdb;

	Viralogy_W1_Deactivate();
	
	//defaults
	update_option('Viralogy_W1_number_of_tweets', "5" );
	update_option('Viralogy_W1_number_of_keyword_results', "10" );
	update_option('Viralogy_W1_tags', "viralogy" );
	update_option('Viralogy_W1_hide_replies', "0" );
	$charset_collate = '';
	if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
		if (!empty($wpdb->charset)) {
			$charset_collate .= " DEFAULT CHARACTER SET $wpdb->charset";
		}
		if (!empty($wpdb->collate)) {
			$charset_collate .= " COLLATE $wpdb->collate";
		}
	}
	$wpdb->query("
		CREATE TABLE `Viralogy_W1_Cache` (
		`id` INT( 11 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR( 255 ) NOT NULL ,
		`data` TEXT NOT NULL ,
		`updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		UNIQUE KEY ( `name` )
		) $charset_collate
	"); 
}

function Viralogy_W1_Deactivate() { 
	global $wpdb;
	update_option('Viralogy_W1_can_update', "" );
	update_option('Viralogy_W1_last_update', "" );

	$wpdb->query("DROP TABLE IF EXISTS `Viralogy_W1_Cache`");	 
}


function Viralogy_W1_Request_Handler() { 
	global $wpdb;

	$username = get_option('Viralogy_W1_twitter_username');
	$password = get_option('Viralogy_W1_twitter_password');
	$action = isset($_POST['Viralogy_W1_action']) ? $_POST['Viralogy_W1_action'] : 'meow';
	//$wpdb->show_errors();
		 
	if($action == 'statuses') {

		$count = $_POST['count'];

		//get a cached version of data if available
		$now = date("YmdHis");
		$cacheName = "statuses_" . $username . "_" . $count;
		$cacheName =  mysql_escape_string($cacheName);
		$results = $wpdb->get_results("SELECT * FROM Viralogy_W1_Cache WHERE name='$cacheName' AND updated >= ($now - INTERVAL 5 MINUTE)") ;
		foreach($results as $row) {
			$data = $row->data;
			$data = Viralogy_replaceTimestampsWithAge($data);
			die($data);
		}

		//get a new copy
		$data = Viralogy_getPage("http://twitter.com/statuses/user_timeline.json?screen_name=" . urlencode($username) . "&count=$count",$username,$password);

		if(strpos($data,'"error"') !== false) {
			//don't cache rate limit messages, just use the old cache
			//we also want to put it back in the cache so we don't but twitter
			$results = $wpdb->get_results("SELECT * FROM Viralogy_W1_Cache WHERE name='" . mysql_escape_string($cacheName) . "'");
			foreach($results as $row) {
				$data = $row->data;
			}
		}

		//cache data for a few minutes
		$escapedData = mysql_escape_string($data);
		$result = $wpdb->query("INSERT INTO Viralogy_W1_Cache (name,data) VALUES ('$cacheName','$escapedData') 
						ON DUPLICATE KEY UPDATE data='$escapedData'");
		
		
		$data = Viralogy_replaceTimestampsWithAge($data);
		die($data);
	
	} else if($action == 'search') {

		$count = $_POST['count'];
		$query = $_POST['query'];

		//get a cached version of data if available
		$now = date("YmdHis");
		$cacheName = "search_" . $query . "_" . $count;
		$cacheName =  mysql_escape_string($cacheName);
		$results = $wpdb->get_results("SELECT * FROM Viralogy_W1_Cache WHERE name='$cacheName' AND updated >= ($now - INTERVAL 5 MINUTE)");
		foreach($results as $row) {
			$data = $row->data;
			$data = Viralogy_replaceTimestampsWithAge($data);
			die($data);
		}

		//get a new copy
		$data = Viralogy_getPage("http://search.twitter.com/search.json?q=" . urlencode($query) . "&rpp=" . $count);

		if(strpos($data,'"error"') !== false) {
			//don't cache rate limit messages, just use the old cache
			//we also want to put it back in the cache so we don't but twitter
			$results = $wpdb->get_results("SELECT * FROM Viralogy_W1_Cache WHERE name='" . mysql_escape_string($cacheName) . "'");
			foreach($results as $row) {
				$data = $row->data;
			}
		}

		//cache data for a few minutes
		$escapedData = mysql_escape_string($data);
		$result = $wpdb->query("INSERT INTO Viralogy_W1_Cache (name,data) VALUES ('$cacheName','$escapedData') 
						ON DUPLICATE KEY UPDATE data='$escapedData'");
		
		
		$data = Viralogy_replaceTimestampsWithAge($data);
		die($data);
	
	}else {
		$fullPath = __FILE__;
		Viralogy_UpgradeCheck($fullPath);
	}
}


function Viralogy_W1_Init() { 
	 //enable widget
	 register_sidebar_widget(VIRALOGY_W1_NAME, "Viralogy_W1_LoadWidget"); 
}



				
//////////////* Widget *//////////////////
function Viralogy_W1_LoadWidget() { ?>
	<style type='text/css'>
		#VIRALOGY_W1_container { width: 245px; height:329px; background: url("<?php echo VIRALOGY_W1_BASE_URL . "Container.png" ?>") no-repeat;position:relative; margin-bottom:10px; margin-top:10px; }
		#VIRALOGY_W1_header { width: 95%; height:70px; position:relative; background:transparent; margin:auto; top:20px; }
		#VIRALOGY_W1_twitterProfile { margin-left:20px; }
		#VIRALOGY_W1_twitterProfile img { cursor:pointer;margin-right:10px; float: left }
		#VIRALOGY_W1_twitterProfile div { height: 50px; border-left:1px solid #c3c3c3; padding-left:10px; float: left; }
		#VIRALOGY_W1_tabs { width: 95%; height:40px; position:relative; background:transparent; margin:auto; }
		#VIRALOGY_W1_tabs div { cursor:pointer; width: 80px; height:30px; position:absolute; bottom:0px; background:#e1e1e1; border:1px solid #c3c3c3; border-bottom:0px; line-height:30px; margin-left:3px; text-align:center; z-index:15; }
		#VIRALOGY_W1_tabs .selected { background:white; bottom:-1px;}
		#VIRALOGY_W1_content { border-top:1px solid #c3c3c3; width: 95%; position:relative; height:170px; max-height:170px; background:transparent; margin:auto; z-index:10; }
		#VIRALOGY_W1_content_body { margin:auto; margin:10px; height:152px; width:90%; overflow:hidden; }
		#VIRALOGY_W1_content_body .VIRALOGY_W1_twitter_tweet { font-size:12px; margin-bottom:9px; float:left; clear:both; width:100% }
		#VIRALOGY_W1_content_body .VIRALOGY_W1_twitter_reply { color:red; }
		#VIRALOGY_W1_content_body .VIRALOGY_W1_twitter_link { color:red; }
		#VIRALOGY_W1_content_body .VIRALOGY_W1_twitter_info,#VIRALOGY_W1_content_body .VIRALOGY_W1_twitter_info a { color:#AAAAAA; font-size:9px;}
		#VIRALOGY_W1_viralogy_up_arrow { cursor:pointer; width: 13px; height:9px; background: url("<?php echo VIRALOGY_W1_BASE_URL . "arrow-up.png" ?>") no-repeat;position:absolute;right:2px; top:7px; }
		#VIRALOGY_W1_viralogy_down_arrow { cursor:pointer; width: 13px; height:9px; background: url("<?php echo VIRALOGY_W1_BASE_URL . "arrow-down.png" ?>") no-repeat;position:absolute;right:2px; bottom:0px; }
		#VIRALOGY_W1_viralogy_logo { cursor:pointer;display:block;width: 50px; height:24px; background: url("<?php echo VIRALOGY_W1_BASE_URL . "Viralogy-logo.png" ?>") no-repeat;position:absolute;bottom:9px;right:15px; }
	</style>
	<script type='text/javascript'>
		function VIRALOGY_W1_switchTabs(tab) {
			var content = document.getElementById('VIRALOGY_W1_content');
			var tweetsTab = document.getElementById('VIRALOGY_W1_tweets_tab');
			var keywordsTab = document.getElementById('VIRALOGY_W1_keywords_tab');
			content.innerHTML = "<br><br><center>Loading...</center>";
			if(tab == 'VIRALOGY_W1_keywords_tab') {
				tweetsTab.className = "";
				keywordsTab.className = "selected";
				VIRALOGY_W1_xmlhttpPost("<?php echo get_option('siteurl') . "/index.php" ?>","Viralogy_W1_action=search&query=<?php echo urlencode(str_replace(","," OR ",get_option('Viralogy_W1_tags'))) ?>&count=<?php echo get_option('Viralogy_W1_number_of_keyword_results') ?>",VIRALOGY_W1_updateKeywordTabContent);
			}else {
				tweetsTab.className = "selected";
				keywordsTab.className = "";
				VIRALOGY_W1_xmlhttpPost("<?php echo get_option('siteurl') . "/index.php" ?>","Viralogy_W1_action=statuses&count=<?php echo (get_option('Viralogy_W1_number_of_tweets')*2) ?>", VIRALOGY_W1_updateTweetTabContent);
			}
		}

		function VIRALOGY_W1_updateTweetTabContent(resp) {
			var content = document.getElementById('VIRALOGY_W1_content');
			var header = document.getElementById('VIRALOGY_W1_header');
			var respObj = eval("(" + resp + ")");
			if(respObj.error) {
				resp = "<br><br>Woah!<br>We're a little overloaded at the moment";
				content.innerHTML = "<div id='VIRALOGY_W1_content_body'>" + resp + "</div>";
				return;
			}
			if(header.innerHTML=='' && respObj && respObj.length > 0) {
				var screenName = respObj[0].user.screen_name;
				var followers = respObj[0].user.followers_count;
				var profileImage = respObj[0].user.profile_image_url;
				var s = "";
				s = "<div id='VIRALOGY_W1_twitterProfile'>";
				s+= "<img onclick='window.open(\"http://twitter.com/" + screenName + "\")' src='" + profileImage + "'>";
				s+= "<div>";
				s+= "<span style='cursor:pointer' onclick='window.open(\"http://twitter.com/" + screenName + "\")'>@" + screenName + "</span><br>";
				//s+= "Is a Rock Star<br>";
				s+= "Followers: " + followers;
				s+= "</div>";
				s+= "</div>";
				header.innerHTML = s;
			}
			var resp = "";
			var count = 0;
			for(var i = 0; i < respObj.length; i++) {
				if(count > <?php echo get_option('Viralogy_W1_number_of_tweets') ?>) {
					//we have to do this because we have to filter the # of replies that come back from twitter
					//currently we request 2x amount desired in an attempt to meet the requirement
					break;
				}
				if(<?php echo (get_option('Viralogy_W1_hide_replies') ? "false" : "true") ?> || respObj[i].text.indexOf("@") != 0) {
					resp+= "<div class='VIRALOGY_W1_twitter_tweet'>";
					resp+= respObj[i].text;
					resp+= "<div class='VIRALOGY_W1_twitter_info'><a href='LINK://twitter.com/" + respObj[i].user.screen_name + "/status/" + respObj[i].id + "' target='_blank'>" + respObj[i].user.screen_name + "</a> " + respObj[i].created_at +"</div>";
					resp+= "</div>\n";
					count++;
				}
			}
			resp = resp.replace(/http([^\s<>]+)/gi,"<a class='VIRALOGY_W1_twitter_link' target='_blank' href='http$1'>http$1</a>");	//highlight links
			resp = resp.replace(/@(\w*)/,"@<a class='VIRALOGY_W1_twitter_reply' target='_blank' href='http://twitter.com/$1'>$1</a>");	//highlight usernames
			resp = resp.replace(/LINK/g,"http");
			
			content.innerHTML = "<div id='VIRALOGY_W1_content_body'>" + resp + "</div>";
			content.innerHTML+= "<div id='VIRALOGY_W1_viralogy_up_arrow' onmouseout='VIRALOGY_W1_stopScroll()' onmouseover='VIRALOGY_W1_startScroll(\"VIRALOGY_W1_content_body\",-10)'></div>";
			content.innerHTML+= "<div id='VIRALOGY_W1_viralogy_down_arrow' onmouseout='VIRALOGY_W1_stopScroll()' onmouseover='VIRALOGY_W1_startScroll(\"VIRALOGY_W1_content_body\",10)'></div>";
		}

		function VIRALOGY_W1_updateKeywordTabContent(resp) {
			var content = document.getElementById('VIRALOGY_W1_content');
			var respObj = eval("(" + resp + ")");
			if(respObj.error) {
				resp = "<br><br>Woah!<br>We're a little overloaded at the moment";
				content.innerHTML = "<div id='VIRALOGY_W1_content_body'>" + resp + "</div>";
				return;
			}
			var resp = "";
			for(var i = 0; i < respObj.results.length; i++) {
				resp+= "<div class='VIRALOGY_W1_twitter_tweet'>";
				resp+= respObj.results[i].text;
				resp+= "<div class='VIRALOGY_W1_twitter_info'><a href='LINK://twitter.com/" + respObj.results[i].from_user + "/status/" + respObj.results[i].id + "' target='_blank'>" + respObj.results[i].from_user + "</a> " + respObj.results[i].created_at +"</div>";
				resp+= "</div>\n";
			}
			resp = resp.replace(/http([^\s<>]+)/gi,"<a class='VIRALOGY_W1_twitter_link' target='_blank' href='http$1'>http$1</a>");	//highlight links
			resp = resp.replace(/@(\w*)/gi,"@<a class='VIRALOGY_W1_twitter_reply' target='_blank' href='http://twitter.com/$1'>$1</a>");	//highlight usernames
			resp = resp.replace(/LINK/g,"http");

			content.innerHTML = "<div style='margin:5px;'>Monitoring <span style='font-size:10px'><?php echo str_replace('"',"'",preg_replace("/([\w#]+)/e","'<a target=\"_blank\" href=\"http://twitter.com/search?q='.urlencode('\\1').'\">$1</a>'",get_option('Viralogy_W1_tags'))) ?></span></div>";
			content.innerHTML+= "<div id='VIRALOGY_W1_content_body'>" + resp + "</div>";
			content.innerHTML+= "<div id='VIRALOGY_W1_viralogy_up_arrow' onmouseout='VIRALOGY_W1_stopScroll()' onmouseover='VIRALOGY_W1_startScroll(\"VIRALOGY_W1_content_body\",-10)'></div>";
			content.innerHTML+= "<div id='VIRALOGY_W1_viralogy_down_arrow' onmouseout='VIRALOGY_W1_stopScroll()' onmouseover='VIRALOGY_W1_startScroll(\"VIRALOGY_W1_content_body\",10)'></div>";
		}
		
		var VIRALOGY_W1_scrollInterval = null;
		var VIRALOGY_W1_scrollElem = null;
		var VIRALOGY_W1_scrollStep = null;
		function VIRALOGY_W1_startScroll(el,step) {
			VIRALOGY_W1_stopScroll();
			VIRALOGY_W1_scrollElem = document.getElementById(el);
			VIRALOGY_W1_scrollStep = step;
			VIRALOGY_W1_scrollElem.scrollTop+= VIRALOGY_W1_scrollStep;
			VIRALOGY_W1_scrollInterval = setInterval("VIRALOGY_W1_scrollElem.scrollTop+= VIRALOGY_W1_scrollStep","75");
		}
		function VIRALOGY_W1_stopScroll() {
			if(VIRALOGY_W1_scrollInterval != null)
				clearInterval(VIRALOGY_W1_scrollInterval);
			VIRALOGY_W1_scrollInterval = null;
		}
		
		function VIRALOGY_W1_xmlhttpPost(strURL,query,callback) {
			var xmlHttpReq = false;
			var self = this;
			// Mozilla/Safari
			if (window.XMLHttpRequest) {
				self.xmlHttpReq = new XMLHttpRequest();
			}
			// IE
			else if (window.ActiveXObject) {
				self.xmlHttpReq = new ActiveXObject("Microsoft.XMLHTTP");
			}
			self.xmlHttpReq.open('POST', strURL, true);
			self.xmlHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			self.xmlHttpReq.onreadystatechange = function() {
				if (self.xmlHttpReq.readyState == 4) {
					callback(self.xmlHttpReq.responseText);
				}
			}
			self.xmlHttpReq.send(query);
		}

	</script>
	<div id='VIRALOGY_W1_container'>
		<div id='VIRALOGY_W1_header'></div>
		<div id='VIRALOGY_W1_tabs'>
			<div id='VIRALOGY_W1_tweets_tab' style='left:10px' class='selected' onclick="VIRALOGY_W1_switchTabs(this.id)">Tweets</div>
			<div id='VIRALOGY_W1_keywords_tab' style='left:95px' onclick="VIRALOGY_W1_switchTabs(this.id)">
			<?php 
				$tags=get_option('Viralogy_W1_tags');
				if(strpos($tags,",") !== false)
					$t = substr($tags,0,strpos($tags,","));
				else
					$t = strlen($tags) > 8 ? substr($tags,0,8) . "..." : $tags;
				echo $t;
			?>
			</div>
		</div>
		<div id='VIRALOGY_W1_content'></div>
		<a id='VIRALOGY_W1_viralogy_logo' href='<?php echo VIRALOGY_W1_URL ?>' target='_blank' title='Viralogy'></a>
	</div>
	<script type='text/javascript'>VIRALOGY_W1_switchTabs()</script>

<?php
	Viralogy_LoadTracker();	
}


///////////////*  Menu *///////////////////
function Viralogy_W1_ShowAdminMenu() {
    // Add a new submenu under Options:
	if (current_user_can('manage_options')) {
		add_options_page(VIRALOGY_W1_NAME, VIRALOGY_W1_NAME, 'administrator', basename(__FILE__), 'Viralogy_W1_ShowOptions');
	}
}

// displays the page content for the Test Options submenu
function Viralogy_W1_ShowOptions() {

    // Read in existing option value from database
    $twitter_username = get_option('Viralogy_W1_twitter_username');
    $twitter_password = get_option('Viralogy_W1_twitter_password');
    $number_of_tweets = get_option('Viralogy_W1_number_of_tweets');
    $number_of_keyword_results = get_option('Viralogy_W1_number_of_keyword_results');
    $tags = get_option('Viralogy_W1_tags');
    $hide_replies = get_option('Viralogy_W1_hide_replies');

    if(isset($_POST['twitter_username'])) {
	
        // Read their posted value
        $twitter_username = $_POST['twitter_username'];
        $twitter_password = $_POST['twitter_password'];
        $number_of_tweets = $_POST['number_of_tweets'];
        $number_of_keyword_results = $_POST['number_of_keyword_results'];
        $tags = $_POST['tags'];
        $hide_replies = $_POST['hide_replies'];
		
        // Save the posted value in the database
        update_option('Viralogy_W1_twitter_username', $twitter_username );
        update_option('Viralogy_W1_twitter_password', $twitter_password );
        update_option('Viralogy_W1_number_of_tweets', $number_of_tweets );
        update_option('Viralogy_W1_number_of_keyword_results', $number_of_keyword_results );
        update_option('Viralogy_W1_tags', $tags );
        update_option('Viralogy_W1_hide_replies', $hide_replies);		

        // Put an options updated message on the screen
        echo "<div class='updated'><p><strong>";
        _e('Options saved.', 'mt_trans_domain' );
        echo "</strong></p></div>";
    }

    echo '<div class="wrap">';
    echo "<h2>" . __( VIRALOGY_W1_NAME . ' Options', 'mt_trans_domain' ) . "</h2>";
    ?>
        <style type='text/css'>
        label {
          display:block;float:left;width:250px;margin-top:2px;
        }
        </style>

	<form name="Viralogy_W1_form1" method="post" action="">

	<p><label style='width:150px'><?php _e("Twitter Username:", 'mt_trans_domain' ); ?>  </label>
	<input type="text" name="twitter_username" value="<?php echo $twitter_username; ?>" size="20">
	<p><label style='width:150px'><?php _e("Twitter Password:", 'mt_trans_domain' ); ?>  </label>
	<input type="password" name="twitter_password" value="<?php echo $twitter_password; ?>" size="20">
	<hr />
	<p><label style='width:180px'><?php _e("Number of Tweets to Show:", 'mt_trans_domain' ); ?>  </label>
	<input type="text" name="number_of_tweets" value="<?php echo $number_of_tweets; ?>" size="3">
	<p><label style='width:180px'><?php _e("Hide my @replies:", 'mt_trans_domain' ); ?> </label>
	<input type="checkbox" name="hide_replies" "<?php echo ($hide_replies ? 'checked' : '') ?>>
	<hr />
	<p><label><?php _e("Comma-separated list of Keywords:", 'mt_trans_domain' ); ?>  </label>
	<input type="text" name="tags" value="<?php echo $tags; ?>" size="30">
	<p><label><?php _e("Number of Keyword Results to Show:", 'mt_trans_domain' ); ?>  </label>
	<input type="text" name="number_of_keyword_results" value="<?php echo $number_of_keyword_results; ?>" size="3">


	<p class="submit">
	<input type="submit" name="Submit" value="<?php _e('Update Options', 'mt_trans_domain' ) ?>" />
	</p>

	</form>
	</div>

	<div>
	<p>
		If you don't have a widget enabled blog, you can add the following code wherever you want the widget to appear by using the Design > Theme Editor section of Wordpress: &lt;?php  Viralogy&#95;W1&#95;LoadWidget() ?&gt;
	</p>
	</div>

<?php } 

function Viralogy_W1_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if ($file == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', 'Viralogy-Twitter-Sidebar').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
?>
