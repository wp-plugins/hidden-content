<?php
/*
Plugin Name: Hidden Content
Plugin URI: http://www.infine.ru/support/wp_plugins/hidden-content.htm
Description: Some blocks of the text can be shown/hidden for bots, logged-in users or all visitors (in posts, pages and comments)
Author: Nikolay aka 'cmepthuk'</a>, перевод от <a href="http://sonika.ru/blog/">Sonika
Author URI: http://www.infine.ru/
Version: 0.3
*/

/*
  Copyright © 2008 by Nikolay (aka cmepthuk), Sonika translate & english support
  THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

### Use WordPress 2.6 Constants
if (!defined('WP_CONTENT_DIR')) {
	define( 'WP_CONTENT_DIR', ABSPATH.'wp-content');
}
if (!defined('WP_CONTENT_URL')) {
	define('WP_CONTENT_URL', get_option('siteurl').'/wp-content');
}
if (!defined('WP_PLUGIN_DIR')) {
	define('WP_PLUGIN_DIR', WP_CONTENT_DIR.'/plugins');
}
if (!defined('WP_PLUGIN_URL')) {
	define('WP_PLUGIN_URL', WP_CONTENT_URL.'/plugins');
}

### Hidden Content FOLDER URL
if (!defined('HC_FOLDER_URL')) {
	define('HC_FOLDER_URL', WP_CONTENT_URL.'/plugins/hidden-content');
}

### Create Text Domain For Translations
add_action('init', 'hidecontent_textdomain');
function hidecontent_textdomain() {
	if (!function_exists('wp_print_styles')) {
		load_plugin_textdomain('hiddenContent', 'wp-content/plugins/hidden-content');
	} else {
		load_plugin_textdomain('hiddenContent', false, 'hidden-content');
	}
}

### Hide Content OPTions
$hcopt = array(op =>       'hiddenContent_', # Option Prefix
               fp =>       'hiddenContent-', # Form Prefix
               edtBtnId => 'hiddenContent'   # Editor Button ID
               );
### Hide Content MODes
$hcmod = array(robots =>   'robots',
               users  =>   'users',
               guests =>   'guests',
               rss =>      'rss',
               opentag  => 'opentag',
               closetag => 'closetag',
               message =>  'message');
### Hide Content DEFault values
$hcdef = array(opentag => 'beginHide',
               closetag => 'endHide',
               message => '<!-- Access denited -->');
### Array With Bot Names (for more, goto http://ru.wikipedia.org/wiki/User_Agent)
$botsNames = array('yandex', 'webalta', 'rambler', 'googlebot', 'aport',
                   'yahoo', 'msnbot', 'turtle', 'mail.ru', 'omsktele',
                   'cuill.com', 'yetibot', 'picsearch', 'EltaIndexer',
                   'Baiduspider', 'antabot', 'Ask Jeeves', 'Gigabot',
                   'gsa-crawler', 'MihalismBot', 'OmniExplorer_Bot', 'Pagebull',
                   'Scooter',
                   'bot', 'spider', 'unknown');

class hc_options {
  function get($name) {
    global $hcopt;
    return get_option($hcopt[op].$name);
  }
  function getA() {
    global $hcopt, $hcmod;
    static $res = array();
    foreach($hcmod as $mode)
      $res[$mode] = get_option($hcopt[op].$mode);
    return $res;
  }

  function set($name, $value = 'false') {
    global $hcopt;
    update_option($hcopt[op].$name, $value);
  }

  function install() {
    global $hcopt, $hcmod, $hcdef;
    static $descr = 'Hide Content - Option for ';
    add_option($hcopt[op].$hcmod[robots],  'true',          $descr.$hcmod[robots],  'yes');
    add_option($hcopt[op].$hcmod[users],   'true',          $descr.$hcmod[users],   'yes');
    add_option($hcopt[op].$hcmod[guests],  'false',         $descr.$hcmod[guests],  'yes');
    add_option($hcopt[op].$hcmod[rss],     'false',         $descr.$hcmod[rss],     'yes');
    add_option($hcopt[op].$hcmod[opentag], $hcdef[opentag], $descr.$hcmod[opentag], 'yes');
    add_option($hcopt[op].$hcmod[closetag],$hcdef[closetag],$descr.$hcmod[closetag],'yes');
    add_option($hcopt[op].$hcmod[message], $hcdef[message], $descr.$hcmod[message], 'yes');
  }
  function uninstall() {
    global $hcopt, $hcmod;
    foreach($hcmod as $mode)
      delete_option($hcopt[op].$mode);
  }

  function init() {
    global $hcopt, $hcmod;
    static $err = false;
    foreach($hcmod as $mode)
      if(get_option($hcopt[op].$mode) == '') {
        $err = true;
        break;
      }
    if($err) $this->install();
  }
}
                    ###############################
                     $hc_options = new hc_options;
                     $hc_options->init();
                     unset($hc_options);
                    ###############################

### Detect search robot
function is_searchBot() {
  global $botsNames;
  static $userAgent = '', $isBot = false;
  $userAgent = strtolower($_SERVER["HTTP_USER_AGENT"]);
  //print $userAgent; // FOR DEBUG
  /* first check - by useragent */
  foreach($botsNames as $botName) {
    $botName = strtolower($botName);
    //print '<br />$botName='.$botName.';'; // FOR DEBUG
    if(preg_match('/'.$botName.'/is', $userAgent)) {
      $isBot = true;
      break;
    }
  }
  /* second - find empty browser header */
  if($_SERVER['HTTP_ACCEPT'] == '' or
     $_SERVER['HTTP_ACCEPT_ENCODING'] == '' or
     $_SERVER['HTTP_ACCEPT_LANGUAGE'] == '' or
     $_SERVER['HTTP_CONNECTION'] == '') $isBot = true;
  /* return result */
  return $isBot;
}

