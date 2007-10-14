<?php
/*
Plugin Name: Who Sees Ads ?
Plugin URI: http://planetozh.com/blog/my-projects/wordpress-plugin-who-sees-ads-control-adsense-display/
Description: Manage your Ads. Decide under when circumstances to display them. Make more money.
Version: 1.3
Author: Ozh
Author URI: http://planetozh.com/
*/

/* Release History
   1.0	 Initial release. Won 4th spot at WordPress Plugin Competition 2007 \o/
   1.01	 fixed: small glitch with PHP < 5.x preventing a cookie to be set (thanks Andrew Flusche)
   1.2	 improved: overall security against utterly improbable XSS attempt
		 added: 'Logged in users' as a context condition
		 added: 'Between 2 dates' as a context condition
		 added: 'Number of views' as a context condition
		 added: 'Fall back to another context' as a context condition
   1.2.1 fixed: javascript bug with WordPress 2.3
   1.2.2 added: support for WP-Contact-Form's terrible & malformed code :P
   1.3	 added: support for external custom option file
		 added: support for Widgets (code heavily based on Denis de Bernardy's work & suggestions)
		 added: smart support for WP-Cache plugin (simple yet efficient idea from Denis de Bernardy)
		 added: 'Number of views by a particular visitor' (suggestion and code by Paula Fugaro)
		 fixed: smarter referral search engine patterns (thanks to XmasB)
		 fixed: conflict between WSA and ShittyMCE Editor
*/

$wp_ozh_wsa['iknowphp'] = true;
	// if set to true, enables advanced conditional tests.
	// Requires knowledge of PHP and your definitive agreement of your being ON YOUR OWN
	// regarding how you'll use this and what you'll make of it. In other words: no support, warranty,
	// responsability, mercy, answers or anything from me if this is set to 'true'.

// Include personal prefs, if any
if (file_exists(dirname(__FILE__).'/my_options.php')) include(dirname(__FILE__).'/my_options.php');
	
/**************** DO NOT EDIT, UNLESS YPN OR GOOGLE TOS CHANGE ******************/

// Adsense: https://www.google.com/adsense/support/bin/answer.py?answer=48182#pla
// Max 3 ad units
define('OZH_WSA_MAX_GOOGLE_AD',3);
// Max 2 search boxes
define('OZH_WSA_MAX_GOOGLE_SEARCH',2);
// Max 3 link units
define('OZH_WSA_MAX_GOOGLE_LINKS',3);
// Max 3 referral units
define('OZH_WSA_MAX_GOOGLE_REF',3);

// YPN: https://publisher.yahoo.com/legal/prog_policy.php
// Max 3 ad units
define('OZH_WSA_MAX_YPN_AD',3);


/**************** DO NOT EDIT ******************/

if (isset($wp_ozh_wsa['my_iknowphp']))
	$wp_ozh_wsa['iknowphp'] = $wp_ozh_wsa['my_iknowphp'];

// Init some stuff
$wp_ozh_wsa['path'] = wp_ozh_wsa_plugin_basename(__FILE__);
$wp_ozh_wsa['optionname'] = 'ozh_wsa';

// Populate the array of possible conditions
if (isset($wp_ozh_wsa['my_conditions'])) {
	$wp_ozh_wsa['conditions'] = $wp_ozh_wsa['my_conditions'];
} else {
	$wp_ozh_wsa['conditions'] = array ('fromSE', 'regular', 'olderthan', 'logged', 'date', 'numviews', 'readerviews', 'fallback', 'any',);
}
if ($wp_ozh_wsa['iknowphp']) {
	$wp_ozh_wsa['conditions'][] = 'on';
	$wp_ozh_wsa['conditions'][] = 'noton';
}


/************** MAIN FUNCTIONS ******************/

// Alias
function ozh_wsa($context) {
	wp_ozh_wsa($context);
}

// The function you'll use.
function wp_ozh_wsa($context, $echo = true) {
	global $wp_ozh_wsa;
	
	if (!isset($wp_ozh_wsa['my_wp-cache']) or $wp_ozh_wsa['my_wp-cache'] !== false) {
		$do_wpcache = $echo;
	}

	// Wrapper for WP-Cache
	if ($do_wpcache) echo '<!--mfunc wp_ozh_wsa(\'' . $context . '\') -->';
	wp_ozh_wsa_main($context, $echo);
	if ($do_wpcache) echo '<!--/mfunc-->';
}


