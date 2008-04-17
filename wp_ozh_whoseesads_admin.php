<?php
/*
This file is part of the Wordpress Plugin "Who Sees Ads" version 2.0
It contains what's needed for the (uber mega sexy) administration menu
See http://planetozh.com/blog/my-projects/wordpress-plugin-who-sees-ads-control-adsense-display/
*/

/************** ADMIN FUNCTIONS ******************/

global $wp_ozh_wsa;

// Print the admin page
function wp_ozh_wsa_addmenupage() {
	global $wp_ozh_wsa;
	
	wp_ozh_wsa_print_css();
	
	if (isset($_POST['whoseesads']) && ($_POST['whoseesads'] == 1) ) wp_ozh_wsa_processforms();
	
	wp_ozh_wsa_print_help();
	wp_ozh_wsa_print_context();
	wp_ozh_wsa_print_duplicate();
	wp_ozh_wsa_print_delete();
	wp_ozh_wsa_print_definitions();
	wp_ozh_wsa_print_javascript();
	wp_ozh_wsa_print_misc();
}

// Add the Quicktag button on the Write/Edit interface
function wp_ozh_wsa_addbutton() {
	// if not in a page with a button toolbar, just return
	if (
		strpos($_SERVER['REQUEST_URI'], 'post.php') === false and
		strpos($_SERVER['REQUEST_URI'], 'post-new.php') === false and
		strpos($_SERVER['REQUEST_URI'], 'page-new.php') === false and
		strpos($_SERVER['REQUEST_URI'], 'page.php') === false
	)
		return;
		
	global $wp_ozh_wsa;
	
	if (isset($wp_ozh_wsa['my_wsa-buttons']) && $wp_ozh_wsa['my_wsa-buttons'] === false)
		return;
	
	switch (count($wp_ozh_wsa['contexts'])) {
	case 0:
		return;

	// Only one context is defined: we'll print a single button
	case 1:
		$keys = array_keys($wp_ozh_wsa['contexts']);
		$context = $keys[0];
		echo <<<JS
		<script type="text/javascript">
		/* <![CDATA[ */
		if(ozh_wsa_Toolbar = document.getElementById("ed_toolbar")) {
			var length = edButtons.length;
			edButtons[length] = new edButton('ozh_wsa', '$context','<!--wsa:$context-->','','');
			ozh_wsa_Toolbar.innerHTML += '<input type="button" value="WSA:$context" onclick="wsa_add_tag(\'$context\',this.id);" class="ed_button" title="Who Sees Ads: add this context" id="ozh_wsa_'+length+'"/>';
		}
JS;
		break;

	// More than one button ? Let's add a dropdown list
	default:
		$buttons = "var length = edButtons.length;\n var wsa_list = new Array();\n";
		$html = "<select id='ozh_wsa_select' class='ed_button' onchange='wsa_select(this)' title='Select context'><option value=''>Who Sees Ads:</option>";
		foreach (array_keys($wp_ozh_wsa['contexts']) as $context) {
			$html .= "<option value='$context'>$context</option>";
			$buttons .= "
			edButtons[length] = new edButton('ozh_wsa_'+length, '$context','<!--wsa:$context-->','','');
			wsa_list['$context'] = length;
			length = length + 1;
			";
		}
		$html .= '</select>';
		echo <<<JS
		<script type="text/javascript">
		/* <![CDATA[ */
		if(ozh_wsa_Toolbar = document.getElementById("ed_toolbar")) {
			$buttons
			ozh_wsa_Toolbar.innerHTML += "$html";
		}
		
JS;
		break;
	}
	
	echo <<<JS
	function wsa_add_tag(context,id) {
		id = id.replace(/ozh_wsa_/,'');
		edInsertTag(edCanvas, id);
	}
	
	function wsa_select(e) {
		edInsertTag(edCanvas, wsa_list[e.value]);
	}
	
	
	/* ]]> */
</script>
JS;
}


/************** FORM PRINTING ******************/

// Main form printing : the Create/Update context form
function wp_ozh_wsa_print_context() {
	global $wp_ozh_wsa;
	
	$help = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/help.gif';
	$help = "<img src='$help' />";
	
	$wp_ozh_wsa['newcontext'] = $new = 'new'.time(); // pseudo-random unique name
	
	$select = "<select name='context_sel' onchange='ozh_wsa_selectcontext()' id='context_sel'>\n";
	$select .= "<option value='$new' selected='selected'>New context...</option>\n";
	foreach($wp_ozh_wsa['contexts'] as $context=>$rules) {
		$select .= "<option value='$context'>$context</option>\n";
	}
	$select .= "</select> <span id='new_toggle'>Name: <input type='text' class='code' id='$new' name='context'></span>\n";

	$newrules = array(
		"$new" => array(
			'code' => '',
			'rules' => array(),
		)
	);
	$wp_ozh_wsa['contexts'] = array_merge ($newrules,$wp_ozh_wsa['contexts']);

	echo '
	<div class="wrap">
	<h2>Edit Context</h2>
	<form method="post" action="" id="ozh_wsa_form_add" onsubmit="return ozh_wsa_check();">';
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo '<input type="hidden" name="action" value="add"/>
	<input type="hidden" name="whoseesads" value="1"/>
	<table border="0" id="wsa_table" class="form-table" cellspacing="5">
	<tbody>
	<tr>
	<th scope="row" valign="top" class="wsa_celltitle">Name of the Context <span class="wsa_helpicon" id="helpicon_name">'; echo $help; echo '</span></th>
	<td valign="top">';
	echo $select;
	echo '<br/>
	<div>
	<span style="color:#99c">Posts:</span> <tt style="color:#669">&lt;!--wsa:<span class="usage_context">context_name</span>--></tt>&nbsp; <span class="wsa_helpicon" id="helpicon_syntax">'; echo $help; echo '</span><br/>
	<span style="color:#99c">PHP:</span> <tt style="color:#669">&lt?php&nbsp;wp_ozh_wsa("<span class="usage_context">context_name</span>");?></tt>
	</div>
	</td>

	</tr>

	<tr>
	<th scope="row" valign="top" class="wsa_celltitle">Possible Rules <span class="wsa_helpicon" id="helpicon_possible">'; echo $help; echo '</span></th>
	<td valign="bottom" class="wsa_cell" id="wsa_active_rules">
	';
	
	// Sortable 1 : possible conditions
	wp_ozh_wsa_print_sortable(1,$new);
		
	echo '</td>
	</tr>
	<tr>
	<th scope="row" valign="top" class="wsa_celltitle">Active Rules <span class="wsa_helpicon" id="helpicon_active">'; echo $help; echo '</span></th>
	<td valign="top" class="wsa_cell">';
	
	// Sortable 2 : active conditions
	wp_ozh_wsa_print_sortable(2,$new);

	echo '
	</tr>
	<tr>
	<th scope="row"  valign="top" class="wsa_celltitle">Ad Code <span class="wsa_helpicon" id="helpicon_code">'; echo $help; echo '</span></th>
	<td valign="top">
	';
	
	wp_ozh_wsa_print_contextcode($new);
	
	if (isset($wp_ozh_wsa['my_rotatecode_separator'])) {
		echo "<span style='color:#aae'>Rotating code separator:</span> <tt style='color:#99c'>${wp_ozh_wsa['my_rotatecode_separator']}</tt>";
	}	

	$ok = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/ok.gif';
	
	echo '</td>
	</tr>
	<tr>
	<th scope="row"  valign="top" class="wsa_celltitle">Optional Comment <span class="wsa_helpicon" id="helpicon_comment">'; echo $help; echo '</span></th>
	<td valign="top">';
	
	wp_ozh_wsa_print_contextcomment($new);
	
	if (isset($wp_ozh_wsa['my_rotatecode_separator'])) {
		$rotatecode = "
		<p>Support for rotating code is enabled. Paste multiple codes using the following separator: <b>${wp_ozh_wsa['my_rotatecode_separator']}</b><br/>
		<b>Example:</b><br/>
		<tt>&lt;img src='banner1.gif' /&gt;<br/>
		${wp_ozh_wsa['my_rotatecode_separator']}<br/>
		&lt;img src='banner2.gif' /&gt;<br/>
		${wp_ozh_wsa['my_rotatecode_separator']}<br/>
		&lt;img src='banner3.gif' /&gt;</tt></p>
		";
	} else {
		$rotatecode = '';
	}
	
	echo '</td>
	</tr>
	<th scope="row">&nbsp;</th>
	<td valign="top">
	<input type="hidden" id="serialize_sortables" name="serialize" />
	<button type="submit" class="wsa_button" /><img src="';echo $ok;echo '" alt="" />Save context &raquo;</button>
	</td>
	</tr>
	</tbody>
	</table>
	</form>
	</div>

	<div id="helpmsg">
	<div class="wsa_helpbox" id="helpbox_syntax" style="display:none">
		<h3>Usage</h3>
		Display your ads using the following syntax:
		<ul>
		<li>Within posts or pages:<br/>
		<strong><tt>&lt;!--wsa:<span class="usage_context">context_name</span>--></tt></strong></li>
		<li>Within your templates (e.g. <em>sidebar.php</em>):<br/>
		<strong><tt>&lt?php wp_ozh_wsa("<span class="usage_context">context_name</span>"); ?></tt></strong></li>
		</ul>
	</div>
	<div class="wsa_helpbox" id="helpbox_name" style="display:none">
		<h3>Name of context</h3>
		<p>Give your context a meaningful name. Use letters, digits or dashes (other characters will be automatically replaced or removed).</p>
		<p>For instance, an Adsense banner on top of your sidebar could be named <em>adsense-sidebar-top</em>.</p>
	</div>
	<div class="wsa_helpbox" id="helpbox_possible" style="display:none">
		<h3>Possible Rules</h3>
		<p>These are all the possible rules you can use to define a context</p>
	</div>
	<div class="wsa_helpbox" id="helpbox_active" style="display:none">
		<h3>Active Rules</h3>
		<p>Drag and order here the rules you want for your context.</p>
		<p>Read them in ascending order, and mentally insert "<em>Otherwise</em>" between each rule to understand the logical scheme.</p>
		<p>The plugin stops on first matching rule. If no rule is matched, no ad is displayed.</p>
	</div>
	<div class="wsa_helpbox" id="helpbox_code" style="display:none">
		<h3>Code</h3>
		<p>Paste the ad for your code, as you would paste it in an HTML or PHP document.</p>
		<p>It can be HTML (like an affiliate banner) or Javascript (like an Adsense ad)</p>';
	echo $rotatecode;
	echo '
	</div>
	<div class="wsa_helpbox" id="helpbox_custom" style="display:none">
		<h3>Advanced Custom Rules</h3>
		<p>You can use PHP expressions as a parameter (with either PHP built-in, WordPress internal or custom functions you have written). The expression must be something you would use in code like:<br/>
		<tt>&lt;?php if (<em>expression</em>) {<em>...</em>} ?></tt></p>
		<p><b>Example</b>: to add a rule to display when <tt>is_home()</tt> returns true; simply write <tt>is_home()</tt> in the textarea</p>
		<p><b>Example</b>: to add a rule to display on home page and 404 errors, write <tt>is_home() or is_404()</tt></p>
		<p>There are 2 possible custom rules:<br/>&nbsp; &nbsp; &middot; <tt>if(<em>expression</em>)</tt><br/>&nbsp; &nbsp; &middot; <tt>if not(<em>expression</em>)</tt></p>
	</div>
	<div class="wsa_helpbox" id="helpbox_adsense_safety" style="display:none">
		<h3>Admin Clicks Safety</h3>
		<p>When enabled, this option will hide Adsense and Yahoo Publisher ads when viewed by blog admin, to prevent any accidental click on your own ads.</p>
	</div>
	<div class="wsa_helpbox" id="helpbox_comment" style="display:none">
		<h3>Optional Comment</h3>
		<p>Feel free to use this area to write anything down. For example: why you made such a rule set.</p>
	</div>
	</div>
	';
}