### Function for hide/show content
function hc_hideContent($content = '') {
  global $hcmod, $hcopt;
  static $hc_options, $show = false, $message = '';
  $hc_options = new hc_options;
  $open = $hc_options->get($hcmod[opentag]);
  $close = $hc_options->get($hcmod[closetag]);
  $message = $hc_options->get($hcmod[message]);
  if(preg_match('/<!--(.*?)'.$open.'(.*?)-->/is', $content)) {
    global $user_ID, $hcopt;
    /* if robot */
    if(is_searchBot() === true && $hc_options->get($hcmod[robots]) === 'true')
      $show = true;
      /* if guest */
      elseif(!$user_ID && $hc_options->get($hcmod[guests]) === 'true')
        $show = true;
        /* if user */
        elseif($user_ID && $hc_options->get($hcmod[users]) === 'true')
          $show = true;

    if(!is_feed()) {
    # For posts
    if($show) $replace = "$3";
      else $replace = $message;
    } else {
    # For FEED
    if($hc_options->get($hcmod[rss]) === 'true') $replace = "$3";
      else $replace = $message;
    }

    $content = preg_replace("/<!--(.*?)".$open."(.*?)-->(.*?)<!--(.*?)".$close."(.*?)-->/is", $replace, $content);
    }
  return $content;
}

### Admin page
add_action('admin_head', 'hc_optionsPageCss');
function hc_optionsPageCss() { ?>
<style type="text/css" media="all">
.hc_css_option {
  position: relative;
  left: -23px;
  padding-left: 23px;
  background: #eaf3fa url('<?php print(HC_FOLDER_URL); ?>/images/admin/cross.gif') left center repeat-y;
}
.hc_css_option small {
  font-weight: normal !important;
}

.hc_css_robot, .hc_css_users, .hc_css_guests, .hc_css_rss {
  /* for future */
}

</style>
<?php }

