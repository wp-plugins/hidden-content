<?php
/*
Plugin Name: Hidden Content
Plugin URI: http://www.infine.ru/support/wp_plugins/hidden-content.htm
Description: Some blocks of the text can be shown/hidden for bots, logged-in users or all visitors (in posts, pages and comments)
Author: Nikolay aka 'cmepthuk'</a>, перевод от <a href="http://sonika.ru/blog/">Sonika
Author URI: http://www.infine.ru/
Version: 0.2.2
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
### Create Text Domain For Translations
add_action('init', 'hidecontent_textdomain');
function hidecontent_textdomain() {
	if (!function_exists('wp_print_styles')) {
		load_plugin_textdomain('hideContent', 'wp-content/plugins/hidden-content');
	} else {
		load_plugin_textdomain('hideContent', false, 'hidden-content');
	}
}

### Hide Content OPTions
$hcopt = array(prefix => 'hiddenContent_',
               formPrefix => 'hiddenContent-',
               editorBtnId => 'hiddenContent');
### Hide Content MODes
$hcmod = array(robots => 'robots',
               users  => 'users',
               guests => 'guests',
               opentag  => 'opentag',
               closetag => 'closetag',
               message => 'message');
### Array With Bot Names
$botsNames = array('yandex', 'webalta', 'rambler', 'googlebot', 'aport', 'yahoo', 'msnbot', 'turtle', 'mail.ru', 'omsktele', 'cuill.com', 'yetibot', 'picsearch', 'bot');

class hc_options {
  function get($name) {
    global $hcopt;
    return get_option($hcopt[prefix].$name);
  }
  function getA() {
    global $hcopt, $hcmod;
    static $res = array();
    foreach($hcmod as $mode)
      $res[$mode] = get_option($hcopt[prefix].$mode);
    return $res;
  }

  function set($name, $value = 'false') {
    global $hcopt;
    update_option($hcopt[prefix].$name, $value);
  }

  function install() {
    global $hcopt, $hcmod;
    static $descr = 'Hide Content - Option for ';
    add_option($hcopt[prefix].$hcmod[robots],  'true',     $descr.$hcmod[robots],   'yes');
    add_option($hcopt[prefix].$hcmod[users],   'true',     $descr.$hcmod[users],    'yes');
    add_option($hcopt[prefix].$hcmod[guests],  'false',    $descr.$hcmod[guests],   'yes');
    add_option($hcopt[prefix].$hcmod[opentag], 'beginHide',$descr.$hcmod[opentag],  'yes');
    add_option($hcopt[prefix].$hcmod[closetag],'endHide',  $descr.$hcmod[closetag], 'yes');
    add_option($hcopt[prefix].$hcmod[message], '<!-- Access denited :( -->',  $descr.$hcmod[message], 'yes');
  }
  function uninstall() {
    global $hcopt, $hcmod;
    foreach($hcmod as $mode)
      delete_option($hcopt[prefix].$mode);
  }

  function init() {
    global $hcopt, $hcmod;
    static $err = false;
    foreach($hcmod as $mode)
      if(get_option($hcopt[prefix].$mode) == '') {
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
  static $userAgent = '', $botsNames = array(), $isBot = false;
  $userAgent = $_SERVER["HTTP_USER_AGENT"];
  /* first check - by useragent */
  foreach($botsNames as $botName) {
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
function infinehiddenContent($content = '') {
  global $hcmod, $hcopt;
  static $hc_options, $show = false;
  $hc_options = new hc_options;
  $open = $hc_options->get($hcmod[opentag]);
  $close = $hc_options->get($hcmod[closetag]);
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
  }
  if($show) $replace = "$3";
    else $replace = $hc_options->get($hcmod[message]);
  $content = preg_replace("/<!--(.*?)".$open."(.*?)-->(.*?)<!--(.*?)".$close."(.*?)-->/is", $replace, $content);
  return $content;
}

