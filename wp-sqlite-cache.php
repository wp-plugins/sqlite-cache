<?php
/**
Plugin Name: SQLite Cache
Description: Provides SQLite cache storage that is applied before WordPress core load. Does not create multiple html files but stores all pages in one file. Dozens of sites on the same webserver may use common SQLite storage. Compatible with WordPress Multisite.
Version: 0.6.1
Author: Andrey K.
Author URI: http://andrey.eto-ya.com/
Plugin URI: http://andrey.eto-ya.com/wp-sqlite-cache
Requires at least: 3.8.1
Tested up to: 4.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

*/

/*
  Copyright 2015 (c) Andrey K. (URL: http://andrey.eto-ya.com/, email: andrey271@bigmir.net)

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
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

define('LITECACHE_DOMAIN', str_replace('www.', '', strtolower($_SERVER['HTTP_HOST'])));

add_action('admin_menu', 'litecache_menu');

function litecache_menu() {
  add_options_page('SQLite Cache', 'SQLite Cache', 'manage_options', 'sqlite_cache', 'litecache_settings_page');
  add_action('admin_init', 'register_litecache' );
  load_plugin_textdomain('litecache', false, basename(dirname(__FILE__)) . '/languages');
}

function litecache_settings_page() {
  $litecache_path = get_site_option('litecache_path');
  if ( is_dir($litecache_path) && ($par = @parse_ini_file($litecache_path . '/litecache.ini')) ) {}
  else {
    $par = array('compress' => '',);
  }
?>
<style type="text/css">
.lite-form {padding: 0 10px; border: solid 1px #bbb; margin: 0 15px 15px 0;}
.lite-form2 {float: left; width: 50%;}
textarea, input[type="text"] {max-width: 100%;}
@media (max-width: 640px) {
  .lite-form2 {width: 100%;}
}
</style>
<div class="wrap">
<h2><?php _e('SQLite Cache Settings', 'litecache'); ?></h2>
<?php if ( is_multisite() && is_super_admin() || (!is_multisite() && current_user_can('manage_options')) ) {
?>

<form method="post" action="options.php" class="lite-form">
  <h3><?php _e('SQLite cache storage directory and settings', 'litecache'); ?></h3>
<?php settings_fields('litecache-multisite-group'); ?>
<p><?php _e('Your WordPress installation parameters:', 'litecache'); ?> </p>
<p>WordPress home path: <code><?php echo get_home_path(); ?></code> </p>
<p>$_SERVER[DOCUMENT_ROOT]: <code><?php echo $_SERVER['DOCUMENT_ROOT']; ?></code> </p>
<p><?php _e('WordPress installation ABSPATH constant', 'litecache'); ?>: <code><?php echo ABSPATH; ?></code></p>
<p><?php _e('This plugin directory', 'litecache'); ?>: <code><?php echo dirname(__FILE__); ?></code></p>
<p><em><?php _e('These settings will be applied for all WordPress sites installed into one common directory or for all sites of WordPress MultiSite installation. One SQLite cache database can serve dozens of sites.', 'litecache'); ?></em></p>
<p><label for="litecache-path"><?php _e('SQLite database directory, full path', 'litecache'); ?></label><br>
  <input type="text" size="70" id="litecache-multisite-path" name="litecache_multisite[path]" value="<?php
    echo $litecache_path; ?>"/>
</p>
<p><label for="litecache-compress"><?php _e('Compress cached data', 'litecache'); ?></label>
  <input type="checkbox"  size="20" id="litecache-multisite-compress" name="litecache_multisite[compress]" value="1" <?php
  echo $par['compress'] ? 'checked="checked"' : ''; ?> />
</p>
<?php submit_button(); ?>
</form>
<?php } ?>

<form method="post" action="options.php" class="form lite-form2">
<?php if ( !$litecache_path ) : ?>
<div class="error"><p><?php _e('Directory for cache is not defined yet. Enter path into the following field. If it is not exist the attempt to create it will be performed.', 'litecache'); ?></p></div>
<?php
    elseif ( ($index = file_get_contents(get_home_path() . '/index.php'))
      && (strpos($index, 'LITECACHE_PATH') === false
        || strpos($index, $litecache_path) === false
        || strpos($index, dirname(__FILE__) . '/lite-cache.php') === false) ) : ?>
  <div class="error"><p><?php
  printf(__('To get cache working you must add manually the following lines in the top of %s file:', 'litecache'),
    '<code>' . get_home_path() . 'index.php' . '</code>');
    echo '<pre>'
      . htmlspecialchars("define('LITECACHE_PATH', '$litecache_path');\ninclude_once '" . dirname(__FILE__) . "/lite-cache.php';")
      . '<pre>';
  ?></p></div>
<?php endif; ?>
  <h3><?php $title = sprintf(__('%s per domain settings', 'litecache'), LITECACHE_DOMAIN);
  echo $title;
 ?></h3>

<?php settings_fields('litecache-domain-group');
$domain_ini = $litecache_path . '/domains/' . LITECACHE_DOMAIN . '.ini';
if ( !file_exists($domain_ini) && $litecache_path) : ?>
<div class="error"><p><?php
  printf(__("File %s doesn't exist yet. Submit «%s» form to attempt create it.", 'litecache'), '<code>' . $domain_ini . '</code>', $title);
?></p></div>
<?php else :
  $param = parse_ini_file($domain_ini);
endif;
if ( empty($param['expire']) ) : ?>
  <div class="error"><p><?php _e('Cache is not active yet: you need to set cache expiration term. 1 hour = 3600 seconds, 1 day = 86400 seconds.', 'litecache'); ?></p></div>
<?php endif; ?>

<p><label for="litecache-domain-exclude"><?php _e('Exclude cache for paths, one expression per line', 'litecache'); ?></label><br>
<textarea  cols="40" rows="6" id="litecache-domain-exclude" name="litecache_domain[exclude]" ><?php
  echo empty($param['exclude']) ? '' : implode("\n", $param['exclude']); ?></textarea><br>
<?php _e('regular expression for <code>REQUEST_URI</code>. Leave empty field to cache all.', 'litecache'); ?></p>
<p>
  <label for="litecache-domain-expire"><?php _e('Expire cached pages after', 'litecache'); ?></label>
  <input type="text"  size="12" id="litecache-domain-expires" name="litecache_domain[expire]" value="<?php echo @$param['expire']; ?>" /> <?php _e('seconds', 'litecache'); ?>
</p>
<p>
<label for="litecache-domain-timer"><?php _e('Show performance time', 'litecache'); ?></label>
  <input type="checkbox"  size="20" id="litecache-domain-timer" name="litecache_domain[timer]" value="1" <?php
    echo @$param['timer'] ? 'checked="checked"' : ''; ?> /> (<?php _e('for testing', 'litecache'); ?>)
</p>

<p>
<label><?php _e('Include HTTP headers', 'litecache'); ?>:</label><br>
  Content-Type with charset <input type="checkbox"  size="20" name="litecache_domain[Content-Type]" value="1" <?php
    echo @$param['Content-Type'] ? 'checked="checked"' : ''; ?> /> &nbsp;
  Content-Length <input type="checkbox"  size="20" name="litecache_domain[Content-Length]" value="1" <?php
    echo @$param['Content-Length'] ? 'checked="checked"' : ''; ?> />
<br>
  ETag <input type="checkbox"  size="20" name="litecache_domain[ETag]" value="1" <?php
    echo @$param['ETag'] ? 'checked="checked"' : ''; ?> /> &nbsp;
  Expires <input type="checkbox"  size="20" name="litecache_domain[Expires]" value="1" <?php
    echo @$param['Expires'] ? 'checked="checked"' : ''; ?> /> &nbsp; (<?php _e('recommended to save traffic', 'litecache'); ?>)
</p>
<?php submit_button(); ?>
</form>

<form method="post" action="options.php" class="form lite-form2">
  <h3><?php _e('Purge Cache for Domain', 'litecache'); ?></h3>
<?php settings_fields('litecache-purge-group'); ?>
<p><label for="litecache-purge"><?php printf(__('Pattern to purge (substring of %s)', 'litecache'), '<code>REQUEST_URI</code>'); ?></label><br>
<input type="text" size="40" id="litecache-purge" name="litecache_purge" /><br>
<?php _e('Leave empty field to purge all.', 'litecache'); ?></p>
<?php submit_button( __('Purge Cache', 'litecache') ); ?>
</form>
<p><?php _e("Cache is not used for authorized users and for visitors who has commenter's cookie (leaved a comment recently) or open PHP session.", 'litecache'); ?></p>
<p><?php _e('Cache of an updated/deleted/trashed page or single post will be cleared by the plugin automatically.', 'litecache'); ?></p>
<p><?php _e('Cache for a commented post or page on new comment will be cleared too.', 'litecache'); ?></p>
<p><?php _e("Don't forget to clear cache after switch theme, change widgets, editing menus and other actions affecting site look.", 'litecache'); ?></p>
<p><?php _e('To disable cache, deactivate the plugin. Remove inclusion of lite-cache.php from WordPress index.php before deleting the plugin.', 'litecache'); ?> &nbsp; <a target="_blank" href="http://andrey.eto-ya.com/wp-sqlite-cache">Plugin Help Page</a></p>
</div>

<?php }

function register_litecache() {
  register_setting('litecache-multisite-group', 'litecache_multisite', 'litecache_multisite');
  register_setting('litecache-domain-group', 'litecache_domain', 'litecache_domain');
  register_setting('litecache-purge-group', 'litecache_purge', 'litecache_purge');
}

function litecache_deactivate() {
  if ($path =  get_site_option('litecache_path')) {
    unlink( $path . '/domains/' . LITECACHE_DOMAIN . '.ini');
  }
}

register_activation_hook('sqlite-cache/wp-sqlite-cache.php', 'litecache_activate');

register_deactivation_hook('sqlite-cache/wp-sqlite-cache.php', 'litecache_deactivate');

function litecache_activate() {
// do nothing
}

function litecache_multisite($input) {
  if ($_POST['option_page'] != 'litecache-multisite-group')
    return;

  $input['path'] = untrailingslashit(trim($input['path']));

  if ( empty($input['path']) ) {
    delete_site_option('litecache_path');
    return;
  }
  if ( ! file_exists($input['path']) && ! mkdir($input['path']) ) {
    add_settings_error('litecache', 'not_dir', __('Can not create a directory.', 'litecache'), 'error');
    return;
  }
  elseif ( file_exists($input['path']) && ! is_dir($input['path']) ) {
    add_settings_error('litecache', 'not_dir', __('This path is not a directory.', 'litecache'), 'error');
    return;
  }
  $db = $input['path'] . '/db.sqlite';

  @unlink($db); // reset storage at all
  $touch = @touch($db);
  if (!$touch) {
    add_settings_error('litecache', 'db_not_created', __('Database file has not been created.', 'litecache'), 'error');
    return;
  }

  if ( !is_dir($input['path'] . '/domains') ) {
    mkdir($input['path'] . '/domains');
  }

  $r = new PDO('sqlite:' . $db);
  $r->query( file_get_contents(dirname(__FILE__) . '/lite-cache-schema.sql') );

  $errorinfo = $r->errorInfo();
  if (!empty($errorinfo[2]) && !strpos($errorinfo[2], 'already exists')) {
    add_settings_error('litecache', 'table_not_created', $errorinfo[0] . $errorinfo[2], 'error');
    return;
  }

  $ini['path'] = $input['path'];
  $ini['compress'] = empty($input['compress']) || '0' === $input['compress'] ? 0 : 1;

  $ini_out = '';
  foreach ($ini as $key => $value) {
    $ini_out .= $key . ' = ' . (is_numeric($value) ? $value : '"' . $value . '"') . "\n\n";
  }
  if ( !file_put_contents($input['path'] . '/litecache.ini', $ini_out) ) {
    add_settings_error('litecache', 'ini_file_not_written',
      sprintf(__('litecache.ini file has not been created/rewritten in %s.', 'litecache'), $input['path']), 'error');
    return;
  }
  update_site_option('litecache_path', $input['path']);
}

function litecache_domain($input) {
  if ($_POST['option_page'] != 'litecache-domain-group')
    return;
  $ini_out = '';
  $ini['with_www'] = (LITECACHE_DOMAIN == strtolower($_SERVER['HTTP_HOST'])) ? 0 : 1;
  $ini['expire'] = abs(@intval($input['expire']));
  $ini['timer'] = empty($input['timer']) || '0' === $input['timer'] ? 0 : 1;

  $ini_out .= 'with_www = ' . $ini['with_www'] . "\n\n";
  $ini_out .= 'expire = ' . $ini['expire'] . "\n\n";
  $ini_out .= 'timer = ' . $ini['timer'] . "\n\n";

  foreach ( array('Content-Type', 'Content-Length', 'ETag', 'Expires') as $key ) {
    $ini[$key] = empty($input[$key]) ? 0 : 1;
    $ini_out .= $key . ' = ' . $ini[$key] . "\n\n";
  }

  $exclude =  explode("\n", $input['exclude']);
  foreach ($exclude as $value) {
    $v = trim($value);
    if ($v) {
      $ini['exclude'][] = $v;
      $ini_out .= 'exclude[] = "' . $v .'"' . "\n";
    }
  }

  if ( !($path = get_site_option('litecache_path')) )
    return false;
  if ( !file_put_contents($path . '/domains/' . LITECACHE_DOMAIN . '.ini', $ini_out) ) {
    add_settings_error('litecache', 'domain_ini_file_not_written', __('Domain ini file has not been created/rewritten.', 'litecache'), 'error');
    return;
  }
  litecache_delete();
  return false;
}

function litecache_pdo() {
  try {
    $pdo = new PDO('sqlite:' . get_site_option('litecache_path') . '/db.sqlite');
  }
  catch(PDOException $e) {
    echo $e->getMessage() . __LINE__;
    return;
  }
  return $pdo;
}

function litecache_delete( $input = '', $vacuum = false ) {
  if ( !($r = litecache_pdo()) )
    return;
  $input = trim($input);
  $sql = 'DELETE FROM "html_cache" WHERE "domain" = "' . LITECACHE_DOMAIN . '"';
  if ($input) {
    $sql .= ' AND "request_uri" LIKE :like';
    $prep = $r->prepare($sql);
    $like = '%' . $input . '%';
    $prep->execute(array(':like' => $like));
  }
  else {
    $r->query($sql);
  }
  if ($vacuum)
    $r->query('vacuum');
  add_settings_error('litecache', 'cache_clean', __('Cache has been cleaned.', 'litecache'), 'success');
}

function litecache_purge($input = '') {
  if ($_POST['option_page'] != 'litecache-purge-group')
    return;
  litecache_delete( $input, $input ? false : true );
}

add_action( 'post_updated', 'litecache_post_updated', 10, 3 ); // ok
add_action( 'delete_post', 'litecache_post_updated' );
add_action( 'wp_trash_post', 'litecache_post_updated' );
add_action( 'transition_comment_status', 'litecache_transition_comment_status', 10, 3 );
add_action( 'wp_insert_comment', 'litecache_insert_comment', 10, 2 );

function litecache_post_updated($post_ID, $post_after = false, $post_before = false) {
  if ( !($r = litecache_pdo()) )
    return;
  $purge[] = get_permalink($post_ID);
  if ( $post_before !== false && $post_before->post_type == 'post') {
    $page_for_posts = get_site_option('page_for_posts');
    $purge[] = $page_for_posts ? get_permalink($page_for_posts) : home_url('/');
  }
// To do: also clear cache for correnspondent category/tag page on post update.

  foreach ($purge as $url) {
    $part = parse_url($url);
    $uri = str_replace("$part[scheme]://$part[host]", '', $url);
    $sql = 'DELETE FROM "html_cache" WHERE "domain" = "' . LITECACHE_DOMAIN . '" AND "request_uri" = :uri';
    $prep = $r->prepare($sql);
    $prep->execute(array(':uri' => $uri));
  }
}

function litecache_insert_comment( $comment_id, $comment ) {
  if ($comment->comment_approved) {
    litecache_post_updated($comment->comment_post_ID);
  }
}

function litecache_transition_comment_status( $old, $new, $comment ) {
  litecache_post_updated($comment->comment_post_ID);
}