// The main function. Give it a context name, it'll echo the corresponding ad if applicable.
function wp_ozh_wsa_main($context,$echo = true) {
	global $wp_ozh_wsa;
	
	// Default strings printed when no ad is shown
	$notdisplayed = "<!-- WSA: rules for context '$context' said: don't show ad -->";
	$notapplied = "<!-- WSA: rules for context '$context' did not apply -->";
	$notfound = "<!-- WSA: context '$context' not found -->";
	$noton404 = "<!-- WSA: ad in context $context not shown: this is a 404 url -->";
	$notmax = "<!-- WSA: ad in context $context not shown: too many ads -->";

	// get rules & ad code for $context
	$rules = $wp_ozh_wsa['contexts'][$context]['rules'];
	$code = $wp_ozh_wsa['contexts'][$context]['adcode'];
	
	// wrong context name ? 
	if (empty($rules)) {
		if ($echo) {
			echo $notfound;
			return $notfound;
		} else {
			return $notfound;
		}
	}
	
	// get ad type, 'google', 'ypn' or false
	$google_ypn = wp_ozh_wsa_is_google_ypn_ad($code);
	
	// Is this a 404 or a preview page
	// If so, per Adsense and YPN TOS , don't display the ads.
	if ( is_preview() and $$google_ypn ) {
		// On preview pages, display a placeholder
		return wp_ozh_wsa_fakegooglead(wp_ozh_wsa_googlead_dimensions($code),wp_ozh_wsa_is_google_ypn_ad($code),'preview',$echo);
	}
	if ( is_404() and $$google_ypn ) {
		// On 404 pages, display nothing
		if ($echo) {
			echo $noton404;
			return $noton404;
		} else {
			return $noton404;
		}
	}
	
	// Hide Adsense & YPN when maximum allowed number of ads has been exceeded
	if (
		( $wp_ozh_wsa['ypn_count']  >= OZH_WSA_MAX_YPN_AD ) or
		( $wp_ozh_wsa['google_count'] ['ad'] >= OZH_WSA_MAX_GOOGLE_AD ) or
		( $wp_ozh_wsa['google_count'] ['search'] >= OZH_WSA_MAX_GOOGLE_SEARCH ) or
		( $wp_ozh_wsa['google_count'] ['links'] >= OZH_WSA_MAX_GOOGLE_LINKS ) or
		( ( $wp_ozh_wsa['google_count'] ['reftext'] + $wp_ozh_wsa['google_count'] ['refimage'] ) >= OZH_WSA_MAX_GOOGLE_REF )		
	) {
		// we've had enough
		if ($echo) {
			echo $notmax;
			return $notmax;
		} else {
			return $notmax;
		}
	}
	
	// Hide Adsense & YPN when viewed by admin ?
	if ( wp_ozh_wsa_is_admin() and ($wp_ozh_wsa['adsense_safety'] == 'on') and $google_ypn ) {
		// Display a placeholder
		return wp_ozh_wsa_fakegooglead(wp_ozh_wsa_googlead_dimensions($code),wp_ozh_wsa_is_google_ypn_ad($code),'admin',$echo);
	}
	
	// Now check each rule for this context
	foreach($rules as $rule) {
		// test if rule is matched
		$test = wp_ozh_wsa_testrule($rule['condition'],$rule['parameter'],$context);
		
		if ($test === true) {
			// Rule is matched: should we display or not ?
			if ($rule['display'] == 'true') {
				// We're displaying!
				
				// Google or YPN ?
				if ($google_ypn == 'google') {
					// Keep track of how many Adsense of each type we've already displayed so far
					$google_type = wp_ozh_wsa_google_ad_type($code);
					$wp_ozh_wsa['google_count'][$google_type] += 1;		
				} elseif ($google_ypn == 'ypn') {
					// Keep track of how many YPN ads we've already displayed so far
					$wp_ozh_wsa['ypn_count'] += 1;
				}

				// Adsense code ? Check for Adsense Manager or Adsense Deluxe plugins
				if ( $google_ypn == 'google' and (function_exists('adsense_deluxe_ads') or function_exists('adsensem_ad')) ) {
					// Those plugins might handle the echoing
					return wp_ozh_wsa_adsenseplugins($code,$echo);
					
				// not Adsense or no plugin: just display the code
				} else {
					if ($echo) {
						echo $code;
						return $code;
					} else {
						return $code;
					}
				}
			
			} else {
				// We've been explicitely told not to display
				if ($echo) {
					echo $notdisplayed;
					return $notdisplayed;
				} else {
					return $notdisplayed;
				}
			}
		}
	}
	
	// Up to this point means we've had rules, but no one matched
	if ($echo) {
		echo $notapplied;
		return $notapplied;
	} else {
		return $notapplied;
	}
}