### Admin page
function infinehcopt() {
  global $hcopt, $hcmod, $botsNames;
  static $hc_options, $updated = false, $optionData = '';
  $hc_options = new hc_options;

  foreach(array_keys($_POST) as $param){ /* sort out all Post-data */
    foreach($hcmod as $mode)             /* for each Post-title loop */
      if(preg_match('/'.$mode.'/is', $param)) {   /* in which we search for our option */
        //print $_POST[$param];
        $optionData = $_POST[$param];
        if($mode !== $hcmod[message]) $optionData = htmlspecialchars($optionData);
        $hc_options->set($mode, $optionData);  /* and if we find - refresh */
        $updated = true;
        break;
      }
  }
  //print_r($_POST); // FOR DEBUG
  if($updated) { echo '<div class="updated fade"><p>'; echo __('Options saved', 'hideContent'); echo '</p></div>'; }
  //$hc_options->uninstall();
  $adm_options = $hc_options->getA(); //print_r($adm_options); // FOR DEBUG

?>
<div class="wrap">
	<h2><?php _e('Hide Content Options', 'hideContent'); ?></h2>
		<p><?php _e('A text between <strong>&lt;!--', 'hideContent'); ?><?php print($adm_options[$hcmod[opentag]]); ?><?php _e('--&gt;</strong> and <strong>&lt;!--', 'hideContent'); ?><?php print($adm_options[$hcmod[closetag]]); ?><?php _e('--&gt;</strong> can be shown to bots,  logged-in users or all visitors. You can configure it here:', 'hideContent'); ?></p>
  <form method="post" action="">
    <table class="form-table">
      <tr valign="top">
        <th scope="row"><?php _e('Hidden Content tags', 'hideContent'); ?></th>
        <td>
          <p><label><small><?php _e('Begin tag:', 'hideContent'); ?></small><br />&lt;!--<input type="text" name="<?php print($hcopt[formPrefix].$hcmod[opentag]); ?>" value="<?php print($adm_options[$hcmod[opentag]]); ?>" />--&gt;</label></p>
          <p><label><small><?php _e('End tag:', 'hideContent'); ?></small><br />&lt;!--<input type="text" name="<?php print($hcopt[formPrefix].$hcmod[closetag]); ?>" value="<?php print($adm_options[$hcmod[closetag]]); ?>" />--&gt;</label></p>
        </td>
      </tr>
      <tr valign="top">
        <th scope="row"><?php _e('Show hidden content for Bots', 'hideContent'); ?><br /><small style="font-weight: normal !important;">(<?php foreach($botsNames as $botName) print($botName.' '); ?> etc.)</small></th>
        <td>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[robots]); ?>" value="true" <?php if($adm_options[$hcmod[robots]] == 'true') print('checked="checked"'); ?> /> <?php _e('Yes', 'hideContent'); ?></label></p>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[robots]); ?>" value="false" <?php if($adm_options[$hcmod[robots]] == 'false') print('checked="checked"'); ?> /> <?php _e('No', 'hideContent'); ?></label></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php _e('Show hidden content for  logged-in usres', 'hideContent'); ?></th>
        <td>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[users]); ?>" value="true" <?php if($adm_options[$hcmod[users]] == 'true') print('checked="checked"'); ?> /> <?php _e('Yes', 'hideContent'); ?></label></p>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[users]); ?>" value="false" <?php if($adm_options[$hcmod[users]] == 'false') print('checked="checked"'); ?> /> <?php _e('No', 'hideContent'); ?></label></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php _e('Show hidden content for all visitors', 'hideContent'); ?></th>
        <td>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[guests]); ?>" value="true" <?php if($adm_options[$hcmod[guests]] == 'true') print('checked="checked"'); ?> /> <?php _e('Yes', 'hideContent'); ?></label></p>
          <p><label><input type="radio" name="<?php print($hcopt[formPrefix].$hcmod[guests]); ?>" value="false" <?php if($adm_options[$hcmod[guests]] == 'false') print('checked="checked"'); ?> /> <?php _e('No', 'hideContent'); ?></label></p>
      </td>
    </tr>
    <tr>
      <th scope="row"><?php _e('Custom massage', 'hideContent'); ?></th>
      <td>
        <p>
          <textarea name="<?php print($hcopt[formPrefix].$hcmod[message]); ?>" cols="60" rows="4" style="width: 98%; font-size: 12px;" class="code"><?php print($adm_options[$hcmod[message]]); ?></textarea><br />
          <small><?php _e('You can use <u>HTML tags</u>', 'hideContent'); ?></small>
        </p>
      </td>
    </tr>
  </table>
  <p class="submit">
    <input type="submit" name="Submit" value="<?php _e('Save options', 'hideContent'); ?>" />
  </p>
	</form>
</div>
<?php
}

function infinehiddenContentAdmPage() {
  add_options_page('HideContent', __('Hide Content', 'hideContent'), 8, __FILE__, 'infinehcopt');
}
add_action('admin_menu', 'infinehiddenContentAdmPage');

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
      '<?php print($hcopt[editorBtnId]); ?>',
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
    hcBut.value = '<?php print(__('Hide Content', 'hideContent')); ?>';
    hcBut.title = hcNr;
    hcBut.onclick = function () {edInsertTag(edCanvas, parseInt(this.title));}
    hcBut.id = "<?php print($hcopt[editorBtnId]); ?>";
  }
  //]]>
  </script>
	<?php
	}
}

add_filter('the_content',  'infinehiddenContent');
add_filter('the_excerpt',  'infinehiddenContent');
add_filter('comment_text', 'infinehiddenContent');


// TODO: add button to visual editor

?>