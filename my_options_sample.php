<?php
/*
This file is part of the Wordpress Plugin "Who Sees Ads" version 2.0
It contains personal and custom settings for *ADVANCED* users.
See http://planetozh.com/blog/my-projects/wordpress-plugin-who-sees-ads-control-adsense-display/
*/

/******************************************************************************
 * Copy my_options_sample.php to my_options.php then edit to suit your needs. *
 * Disclaimer: use this file if you know what it implies. You're on your own. * 
 ******************************************************************************/

/* Where to add the "Who Sees Ads" submenu ? */
// $wp_ozh_wsa['my_menu'] = 'themes.php';

/* Override the ['iknowphp'] variable */
// $wp_ozh_wsa['my_iknowphp'] = false;

/* Height of textarea for pasting code in. Must be a proper CSS value */
// $wp_ozh_wsa['my_codetextarea'] = '220px';

/* Support for multiple code in a single context, to be randomly picked (rotated) */
// $wp_ozh_wsa['my_rotatecode_separator'] = '**** ROTATE ****'

/* List of custom search engines. Overrides, does not add to original list */
// $wp_ozh_wsa['my_search_engines'] = array('/search?', '.google.', 'web.info.com', 'search.', 'del.icio.us/search', 'soso.com', '/search/', '.yahoo.', );

/* List of standard context conditions. Feel free to remove the one you'll never use. */
// $wp_ozh_wsa['my_conditions'] = array ('fromSE', 'regular', 'olderthan', 'logged', 'date', 'numviews', 'readerviews', 'fallback', 'any',);

/* Disable widget support */
// $wp_ozh_wsa['my_widgets'] = false;

/* Disable additional buttons in the "Write Post / Page" interface */
// $wp_ozh_wsa['my_wsa-buttons'] = false;

/* A custom fake ad, to be displayed if "Admin Click Safety" feature is enabled */
/* Token with %%token%% syntax will be replaced with $token -- see wp_ozh_wsa_fakegooglead() for more infos */
// $wp_ozh_wsa['my_fakead'] = '<div class="myfakead">Actual %%type%% not shown (dimension: %%width%%x%%height%%)</div>';

/* Some HTML (or CSS or anything) wrapping for the (default, or your custom) fake ads */
// $wp_ozh_wsa['my_fakead-before'] = '<div style="border:1px solid red;float:right;">';
// $wp_ozh_wsa['my_fakead-after'] = '</div>';

/* Some CSS styling for the (default, or your custom) fake ads */
// You can either define inline here, or file_get_contents('./mycss.css'), etc..
// $wp_ozh_wsa['my_fakead-css'] = '<style>div.myfakead {border:3px solid red}</style>';

/* Override Adsense or YPN maximums */
// define('OZH_WSA_MAX_GOOGLE_AD',3);
// define('OZH_WSA_MAX_GOOGLE_SEARCH',2);
// define('OZH_WSA_MAX_GOOGLE_LINKS',3);
// define('OZH_WSA_MAX_GOOGLE_REF',3);
// define('OZH_WSA_MAX_YPN_AD',3);


?>