// Parses text (content of post or page) to replace any <!--ozh_wsa:myad--> with the corresponding ad, if applicable
function wp_ozh_wsa_filter($text) {
	$text = preg_replace_callback('/<!-- ?(wp_)?(ozh_)?wsa ?: ?([^>]+)? ?-->/', 'wp_ozh_wsa_filter_callback', $text);
	return $text;
}

function wp_ozh_wsa_filter_callback($in) {
	$context = trim($in[3]);
	return wp_ozh_wsa($context,false);
}

// Test rule, return boolean
function wp_ozh_wsa_testrule($condition, $param, $context = '') {
	switch($condition) {
	// core rules
	case 'fromSE':
		return wp_ozh_wsa_is_fromsearchengine();
		break;
	case 'regular':
		return wp_ozh_wsa_regularvisitor();
		break;
	case 'olderthan':
		return wp_ozh_wsa_olderthan($param);
		break;
	case 'logged':
		return wp_ozh_wsa_loggedvisitor();
		break;
	case 'date':
		return wp_ozh_wsa_testdate($param);
		break;
	case 'numviews':
		return wp_ozh_wsa_numview($param, $context);
		break;
	case 'readerviews':
		return wp_ozh_wsa_readerview($param, $context);
		break;
	case 'fallback':
		return wp_ozh_wsa($param);
		break;
	case 'any':
		return true;
		break;
	// optional rules
	case 'on':
		return eval("return ($param) ;");
		break;
	case 'noton':
		return eval("return (!($param)) ;");
		break;
	// oops ?
	default:
		return ("Unknown condition used in plugin Who Sees Ads. Condition used was: <b>$condition</b>"); // should never happen anyway
	}
}

// Uses 3rd party plugins if applicable to display an Adsense ad
function wp_ozh_wsa_adsenseplugins($code,$echo = true) {
	// <!--adsense#name--> or <!--adsense--> for both Adsense Manager & Adsense Deluxe
	// adsensem_ad('name') or adsensem_ad() for Adsense Manager
	// adsense_deluxe_ads('my_ad_unit') or adsense_deluxe_ads() for Adsense Deluxe
	
	$plugin = '';
	
	// case 1: <!--adsense#name--> or <!--adsense-->
	if (preg_match('/^<!--adsense(#[^- ]+)?-->$/',$code, $matches)) {
		if ($matches[1]) {
			// we have a parameter like <!--adsense#myad-->
			$ad = str_replace('#','',$matches[1]);
		} else {
			// just <!--adsense-->
			$ad = '';
		}
		
		// Pass this to one of the plugins. Wild guess: one shouldn't have both plugin activated!
		if (function_exists('adsense_deluxe_ads')) {
			// Adsense Deluxe will do the job
			$plugin = 'adsense_deluxe_ads';
		} else {
			// Adsense Manager will make it
			$plugin = 'adsensem_ad';
		}
		
	// case 2: adsense_deluxe_ads('my_ad_unit') or adsense_deluxe_ads() for Adsense Deluxe
	} elseif (preg_match('/^adsense_deluxe_ads\(([\'"]([^\'"]+)[\'"])?\)$/',$code, $matches)) {
		if ($matches[2]) {
			// we have a parameter like adsense_deluxe_ads('my_ad_unit')
			$ad = $matches[2];
		} else {
			// just adsense_deluxe_ads()
			$ad = '';
		}
		$plugin = 'adsense_deluxe_ads';
	
	// case 3: adsensem_ad('name') or adsensem_ad() for Adsense Manager
	} elseif (preg_match('/^adsensem_ad\(([\'"]([^\'"]+)[\'"])?\)$/',$code, $matches)) {
		if ($matches[2]) {
			// we have a parameter like adsensem_ad('my_ad_unit')
			$ad = $matches[2];
		} else {
			// just adsensem_ad()
			$ad = '';
		}
		$plugin = 'adsensem_ad';
	}
	
	if ($plugin) {
		// case 1, 2 or 3
		$return = wp_ozh_wsa_adsenseplugins_get($plugin, $ad, $echo);
	} else {
		// otherwise: not something to be handled by a plugin, so just return unmodified code
		$return = $code;
	}
	
	if ($echo) {
		echo $return;
	} else {
		return $return;
	}
}

