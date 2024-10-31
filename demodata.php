<?php

/*
  Plugin Name: Multilingual Demo Data Creator
  Plugin URI: http://zanto.org/
  Description: Multilingual Demo Data Creator enables you to create demo users, blogs, posts, comments and blogroll links in different languages for a Wordpress site or multisite. PLEASE NOTE: deleting the data created by this plugin will delete EVERYTHING (pages, posts, comments, users - everything) on your site, so DO NOT use on a production site, or one where you want to save the data.
  Version: 0.1
  Author: Ayebale Mucunguzi
  Author URI: http://profiles.wordpress.org/brooksx/
 */
/*
  This plugin was inspired by Demo Data Creator by Chris Taylor (http://www.stillbreathing.co.uk)
  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */


require_once(ABSPATH . "wp-admin" . '/includes/file.php');
$wmdd_languages = array('en_US', 'fr_FR', 'ru_RU');

function mo_files_functions() {
    wmdd_need_lang_files();
}

add_action('init', 'mo_files_functions');
// if the file is being called in an AJAX call
if (isset($_GET['ajax']) && $_GET['ajax'] == "true") {

    // when the admin menu is built
    add_action('admin_menu', 'wmdd_do_ajax');
}

// check for MultiSite
function wmdd_is_multisite() {
    if (
            version_compare(get_bloginfo("version"), "3", ">=")
            && defined('MULTISITE')
            && MULTISITE
    ) {
        return true;
    }
    return false;
}

// check for WPMU
function wmdd_is_mu() {
    if (
            defined('VHOST')
            && function_exists("is_site_admin")
    ) {
        return true;
    }
    return false;
}

// when the admin menu is built
add_action('admin_menu', 'wmdd_add_menu_items');

// if this is not WPMU/MultiSite
if (!wmdd_is_multisite() || !wmdd_is_mu()) {
    // set up the $current_site global
    global $current_site;
    $current_site->domain = wmdd_blog_domain();
}

// include registration functions - DEPRECATED
//require_once(ABSPATH . WPINC . '/registration.php');
// ======================================================
// Admin functions
// ======================================================
// do an ajax request
function wmdd_do_ajax() {

    echo '
	<div id="wmdd_results">
	';

    // listen for form submission
    wmdd_watch_form();

    echo '
	</div>
	';

    // stop processing
    exit();
}

// create the data
function wmdd_create() {
    // listen for a form submission
    if (count($_POST) > 0 && isset($_POST["create"])) {

        // get the upgrade functions
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wmdd_languages;
        switch ($_POST["create"]) {

            // creating blogs
            case "blog":

                if (wmdd_is_multisite() || wmdd_is_mu()) {
                    $locale = in_array($_POST["WPLANG"], $wmdd_languages) ? $_POST["WPLANG"] : 'en_US';
                    $code = wmdd_code($locale);
                    require_once('/lang-data/' . $code . '.php');
                    wmdd_create_blogs();
                    wmdd_need_lang_files($locale);
                }
                break;

            // creating content
            case "blogdata":
                global $blog_id;

                if (wmdd_is_multisite() || wmdd_is_mu()) {
                    if (isset($_POST['selectblog'])) {
                        $blogid = $_POST['selectblog'];
                    } else {
                        $blogid = $blog_id;
                    }
                    switch_to_blog($blogid);
                    $b_locale = get_option('WPLANG');
                    $code = wmdd_code($b_locale);
                } else {
                    $b_locale = in_array($_POST["WPLANG"], $wmdd_languages) ? $_POST["WPLANG"] : 'en_US';
                    $code = wmdd_code($b_locale);
                    wmdd_create_users();
                    $wpdb->query($wpdb->prepare("INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", "WPLANG", $b_locale, "yes"));
                }
                require_once('/lang-data/' . $code . '.php');
                wmdd_create_categories();
                wmdd_create_posts();
                wmdd_create_pages();
                wmdd_create_comments();
                wmdd_create_links();
                wmdd_need_lang_files($b_locale);

                if (wmdd_is_multisite() || wmdd_is_mu()) {
                    restore_current_blog();
                }
                break;
        }
    }
}

// add the menu items to the Site Admin list
function wmdd_add_menu_items() {
    // add includes
    if (isset($_GET["page"]) && $_GET["page"] == "wmdd_form") {
        add_action("admin_head", "wmdd_css");
        add_action("admin_head", "wmdd_js");
    }

    // WP3
    if (version_compare(get_bloginfo("version"), "3", ">=")) {
        // multisite
        if (wmdd_is_multisite()) {
            add_submenu_page('ms-admin.php', 'M-Demo Data Creator', 'M-Demo Data Creator', 'edit_users', 'wmdd_form', 'wmdd_form');
            // standard
        } else {
            add_submenu_page('tools.php', 'M-Demo Data Creator', 'M-Demo Data Creator', 'edit_users', 'wmdd_form', 'wmdd_form');
        }
    } else {
        // MU
        if (wmdd_is_mu()) {
            add_submenu_page('wpmu-admin.php', 'M-Demo Data Creator', 'M-Demo Data Creator', 'edit_users', 'wmdd_form', 'wmdd_form');
            // standard
        } else {
            add_submenu_page('tools.php', 'M-Demo Data Creator', 'M-Demo Data Creator', 'edit_users', 'wmdd_form', 'wmdd_form');
        }
    }
}

// add the CSS to the admin page head
function wmdd_css() {
    if (isset($_GET["page"]) && $_GET["page"] == "wmdd_form") {
        echo '
		<style type="text/css">
		html body form.wmdd fieldset p {
		border-width: 0 0 1px 0;
		border-color: #AAA;
		}
		html body form.wmdd fieldset label {
		float: left;
		width: 32em;
		}
		html body form.wmdd fieldset input {
		width: 6em;
		}
		html body form.wmdd fieldset input[type="text"] {
		width: 18em;
		}
		.wmddpending .spinner {
         display: block;
         float: left;
         margin: 0 5px 0 0;
        }
		.promo{
		padding: 5px;
        border: dashed #ddd;
		}
		.promo img{
		vertical-align:middle;
		}
		
		</style>
		';
    }
}