function hc_optionsPage() {
  global $hcopt, $hcmod, $botsNames, $hcdef;
  static $hc_options, $updated = false, $optionData = '';
  function fill_empty_post_value($what, $value) {
    global $hcopt, $hcmod;
    if(empty($_POST[$hcopt[fp].$hcmod[$what]])) $_POST[$hcopt[fp].$hcmod[$what]] = $value;
  }
  $hc_options = new hc_options;

  if($_POST['act'] == 'set'){
    fill_empty_post_value($hcmod[opentag], $hcdef[opentag]);
    fill_empty_post_value($hcmod[closetag],$hcdef[closetag]);
    fill_empty_post_value($hcmod[robots],  'false');
    fill_empty_post_value($hcmod[users],   'false');
    fill_empty_post_value($hcmod[guests],  'false');
    fill_empty_post_value($hcmod[rss],     'false');
  }

  if(!empty($_POST))
  foreach(array_keys($_POST) as $param){ # sort out all Post-data
    foreach($hcmod as $mode)             # for each Post-title loop
      if(preg_match('/'.$mode.'/is', $param)) {   # in which we search for our option
        $optionData = $_POST[$param];
        if($mode !== $hcmod[message]) $optionData = htmlspecialchars($optionData);
        $hc_options->set($mode, $optionData);  # and if we find - refresh
        $updated = true;
        break;
      }
  }

  //print_r($_POST); // FOR DEBUG
  if($updated) { echo '<div class="updated fade"><p>'; echo __('Options saved', 'hiddenContent'); echo '</p></div>'; }
  //$hc_options->uninstall();
  $adm_options = $hc_options->getA(); //print_r($adm_options); // FOR DEBUG

?>
<div class="wrap">
	<h2><?php _e('Hide Content Options', 'hiddenContent'); ?></h2>
		<p><?php _e('A text between <strong>&lt;!--', 'hiddenContent'); ?><?php print($adm_options[$hcmod[opentag]]); ?><?php _e('--&gt;</strong> and <strong>&lt;!--', 'hiddenContent'); ?><?php print($adm_options[$hcmod[closetag]]); ?><?php _e('--&gt;</strong> can be shown to bots,  logged-in users or all visitors. You can configure it here:', 'hiddenContent'); ?></p>
  <form method="post" action="">
    <table class="form-table">
      <tr valign="top">
        <th scope="row"><?php _e('Hidden Content tags', 'hiddenContent'); ?></th>
        <td>
          <p><label><small><?php _e('Begin HideContent tag:', 'hiddenContent'); ?></small><br />&lt;!--<input type="text" name="<?php print($hcopt[fp].$hcmod[opentag]); ?>" value="<?php print($adm_options[$hcmod[opentag]]); ?>" />--&gt;</label></p>
          <p><label><small><?php _e('End HideContent tag:', 'hiddenContent'); ?></small><br />&lt;!--<input type="text" name="<?php print($hcopt[fp].$hcmod[closetag]); ?>" value="<?php print($adm_options[$hcmod[closetag]]); ?>" />--&gt;</label></p>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row"><?php _e('Visibility properties', 'hiddenContent'); ?><br /></th>
        <td>
          <p class="hc_css_option hc_css_robot">
            <label><input type="checkbox" name="<?php print($hcopt[fp].$hcmod[robots]); ?>" value="true" <?php if($adm_options[$hcmod[robots]] == 'true') print('checked="checked"'); ?> /> <?php _e('Show for <strong>Search Robots</strong>?', 'hiddenContent'); ?></label><br />
            <small>(<?php _e('Bots:', 'hiddenContent'); ?> <?php foreach($botsNames as $botName) print($botName.' '); ?>)</small>
          </p>
          <p class="hc_css_option hc_css_users">
            <label><input type="checkbox" name="<?php print($hcopt[fp].$hcmod[users]); ?>" value="true" <?php if($adm_options[$hcmod[users]] == 'true') print('checked="checked"'); ?> /> <?php _e('Show for <strong>logged-in users</strong>?', 'hiddenContent'); ?></label><br />
            <small>(<?php _e('Registered users who', 'hiddenContent'); ?> <a href="<?php print(get_bloginfo('url').'/wp-login.php'); ?>"><?php _e('enter</a> the site', 'hiddenContent'); ?>)</small>
          </p>
          <p class="hc_css_option hc_css_guests">
            <label><input type="checkbox" name="<?php print($hcopt[fp].$hcmod[guests]); ?>" value="true" <?php if($adm_options[$hcmod[guests]] == 'true') print('checked="checked"'); ?> />
            <?php _e('Show for <strong>all visitors</strong>?', 'hiddenContent'); ?></label><br />
            <small>(<?php _e('Unregistered users', 'hiddenContent'); ?>)</small>
          </p>

          <p class="hc_css_option hc_css_rss">
            <label><input type="checkbox" name="<?php print($hcopt[fp].$hcmod[rss]); ?>" value="true" <?php if($adm_options[$hcmod[rss]] == 'true') print('checked="checked"'); ?> />
            <?php _e('Show in <strong>FEED</strong>?', 'hiddenContent'); ?></label><br />
            <small>(<?php _e('RSS, Atom, etc', 'hiddenContent'); ?>)</small>
          </p>

        </td>
      </tr>
      <tr>
      <th scope="row"><?php _e('Custom massage', 'hiddenContent'); ?></th>
      <td>
        <p>
          <textarea name="<?php print($hcopt[fp].$hcmod[message]); ?>" cols="60" rows="4" style="width: 98%; font-size: 12px;" class="code"><?php print($adm_options[$hcmod[message]]); ?></textarea><br />
          <small><?php _e('You can use <u>HTML tags</u>', 'hiddenContent'); ?></small>
        </p>
      </td>
    </tr>
  </table>
  <p class="submit">
    <input type="hidden" name="act" value="set" />
    <input type="submit" name="Submit" value="<?php _e('Save options', 'hiddenContent'); ?>" />
  </p>
	</form>
</div>
<?php
}