// Wrapper function for 3rd party plugins.
function wp_ozh_wsa_adsenseplugins_get($plugin, $ad, $echo) {
	if ($echo) {
		call_user_func($plugin, $ad);
	} else {
		ob_start();
		call_user_func($plugin, $ad);
		$catch = ob_get_contents();
		ob_end_clean();
		return $catch;
	}
}


/************** ADMIN FUNCTIONS ******************/

// The 'onload' stuff
function wp_ozh_wsa_init() {
	wp_ozh_wsa_readoptions();
	if (!is_admin()) {
		wp_ozh_wsa_setcookie();
	}	
}

// Add a page to the Admin area
function wp_ozh_wsa_addmenu() {
	global $wp_ozh_wsa, $plugin_page, $pagenow;
	if (is_plugin_page() && strpos($plugin_page, 'whoseesads') !== false) {
		// add Scriptaculous & co only on the plugin page
		wp_enqueue_script('scriptaculous');
	}
	require_once(ABSPATH.'wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/wp_ozh_whoseesads_admin.php');

	// Add submenu page where applicable 
	if (isset($wp_ozh_wsa['my_menu'])) {
		add_submenu_page($wp_ozh_wsa['my_menu'], 'Who Sees Ads ?', 'Who Sees Ads', 10, wp_ozh_wsa_plugin_basename(__FILE__), 'wp_ozh_wsa_addmenupage');
		$wp_ozh_wsa['admin_url'] = get_option('siteurl') . '/wp-admin/'.$wp_ozh_wsa['my_menu'].'?page=' . wp_ozh_wsa_plugin_basename(__FILE__);
	} else {
		add_options_page('Who Sees Ads ?', 'Who Sees Ads', 10, wp_ozh_wsa_plugin_basename(__FILE__), 'wp_ozh_wsa_addmenupage');
		$wp_ozh_wsa['admin_url'] = get_option('siteurl') . '/wp-admin/options-general.php?page=' . wp_ozh_wsa_plugin_basename(__FILE__);
	}
	
}

// Save options
function wp_ozh_wsa_saveoptions() {
	global $wp_ozh_wsa;

	// Of course and as usual with Clean Plugins(tm) we're storing everything into ONE database entry.
	$options = array(
		'contexts' => $wp_ozh_wsa['contexts'],						// array of context rules
		'old' => intval($wp_ozh_wsa['old']),						// default value for "old post"
		'regular' => array_map('intval',$wp_ozh_wsa['regular']),	// array (how_many_visits, over_how_many_days)
		'help' => $wp_ozh_wsa['help'],								// boolean for help displaying
		'adsense_safety' => $wp_ozh_wsa['adsense_safety'], 			// 'on' or 'off' for Admin Adsense Safety feature
		'date_format' => $wp_ozh_wsa['date_format'],				// 'dmy' or 'mdy'
	);
	
	if (get_option($wp_ozh_wsa['optionname']) === false) {
		// Option doesn't exist in option table : create it
		add_option($wp_ozh_wsa['optionname'],$options, "Ozh's Who Sees Ads options");
	} else {
		// Option found : update it
		update_option($wp_ozh_wsa['optionname'],$options);
	}
}