// add the JavaScript to the admin page head
function wmdd_js() {
    if (isset($_GET["page"]) && $_GET["page"] == "wmdd_form") {
        echo '
		<script type="text/javascript">
       
		jQuery(document).ready(function(){
			jQuery(".wmddbutton").bind("click", function(e) {
			    
			    jQuery("html, body").animate({ scrollTop: 0 }, "slow");
				var id = jQuery(this).attr("id");
				var div = jQuery("#" + id + "output");
				var form = jQuery("#" + id + "form");
				if((id)=="delete"){
			    	if(!confirm(\''.__('Do you want to proceed with this action?','wmdd').'\')) {
                      e.stopImmediatePropagation();   
					  e.preventDefault();
					  return false;
                    }
				}
				div.html(\'<div class="wmddpending updated"><p><span class="spinner"></span> <strong>'.__("Working, please wait...","wmdd").'</strong></p></div>\');
				var formdata = form.serialize();
				jQuery.ajax({
					data: formdata,
					type: "POST",';
        // WP3
        if (version_compare(get_bloginfo("version"), "3", ">=")) {
            // multisite
            if (wmdd_is_multisite()) {
                echo 'url: "ms-admin.php?page=wmdd_form&ajax=true",
							';
                // standard
            } else {
                echo 'url: "tools.php?page=wmdd_form&ajax=true",
							';
            }
        } else {
            // MU
            if (wmdd_is_mu()) {
                echo 'url: "wpmu-admin.php?page=wmdd_form&ajax=true",
							';
                // standard
            } else {
                echo 'url: "tools.php?page=wmdd_form&ajax=true",
							';
            }
        }
        echo '
					success: function(data) {
						div.html(data);
						location.reload();
					},
					error: function() {
						div.html(\'<div class="error"><p>' . __("Sorry, the process failed", "wmdd") . '</p></div>\');
					}
				});
				e.preventDefault();
				return false;
			});
		});
		</script>
		';
    }
}

// create demo users
function wmdd_create_users() {

    global $wpdb, $wmdd_demouser;
    global $current_site;
    $domain = $current_site->domain;
    if ($domain == "") {
        $domain = $_SERVER["SERVER_NAME"];
    }
    // if the domain does not have a suffix (for example "localhost" but a .com at the end to pas WordPress email checking
    if (strpos($domain, ".") === false)
        $domain .= ".com";

    // get the users settings
    $users = @$_POST["users"] == "" ? 100 : (int) $_POST["users"];
    $users = $users > 1000 ? $users = 1000 : $users = $users;
    $useremailtemplate = @$_POST["useremailtemplate"] == "" ? "demouser[x]@" . $domain : $_POST["useremailtemplate"];

    // check all the settings
    if (
            $users != "" &&
            $useremailtemplate != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        // turn off new registration notifications for WPMU/MultiSite
        if (wmdd_is_multisite() || wmdd_is_mu()) {
            $registrationnotification = get_site_option("registrationnotification");
            update_site_option("registrationnotification", "no");
        }

        $userx = $wpdb->get_var("select count(ID) from " . $wpdb->users . ";");
        $created = 0;

        // loop the number of required users
        for ($u = 0; $u < $users; $u++) {
            $userx++;

            // generate the details for this user
            // get a random name
            $firstname = wmdd_firstname();
            $lastname = wmdd_lastname();
            $username = $firstname . $userx;
            $email = str_replace("[x]", $userx, $useremailtemplate);
            $random_password = wp_generate_password(12, false);

            if (email_exists($email)) {

                $error .= "<li>Email exists: " . $email . "</li>";
            } else {

                // check the user can be created
                //$id = wp_create_user($username, $random_password, $email);
                // check the user can be created
                $id = wp_insert_user(array(
                    'user_login' => $username
                    , 'first_name' => $firstname
                    , 'last_name' => $lastname
                    , 'user_pass' => $random_password
                    , 'user_email' => $email
                    , 'nickname' => $username
                    , 'display_name' => $firstname
                        ));


                if (is_wp_error($id)) {
                    $error .= "<li>Error creating user " . $userx;
                    $error .= ": " . $id->get_error_message();
                    $error .= "</li>";
                    $userx--;
                    break;

                    // break out of this loop
                }
            }
            $created++;
        }

        $success = false;
        if ($created == $users) {
            $success = true;
        }

        // turn registration notification back on for WPMU/MultiSite
        if (wmdd_is_multisite() || wmdd_is_mu()) {
            update_site_option("registrationnotification", $registrationnotification);
        }

        if ($success) {

            echo '
			<div class="updated">
			<p>' . $created . " " . __("demo users created", "wmdd") . '</p>
			</div>
			';
        } else {

            echo '
			<div class="error">
			<p>' . $created . ' ' . __("demo users created", "wmdd") . '</p>
			<p>' . __("Errors encountered:", "wmdd") . '</p>
			<ul>
			' . $error . '
			</ul>
			</div>
			';
        }
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

// create demo blogs
function wmdd_create_blogs() {
    // if this is WPMU/MultiSite
    if (wmdd_is_multisite() || wmdd_is_mu()) {

        global $wpdb, $wmdd_languages, $current_site, $wmdd_demoblog;
        // get the blog settings
        $lang = in_array($_POST["WPLANG"], $wmdd_languages) ? $_POST["WPLANG"] : 'en_US';

        // check all the settings
        if (
                $lang != ""
        ) {
            $go = true;
        } else {
            $go = false;
        }

        // if the settings are OK
        if ($go) {

            // turn off new registration notifications
            $registrationnotification = get_site_option("registrationnotification");
            update_site_option("registrationnotification", "no");

            $success = true;

            // get highest blog id
            $sql = "select max(blog_id) from " . $wpdb->blogs . ";";
            $blogid = (int) $wpdb->get_var($sql) + 1;


            // get a random blogname
            $blogname = wmdd_blogname();
            $blogdomain = $wmdd_demoblog . $blogid;

            // check the blog can be created
            if (!wmdd_create_blog($blogid, $blogdomain, $blogname, get_current_user_id())) {
                $success = false;
                $error .= "<li>Error creating blog " . $blogid . '</li>';
            }



            // turn registration notification back on
            update_site_option("registrationnotification", $registrationnotification);

            if ($success) {
                global $wpdb;
                echo '
				<div class="updated">
				<p>' . $blogname . " " . __("demo blog created", "wmdd") . '</p>
				</div>
				';

                if (!is_null($blogid)) {
                    switch_to_blog($blogid);
                }
                wmdd_create_users();
                $wpdb->query($wpdb->prepare("INSERT INTO `$wpdb->options` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`)", "WPLANG", $lang, "yes"));
                if (!is_null($blogid)) {
                    restore_current_blog();
                }
            } else {

                echo '
				<div class="error">
				<p>' . $blogname . ' ' . __("demo blogs created", "wmdd") . '</p>
				<p>' . __("Errors encountered:", "wmdd") . '</p>
				<ul>
				' . $error . '
				</ul>
				</div>
				';
            }
        } else {

            echo '
			<div class="error">
			<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
			</div>
			';
        }
    } else {
        echo '
		<div class="error">
		<p>' . __("This site does not support multiple blogs.", "wmdd") . '</p>
		</div>
		';
    }
}

// create demo categories
function wmdd_create_categories() {
    global $wpdb;
    global $current_site;

    // get the categories and tags settings
    $maxblogcategories = @$_POST["maxblogcategories"] == "" ? 10 : intval($_POST["maxblogcategories"]);
    $maxblogcategories = $maxblogcategories > 25 ? $maxblogcategories = 25 : $maxblogcategories = $maxblogcategories;

    // check all the settings
    if (
            $maxblogcategories != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        $categoryx = 0;


        // get a random number of blog categories
        $categories = rand(0, $maxblogcategories);

        // loop the number of required categories
        for ($c = 0; $c < $categories; $c++) {

            $categoryx++;

            // see if the category can be inserted
            if (!wmdd_create_category($current_site->domain, $categoryx)) {
                $categoryx--;
            }
        }


        echo '
		<div class="updated">
		<p>' . $categoryx . " " . __("demo categories created", "wmdd") . '</p>
		</div>
		';
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

// create demo posts
function wmdd_create_posts() {
    global $wpdb;
    global $current_site;

    // get the post settings
    $maxblogposts = @$_POST["maxblogposts"] == "" ? 50 : (int) $_POST["maxblogposts"];
    $maxblogposts = $maxblogposts > 100 ? $maxblogposts = 100 : $maxblogposts = $maxblogposts;
    $maxpostlength = @$_POST["maxpostlength"] == "" ? 10 : (int) $_POST["maxpostlength"];
    $maxpostlength = $maxpostlength > 50 ? $maxpostlength = 50 : $maxpostlength = $maxpostlength;
    $maxpostlength = $maxpostlength < 1 ? $maxpostlength = 1 : $maxpostlength = $maxpostlength;

    // check all the settings
    if (
            $maxblogposts != "" &&
            $maxpostlength != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        $postx = 0;

        // get a random number of blog posts
        $posts = rand(0, $maxblogposts);

        // loop the number of required posts
        for ($p = 0; $p < $posts; $p++) {

            $postx++;

            // see if the post can be inserted
            if (!wmdd_create_post($current_site->domain, $maxpostlength, $postx)) {
                $postx--;
            }
        }


        echo '
		<div class="updated">
		<p>' . $postx . " " . __("demo posts created", "wmdd") . '</p>
		</div>
		';
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

// create demo pages
function wmdd_create_pages() {
    global $wpdb;
    global $current_site;

    // get the pages settings
    $maxpages = @$_POST["maxpages"] == "" ? 25 : (int) $_POST["maxpages"];
    $maxpages = $maxpages > 25 ? $maxpages = 25 : $maxpages = $maxpages;
    $maxtoppages = @$_POST["maxtoppages"] == "" ? 5 : (int) $_POST["maxtoppages"];
    $maxtoppages = $maxtoppages > 5 ? $maxtoppages = 5 : $maxtoppages = $maxtoppages;
    $maxpageslevels = @$_POST["maxpageslevels"] == "" ? 3 : (int) $_POST["maxpageslevels"];
    $maxpageslevels = $maxpageslevels > 3 ? $maxpageslevels = 3 : $maxpageslevels = $maxpageslevels;
    $maxpagelength = @$_POST["maxpagelength"] == "" ? 10 : (int) $_POST["maxpagelength"];
    $maxpagelength = $maxpagelength > 10 ? $maxpagelength = 10 : $maxpagelength = $maxpagelength;

    // check all the settings
    if (
            $maxpages != ""
            && $maxtoppages != ""
            && $maxpageslevels != ""
            && $maxpagelength != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        $pagex = 0;

        // get a random number of top pages
        $toppages = rand(1, $maxtoppages);

        // loop the number of top pages
        for ($p = 0; $p < $toppages; $p++) {
            $pagex++;

            $id = wmdd_create_page($current_site->domain, 0, $maxpagelength, $pagex);

            if (!$id) {
                $pagex--;
            } else {
                // add the page id to the array
                $pageids[0][] = $id;
            }
        }

        // get random number of sublevels
        $levels = rand(1, $maxpageslevels);

        // if the levels is greater than 1
        if ($levels > 1 && $pageids[0] && count($pageids[0]) > 0) {
            // loop the top level pages
            foreach ($pageids[0] as $pageid) {
                $pagex++;

                $id = wmdd_create_page($current_site->domain, $pageid, $maxpagelength, $pagex);

                if (!$id) {
                    $pagex--;
                } else {
                    // add the page id to the array
                    $pageids[1][] = $id;
                }
            }
        }

        // if the levels is greater than 2
        if ($levels > 2 && $pageids[1] && count($pageids[1]) > 0) {
            // loop the level 1 pages
            foreach ($pageids[1] as $pageid) {
                $pagex++;

                $id = wmdd_create_page($current_site->domain, $pageid, $maxpagelength, $pagex);

                if (!$id) {
                    $pagex--;
                }
            }
        }


        echo '
		<div class="updated">
		<p>' . $pagex . " " . __("demo pages created", "wmdd") . '</p>
		</div>
		';
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

// create demo comments
function wmdd_create_comments() {
    global $wpdb;
    global $current_site;

    // get the comments settings
    $maxcomments = @$_POST["maxcomments"] == "" ? 50 : (int) $_POST["maxcomments"];
    $maxcomments = $maxcomments > 50 ? $maxcomments = 50 : $maxcomments = $maxcomments;

    // check all the settings
    if (
            $maxcomments != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        $commentx = 0;

        // get posts
        $sql = "select id from " . $wpdb->posts . ";";
        $posts = $wpdb->get_results($sql);

        // loop posts
        foreach ($posts as $post) {

            // get a random number of comments
            $comments = rand(0, $maxcomments);

            // loop the number of required comments
            for ($c = 0; $c < $comments; $c++) {
                $commentx++;

                // see if the comment can be inserted
                if (!wmdd_create_comment($current_site->domain, $post->id, $commentx)) {
                    // continue
                    $commentx--;
                }
            }
        }


        echo '
		<div class="updated">
		<p>' . $commentx . ' ' . __("demo comments created", "wmdd") . '</p>
		</div>
		';
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

function wmdd_format_code_lang($code = '') {
    $code = strtolower(substr($code, 0, 2));
    $lang_codes = array(
        'aa' => 'Afar', 'ab' => 'Abkhazian', 'af' => 'Afrikaans', 'ak' => 'Akan', 'sq' => 'Albanian', 'am' => 'Amharic', 'ar' => 'Arabic', 'an' => 'Aragonese', 'hy' => 'Armenian', 'as' => 'Assamese', 'av' => 'Avaric', 'ae' => 'Avestan', 'ay' => 'Aymara', 'az' => 'Azerbaijani', 'ba' => 'Bashkir', 'bm' => 'Bambara', 'eu' => 'Basque', 'be' => 'Belarusian', 'bn' => 'Bengali',
        'bh' => 'Bihari', 'bi' => 'Bislama', 'bs' => 'Bosnian', 'br' => 'Breton', 'bg' => 'Bulgarian', 'my' => 'Burmese', 'ca' => 'Catalan; Valencian', 'ch' => 'Chamorro', 'ce' => 'Chechen', 'zh' => 'Chinese', 'cu' => 'Church Slavic; Old Slavonic; Church Slavonic; Old Bulgarian; Old Church Slavonic', 'cv' => 'Chuvash', 'kw' => 'Cornish', 'co' => 'Corsican', 'cr' => 'Cree',
        'cs' => 'Czech', 'da' => 'Danish', 'dv' => 'Divehi; Dhivehi; Maldivian', 'nl' => 'Dutch; Flemish', 'dz' => 'Dzongkha', 'en' => 'English', 'eo' => 'Esperanto', 'et' => 'Estonian', 'ee' => 'Ewe', 'fo' => 'Faroese', 'fj' => 'Fijjian', 'fi' => 'Finnish', 'fr' => 'French', 'fy' => 'Western Frisian', 'ff' => 'Fulah', 'ka' => 'Georgian', 'de' => 'German', 'gd' => 'Gaelic; Scottish Gaelic',
        'ga' => 'Irish', 'gl' => 'Galician', 'gv' => 'Manx', 'el' => 'Greek, Modern', 'gn' => 'Guarani', 'gu' => 'Gujarati', 'ht' => 'Haitian; Haitian Creole', 'ha' => 'Hausa', 'he' => 'Hebrew', 'hz' => 'Herero', 'hi' => 'Hindi', 'ho' => 'Hiri Motu', 'hu' => 'Hungarian', 'ig' => 'Igbo', 'is' => 'Icelandic', 'io' => 'Ido', 'ii' => 'Sichuan Yi', 'iu' => 'Inuktitut', 'ie' => 'Interlingue',
        'ia' => 'Interlingua (International Auxiliary Language Association)', 'id' => 'Indonesian', 'ik' => 'Inupiaq', 'it' => 'Italian', 'jv' => 'Javanese', 'ja' => 'Japanese', 'kl' => 'Kalaallisut; Greenlandic', 'kn' => 'Kannada', 'ks' => 'Kashmiri', 'kr' => 'Kanuri', 'kk' => 'Kazakh', 'km' => 'Central Khmer', 'ki' => 'Kikuyu; Gikuyu', 'rw' => 'Kinyarwanda', 'ky' => 'Kirghiz; Kyrgyz',
        'kv' => 'Komi', 'kg' => 'Kongo', 'ko' => 'Korean', 'kj' => 'Kuanyama; Kwanyama', 'ku' => 'Kurdish', 'lo' => 'Lao', 'la' => 'Latin', 'lv' => 'Latvian', 'li' => 'Limburgan; Limburger; Limburgish', 'ln' => 'Lingala', 'lt' => 'Lithuanian', 'lb' => 'Luxembourgish; Letzeburgesch', 'lu' => 'Luba-Katanga', 'lg' => 'Ganda', 'mk' => 'Macedonian', 'mh' => 'Marshallese', 'ml' => 'Malayalam',
        'mi' => 'Maori', 'mr' => 'Marathi', 'ms' => 'Malay', 'mg' => 'Malagasy', 'mt' => 'Maltese', 'mo' => 'Moldavian', 'mn' => 'Mongolian', 'na' => 'Nauru', 'nv' => 'Navajo; Navaho', 'nr' => 'Ndebele, South; South Ndebele', 'nd' => 'Ndebele, North; North Ndebele', 'ng' => 'Ndonga', 'ne' => 'Nepali', 'nn' => 'Norwegian Nynorsk; Nynorsk, Norwegian', 'nb' => 'Bokm�l, Norwegian, Norwegian Bokm�l',
        'no' => 'Norwegian', 'ny' => 'Chichewa; Chewa; Nyanja', 'oc' => 'Occitan, Proven�al', 'oj' => 'Ojibwa', 'or' => 'Oriya', 'om' => 'Oromo', 'os' => 'Ossetian; Ossetic', 'pa' => 'Panjabi; Punjabi', 'fa' => 'Persian', 'pi' => 'Pali', 'pl' => 'Polish', 'pt' => 'Portuguese', 'ps' => 'Pushto', 'qu' => 'Quechua', 'rm' => 'Romansh', 'ro' => 'Romanian', 'rn' => 'Rundi', 'ru' => 'Russian',
        'sg' => 'Sango', 'sa' => 'Sanskrit', 'sr' => 'Serbian', 'hr' => 'Croatian', 'si' => 'Sinhala; Sinhalese', 'sk' => 'Slovak', 'sl' => 'Slovenian', 'se' => 'Northern Sami', 'sm' => 'Samoan', 'sn' => 'Shona', 'sd' => 'Sindhi', 'so' => 'Somali', 'st' => 'Sotho, Southern', 'es' => 'Spanish; Castilian', 'sc' => 'Sardinian', 'ss' => 'Swati', 'su' => 'Sundanese', 'sw' => 'Swahili',
        'sv' => 'Swedish', 'ty' => 'Tahitian', 'ta' => 'Tamil', 'tt' => 'Tatar', 'te' => 'Telugu', 'tg' => 'Tajik', 'tl' => 'Tagalog', 'th' => 'Thai', 'bo' => 'Tibetan', 'ti' => 'Tigrinya', 'to' => 'Tonga (Tonga Islands)', 'tn' => 'Tswana', 'ts' => 'Tsonga', 'tk' => 'Turkmen', 'tr' => 'Turkish', 'tw' => 'Twi', 'ug' => 'Uighur; Uyghur', 'uk' => 'Ukrainian', 'ur' => 'Urdu', 'uz' => 'Uzbek',
        've' => 'Venda', 'vi' => 'Vietnamese', 'vo' => 'Volap�k', 'cy' => 'Welsh', 'wa' => 'Walloon', 'wo' => 'Wolof', 'xh' => 'Xhosa', 'yi' => 'Yiddish', 'yo' => 'Yoruba', 'za' => 'Zhuang; Chuang', 'zu' => 'Zulu');
    $lang_codes = apply_filters('lang_codes', $lang_codes, $code);
    return strtr($code, $lang_codes);
}

// create demo links
function wmdd_create_links() {
    global $wpdb;
    global $current_site;

    // get the links settings
    $maxbloglinks = @$_POST["maxbloglinks"] == "" ? 25 : (int) $_POST["maxbloglinks"];
    $maxbloglinks = $maxbloglinks > 100 ? $maxbloglinks = 100 : $maxbloglinks = $maxbloglinks;

    // check all the settings
    if (
            $maxbloglinks != ""
    ) {
        $go = true;
    } else {
        $go = false;
    }

    // if the settings are OK
    if ($go) {

        $linkx = 0;

        // get a random number of bookmarks
        $links = rand(0, $maxbloglinks);

        // loop the number of required bookmarks
        for ($l = 0; $l < $links; $l++) {
            $linkx++;
            if (!wmdd_create_link($current_site->domain, $linkx)) {
                // continue
                $linkx--;
            }
        }


        echo '
		<div class="updated">
		<p>' . $linkx . " " . __("demo links created", "wmdd") . '</p>
		</div>
		';
    } else {

        echo '
		<div class="error">
		<p>' . __("Some of your settings were not valid. Please check all the settings below.", "wmdd") . '</p>
		</div>
		';
    }
}

// ======================================================
// Data creation functions
// ======================================================
// get the domain for this blog
function wmdd_blog_domain() {
    $u = get_bloginfo("wpurl");
    $u = str_replace("http://", "", $u);
    $u = str_replace("https://", "", $u);
    $parts = explode("/", $u);
    $domain = $parts[0];
    return $domain;
}

// create a bookmark
function wmdd_create_link($blogdomain, $linkx) {
    $link = array(
        'link_id' => 0,
        'link_name' => 'Bookmark ' . $linkx,
        'link_url' => 'http://' . $blogdomain . '/#bookmark' . $linkx,
        'link_rating' => 0
    );
    return wp_insert_link($link);
}

// create a comments
function wmdd_create_comment($blogdomain, $postid, $commentx) {
    $commentcontent = wmdd_generate_random_text(1000);
    $time = current_time('mysql', $gmt = 0);
    $comment = array(
        'comment_post_ID' => $postid,
        'comment_author' => 'Commenter ' . $commentx,
        'comment_author_email' => 'commenter@' . $blogdomain,
        'comment_author_url' => 'http://commenter.url',
        'comment_content' => $commentcontent,
        'comment_type' => '',
        'comment_parent' => 0,
        'user_ID' => 0,
        'comment_author_IP' => '127.0.0.1',
        'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
        'comment_date' => $time,
        'comment_date_gmt' => $time,
        'comment_approved' => 1,
    );
    return wp_insert_comment($comment);
}

// create a category
function wmdd_create_category($blogdomain, $categoryx) {
    global $wmdd_categories;
    $cat = $wmdd_categories[array_rand($wmdd_categories, 1)];
    return wp_create_category($cat);
}

// create a post
function wmdd_create_post($blogdomain, $maxpostlength, $postx) {
    global $wmdd_demopost;
    // get a random user for the current blog
    $userid = wmdd_random_blog_user_id();
    // generate the random post data
    $postcontent = wmdd_generate_html(rand(1, $maxpostlength));
    // generate random date (thanks to derscheinwelt for this: http://wordpress.org/support/topic/plugin-demo-data-creator-random-creation-date)
    $randdate = date('Y-m-d H:i:s', strtotime(mt_rand(-1095, 10) . ' days' . mt_rand(0, 1440) . 'minutes'));
    // generate array of category ids
    $cats = wmdd_random_categories($blogdomain);
    $post = array('post_status' => 'live',
        'post_date' => $randdate,
        'post_type' => 'post',
        'post_author' => $userid,
        'ping_status' => 'open',
        'post_parent' => 0,
        'menu_order' => 0,
        'to_ping' => '',
        'pinged' => '',
        'post_password' => '',
        'guid' => 'http://' . $blogdomain . '/post' . $postx,
        'post_content_filtered' => '',
        'post_excerpt' => '',
        'import_id' => 0,
        'post_status' => 'publish',
        'post_title' => $wmdd_demopost . $postx,
        'post_content' => $postcontent,
        'post_excerpt' => '',
        'post_category' => $cats);
    return wp_insert_post($post);
}

// create a page
function wmdd_create_page($blogdomain, $parentid, $maxpagelength, $pagex) {
    global $wmdd_pagetitle;
    $pagecontent = wmdd_generate_html(rand(1, $maxpagelength));
    $page = array('post_status' => 'live',
        'post_type' => 'page',
        'post_author' => 1,
        'ping_status' => 'open',
        'post_parent' => $parentid,
        'menu_order' => 0,
        'to_ping' => '',
        'pinged' => '',
        'post_password' => '',
        'guid' => 'http://' . $blogdomain . '/page' . $pagex,
        'post_content_filtered' => '',
        'post_excerpt' => '',
        'import_id' => 0,
        'post_status' => 'publish',
        'post_title' => $wmdd_pagetitle . $pagex,
        'post_content' => $pagecontent,
        'post_excerpt' => '',
        'post_category' => '');
    return wp_insert_post($page);
}

// create a blog (taken from /wp-admin/wpmu-edit.php)
function wmdd_create_blog($newid, $blogdomain, $blogname, $user_id) {
    // if this is WPMU
    if ((wmdd_is_multisite() || wmdd_is_mu()) && $newid > 1) {
        global $current_site;
        global $current_user;
        global $wpdb;
        global $wp_queries;

        $wp_queries = str_replace($wpdb->base_prefix . "1_", $wpdb->base_prefix . $newid . "_", $wp_queries);
        $wp_queries = str_replace($wpdb->base_prefix . ($newid - 1) . "_", $wpdb->base_prefix . $newid . "_", $wp_queries);


        if (is_subdomain_install()) {
            $newdomain = $blogdomain . '.' . preg_replace('|^www\.|', '', $current_site->domain);
            $path = $current_site->path;
        } else {
            $newdomain = $current_site->domain;
            $path = $current_site->path . $blogdomain . '/';
        }

        // install this blog
        $meta = apply_filters('signup_create_blog_meta', array('lang_id' => 1, 'public' => 1));
        $id = wpmu_create_blog($newdomain, $path, $blogname, $user_id, $meta, $current_site->id);

        // in case the tables haven't been created, create them
        $post_table = $wpdb->base_prefix . $id . "_posts";
        $post_table_exists = $wpdb->get_results("show tables like '" . $post_table . "';");
        if (!$post_table_exists) {
            print "<p>Table <code>" . $post_table . "</code> was not created ... attempting to create blog tables manually</p>";
            global $wp_queries;
            $wp_queries = str_replace($wpdb->base_prefix, $wpdb->base_prefix . $id . "_", $wp_queries);
            dbDelta($wp_queries);
            switch_to_blog($id);
            populate_options();
            restore_current_blog();
        }

        if (!is_wp_error($id)) {
            if (get_user_option($user_id, 'primary_blog') == 1)
                update_user_option($user_id, 'primary_blog', $id, true);

            // add the user to the blog
            add_user_to_blog($id, $user_id, 'administrator');

            return $id;
        } else {
            return false;
        }
    } else {
        return true;
    }
}

// ======================================================
// Helper functions
// ======================================================
// get group locale
function wmdd_code($locale) {
    $code = strtolower(substr($locale, 0, 2));
    if (in_array($code, array('fr', 'de', 'ru'))) {// have characters unique to them
        return $code;
    }
    return 'en';
}

// get a random user id
function wmdd_random_user_id($id = 0) {
    global $wpdb;
    return $wpdb->get_var("select id from " . $wpdb->users . " where " . $wpdb->escape($id) . " = 0 or id <> " . $wpdb->escape($id) . " order by rand() limit 1;");
}

// get a random user id for the current blog
function wmdd_random_blog_user_id($id = 0) {
    $user_fields = array('ID');
    $wp_user_query = new WP_User_Query(array('fields' => $user_fields));
    $rs = $wp_user_query->results;
    $x = array_rand($rs);
    return $rs[$x]->ID;
}

// delete demo data
function wmdd_delete() {
    global $wpdb;
    global $current_site;

    echo '
	<div class="updated">
	<h2>' . __("Deleting demo data...", "wmdd") . '</h2>
	<ul>
	';

     // if this is WPMU/MultiSite
    if (wmdd_is_multisite() || wmdd_is_mu()) {
        switch_to_blog(BLOG_ID_CURRENT_SITE);
		// count blogs
        $sql = "select count(blog_id) from " . $wpdb->blogs . " where blog_id > 1;";
        $blogcount = $wpdb->get_var($sql);
        // delete blogs
        $sql = "select blog_id from " . $wpdb->blogs . " where blog_id > 1;";
        $blogs = $wpdb->get_results($sql);
        foreach ($blogs as $blog) {
		    if(BLOG_ID_CURRENT_SITE!=$blog->blog_id){
			     wpmu_delete_blog($blog->blog_id, true);
			}
        }
    }
	
    // delete all pages except page ID 1
    $sql = "delete from " . $wpdb->posts . " where id > 1;";
    $posts = $wpdb->query($sql);
    if ($posts === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete user meta
    $sql = "delete from " . $wpdb->usermeta . " where user_id > 1;";
    $users = $wpdb->query($sql);
    if ($users === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // count users
    $sql = "select count(id) from " . $wpdb->users . " where id > 1;";
    $usercount = $wpdb->get_var($sql);


    // delete blog 1 comments
    $sql = "delete from " . $wpdb->comments . ";";
    $comments = $wpdb->query($sql);
    if ($comments === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete blog 1 links
    $sql = "delete from " . $wpdb->links . ";";
    $links = $wpdb->query($sql);
    if ($links === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete blog 1 terms
    $sql = "delete from " . $wpdb->terms . " where term_id > 2;";
    $terms = $wpdb->query($sql);
    if ($terms === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete blog 1 term taxonomy
    $sql = "delete from " . $wpdb->term_taxonomy . " where term_id > 2;";
    $term_taxonomy = $wpdb->query($sql);
    if ($term_taxonomy === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete blog 1 term relationships
    $sql = "delete from " . $wpdb->term_relationships . " where term_taxonomy_id > 2;";
    $term_relationships = $wpdb->query($sql);
    if ($term_relationships === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // delete registration log (if the table exists)
    if ($wpdb->registration_log != "") {
        $sql = "delete from " . $wpdb->registration_log . ";";
        $registration_log = $wpdb->query($sql);
        if ($registration_log === false) {
            echo '<li>Error with SQL: ' . $sql . '</li>';
        }
    }

    // delete site categories (if the table exists)
    if ($wpdb->sitecategories != "") {
        $sql = "delete from " . $wpdb->sitecategories . " where cat_ID > 2;";
        $sitecategories = $wpdb->query($sql);
        if ($sitecategories === false) {
            echo '<li>Error with SQL: ' . $sql . '</li>';
        }
    }

    // if this is WPMU/MultiSite
    if (wmdd_is_multisite() || wmdd_is_mu()) {

        // alter auto integer
        $sql = "ALTER TABLE " . $wpdb->blogs . " AUTO_INCREMENT = 2;";
        $users = $wpdb->query($sql);

        echo '<li>' . $blogcount . ' ' . __("blogs deleted", "wmdd") . '</li>
		';
    } else {

        echo '<li>' . __("Blog data deleted", "wmdd") . '</li>
		';
    }


    // delete users
    $sql = "delete from " . $wpdb->users . " where id > 1;";
    $users = $wpdb->query($sql);
    if ($users === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    // alter auto integer
    $sql = "ALTER TABLE " . $wpdb->users . " AUTO_INCREMENT = 2;";
    $users = $wpdb->query($sql);
    if ($users === false) {
        echo '<li>Error with SQL: ' . $sql . '</li>';
    }

    echo '<li>' . $usercount . ' ' . ("users deleted") . '</li>
	';

    echo '
	</ul>
	</div>
	';
}

// watch for a form action
function wmdd_watch_form() {
    // if submitting form
    if (is_array($_POST) && count($_POST) > 0 && isset($_POST["action"])) {
        set_time_limit(300);

        if ($_POST["action"] == "create") {
            wmdd_create();
        } else {
            echo '
			<!-- No POST action of "CREATE" -->
			';
        }

        if ($_POST["action"] == "delete") {
            wmdd_delete();
        }
    } else {
        echo '
		<!-- No POST action -->
		';
    }
}

// ======================================================
// Admin forms
// ======================================================
// write out the form
function wmdd_form() {
    global $current_site;
    global $wpdb, $wmdd_languages;

    // detect BuddyPress

    echo '
	<div class="wrap">
	';


    wmdd_watch_form();

    $formpage = "tools";
    // WP3
    if (wmdd_is_multisite()) {
        $formpage = "ms-admin";
    } else if (wmdd_is_mu()) {
        $formpage = "wpmu-admin";
    }

    $domain = $current_site->domain;
    if ($domain == "") {
        $domain = $_SERVER["SERVER_NAME"];
    }

    echo '
	
		<h2>' . __("Create demo data", "wmdd") . '</h2>
		<p>' . __("Warning: this may take some time if you are creating a lot of data.", "wmdd") . '</p>
		';
    foreach ($wmdd_languages as $lang) {
        if ($lang == 'en_US') { // American English
            $flag = true;
            $ae = __('English');
            $lang_list[$ae] = '<option value="' . esc_attr($lang) . '"' . selected(get_locale(), $lang, false) . '> ' . $ae . '</option>';
        } else {
            $translated = wmdd_format_code_lang($lang);
            $lang_list[$translated] = '<option value="' . esc_attr($lang) . '"' . selected(get_locale(), $lang, false) . '> ' . esc_html($translated) . '</option>';
        }
    }
    if ($flag === false) // WordPress english
        $lang_list[] = '<option value=""' . selected(get_locale(), '', false) . '>' . __('English') . "</option>";

    // Order by name
    uksort($lang_list, 'strnatcasecmp');
    echo'
	    <div id="createusersoutput"></div>
		<div id="createblogsoutput"></div>
	    <div id="createdataoutput"></div>
		<div id="createcommentsoutput"></div>
		<div id="createpagesoutput"></div>
		<div id="createpostsoutput"></div>
		<div id="createcategoriesoutput"></div>
		<div id="deleteoutput"></div>';
    // if this is WPMU/MultiSite
    if (wmdd_is_multisite() || wmdd_is_mu()) {

        //blog list
        $blogs = get_blogs_of_user(get_current_user_id());
        $blog_list = "";
        foreach ($blogs as $blog) {
            $blog_list.='<option value="' . $blog->userblog_id . '">' . $blog->blogname . '</option>';
        }
        echo '
		<form action="' . $formpage . '.php?page=wmdd_form&amp;create=blog" method="post" class="wmdd" id="createblogsform">
		<fieldset>
		
			<h4>' . __("Create New Demo Blog", "wmdd") . '</h4>
			
			<p><label for="bloglang">' . __("Blog language", "wmdd") . '</label>
			<select name="WPLANG" id="WPLANG">
				 ' . implode("\n\t", $lang_list) . '
			</select>
			
			<p><label for="users">' . __("Number of users (max 1000)", "wmdd") . '</label>
			<input type="text" name="users" id="users" value="100" /></p>
			
			<p><label for="useremailtemplate">' . __("User email template (with [x] for the user ID)", "wmdd") . '</label>
			<input type="text" name="useremailtemplate" id="useremailtemplate" value="demouser[x]@' . $domain . '" class="text" /></p>
			
			
		</fieldset>
		<p><label for="createblogs">' . __("Create blogs", "wmdd") . '</label>
			<input type="hidden" name="create" value="blog" />
			<input type="hidden" name="action" value="create" />
			<button type="submit" class="button wmddbutton" id="createblogs">' . __("Create blog", "wmdd") . '</button></p>
		</form>';
    }
	$user_option='';
    if (wmdd_is_multisite() || wmdd_is_mu()) { //Blog language will determine the language of data to insert
        $lang_part = '
		<label for="selectblog">' . __("Select Blog to insert data", "wmdd") . '</label>
			<select name="selectblog" id="selectblog">
			' . $blog_list . '
			</select>';
    } else {
        $lang_part = '
		<label for="selectblog">' . __("Select Language of Demo Content to use", "wmdd") . '</label>
			<select name="WPLANG" id="WPLANG">
				 ' . implode("\n\t", $lang_list) . '
			</select>';

        $user_option = '<fieldset><p><label for="users">' . __("Number of users (max 1000)", "wmdd") . '</label>
			<input type="text" name="users" id="users" value="100" /></p></fieldset>';
    }

    echo '
		
		<form action="' . $formpage . '.php?page=wmdd_form&amp;create=blogdata" method="post" class="wmdd" id="createdataform">
		
		<h4>' . __("Create Blog Data", "wmdd") . '</h4>
		
		<fieldset><p>' . $lang_part . '</p></fieldset>
		' . $user_option . '
		<fieldset>
		
			<p><label for="maxblogcategories">' . __("Maximum number of categories (max 25)", "wmdd") . '</label>
			<input type="text" name="maxblogcategories" id="maxblogcategories" value="10" /></p>
			
		</fieldset>
		
		<fieldset>
		
			<p><label for="maxblogposts">' . __("Maximum number of posts (max 100)", "wmdd") . '</label>
			<input type="text" name="maxblogposts" id="maxblogposts" value="50" /></p>
			
			<p><label for="maxpostlength">' . __("Maximum number of blog post paragraphs (min 1, max 50)", "wmdd") . '</label>
			<input type="text" name="maxpostlength" id="maxpostlength" value="10" /></p>
			
		</fieldset>
		
		<fieldset>
		
			<p><label for="maxpages">' . __("Maximum number of pages (max 50)", "wmdd") . '</label>
			<input type="text" name="maxpages" id="maxpages" value="25" /></p>
			
			<p><label for="maxtoppages">' . __("Maximum number of top-level pages (max 10)", "wmdd") . '</label>
			<input type="text" name="maxtoppages" id="maxtoppages" value="5" /></p>
			
			<p><label for="maxpageslevels">' . __("Maximum number of level to nest pages (max 5)", "wmdd") . '</label>
			<input type="text" name="maxpageslevels" id="maxpageslevels" value="3" /></p>
			
			<p><label for="maxpagelength">' . __("Maximum number of blog page paragraphs (min 1, max 50)", "wmdd") . '</label>
			<input type="text" name="maxpagelength" id="maxpagelength" value="10" /></p>
			
			<p><label for="createpages">' . __("Create pages", "wmdd") . '</label>
			
		</fieldset>
			
		<fieldset>
		
			<p><label for="maxcomments">' . __("Maximum number of comments per post (max 50)", "wmdd") . '</label>
			<input type="text" name="maxcomments" id="maxcomments" value="10" /></p>
			
		</fieldset>
			
		<fieldset>
		
			<p><label for="maxbloglinks">' . __("Maximum number of links in blogroll (max 100)", "wmdd") . '</label>
			<input type="text" name="maxbloglinks" id="maxbloglinks" value="25" /></p>
			
		</fieldset>
		
		<p><label for="createlinks">' . __("Create Data", "wmdd") . '</label>
			<input type="hidden" name="create" value="blogdata" />
			<input type="hidden" name="action" value="create" />
			<button type="submit" class="button wmddbutton" id="createdata">' . __("Create Data", "wmdd") . '</button></p>
		
		</form>';



    echo '		
		<h3>Delete demo data</h3>
		
		<form action="' . $formpage . '.php?page=wmdd_form" method="post" class="wmdd" id="deleteform">
		<fieldset>
		
		';
    echo '
			<p>Delete all user and blog data in your database.</p>
			<p><strong>WARNING: This will delete ALL data.</strong></p>
			';
    echo '
			<p><label for="delete">Delete demo data</label>
			<input type="hidden" name="action" value="delete" />
			<button type="submit" class="button wmddbutton" name="delete" id="delete">Delete</button></p>
		
		</fieldset>
		</form>';


    echo '
	</div>
	<div class="promo"><p><a href="http://zanto.org"><img src="'.plugin_dir_url(__FILE__).'/zanto-logo.png"/></a>'.__('This plugin was braught to you by Zanto: Create Multilingual WordPress Blogs with <a href="http://zanto.org">Zanto Wordpress Translation Plugin </a>','wmdd').'</p></div>
	';
}

// ======================================================
// Content functions
// ======================================================
// return a random array of category ids
function wmdd_random_categories() {
    $limit = rand(1, 6);
    global $wpdb;
    return $wpdb->get_col("select term_id from " . $wpdb->terms . " order by rand() limit " . $limit . ";");
}

// return a random contact first name
function wmdd_firstname() {
    $wmdd_firstnames = explode(",", "Alan,Albert,Allen,Amy,Andrew,Angela,Anita,Ann,Anne,Annette,Anthony,Arthur,Barbara,Barry,Beth,Betty,Beverly,Bill,Billy,Bobby,Bonnie,Bradley,Brenda,Brian,Bruce,Bryan,Carl,Carol,Carolyn,Catherine,Cathy,Charles,Cheryl,Chris,Christine,Christopher,Cindy,Connie,Craig,Curtis,Cynthia,Dale,Daniel,Danny,Darlene,Darryl,David,Dawn,Dean,Debbie,Deborah,Debra,Denise,Dennis,Diana,Diane,Donald,Donna,Dorothy,Douglas,Edward,Elizabeth,Ellen,Eric,Frank,Gail,Gary,George,Gerald,Glenn,Gloria,Greg,Gregory,Harold,Henry,Jack,Jacqueline,James,Jane,Janet,Janice,Jay,Jean,Jeff,Jeffery,Jeffrey,Jennifer,Jerry,Jill,Jim,Jimmy,Joan,Joanne,Joe,John,Johnny,Jon,Joseph,Joyce,Judith,Judy,Julie,Karen,Katharine,Kathleen,Kathryn,Kathy,Keith,Kelly,Kenneth,Kevin,Kim,Kimberly,Larry,Laura,Laurie,Lawrence,Leslie,Linda,Lisa,Lori,Lynn,Margaret,Maria,Mark,Martha,Martin,Mary,Matthew,Michael,Michele,Michelle,Mike,Nancy,Pamela,Patricia,Patrick,Paul,Paula,Peggy,Peter,Philip,Phillip,Ralph,Randall,Randy,Raymond,Rebecca,Renee,Rhonda,Richard,Rick,Ricky,Rita,Robert,Robin,Rodney,Roger,Ronald,Ronnie,Rose,Roy,Russell,Ruth,Samuel,Sandra,Scott,Sharon,Sheila,Sherry,Shirley,Stephanie,Stephen,Steve,Steven,Susan,Suzanne,Tammy,Teresa,Terri,Terry,Theresa,Thomas,Tim,Timothy,Tina,Todd,Tom,Tony,Tracy,Valerie,Vicki,Vickie,Vincent,Walter,Wanda,Wayne,Wendy,William,Willie");
    return $wmdd_firstnames[array_rand($wmdd_firstnames, 1)];
}

// return a random contact last name
function wmdd_lastname() {
    $wmdd_lastnames = explode(",", "Smith,Johnson,Williams,Jones,Brown,Davis,Miller,Wilson,Moore,Taylor,Anderson,Thomas,Jackson,White,Harris,Martin,Thompson,Garcia,Robinson,Clark,Lewis,Lee,Walker,Hall,Allen,Young,King,Wright,Lopez,Hill,Scott,Green,Adams,Baker,Nelson,Carter,Mitchell,Perez,Roberts,Turner,Phillips,Campbell,Parker,Evans,Edwards,Collins,Stewart,Morris,Rogers,Reed,Cook,Morgan,Bell,Murphy,Bailey,Rivera,Cooper,Richardson,Cox,Howard,Ward,Peterson,Gray,James,Watson,Brooks,Kelly,Sanders,Price,Bennett,Wood,Barnes,Ross,Henderson,Coleman,Jenkins,Perry,Powell,Long,Patterson,Hughes,Flores,Washington,Butler,Simmons,Foster,Bryant,Alexander,Russell,Griffin,Hayes,Myers,Ford,Hamilton,Graham,Sullivan,Wallace,Woods,Cole,West,Jordan,Owens,Reynolds,Fisher,Ellis,Harrison,Gibson,Mcdonald,Cruz,Marshall,Gomez,Murray,Freeman,Wells,Webb,Simpson,Stevens,Tucker,Porter,Hunter,Hicks,Crawford,Henry,Boyd,Mason,Morales,Kennedy,Warren,Dixon,Ramos,Reyes,Burns,Gordon,Shaw,Holmes,Rice,Robertson,Hunt,Black,Daniels,Palmer,Mills,Nichols,Grant,Knight,Ferguson,Rose,Stone,Hawkins,Dunn,Perkins,Hudson,Spencer,Gardner,Stephens,Payne,Pierce,Berry,Matthews,Arnold,Wagner,Willis,Ray,Watkins,Olson,Carroll,Duncan,Snyder,Hart,Cunningham,Bradley,Lane,Andrews,Ruiz,Harper,Fox,Riley,Armstrong,Carpenter,Weaver,Greene,Lawrence,Elliott,Chavez,Sims,Austin,Peters,Kelley,Franklin,Lawson,Fields,Ryan,Schmidt,Carr,Castillo,Wheeler,Chapman,Oliver,Montgomery,Richards,Williamson,Johnston,Banks,Meyer,Bishop,Mccoy,Howell,Morrison,Hansen,Garza,Harvey,Little,Burton,Stanley,Nguyen,George,Jacobs,Reid,Kim,Fuller,Lynch,Dean,Gilbert,Garrett,Welch,Larson,Frazier,Burke,Hanson,Day,Moreno,Bowman,Fowler");
    return $wmdd_lastnames[array_rand($wmdd_lastnames, 1)];
}

// return a random blog name
function wmdd_blogname() {
    global $wmdd_names1, $wmdd_names2;
    return $wmdd_names1[array_rand($wmdd_names1, 1)] . ' ' . $wmdd_names2[array_rand($wmdd_names2, 1)];
}

// generate random html content
function wmdd_generate_html($maxblocks = 4) {
    global $wmdd_head, $wmdd_htmlstr;

    $htmlstr = $wmdd_htmlstr;
    $htmlstr = explode("<!--break-->", $htmlstr);
    $blocks = count($htmlstr) - 1;
    if ($maxblocks > count($htmlstr)) {
        $maxblocks = $blocks + 1;
    }

    $out = "";

    for ($x = 0; $x < $maxblocks; $x++) {
        $out .= $htmlstr[rand(0, $blocks)] . "\n\n";
    }
    return $out;
}

// generate random text content
function wmdd_generate_random_text($maxlength, $randomstart = true) {
    global $wmdd_str;
    $len = rand(0, $maxlength);

    if (!$randomstart) {

        $out = trim(substr($wmdd_str, 0, $len));
    } else {

        $out = trim(substr($wmdd_str, rand(0 + $len, strlen($wmdd_str) - $len), $len));
    }

    return $out;
}

//download language files
function wmdd_download_lang($language) {
    $tmp = download_url(wmdd_get_lang_location($language));
    if (is_wp_error($tmp)) {
        @unlink($file_array['tmp_name']);
        return false;
    }
    $filename = $language;
    $tmppath = pathinfo($tmp);
    $new = $tmppath['dirname'] . "/" . $filename . "." . $tmppath['extension'];
    rename($tmp, $new);
    $tmp = $new;
    //basename($url );

    $file_array = array(
        'name' => $language . '.mo',
        'tmp_name' => $tmp
    );

    $_POST['action'] = 'wp_handle_sideload';
    add_filter('upload_dir', 'mo_upload_dir');
    $id = wp_handle_sideload($file_array, 0);
    remove_filter('upload_dir', 'mo_upload_dir');

    // Check for handle sideload errors.
    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return false;
    }

    return true;
}

function mo_upload_dir($upload) {
    // use wp-content/languages 
    $upload['path'] = WP_CONTENT_DIR . '/languages';
    $upload['url'] = content_url() . '/languages';
    return $upload;
}

function wmdd_get_lang_location($lang) {
    if ($lang == 'fr_FR') {
        $tagged_version = '3.6';
    } elseif ($lang == 'ru_RU') {
        $tagged_version = '3.8';
    } else {
        global $wp_version;
        $tagged_version = $wp_version;
    }
    return $location = "http://svn.automattic.com/wordpress-i18n/" . $lang . "/tags/" . $tagged_version . "/messages/" . $lang . ".mo";
}

// This is to enable recognition of .mo files by wordpress uploading system.
function add_custom_mimes($existing_mimes=array()) {
    $existing_mimes['mo'] = 'application/octet-stream';
    return $existing_mimes;
}

add_filter('upload_mimes', 'add_custom_mimes');

function wmdd_need_lang_files($lang=null) {
    $i_connectivity = wmdd_check_internet_connection();
    if ($i_connectivity) {
        $languages = get_available_languages();
        if ($lang == null) {
            $lang = get_option('WPLANG');
        } 
        if ($lang !== 'en_US' && $lang != '') {
            if (!empty($languages)) {
                if (!in_array($lang, $languages)) {
                    wmdd_download_lang($lang);
                }
            } else {
                wmdd_download_lang($lang);
            }
        }
    }
    return;
}

function wmdd_check_internet_connection($sCheckHost = 'www.google.com') {
    return (bool) @fsockopen($sCheckHost, 80, $iErrno, $sErrStr, 5);
}

function wmdd_get_locale($locale) {
    $locale_temp = get_option("WPLANG");
    if (!empty($locale_temp)) {
        $locale = $locale_temp;
    }
    return $locale;
}

add_filter('locale', 'wmdd_get_locale');
?>