function hc_hideContentAdmPage() {
  add_options_page('HideContent', __('Hide Content', 'hiddenContent'), 8, __FILE__, 'hc_optionsPage');
}
add_action('admin_menu', 'hc_hideContentAdmPage');

### Add button to non-visual editor (for post.php, page.php, post-new.php, page-new.php, comment.php)
if (strpos($_SERVER['REQUEST_URI'], 'post.php') || strpos($_SERVER['REQUEST_URI'], 'post-new.php') || strpos($_SERVER['REQUEST_URI'], 'page-new.php') || strpos($_SERVER['REQUEST_URI'], 'page.php') || strpos($_SERVER['REQUEST_URI'], 'comment.php')) {
	add_action('admin_footer', 'hiddenConntentAddQuickTag');

	function hiddenConntentAddQuickTag() {
  global $hcopt, $hcmod;
  static $hc_options, $openTag, $closeTag;
  $hc_options = new hc_options;
  $openTag  = $hc_options->get($hcmod[opentag]);
  $closeTag = $hc_options->get($hcmod[closetag]);
  ?>

  <script type="text/javascript">
  //<![CDATA[
  if (hcToolbar = document.getElementById("ed_toolbar")) {
    var hcNr, hcBut;
    hcNr = edButtons.length;
    edButtons[hcNr] = new edButton(
      '<?php print($hcopt[edtBtnId]); ?>',
      '',
      '<!--<?php print($openTag); ?>-->',
      '<!--<?php print($closeTag); ?>-->',
      ''
    );

    var hcBut = hcToolbar.lastChild;
    while (hcBut.nodeType != 1) {
      hcBut = hcBut.previousSibling;
    }
    hcBut = hcBut.cloneNode(true);
    hcToolbar.appendChild(hcBut);
    hcBut.value = '<?php print(__('Hide Content', 'hiddenContent')); ?>';
    hcBut.title = hcNr;
    hcBut.onclick = function () {edInsertTag(edCanvas, parseInt(this.title));}
    hcBut.id = "<?php print($hcopt[edtBtnId]); ?>";
  }
  //]]>
  </script>
	<?php
	}
}

add_filter('the_content',  'hc_hideContent');
add_filter('the_excerpt',  'hc_hideContent');
add_filter('comment_text', 'hc_hideContent');



// TODO: add button to visual editor

?>