// Print the intro & wizards
function wp_ozh_wsa_print_help() {
	global $wp_ozh_wsa;
	
	if (!$wp_ozh_wsa['help'] ) return;
	
	$wand = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/wand.gif';

	echo '
	<div class="wrap"><h2>Who Sees Ads ?</h2>
	<p><strong>Who Sees Ads</strong> is an advanced <strong>ad management</strong> plugin that lets you decide who will see your ads, depending on user defined <strong>conditions</strong>. The association of an ad and these conditions is called a <strong>context</strong>: a set of circumstances you define, that will eventually display or not an ad. For instance, you could consider the following criteria: Is the visitor a regular reader? Does this visitor come from a search engine? Is the visitor currently reading an old post, or something fresh?</p>
	<p>Create contexts and visually order rules so that they fit your logic. The ad behavior, <em>Display</em> or <em>Don\'t Display</em>, is determined by the first ruled that is matched. If no rule is matched, nothing displays.</p>';

	echo '<table border="0">';
	
	if (!$wp_ozh_wsa['contexts']['example-sidebar']) {
		echo '
	<tr><td colspan="2">
	<strong>Example</strong>: Let\'s say you have an ad somewhere in your sidebar. You want this ad to be always hidden to regular readers, and displayed for others. This context named <tt>example-sidebar</tt> would have the following rules:
	</td></tr>
	<tr><td><code>&nbsp; if [Regular Reader] then [Don\'t Display];<br/>
	&nbsp; Otherwise: if [Anything] then [Display];</code>
	</td><td>
	<form method="post" name="wizard1" action="">';
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo '<input type="hidden" name="action" value="wizard"/>
	<input type="hidden" name="whoseesads" value="1"/>
	<input type="hidden" name="context_sel_wizard" value="ozh_wsa_wizard"/>
	<input type="hidden" name="context" value="example-sidebar"/>
	<input type="hidden" name="ozh_wsa_wizard_any" value="1"/>
	<input type="hidden" name="ozh_wsa_wizard_any_display" value="true"/>
	<input type="hidden" name="ozh_wsa_wizard_regular" value="1"/>
	<input type="hidden" name="ozh_wsa_wizard_regular_display" value="false"/>
	<input type="hidden" name="ozh_wsa_wizard_adcode" value="Paste here the code (javascript, html banner, affiliate image, etc...) for this ad"/>
	<input type="hidden" name="ozh_wsa_wizard_comment" value="This is an optional comment. Anything that will help you remember what these rules are for, for example."/>
	<input type="hidden" name="serialize" value="ozh_wsa_wizard_2[]=regular&ozh_wsa_wizard_2[]=any"/>
	<button type="submit" class="wsa_button" /><img src="';echo $wand;echo '" alt="" /> Create this Context &raquo</button>
	</form>
	</td></tr>';
	}

	if (!$wp_ozh_wsa['contexts']['example-post-bottom']) {
		echo '
	<tr><td colspan="2">
	<p><strong>Example</strong>: You have an ad right after every post. You want the following: the ad would not display while the post is fresh, but after 15 days, show the ad. But not for regular readers. On top of these rules, if anyone comes to the post from a search engine, disregard any previous rule and always show an ad. Here is how you would define this context <tt>example-post-bottom</tt>:
	</td></tr>
	<tr><td><code>&nbsp; if [from Search Engine] then [Display];<br/>
	&nbsp; Otherwise: if [Regular Reader] then [Don\'t Display];<br/>
	&nbsp; Otherwise: if [Post Older than 15 Days] then [Display];
	</td><td>	
	<form method="post" name="wizard2" action="">';
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo '<input type="hidden" name="action" value="wizard"/>
	<input type="hidden" name="context_sel_wizard" value="ozh_wsa_wizard"/>
	<input type="hidden" name="whoseesads" value="1"/>
	<input type="hidden" name="context" value="example-post-bottom"/>
	<input type="hidden" name="ozh_wsa_wizard_any" value="1"/>
	<input type="hidden" name="ozh_wsa_wizard_any_display" value="true"/>
	<input type="hidden" name="ozh_wsa_wizard_fromSE" value="1"/>
	<input type="hidden" name="ozh_wsa_wizard_fromSE_display" value="true"/>
	<input type="hidden" name="ozh_wsa_wizard_regular" value="1"/>
	<input type="hidden" name="ozh_wsa_wizard_regular_display" value="false"/>
	<input type="hidden" name="ozh_wsa_wizard_olderthan" value="15"/>
	<input type="hidden" name="ozh_wsa_wizard_olderthan_display" value="true"/>
	<input type="hidden" name="ozh_wsa_wizard_adcode" value="Paste here the code (javascript, html banner, affiliate image, etc...) for this ad"/>
	<input type="hidden" name="ozh_wsa_wizard_comment" value="This is an optional comment. Anything that will help you remember what these rules are for, for example."/>
	<input type="hidden" name="serialize" value="ozh_wsa_wizard_2[]=fromSE&ozh_wsa_wizard_2[]=regular&ozh_wsa_wizard_2[]=olderthan"/>
	<button type="submit" class="wsa_button" onclick="" /><img src="';echo $wand;echo '" alt="" /> Create this Context &raquo;</button>
	</form>
	</td></tr>';
	}
	
	echo '</table>	
	</div>
	';
}

// Print the "Duplicate/Rename" form?
function wp_ozh_wsa_print_duplicate() {
	global $wp_ozh_wsa;
	
	if ($wp_ozh_wsa['contexts']) {
		$contexts = array_keys($wp_ozh_wsa['contexts']);
		array_shift($contexts); // first context is always the new123456 temporary one
	}
	
	if (!$contexts) return;
	
	echo '
	<div class="wrap">
	<h2>Duplicate / Rename Context</h2>
	';

	echo '
	<form method="post" id="ozh_wsa_form_duprename" onsubmit="return (ozh_wsa_duprename_check());" action="">
	<table class="form-table">
	<tbody>
	<input type="hidden" name="whoseesads" value="1"/>
	<input type="hidden" id="wsa_duprename" name="action" value="">
	<tr>
	<th scope="row">Select Context</th>
	<td>
	<select id="duprename_source" name="source" style="padding:3px">
	';

	foreach ($contexts as $context) {
		echo "<option value='$context'>$context</option>";
	}
	
	$duplicate = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/duplicate.gif';
	$rename = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/rename.gif';
	
	echo '</select>
	<span style="font-size:24px">&rarr;</span> <input type="text" id="duprename_target" name="target"/>
	</td>
	</tr>
	<th scope="row">&nbsp;</th>
	<td><button type="submit" class="wsa_button" onclick="return ozh_wsa_duplicate()" ><img src="';echo $duplicate;echo '" alt="" />Duplicate &raquo;</button>
	 &nbsp; 
	<button type="submit" class="wsa_button" onclick="return ozh_wsa_rename()" /><img src="';echo $rename;echo '" alt="" />Rename &raquo;</button>
	';
	
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	
	echo '
	</td></tr></tbody></table>
	</form>
	</div>
	';
}

// Print the Delete form
function wp_ozh_wsa_print_delete() {
	global $wp_ozh_wsa;
	
	$contexts = array_keys($wp_ozh_wsa['contexts']);
	array_shift($contexts);
	
	if (!$contexts) return;

	echo '
	<div class="wrap">
	<h2>Delete Context</h2>
	';

	echo '
	<form method="post" id="ozh_wsa_form_delete" action="">
	<table class="form-table"><tbody>
	<input type="hidden" name="action" value="delete">
	<input type="hidden" name="whoseesads" value="1"/>
	<tr>
	<th scope="row" valign="top">Select Context(s)</th>
	<td>
	<ul class="wsa_del">
	';

	foreach ($contexts as $context) {
		echo '<li class="wsa_listdel">';
		echo "<label class='wsa_del' for='del_context_$context'>";
		echo "<input name='del_context[]' type='checkbox' id='del_context_$context' value='$context' /><span title='Delete this context'> $context </span></label></li>\n";
	}

	$cancel = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/cancel.gif';

	echo '</ul>
	</td></tr>
	<tr>
	<th scope="row">&nbsp;</th>
	<td valign="top"><button type="submit" class="wsa_button" onclick="return ozh_wsa_checkdelete();" /><img src="';echo $cancel;echo '" alt="" />Delete &raquo;</button></td>
	';
	
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo '</tr></tbody></table>
	</form></div>
	';
	
}

// Print the misc footer info & form. Please leave this untouched !
function wp_ozh_wsa_print_misc() {
	global $wp_ozh_wsa;
	
	if ($wp_ozh_wsa['help'] === true or !isset($wp_ozh_wsa['help'])) $checked='checked="checked"';
	
	$dollar = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/paypal-dollar.gif';
	$euro = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/paypal-euro.gif';
	$pound = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/paypal-pound.gif';
	
	$paypal = <<<PAYPAL
<div class="wsa_paypal">
<form style="display:inline;" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_new"><input name="cmd" value="_xclick" type="hidden"><input name="business" value="ozh@planetozh.com" type="hidden"><input name="item_name" value="PlanetOzh Wordpress Plugins and Stuff" type="hidden"><input name="no_note" value="1" type="hidden"><input name="currency_code" value="USD" type="hidden"><input name="tax" value="0" type="hidden"><input name="bn" value="PP-DonationsBF" type="hidden"><input src="$dollar" name="submit" alt="Donation via PayPal : fast, simple and secure!" border="0" type="image"></form>
<form style="display:inline" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_new"><input name="cmd" value="_xclick" type="hidden"><input name="business" value="ozh@planetozh.com" type="hidden"><input name="item_name" value="PlanetOzh Wordpress Plugins and Stuff" type="hidden"><input name="no_note" value="1" type="hidden"><input name="currency_code" value="EUR" type="hidden"><input name="tax" value="0" type="hidden"><input name="bn" value="PP-DonationsBF" type="hidden"><input src="$euro" name="submit" alt="Donation via PayPal : fast, simple and secure!" border="0" type="image"></form>
<form style="display:inline" action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_new"><input name="cmd" value="_xclick" type="hidden"><input name="business" value="ozh@planetozh.com" type="hidden"><input name="item_name" value="PlanetOzh Wordpress Plugins and Stuff" type="hidden"><input name="no_note" value="1" type="hidden"><input name="currency_code" value="GBP" type="hidden"><input name="tax" value="0" type="hidden"><input name="bn" value="PP-DonationsBF" type="hidden"><input src="$pound" name="submit" alt="Donation via PayPal : fast, simple and secure!" border="0" type="image"></form>
</div>
PAYPAL;

	
	echo "
	<div class=\"wrap\">
	<table class='form-table'>
	<tr><td>
	<form id=\"ozh_wsa_form_toggle\" method=\"post\">Display the short explanation &amp; wizards at the top of this page 
	<input type=\"hidden\" name=\"action\" value=\"help\"/><input type=\"checkbox\" $checked name=\"toggle\" value=\"1\" onclick=\"$('ozh_wsa_form_toggle').submit()\"/>";
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo "
	<input type=\"hidden\" name=\"whoseesads\" value=\"1\"/>
	</form>
	<p>$paypal Does this plugin make you happy? Do you find it useful? If you think this plugin helps you monetize your blog, please consider donating. I've spent countless hours developing and testing it, any donation of a few bucks or euros is really rewarding and keeps me motivated to release free plugins. <strong>Thank you for your support!</strong></p>
	<p>If you like this plugin, check my other <a href='http://planetozh.com/blog/my-projects/'>WordPress related stuff</a>!</p>
	</td></tr></table>
	</div>\n";
}

// Print an unordered list (<ul>) element that will have the "Sortable" property (javascript drag & drop)
function wp_ozh_wsa_print_sortable($list,$new) {
	global $wp_ozh_wsa;

	$contexts = array_keys($wp_ozh_wsa['contexts']);
	
	$html = '';
	
	foreach($contexts as $context) {
	
		$used=array();
		
		foreach($wp_ozh_wsa['contexts'][$context]['rules'] as $i=>$rule) {
			$used[] = $rule['condition'];
		}
		
		if ($list == 1) {
			$itemlist = array_diff($wp_ozh_wsa['conditions'],$used);
		} else {
			$itemlist = $used;
			
		}
		
		if ($context == $new) {
			$style = '';
		} else {
			$style = "style='display:none'";
		}
		
		$html .= "<ul id='${context}_${list}' $style class='wsa_sortable wsa_sortable_${list}'>\n";
		foreach ($itemlist as $item) {
			$html .= wp_ozh_wsa_print_sortable_item($item,$context,$list);
		}
		$html .= "</ul>\n";
	
	}

	echo $html;

}

// Print an element (<li>) inside a "Sortable" <ul>
function wp_ozh_wsa_print_sortable_item($item,$context,$list) {

	global $wp_ozh_wsa;
	
	$help = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/help.gif';
	$help = "<img src='$help' />";

	switch($item) {
	
	case 'fromSE':
		$text = "Visitor comes from a search engine <input type='hidden' value='1' name='${context}_fromSE'/>";
		break;
	
	case 'regular':
		$text = "Visitor is a regular reader <input type='hidden' value='1' name='${context}_regular'/>";
		break;
	
	case 'olderthan':
		$old = wp_ozh_wsa_get_parameter($context,'olderthan');
		if ($old === false) $old = $wp_ozh_wsa['old'];
		$old = intval($old); // sanitize: accept only integers
		$text = "Post is older than <input type='text' name='${context}_olderthan' size='3' value='$old'/> days";
		break;
	
	case 'logged':
		$text = "Visitor is logged in <input type='hidden' value='1' name='${context}_logged'/>";
		break;
	
	case 'date':
		$date = wp_ozh_wsa_get_parameter($context,'date');
		if ($date === false) {
			if ($wp_ozh_wsa['date_format'] == 'dmy') {
				$before = date('d/m/Y');
				$after = date('d/m/Y', strtotime("+1 month"));
			} else {
				$before = date('m/d/Y');
				$after = date('m/d/Y', strtotime("+1 month"));
			}
		} else {
			$before = wp_ozh_wsa_date_convert_todate($date['before'],$wp_ozh_wsa['date_format']);
			$after = wp_ozh_wsa_date_convert_todate($date['after'],$wp_ozh_wsa['date_format']);
		}
		
		$help = ( $wp_ozh_wsa['date_format'] == 'dmy' ? 'dd/mm/yyyy' : 'mm/dd/yyyy' );
		
		$text = "Date is between <input type='text' title='$help' name='${context}_date_before' size='9' value='$before'/> & <input type='text' title='$help' name='${context}_date_after' size='9' value='$after'/>";
		break;
	
	case 'numviews':
		$views = intval(wp_ozh_wsa_get_parameter($context,'numviews'));
		if (!$views) $views = 1000;
		$numviews = $wp_ozh_wsa['contexts'][$context]['views'];
		if ($numviews) $numviews = "(currently: $numviews views)";
		$text = "Ad displayed &lt;=  <input type='text' name='${context}_numviews' size='3' value='$views'/> times $numviews";
		break;
	
	// Suggestion and code by Paula Fugaro (http://ezexpertwebtools.com/) 
	case 'readerviews':
		$contextviews = intval(wp_ozh_wsa_get_parameter($context,'readerviews'));
		if (!$contextviews) $contextviews = 1000;
        if (isset($_COOKIE['wp_ozh_wsa_'.$context])) {
            $readerviews = $_COOKIE['wp_ozh_wsa_'.$context];
        }
		if ($readerviews) $readerviews = "(you: $readerviews views)";
		$text = "Ad viewed by visitor &lt;=  <input type='text' name='${context}_readerviews' size='3' value='$contextviews'/> times $readerviews";
		break;

	case 'fallback':

		if (count($wp_ozh_wsa['contexts'])>=3 or (count($wp_ozh_wsa['contexts']) == 2 && $context == $wp_ozh_wsa['newcontext'])) {
			$all = $wp_ozh_wsa['contexts'];
			$current = wp_ozh_wsa_get_parameter($context,'fallback');
			$list = "context <select name=\"${context}_fallback\" class=\"wsa_display_drop\">\n";
			foreach ($all as $con=>$stuff) {
				if ($con != $wp_ozh_wsa['newcontext'] && $con != $context ) {
					$selected = ($con == $current) ? 'selected="selected"' : '' ;
					$list .= "<option value=\"$con\" $selected>$con</option>\n";
				}
			}
			$list .= "</select>\n";
		} else {
			$list = "another context (not enough created)";
		}
		$text = "Previous conditions fail, try $list";
	
		break;
	
	case 'on':
		$on = attribute_escape(wp_ozh_wsa_get_parameter($context,'on'));
		$text = "<tt><b>(</b></tt><textarea rows='1' class='wsa_smalltextarea' cols='25' id='${context}_cond_on' name='${context}_on'>$on</textarea><tt><b>)</b></tt>";
		break;
	
	case 'noton':
		$noton = attribute_escape(wp_ozh_wsa_get_parameter($context,'noton'));
		$text = "<tt><b>(<span style='color:#FF8000' title='if not'>!</span>(</b></tt><textarea rows='1' class='wsa_smalltextarea' cols='25' id='${context}_cond_noton' name='${context}_noton'>$noton</textarea><tt><b>))</b></tt>";
		break;
	
	case 'any':
		$text = "Any condition <input type='hidden' value='1' name='${context}_any'/>";
		break;
	
	default:
		$text = "error: condition '$item' for context ${context} ?";
	}
	
	if ($list == 2) {
		if (wp_ozh_wsa_get_display($context,$item) == "true" ) {
			$selected_true = "selected='selected'";
			$selected_false = '';
		} else {
			$selected_true = '';
			$selected_false = "selected='selected'";
		}
	}
	
	switch($item) {
	case 'noton':
	case 'on':
		$then = '<b>{</b>';
		$after = "<tt><b>}</b></tt> <span class='wsa_helpicon helpicon_custom'>$help</span>";
		break;
	case 'fallback':
		$then = 'HEY';
		$after = '';
	default:
		$then = 'then ';
		$after = '';	
	}
	
	$text = "<tt>if</tt> $text ";
	if ( $item != 'fallback') {
		$text .= "<tt>$then</tt><select id='${context}_${item}_display' class='wsa_display_drop' name='${context}_${item}_display'>
		<option $selected_true value='true'>display</option>
		<option $selected_false value='false'>dont display</option>
		</select>$after";
	}
	$id = $context . '_' . $item;
	return "<li id='$id' class='wsa_sortable_li'><span class=\"wsa_handle\">&times;</span> $text</li>\n";

}

// Print code textareas
function wp_ozh_wsa_print_contextcode($new) {
	global $wp_ozh_wsa;

	$contexts = array_keys($wp_ozh_wsa['contexts']);
	
	$html = '';
	
	foreach($contexts as $context) {

		if ($context == $new) {
			$style = '';
		} else {
			$style = "style='display:none' disabled='disabled'";
		}
		$code = attribute_escape($wp_ozh_wsa['contexts'][$context]['adcode']);
		
		$html .= "<textarea wrap='off' id='${context}_code' name='${context}_adcode' $style class='wsa_code'>$code</textarea>\n";
		
	}
	
	echo $html;
}

// Print comment textareas
function wp_ozh_wsa_print_contextcomment($new) {
	global $wp_ozh_wsa;

	$contexts = array_keys($wp_ozh_wsa['contexts']);
	
	$html = '';
	
	foreach($contexts as $context) {

		if ($context == $new) {
			$style = '';
		} else {
			$style = "style='display:none' disabled='disabled'";
		}
		$comment = attribute_escape($wp_ozh_wsa['contexts'][$context]['comment']);
		
		$html .= "<textarea wrap='off' id='${context}_comment' name='${context}_comment' $style class='wsa_comment'>$comment</textarea>\n";
		
	}
	
	echo $html;
}


// Print the "Global Options" form
function wp_ozh_wsa_print_definitions() {
	global $wp_ozh_wsa;
	
	$old = $wp_ozh_wsa['old'];
	$reg_num = $wp_ozh_wsa['regular'][0];
	$reg_days = $wp_ozh_wsa['regular'][1];
	if ($wp_ozh_wsa['adsense_safety'] == 'on') {
		$checked_on = 'checked="checked"';
		$checked_off = '';
	} else {
		$checked_off = 'checked="checked"';
		$checked_on = '';
	}
	if ($wp_ozh_wsa['date_format'] == 'dmy') {
		$checked_dmy = 'checked="checked"';
		$checked_mdy = '';
	} else {
		$checked_mdy = 'checked="checked"';
		$checked_dmy	 = '';
	}
	
	if ($wp_ozh_wsa['help'] === true or !isset($wp_ozh_wsa['help'])) $checked='checked="checked"';

	
	// List of users with publishing rights, if applicable. FIXME: should be ok, might not be completely foolproof regarding Roles.
	// Get list of authors
	$authors = get_author_user_ids();
	$dropdown_options = array(
		'class' => 'wsa_display_drop',
		'echo' => false, 'name' => 'admin_id',
		'include' => get_author_user_ids(),
	);
	// User currently registered as owner still in here ?
	if (!in_array($wp_ozh_wsa['admin_id'], $authors)) {
		$dropdown_options['show_option_none'] = 'Please select';
		$dropdown_options['selected'] = '-1';
	} else {
		$dropdown_options['selected'] = $wp_ozh_wsa['admin_id'];
	}
	$admin_id = wp_dropdown_users( $dropdown_options );
	
	if (substr_count($admin_id, '<option value') == 1) {
		// Only one user found : show nothing
		preg_match("!<option value='(\d+)!", $admin_id, $matches);
		$admin_id = '<input type="hidden" name="admin_id" value="'.$matches[1].'" />';
		// This user_id must be the same as $wp_ozh_wsa['admin_id'] (prevent errors after user deleting)
		if ($wp_ozh_wsa['admin_id'] != $matches[1]) {
			$checked_on = $checked_off = '';
			$admin_id .= '<span style="color:#f22">(Please <strong>update</strong> this option !)</span>';
		}
	} else {
		// More than one user
		$admin_id_display = ($wp_ozh_wsa['adsense_safety'] == 'on') ? 'inline' : 'none';
		$admin_id = "<span id='admin_id_span' style='display:$admin_id_display'>for user $admin_id (blog / Adsense account owner)</span>";
	}
	
	$help = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/help.gif';
	$help = "<img src='$help' />";
	$ok = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images/ok.gif';
	
	echo <<<HTML
    <div class="wrap">
    <h2>Global Options</h2>
    <form method="post" action="" id="ozh_wsa_form_definitions">
HTML;
	wp_ozh_wsa_nonce_field($wp_ozh_wsa['nonce']);
	echo <<<HTML
	<table class="form-table"><tbody>
	<input type="hidden" name="whoseesads" value="1"/>
    <input type="hidden" name="action" value="definitions">
    <tr><th scope="row">Old post</th><td>An "<strong>old post</strong>" is a post or page which has been posted more than <input type="text" class="code" value="$old" name="old" size="3"> days ago (you can fine tune this on a per context basis)</p></td></tr>
	<tr><th scope="row">Regular Reader</th><td>A "<strong>regular reader</strong>" is someone who has viewed at least <input type="text" class="code" value="$reg_num" name="regular_num" size="3"> pages over the last <input type="text" class="code" value="$reg_days" name="regular_days" size="3"> days</p></td></tr>
	<tr><th scope="row">Click Safety</th><td>The <strong>Admin Clicks Safety</strong><span class="wsa_helpicon" id="helpicon_adsense_safety">$help</span> option is
	<input name="adsense_safety" id="adsense_safety_on" value="on" $checked_on type="radio"><label for="adsense_safety_on">Enabled</label>
	<input name="adsense_safety" id="adsense_safety_off" value="off" $checked_off type="radio"><label for="adsense_safety_off">Disabled</label>
	$admin_id </td></tr>
	<tr><th scope="row">Date format</th><td>Your preferred <strong>Date format</strong> is 
	<input name="date_format" id="date_format_dmy" value="dmy" $checked_dmy type="radio"><label for="date_format_dmy">dd/mm/yyyy</label>
	<input name="date_format" id="date_format_mdy" value="mdy" $checked_mdy type="radio"><label for="date_format_mdy">mm/dd/yyyy</label>
	</td></tr>
	<tr><th scope="row">&nbsp;</th><td><button type="submit" class="wsa_button" /><img src="$ok" alt="" />Update Options &raquo;</button></td></tr>
	</tbody></table>
	</form>
	</div>
HTML;
}


	

// Print the javascript functions needed by this amazingly sexy UI
function wp_ozh_wsa_print_javascript() {
	global $wp_ozh_wsa;
	$new = $wp_ozh_wsa['newcontext'];
	
	$sortables = '';
	$context_hideall = '';
	
	$contexts = array_keys($wp_ozh_wsa['contexts']);
	$context_list = "['" . join ("', '", $contexts) . "']";

	array_unshift($contexts,$new);
	

	if ($wp_ozh_wsa['iknowphp']) $helpcustom = <<<HELPC
	$$('.helpicon_custom').each(function(item){
		ozh_wsa_togglehelp(item,'helpbox_custom');
	});
	
	// Make textareas for custom rules bigger when clicked
	$$('.wsa_smalltextarea').each(function(item){
		$(item).onfocus=function(){ $(item).style.height = '48px'};
		$(item).onblur=function(){ $(item).style.height = '15px'};
	});
HELPC;

	foreach ($contexts as $name) {
		$sortables .= <<<SORTABLE
		Sortable.create("${name}_1",
		 {dropOnEmpty:true,handle:"handle",containment:["${name}_1","${name}_2"],constraint:false});
		Sortable.create("${name}_2",
		 {dropOnEmpty:true,handle:"handle",containment:["${name}_1","${name}_2"],constraint:false});
	
SORTABLE;

		$context_hideall .= "
		$('${name}'+'_1').style.display = 'none';
		$('${name}'+'_2').style.display = 'none';
		$('${name}'+'_code').style.display = 'none';
		$('${name}'+'_code').disabled = 'true';
		$('${name}'+'_comment').style.display = 'none';
		$('${name}'+'_comment').disabled = 'true';	
		";
	}
	
	
	echo <<<JS
	<script type="text/javascript">
	// <![CDATA[
	/*
	 _____  _____   ____ _______ ____ _________     _______  ______    _____ _    _  _____ _  __ _____ 
	|  __ \|  __ \ / __ \__   __/ __ \__   __\ \   / /  __ \|  ____|  / ____| |  | |/ ____| |/ // ____|
	| |__) | |__) | |  | | | | | |  | | | |   \ \_/ /| |__) | |__    | (___ | |  | | |    | ' /| (___  
	|  ___/|  _  /| |  | | | | | |  | | | |    \   / |  ___/|  __|    \___ \| |  | | |    |  <  \___ \ 
	| |    | | \ \| |__| | | | | |__| | | |     | |  | |    | |____   ____) | |__| | |____| . \ ____) |
	|_|    |_|  \_\_____/  |_|  \____/  |_|     |_|  |_|    |______| |_____/ \____/ \_____|_|\_\_____/ 
    God I wished I didn't use prototype when starting this beast....
	*/

	var wsa_context_list = $context_list;
	
	// Make all list draggable & sortable
	$sortables
	
	// Sanitize field value, and highlight on change
	function ozh_wsa_sanitize_blur(what) {
		oldvalue = $(what).value;
		ozh_wsa_sanitize(what);
		if (oldvalue != $(what).value) {
			new Effect.Highlight(what, {startcolor:'#ffff00', endcolor:'#f4f4f4'})
		}
	}
	
	// Sanitize field value
	function ozh_wsa_sanitize(what) {
		// replace underscores and spaces with dashes
		$(what).value = $(what).value.replace(/_| /g,"-");
		// no multiple consecutive dashes
		$(what).value = $(what).value.replace(/-+/g,"-");
		// remove all that's not a letter, a digit or a dash
		$(what).value = $(what).value.replace(/[^a-zA-Z0-9-]/g,"");
		// trim
		$(what).value = $(what).value.replace(/^\s+|\s+$/g,"");
		$(what).value = $(what).value.replace(/^-+|-+$/g,"");
	}
	
	// Check if main form can be submitted
	function ozh_wsa_check() {
	
		// No context selected
		if ($('context_sel').value == '') {
			alert("Please select a context");
			return false;
		}
		
		// "New context" selected, but no name given
		if ($('context_sel').value == '$new' && $('$new').value == '') {
			alert("Please input a name for this context");
			return false;
		}
		
		// Name already taken ?
		if (ozh_wsa_alreadyexists( $('$new').value )) {
			alert('This context already exists. Please pick another name');
			$('context_sel').focus();
			return false;
		}
		
		ozh_wsa_sanitize('$new');
		
		name = $('context_sel').value;
		
		// check if second list (active conditions) not empty
		if (Sortable.serialize(name+"_2") == "") {
			alert("Please select display rules");
			return false;
		}
		
		// check if ad code not empty
		if ($(name+'_code').value == "") {
			alert("Please input code for this context");
			return false;
		}
		
		$("serialize_sortables").value = Sortable.serialize(name+"_2");

		//return false;
		return true;
	}
	
	// Hide all context divs
	function ozh_wsa_hideallcontexts() {
		$context_hideall
	}

	// Update "Usage" example
	function ozh_wsa_updateusage(value) {
		if (value == undefined || value == '') {value = 'context_name';}
		$$('.usage_context').each(function(item){
			$(item).innerHTML = value;
		});
	}
	
	// Stuff to do when selecting a new content
	function ozh_wsa_selectcontext() {
		// Either show the "Name" text field, or hide & reset it
		if($('context_sel').value == '$new') {
			$('new_toggle').style.display = 'inline';
			ozh_wsa_updateusage($('$new').value);
			$('$new').focus();
		} else {
			$('new_toggle').style.display = 'none';
			$('$new').value = '';
			ozh_wsa_updateusage($('context_sel').value);
			$('wsa_active_rules').focus();
		}
		
		// Show appropriate rules
		ozh_wsa_hideallcontexts();
		// show name_1 (possible rules) name_2 (active rules) and name_code (ad code)
		$($('context_sel').value+'_1').style.display = 'block';
		$($('context_sel').value+'_2').style.display = 'block';
		$($('context_sel').value+'_code').style.display = 'block';
		$($('context_sel').value+'_code').disabled = false;
		$($('context_sel').value+'_comment').style.display = 'block';
		$($('context_sel').value+'_comment').disabled = false;
	}
	
	// Checks and sanitize value 
	function ozh_wsa_duprename_check() {
		// check if target not empty
		if ($('duprename_target').value == "") {
			alert('Please input a name');
			$('wsa_duprename').value='';
			return false;
		}

		// check if target == source
		ozh_wsa_sanitize('duprename_target');		
		if ($('duprename_target').value == $('duprename_source').value) {
			alert('Names must be different');
			$('wsa_duprename').value='';
			return false;
		}

		// check if one of the button has been clicked first (no submitting with Return)
		if ($('wsa_duprename').value != 'duplicate' && $('wsa_duprename').value != 'rename' ) {
			alert('Please click either "Duplicate" or "Rename"');
			return false;
		}
		
		// Name already taken ?
		if (ozh_wsa_alreadyexists( $('duprename_target').value )) {
			alert('This context already exists. Please pick another name');
			return false;
		}
		
		return true;
	}
	
	// Return true or false if a context name already exists
	function ozh_wsa_alreadyexists( context) {
		return ( wsa_context_list.indexOf(context) == -1 ) ? false : true ;
	}
	
	// Do checks before submitting a duplication
	function ozh_wsa_duplicate() {
		$('wsa_duprename').value='duplicate';
		if (ozh_wsa_duprename_check() == false) return false;
		$('ozh_wsa_form_duprename').submit();
		return true;
	}
	
	// Do checks before submitting a renaming
	function ozh_wsa_rename() {
		$('wsa_duprename').value='rename';
		if (ozh_wsa_duprename_check() == false) return false;
		$('ozh_wsa_form_duprename').submit();
		return true;
	}
	
	// Check selection when pressing "Delete"
	function ozh_wsa_checkdelete() {
		var found = false;
		(Form.getInputs($("ozh_wsa_form_delete"),"checkbox")).each(function(item){
			if (item.checked) {
				found = true;
				//break;
			}
		});
		if (found) {
			var answer = confirm('Permanently deleted selected Context ?  OK to delete, Cancel to stop.');
			if (answer) return true;
		} else {
			alert('Please select a context');
			return false;
		}
	}

	// Reset a few things on load
	$('$new').value='';
	$('context_sel').selectedIndex = 0;
	$('$new'+'_code').value='';
	if ($('duprename_target')) {
		$('duprename_target').value = '';
		$('wsa_duprename').value= '';
	}
	$("serialize_sortables").value = '';
	
	// Context name on the fly sanitization
	$('$new').onblur = function(){ozh_wsa_sanitize_blur('$new');ozh_wsa_updateusage($('$new').value)};
	if ($('duprename_target'))
		$('duprename_target').onblur = function(){ozh_wsa_sanitize_blur('duprename_target');};

	
	// Help stuff init
	['name','syntax','possible','active','code','adsense_safety','comment'].each(function(item){
		ozh_wsa_togglehelp('helpicon_'+item,'helpbox_'+item);
	});
	$helpcustom
	
	// Help display function
	function ozh_wsa_togglehelp(item,target) {
		$(item).onmouseover=function(e) {
			// for MSIE: get event, and hide <select> elements
			if (!e) {
				var e = window.event; 
				ozh_wsa_toggleselect('hide',target);
			}
			Effect.Appear($(target),{duration:0.2});
			$(target).style.top = (Event.pointerY(e)-16)+'px';
			$(target).style.left = (Event.pointerX(e)+15)+'px';
		};
		$(item).onmouseout=function(e){
			if (!e) ozh_wsa_toggleselect('show');
			Effect.Fade($(target),{duration:0.1});
		};
	}
	
	// Toggle visibility of <select> elements (for MSIE, grrr)
	var wsa_visibledrops = new Array();
	function ozh_wsa_toggleselect(action,helpdiv) {
		if (action == 'hide') {
			$$('.wsa_display_drop').each(function(item){
				// if element is visible (ie inside a visible <ul>) hide it and add it to our list
				if ($(item).parentNode.parentNode.style.display != 'none' ) {
					wsa_visibledrops[wsa_visibledrops.length] = item;
					$(item).style.visibility = 'hidden';
				}
			});
		} else {
			// show every item in our list
			wsa_visibledrops.each(function(item){
				$(item).style.visibility = 'visible';
			});
			// all elements now back to visible, empty our list
			wsa_visibledrops = new Array();
		}
	}
	
	// Toggle display list of blog users for Adsense Safety
	if ($('admin_id_span')) {
		$('adsense_safety_on').onclick = function() {
			$('admin_id_span').style.display = 'inline';
		};
		$('adsense_safety_off').onclick = function() {
			$('admin_id_span').style.display = 'none';
		};
	}
	// ]]>
	</script>
JS;
}

// Print styles
function wp_ozh_wsa_print_css() {
global $wp_ozh_wsa;

$images = get_bloginfo('wpurl').'/wp-content/plugins/'.dirname($wp_ozh_wsa['path']).'/images';

if (isset($wp_ozh_wsa['my_codetextarea'])) {
	$textarea_height = $wp_ozh_wsa['my_codetextarea'];
} else {
	$textarea_height = '80px';
}

	echo <<<CSS
<style type="text/css">
.wrap {
	margin-bottom:40px;
}
.wsa_del {
	list-style-type:none;
	clear:both;
	overflow:auto;
	_height:1%;
	padding:0 3px;
	margin:0;
}
.wsa_listdel {
	float:left;
	width:30%;
}
.wsa_listdel:hover {
	color:#d00;
}
.wsa_display_drop {
	font-size:10px;
}
.wsa_button {
	background: url( images/fade-butt.png );
	border: 3px double #999;
	border-left-color: #ccc;
	border-top-color: #ccc;
	color: #333;
	padding: 0.25em;
	height:40px;
	font-size:13px;
	margin:1px;
}
.wsa_button:hover {
	background-position:0px -2px;
	border-color: #999;
}
.wsa_button img {
	padding-right:1px;
}
.wsa_button:hover img {
	padding-right:0px;
	padding-left:1px;
}
.wsa_helpicon {
	cursor:pointer;
}
.wsa_helpbox {
	position:absolute;
	right:50px;
	margin:15 5%;
	z-index:9999;
	font-size:11px;
	width:306px;
	padding:2px 10px 10px 25px;
	background: transparent url($images/helpbox.png) 0 0 no-repeat !important;
	#background:#ffc;
}
.wsa_helpbox h3 {
	font-size:120%;
}
.wsa_smalltextarea {
	font-size:11px;
	height:15px;
}
.wsa_code:hover , .wsa_code:focus {
	border:2px solid #aae;
}
.wsa_code {
	width:98%;
	height:$textarea_height;
	overflow:auto;
	border:2px solid #ddf;
	_border:2px solid #aae;
	margin:0;
}
.wsa_comment{
	width:98%;
	height:40px;
	overflow:auto;
	border:1px solid #ddd;
	margin:0;
	background:#f5f5f5 !important;
}
.wsa_comment:hover , .wsa_comment:focus {
	border:1px solid #aae;
}
.wsa_celltitle{
	width:170px;
	_width:200px;
	height:30px;
}
.wsa_cell ul{
	background:white;
	padding:5px;
	border:1px solid #555;
	min-height:30px;
	width:75%;
}
.wsa_sortable {
	margin:0;
	padding:5px;
	padding-left:25px !important;
	padding-left:9px;
}
.wsa_sortable_1 {
	list-style-type:none;
}
.wsa_sortable_2 {
	list-style-type:decimal !important;
	list-style-type:none;
}
.wsa_sortable_li tt {
	color:#55a;
}
.wsa_sortable_li {
	border:1px solid #aac;
	padding:2px 10px;
	background:#cfebf7;
	white-space:nowrap;
}
span.wsa_handle {
	color:#aae;
	font-size:16px;
	font-weight:bolder;
	cursor:move;
}
.wsa_paypal {
float:left;margin-right:10px;
}
.wsa_paypal input {
	border:0px;
	background:white;
}
label {margin-right:1em;}
</style>
CSS;
}

/************** FORM PROCESSING ******************/

// Main processing function
function wp_ozh_wsa_processforms() {
	global $wp_ozh_wsa;
	
	check_admin_referer($wp_ozh_wsa['nonce']);

	echo '<div id="message" class="updated fade">';
	
	switch($_POST['action']) {
	case 'wizard':
		$_POST['context_sel'] = $_POST['context_sel_wizard'];
		// onto the regular 'add' case now, no break here
	case 'add':
		$msg = wp_ozh_wsa_processforms_add();
		break;
	case 'definitions':
		$msg = wp_ozh_wsa_processforms_definitions();
		break;
	case 'delete':
		$msg = wp_ozh_wsa_processforms_delete();
		break;
	case 'duplicate':
	case 'rename':
		$msg = wp_ozh_wsa_processforms_duprename();
		break;
	case 'help':
		$msg = wp_ozh_wsa_processforms_help();
		break;
	}
	wp_ozh_wsa_readoptions();

	// ze debug print_r !
	// echo "<pre>";echo '$_POST: ';print_r(array_map('attribute_escape',$_POST));echo "</pre>";
	
	echo "<p><strong>Who Sees Ads &raquo;</strong> $msg</p>\n";
	
	echo "</div>\n";
}

// Function processing the "Help" form
function wp_ozh_wsa_processforms_help() {
        global $wp_ozh_wsa;

        if ($_POST['toggle']==1) {
                $wp_ozh_wsa['help'] = true;
        } else {
                $wp_ozh_wsa['help'] = false;
        }

        wp_ozh_wsa_saveoptions();

        return 'Help display toggled';
}


// Function processing the "Global Options" form
function wp_ozh_wsa_processforms_definitions() {
	global $wp_ozh_wsa;
	
	$wp_ozh_wsa['old'] = intval($_POST['old']);
	$wp_ozh_wsa['regular'] = array(intval($_POST['regular_num']),intval($_POST['regular_days']));
	$wp_ozh_wsa['adsense_safety'] = $_POST['adsense_safety'];
	$wp_ozh_wsa['date_format'] = $_POST['date_format'];
	$wp_ozh_wsa['admin_id'] = $_POST['admin_id'];
	if ($_POST['toggle']==1) {
		$wp_ozh_wsa['help'] = true;
	} else {
		$wp_ozh_wsa['help'] = false;
	}

	wp_ozh_wsa_saveoptions();
	
	return 'Options updated';
}

// Function processing the main form (Add / Update a context)
function wp_ozh_wsa_processforms_add() {
	global $wp_ozh_wsa;
	
	// field 'serialize' contains the list of items which were in the second sortable list, ie the "active conditions"
	// let's parse its content
	// [serialize] => coucou_2[]=olderthan&coucou_2[]=any
	parse_str($_POST['serialize'],$rules);
	// Array ( [coucou_2] => Array ( [0] => olderthan [1] => any ) )
	$rules = array_shift($rules); // array([0]=>'olderthan', [1]=>'regular')

	if ($_POST['context']) {
		$contextname = wp_ozh_wsa_processforms_sanitize($_POST['context']);
		if (array_key_exists($contextname,$wp_ozh_wsa['contexts'])) {
			return "<b>Error</b>: There is already another context named <b>$contextname</b>";
		}
		$action = 'created';
	} else {
		$contextname = wp_ozh_wsa_processforms_sanitize($_POST['context_sel']);
		$action = 'updated';
	}
	$context = wp_ozh_wsa_processforms_sanitize($_POST['context_sel']);
	
	$wp_ozh_wsa['contexts'][$contextname]['rules'] = array(); // reset array in case we're dealing with updating a ruleset
	
	$wp_ozh_wsa['contexts'][$contextname]['adcode'] = trim($_POST[$context.'_adcode']);
	
	$wp_ozh_wsa['contexts'][$contextname]['comment'] = trim($_POST[$context.'_comment']);
	
	// Merge toto_date_before and toto_date_after
	if ($_POST[$context.'_date_before'] && $_POST[$context.'_date_after']) {
		$before = wp_ozh_wsa_date_convert($_POST[$context.'_date_before'],$wp_ozh_wsa['date_format']);
		$after = wp_ozh_wsa_date_convert($_POST[$context.'_date_after'],$wp_ozh_wsa['date_format']);
		if ($before > $after) list($before,$after) = array($after,$before);
		$_POST[$context.'_date'] = array(
			'before' => $before,
			'after' => $after,
		);
	}
	
	foreach($rules as $i=>$entry) {
		$condition = $_POST[$context.'_'.$entry];
		$display = $_POST[$context.'_'.$entry.'_display'];
		
		// Check if 'on' and 'noton' values are authorized
		if ($wp_ozh_wsa['iknowphp'] !== true && ( $entry == 'on' or $entry == 'noton' ) ) {
			die('<h1>OH HAI !! TIZ FORBIDDUN !</h1><p>h4x0r attempt ? You tried to POST things that are not authorized.</p>');	
		}
		
		$wp_ozh_wsa['contexts'][$contextname]['rules'][$i]['condition'] = $entry;
		$wp_ozh_wsa['contexts'][$contextname]['rules'][$i]['parameter'] = $condition;
		$wp_ozh_wsa['contexts'][$contextname]['rules'][$i]['display'] = $display;
		
		// Do we reset the view count ?
		if ($entry == 'numviews') $dontreset = true;
	}
	
	if (!$dontreset) unset($wp_ozh_wsa['contexts'][$contextname]['views']);
	
	wp_ozh_wsa_saveoptions();
	
	return "Context <b>$contextname</b> $action";

}

// Function processing the "Delete" form
function wp_ozh_wsa_processforms_delete() {
	
	global $wp_ozh_wsa;

	foreach($_POST['del_context'] as $context) {
		if ($wp_ozh_wsa['do_widgets']) wp_ozh_wsa_widget_deleterename($context);
		unset($wp_ozh_wsa['contexts'][$context]);
		wp_ozh_wsa_check_fallback($context);
	}
	
	wp_ozh_wsa_saveoptions();
	
	return 'Selection deleted';
	
}

// Function processing the "Duplicate / Rename" form
function wp_ozh_wsa_processforms_duprename() {
	global $wp_ozh_wsa;
	
	$source = wp_ozh_wsa_processforms_sanitize($_POST['source']);
	$dest = wp_ozh_wsa_processforms_sanitize($_POST['target']);
	
	if ($source == $dest) {
		return "<b>Error</b>: Names must be different";
	}

	if ($dest == '') {
		return "<b>Error</b>: Name must not be empty. It must contains only letters and numbers";
	}
	
	if (array_key_exists($dest,$wp_ozh_wsa['contexts'])) {
		return "<b>Error</b>: There is already another context named <b>$dest</b>";
	}

	$wp_ozh_wsa['contexts'][$dest] = $wp_ozh_wsa['contexts'][$source];
	if ($_POST['action'] == 'rename') {
		if ($wp_ozh_wsa['do_widgets']) wp_ozh_wsa_widget_deleterename($source, $dest);
		unset($wp_ozh_wsa['contexts'][$source]);
		$action = 'renamed';
		wp_ozh_wsa_check_fallback($source, $dest);
	} else {
		$action = 'duplicated';
	}
	
	wp_ozh_wsa_saveoptions();	
	
	return "Context <b>$source</b> $action to <b>$dest</b>";
	
}

// Rename or delete widgets affected by context deleting or renaming
function wp_ozh_wsa_widget_deleterename($source, $dest = '') {
	$source = 'ad-' . $source;
	if ($dest) $dest = 'ad-' . $dest;
	$sidebars = get_option('sidebars_widgets');
	foreach ( array_keys($sidebars) as $sidebar_id ) {
		if ( is_array($sidebars[$sidebar_id]) 
			&& ( $key = array_search($source, $sidebars[$sidebar_id]) ) !== false
		) {
			if ($dest) {
				$sidebars[$sidebar_id][$key] = $dest;
			} else {
				unset($sidebars[$sidebar_id][$key]);
				// wtf. I didn't understand how to use the widget API with something like unregister_sidebar_widget().
			}
			break;
		}
	}
	update_option('sidebars_widgets', $sidebars);
}

// Check if any 'fallback' condition is affected upon rename or delete of a context
function wp_ozh_wsa_check_fallback($old, $new = '') {
	// on rename: rename any existing fallback parameter
	// on delete: unset any existing fallback condition

	global $wp_ozh_wsa;
	
	foreach($wp_ozh_wsa['contexts'] as $context=>$rules) {
		if (wp_ozh_wsa_get_parameter($context, 'fallback') == $old) {
			if ($new != '') {
				// it's a rename
				wp_ozh_wsa_set_parameter($context, 'fallback', $new);
			} else {
				// it's a delete
				wp_ozh_wsa_delete_condition($context, 'fallback');
			}
		}
	}
		
}

/************** INTERNAL FUNCTIONS ******************/

// Finds 'display' value of $condition (ex: fromSE, olderthan) for context $context
function wp_ozh_wsa_get_display($context,$condition) {
	
	global $wp_ozh_wsa;
	
	foreach ($wp_ozh_wsa['contexts'][$context]['rules'] as $rule) {
		if ($rule['condition'] == $condition) {
			return $rule['display'];
		}
	}
	return "not found $context,$condition";
}


// Finds 'parameter' value of condition $condition of context $context
function wp_ozh_wsa_get_parameter($context,$condition) {
	
	global $wp_ozh_wsa;
	
	foreach ($wp_ozh_wsa['contexts'][$context]['rules'] as $rule) {
		if ($rule['condition'] == $condition)
			return $rule['parameter'];
	}
	return false;
}

// Sets 'parameter' value of condition $condition of context $context to $parameter
function wp_ozh_wsa_set_parameter($context,$condition,$parameter) {
	
	global $wp_ozh_wsa;
	
	if (!$wp_ozh_wsa['contexts'][$context]) return false;
	
	$i=0;
	foreach ($wp_ozh_wsa['contexts'][$context]['rules'] as $rule) {
		if ($rule['condition'] == $condition) {
			$wp_ozh_wsa['contexts'][$context]['rules'][$i]['parameter'] = $parameter;
			wp_ozh_wsa_saveoptions();
			return true;
		}
		$i++;
	}
	return false;
}


// Deletes condition $condition of context $context
function wp_ozh_wsa_delete_condition($context,$condition) {
	
	global $wp_ozh_wsa;
	
	if (!$wp_ozh_wsa['contexts'][$context]) return false;
	
	$i=0;
	foreach ($wp_ozh_wsa['contexts'][$context]['rules'] as $rule) {
		if ($rule['condition'] == $condition) {
			unset($wp_ozh_wsa['contexts'][$context]['rules'][$i]);
			wp_ozh_wsa_saveoptions();
			return true;
		}
		$i++;
	}
	return false;
}

// Converts dd/mm/yyyy or mm/dd/yyyy to a Unix timestamp (GMT at 00:01:01)
// @input $date A date in the form of dd/mm/yyyy or mm/dd/yyyy
// @input $format The preferred format as stored in options ('dmy' or 'mdy')
function wp_ozh_wsa_date_convert($date,$format) {
	if (!preg_match('!\d\d\/\d\d\/\d\d\d\d!',$date)) return 70157227; // not a date? return my date of birth :)
	$date = explode('/',$date);
	if ($format == 'dmy') {
		$day = $date[0];
		$month = $date[1];	
	} else {
		$day = $date[1];
		$month = $date[0];
	}
	$year = $date[2];
	
	return gmmktime ( 0, 1, 1, $month, $day, $year );	
}

// Converts a timestamp to dd/mm/yyyy or mm/dd/yyyy
// @input $date A timestamp
// @input $format The preferred format as stored in options ('dmy' or 'mdy')
function wp_ozh_wsa_date_convert_todate($date,$format) {
	if ($format == 'dmy') {
		$format = 'd/m/Y';
	} else {
		$format = 'm/d/Y';
	}
	return date($format,$date);
}

// Context name sanitization
function wp_ozh_wsa_processforms_sanitize($context) {
	// Very destructive set of function to allow only a limited set of characters
	$context = str_replace(' ','_',$context);
	$context = preg_replace('/[^a-zA-Z0-9_-]/','',$context);
	$context = trim($context);
	$context = addslashes($context);
	return $context;	
}


// Nonce confirmation
function wp_ozh_wsa_nonce_explain() {
	return "You are about to modify your preferences or settings for Who Sees Ads, your ultimate ad management plugin.\n\nAre you sure you want to do this ?";
}

// Nonce wrapper for old WP versions
if ( !function_exists('wp_nonce_field') ) {
	function wp_ozh_wsa_nonce_field($action = -1) { return; }
	$wp_ozh_wsa['nonce'] = -1;
} else {
	function wp_ozh_wsa_nonce_field($action = -1) { return wp_nonce_field($action); }
	$wp_ozh_wsa['nonce'] = 'ozh-wsa';
}

add_action('explain_nonce_ozh-wsa','wp_ozh_wsa_nonce_explain');
add_action('admin_footer', 'wp_ozh_wsa_addbutton');

?>