// Read options
function wp_ozh_wsa_readoptions() {
	global $wp_ozh_wsa;

	$options = get_option($wp_ozh_wsa['optionname']);
	
	if ($options === false) {
		// Options not found : default values
		$wp_ozh_wsa['contexts'] = array();
		$wp_ozh_wsa['old'] = 20;
		$wp_ozh_wsa['regular'] = array(2,10);
		$wp_ozh_wsa['help'] = true;
		$wp_ozh_wsa['adsense_safety']  = false;
		$wp_ozh_wsa['date_format'] = 'dmy';
	} else {
		// Options found. We still check for empty values, though
		$wp_ozh_wsa['contexts'] = $options['contexts'];
		if (!isset($wp_ozh_wsa['contexts']) or empty($wp_ozh_wsa['contexts']))
			$wp_ozh_wsa['contexts'] = array();
		$wp_ozh_wsa['old'] = $options['old'];
		if (!isset($wp_ozh_wsa['old']) or empty($wp_ozh_wsa['old']))
			$wp_ozh_wsa['old'] = 20;
		$wp_ozh_wsa['regular'] = $options['regular'];
		if (!isset($wp_ozh_wsa['regular']) or empty($wp_ozh_wsa['regular']))
			$wp_ozh_wsa['regular'] = array(2,10);
		$wp_ozh_wsa['help'] = $options['help'];
		$wp_ozh_wsa['adsense_safety'] = $options['adsense_safety'];
		$wp_ozh_wsa['date_format'] = $options['date_format'];
	}
	
	// strip slashes where needed
	foreach($wp_ozh_wsa['contexts'] as $context_name=>$context_array) {
		// rules:
		foreach ($context_array['rules'] as $i=>$array_rules) {
			// remove advanced rules when 'iknowphp' is false, stripslashes otherwise
			if (!$wp_ozh_wsa['iknowphp'] && ($i['condition'] == 'on' or $i['condition'] == 'noton') ) {
				unset($wp_ozh_wsa['contexts'][$context_name]['rules'][$i]);
			} else {
				if (is_array($array_rules['parameter'])) {
					$param = array_map('stripslashes',$array_rules['parameter']);
				} else {
					$param = stripslashes($array_rules['parameter']);
				}
				$wp_ozh_wsa['contexts'][$context_name]['rules'][$i]['parameter'] = $param;
			}
		}
		// adcode:
		$wp_ozh_wsa['contexts'][$context_name]['adcode'] = stripslashes($context_array['adcode']);
		// comment:
		$wp_ozh_wsa['contexts'][$context_name]['comment'] = stripslashes($context_array['comment']);
		
	}
	
	
}


/************** INTERNAL FUNCTIONS ******************/

// Built in function plugin_basename() is broken for Win32 installs, as of WP 2.2 -- the following is added to WordPress core in 2.3
function wp_ozh_wsa_plugin_basename($file) {
	$file = str_replace('\\','/',$file); // sanitize for Win32 installs
	$file = preg_replace('|/+|','/', $file); // remove any duplicate slash
	$file = preg_replace('|^.*/wp-content/plugins/|','',$file); // get relative path from plugin dir
	return $file;
}

// Checks if a post is older than a given limit. Returns boolean
function wp_ozh_wsa_olderthan($limit) {
	$ageunix = time() - get_the_time('U');
	$days_old = floor($ageunix/(24*60*60));
	if ($days_old > $limit) {
		return true;
	} else {
		return false;
	}
}

// Checks if a visitor's referrer shows a search engine. Returns boolean
function wp_ozh_wsa_is_fromsearchengine() {
	$ref = $_SERVER['HTTP_REFERER'];
	if (isset($wp_ozh_wsa['my_search_engines'])) {
		$SE = $wp_ozh_wsa['my_search_engines'];
	} else {
		$SE = array('/search?', 'images.google.', 'web.info.com', 'search.', 'del.icio.us/search',
		'soso.com', '/search/', '.yahoo.', 
		);
	}
	foreach ($SE as $url) {
		if (strpos($ref,$url)!==false) return true;
	}
	return false;	
}


// Checks if a visitor is registered & logged in. Returns boolean
function wp_ozh_wsa_loggedvisitor() {
	return is_user_logged_in();
}

// Checks if a context had less than $views. Returns boolean
function wp_ozh_wsa_numview($views, $context) {
	global $wp_ozh_wsa;
	
	if ($wp_ozh_wsa['contexts'][$context]['views'] < $views) {
		$wp_ozh_wsa['contexts'][$context]['views']++;
		wp_ozh_wsa_saveoptions();
		return true;
	} else {
		return false;
	}
}

// Checks if current visitor had less than $views of context. Returns boolean
// Suggestion and code by Paula Fugaro (http://ezexpertwebtools.com/) 
function wp_ozh_wsa_readerview($contextviews, $context) {

	if (isset($_COOKIE['wp_ozh_wsa_'.$context])) {
		$readerviews = $_COOKIE['wp_ozh_wsa_'.$context];
	} else {
		$readerviews = 0;
	}
	
	if ($readerviews < $contextviews) {
		$readerviews++;
        $url = parse_url(get_option('home'));
        $path = $url['path'].'/';
        // Must set cookie using Javascript since 
        // output has already been sent to the browser
        echo "<script type=\"text/javascript\">
                <!--
                function FixCookieDate (date) {
                  var base = new Date(0);
                  var skew = base.getTime(); // dawn of (Unix) time - should be 0
                  if (skew > 0)  // Except on the Mac - ahead of its time
                    date.setTime (date.getTime() - skew);
                }
                
                var expdate = new Date ();
                FixCookieDate (expdate);
                expdate.setTime (expdate.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 year from now 
                
                document.cookie = \"wp_ozh_wsa_$context\" + \"=\" + escape ($readerviews) + \"; expires=\" + expdate.toGMTString() + \"; path=\" + \"$path\";
                //-->
                </script>
                ";
		return true;
	} else {
		return false;
	}
}

// Checks if today's date is between a date interval. Returns boolean
// @input $date array('before'=>timestamp, 'after'=>timestamp)
function wp_ozh_wsa_testdate($date) {
	global $wp_ozh_wsa;
	$now = time();
	return ( $now > $date['before'] && $now <= $date['after']);
}


// Checks if a visitor is a regular visitor. Returns boolean
function wp_ozh_wsa_regularvisitor() {
	global $wp_ozh_wsa;
	
	list ($visits, $last) = $wp_ozh_wsa['cookie'];
	list ($reg_visits, $reg_days) = $wp_ozh_wsa['regular'];

	$time = time();
	$diff = $time - $last;

	if ( ($visits >= $reg_visits) and ($diff <= (86400 * $reg_days)) ) {
		return true;
	} else {
		return false;
	}
	
}

// Write cookie
function wp_ozh_wsa_setcookie() {
	global $wp_ozh_wsa;
	if (isset($_COOKIE['wp_ozh_wsa_visits'])) {
		$visits = $_COOKIE['wp_ozh_wsa_visits'] + 1;
	} else {
		$visits = 1;
	}
	
	if (isset($_COOKIE['wp_ozh_wsa_visit_lasttime'])) {
		$lasttime = $_COOKIE['wp_ozh_wsa_visit_lasttime'];
	} else {
		$lasttime = 0;
	}
	
	$time = time();
	$url = parse_url(get_option('home'));
	setcookie('wp_ozh_wsa_visits', $visits, $time+60*60*24*365, $url['path'] . '/');
	setcookie('wp_ozh_wsa_visit_lasttime', $time, $time+60*60*24*365, $url['path'] . '/');
	$wp_ozh_wsa['cookie'] = array($visits, $lasttime);
}

// Is the code a PHP snippet ? Returns boolean
// UNUSED. Potentially too compromising.
function wp_ozh_wsa_is_php($code) {
	if (preg_match('/^<\?php.*\?>$/s',$code)) return true;
	return false;
}

// Is the code a Google Adsense or a Yahoo Publisher Network ad ? Returns 'google', 'ypn' or false
function wp_ozh_wsa_is_google_ypn_ad($code) {
	if (wp_ozh_wsa_is_google_ad($code)) return 'google';
	if (wp_ozh_wsa_is_ypn_ad($code)) return 'ypn';
	return false;
}

// Is the code a Google Adsense ad ? Returns boolean
function wp_ozh_wsa_is_google_ad($code) {
	$code = strtolower($code);
	// regular javascript ad code
	if (strpos($code,'google_ad_client')!==false) return true;
	// regular javascript google search code
	if (strpos($code,'<!-- SiteSearch Google -->')!==false) return true;
	
	// code used with Adsense Deluxe or Adsense Manager plugin
	if (
		preg_match('/^<!--adsense(#[^- ]+)?-->$/', $code) or
		preg_match('/^adsense_deluxe_ads\(([\'"]([^\'"]+)[\'"])?\)$/', $code) or
		preg_match('/^adsensem_ad\(([\'"]([^\'"]+)[\'"])?\)$/', $code)	
	) return true;
		
	return false;
}

// Returns type of a Google Adsense Ad : 'reftext', 'refimage', 'links', 'search' or 'ad'
function wp_ozh_wsa_google_ad_type($code) {

	if (strpos($code,'<!-- SiteSearch Google -->')!==false)
		return 'search';
	
	// We're looking for the ad format variable:
	// referral text : google_ad_format = "ref_text";
	// referral image: google_ad_format = "110x32_as_rimg";
	// link unit: google_ad_format = "120x90_0ads_al_s";
	// ad unit: google_ad_format = "250x250_as";
	preg_match('/google_ad_format *= *["\']([^"\']+)["\'] *;/',$code,$matches);
	
	$format = strtolower($matches[1]);	
	if ($format == 'ref_text') {
		return 'reftext';
	} elseif (strpos($format,'_rimg') !== false) {
		return 'refimage';
	} elseif (strpos($format,'_al_s') !== false) {
		return 'links';
	} else {
		return 'ad';
	}
}

// Is the code a Yahoo Publisher Network ad ? Returns boolean
function wp_ozh_wsa_is_ypn_ad($code) {
	if (strpos(strtolower($code),'ctxt_ad_partner')!==false) return true;
	return false;
}

// Returns array(width,length) of the dimensions of an Adsense ad
function wp_ozh_wsa_googlead_dimensions($code) {
	preg_match_all('/google_ad_(width|height) *= *([\d]+) *;/',strtolower($code),$matches);
	${$matches[1][0]} = $matches[2][0];
	${$matches[1][1]} = $matches[2][1];
	// weeeeeeee ! How cool was it ? :)
	return array($width,$height);
}

// Returns an appropriate font-size for a given width
function wp_ozh_wsa_fontsize($width,$factor,$max,$min) {
	if (($width / $factor) > $max) {
		$size = $max;
	} elseif ( ($width / $factor) < $min) {
		$size = $min;
	} else {
		$size = ($width / $factor);
	}
	return intval($size);

}

// Prints CSS for fake google ads
function wp_ozh_wsa_fakegooglead_css() {

	global $wp_ozh_wsa;
	if (isset($wp_ozh_wsa['my_fakead-css'])) {
		echo $wp_ozh_wsa['my_fakead-css'] ;
		return;
	}

	$out = '#006E2E';
	$mid = '#008C00';
	$in = '#6BBA70';
	
	echo "
	<style type='text/css'>
	.wsa_ad_out {
	-moz-border-radius:15px;
	word-wrap: break-word;
	background:$out;
	padding:7px;
	text-align:center;
	overflow:hidden;
	}
	.wsa_ad_in {
	-moz-border-radius:15px;
	border:7px solid $mid;
	padding:5px;
	background:$in;
	color:$out;
	}
	.wsa_ad_in p {margin:0}
	.wsa_ad_in .p1 {
	font-family:Verdana,Chicago,Sans-serif;
	}
	.wsa_ad_in .p2 {
	font-family:Verdana,Chicago,Sans-serif;
	}
	.wsa_ad_in a {
	color:$out;
	text-decoration:underline;
	}
	.wsa_ad_center {
	border:1px solid #cfc;
	height:100%;
	-moz-border-radius:15px;
	}
	</style>
	";	
}

// Prints a fake google ad
function wp_ozh_wsa_fakegooglead($dimensions = array(125,125),$type='google',$why='',$echo = true) {
	global $wp_ozh_wsa;
	
	list($width,$height) = $dimensions;
	if ($width=='') $width = 250;
	if ($height=='') $height = 120;
	
	$font = wp_ozh_wsa_fontsize($width,10,22,10).'px';
	$font2 = wp_ozh_wsa_fontsize($width,16,11,8).'px';
	$inheight = ($height-18).'px';
	
	if ($type == 'google') {
		$type = 'Google Adsense';
	} else {
		$type = 'Yahoo Publisher Network';
	}
	
	if ($why == 'preview') {
		$why = 'on preview';
	} else {
		$why = 'when viewed by admin';
	}
	
	if ($wp_ozh_wsa['google_css'] !== true) {
		$wp_ozh_wsa['google_css'] = true;
		wp_ozh_wsa_fakegooglead_css();
	}

	$div = '';
	
	if (isset($wp_ozh_wsa['my_fakead-before']))
		$div .= $wp_ozh_wsa['my_fakead-before'];
		
	if (isset($wp_ozh_wsa['my_fakead'])) {
		$fakead = $wp_ozh_wsa['my_fakead'];
		foreach (array('width', 'height', 'why', 'type', 'font', 'font2', 'inheight') as $item) {
			$fakead = str_replace("%%${item}%%", ${$item}, $fakead);
		}
		$div .= $fakead;
	} else {	
		$div .= "
		<div class='wsa_ad_out' style='width:${width}px;height:${height}px;max-width:100%;max-height:100%;'>
		<div class='wsa_ad_center'>
		<div class='wsa_ad_in' style='height:$inheight;'>
		<p class='p1' style='font-size:$font;'><b>Ad Placeholder</b></p>
		<p class='p2' style='font-size:$font2;'>$type ad disabled ($why) by <a href='http://planetozh.com/blog/my-projects/wordpress-plugin-who-sees-ads-control-adsense-display/'>Who Sees Ads</a>. Regular visitors will see real $type ads.</p>
		</div>
		</div>
		</div>	
		";
	}
	
	if (isset($wp_ozh_wsa['my_fakead-after']))
		$div .= $wp_ozh_wsa['my_fakead-after'];

	if ($echo) {
		echo $div;
	} else {
		return $div;
	}
}

// Determines if viewer is the blog admin. Returns a boolean
function wp_ozh_wsa_is_admin() {
	if (preg_match("/wordpressuser[^=]*=admin/i", $_SERVER["HTTP_COOKIE"])) {
		return TRUE;
	} else {
		return FALSE;
	}
}


/************** WIDGET FUNCTIONS ******************/

// Conditional widget enabling
function wp_ozh_wsa_maybe_widgetize() {
	global $wp_ozh_wsa;
	if (!isset($wp_ozh_wsa['my_menu']) or $wp_ozh_wsa['my_menu'] !== false) {
		$wp_ozh_wsa['do_widgets'] = true;
		add_action('widgets_init', 'wp_ozh_wsa_widgetize');
	}
}

// Create Widgets
function wp_ozh_wsa_widgetize() {
	global $wp_ozh_wsa;
	
	foreach ( array_keys($wp_ozh_wsa['contexts']) as $context ) {
		register_sidebar_widget('Ad: ' . $context,
			create_function(
				'$args',
				'wp_ozh_wsa(\'' . $context . '\', $args);'
				)
			);
		register_widget_control('Ad: ' . $context, 'wp_ozh_wsa_widget_control', 200, 100);
	}
}

// Create Widget (sort of) controls
function wp_ozh_wsa_widget_control() {
	global $wp_ozh_wsa;
	echo 'Configure and set up display rules on the <a href="'.$wp_ozh_wsa['admin_url'].'">Who Sees Ads</a> administration page';
}


/********************************************************/

add_action('plugins_loaded', 'wp_ozh_wsa_init');
add_action('plugins_loaded', 'wp_ozh_wsa_maybe_widgetize');
add_action('admin_menu',     'wp_ozh_wsa_addmenu');
add_action('the_content',    'wp_ozh_wsa_filter');


?>