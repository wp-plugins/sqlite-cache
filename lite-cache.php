<?php

class Litecache {
  private $settings;
  private $domain;
  private $uri;
  private $hash;
  private $compress;

  private $pdo;
  private $timer;

  public function __construct() {
    $this->timer[] = microtime(1);
    if (basename($_SERVER['SCRIPT_NAME']) != 'index.php' || !defined('LITECACHE_PATH') )
      return;
    $param = @parse_ini_file(LITECACHE_PATH . '/litecache.ini');
    if (!$param)
      return;

    $http_host = strtolower($_SERVER['HTTP_HOST']);
    $this->domain = str_replace('www.', '', $http_host);
    $schema = 'on' == $_SERVER['HTTPS'] ? 'https://' : 'http://';

    $pos_sharp = strpos($_SERVER['REQUEST_URI'], '#');
    $this->uri = ($pos_sharp === false) ? $_SERVER['REQUEST_URI'] : substr($_SERVER['REQUEST_URI'], 0, $pos_sharp);
    $this->hash = md5($this->domain . $this->uri);
    $this->compress = empty($param['compress']) || 0 == $param['compress'] ? 0 : 1;

    $this->settings = @parse_ini_file(LITECACHE_PATH . '/domains/' . $this->domain . '.ini');
    if ( $this->settings['with_www'] && ($this->domain == $http_host) ) {
      http_response_code('301');
      header('Location: ' . $schema . 'www.' . $http_host . $_SERVER['REQUEST_URI']);
      die;
    }
    elseif ( ! $this->settings['with_www'] && ($this->domain != $http_host) ) {
      http_response_code('301');
      header('Location: ' . $schema . $this->domain . $_SERVER['REQUEST_URI']);
      die;
    }
    if ( !$this->exclude() ) {
      $this->check();
      if ('HEAD' == $_SERVER['REQUEST_METHOD']) {
        return; // the content for HEAD method must not be included into cache storage as this content is empty!
      }
      ob_start(array($this, 'handler'));
    }
  }

  public function handler($str = '') {
    $now = time();
    $data = $this->compress && ('' !== $str) ? gzcompress($str) : $str;
    $expire = $now + $this->settings['expire'];
    $sql = "INSERT INTO 'html_cache' ('hash', 'domain', 'request_uri', 'content', 'expire', 'headers')
      VALUES (:hash, :domain, :request_uri, :content, :expire, :headers)";
    if ( $this->pdo ) {
      $prepared = $this->pdo->prepare($sql);
    }
    else {
      error_log('Litecache::handler: SQLite PDO object does not exist.');
      return $str;
    }

    $headers_list = headers_list();
    $code = http_response_code();
    $headers = array();
    foreach ($headers_list as $key => $h) {
      if ( preg_match('/(Content-Type|Location): ?(.*)$/', $h, $res) ) {
        $headers[$res[1]] = $res[2];
      }
    }

    if ( 200 == $code ) {
      if ($this->settings['ETag']) {
        $etag = md5($_SERVER['REQUEST_URI'] . $str);
        $headers['ETag'] = $etag;
      }
      if ($this->settings['Content-Length']) {
        $length = strlen($data) + ($this->settings['timer'] ? 24 : 0);
        $headers['Content-Length'] = $length;
      }
      if ($this->settings['Expires'])
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', $one['expire']));
      if ($this->settings['ETag'])
        header("Etag: \"$etag\"");
    }
    elseif ( 404 == $code ) {
      $headers = array( 'response_code' => $code );
      $data = 'Error: 404 Not Found';
      $data = $this->compress ? gzcompress($data) : $data;
    }
    elseif ( 301 == $code || 302 == $code ) {
      $headers['response_code'] = $code;
      $data = '';
    }

    $values = array(
      ':hash' => $this->hash,
      ':domain' => $this->domain,
      ':request_uri' => $this->uri,
      ':content' => $data,
      ':expire' => $expire,
      ':headers' => $headers ? serialize($headers) : '',
    );

    $prepared->execute($values);
    $this->timer[] = microtime(1);
    return $str . ($this->settings['timer'] ? 'NO CACHE: ' . ($this->timer[1] - $this->timer[0]) : '');
  }

  private function exclude() {
    if (empty($this->settings)) {
      return true;
    }
    if ( !empty($this->settings['exclude']) ) {
      foreach ($this->settings['exclude'] as $exp) {
        if (preg_match("/$exp/i", $this->uri)) {
          return TRUE;
        }
      }
    }

    if (preg_match('/\/wp-|\.php/', $this->uri)) {
      return TRUE;
    }

/* Multisite - exclude uploads */
    if (preg_match('/\/files\/|\/blogs\.dir\//', $this->uri))
      return TRUE;

    if ( 'POST' == $_SERVER['REQUEST_METHOD'] )
      return TRUE;
    if ( !empty($_SESSION) )
      return TRUE;

    foreach ($_COOKIE as $key => $value) {
      if ( strpos($key, 'comment_author_') !== FALSE )
        return TRUE;
      if ( strpos($key, 'wordpress_logged_in_') !== FALSE )
        return TRUE;
    }
    return FALSE;
  }

/* Tryes to extract page content form cache, delete expired cache entry and exit, else just return. */
  private function check() {
    $now = time();
    try {
      $this->pdo = new PDO('sqlite:' . LITECACHE_PATH . '/db.sqlite');
    }
    catch(PDOException $e) {
      error_log($e->getMessage());
      return;
    }

    $sql_select = 'SELECT * FROM "html_cache" WHERE "domain" = "' . $this->domain .'" AND "hash" = "' . $this->hash . '";';
    $result = $this->pdo->query($sql_select, PDO::FETCH_ASSOC);
    $one = $result->fetch();
    $max_age = $one['expire'] - $now;
    if ($one) {
      if ($max_age < 0) {
        $this->pdo->query('DELETE FROM "html_cache" WHERE "hash" = "' . $one['hash'] . '";', PDO::FETCH_ASSOC);
        return;
      }

      $headers = $one['headers'] ? unserialize($one['headers']) : array();

      if ( !empty($headers['response_code']) ) {
        $code = $headers['response_code'];
        http_response_code( $code );
        if ( 301 == $code || 302 == $code ) {
          header('Location: ' . $headers['Location']);
          die;
        }
        elseif ( $code >= 400 ) {
          echo $this->compress && ( '' !== $one['content'] ) ? gzuncompress($one['content']) : $one['content'];
        }
        die;
      }

      if ($this->settings['Expires'] && $one['expire'])
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', $one['expire']));

      header('Cache-Control: max-age=' . ($one['expire'] - $now ) . ', must-revalidate');

      if ($this->settings['Content-Type'] && $headers['Content-Type'])
        header('Content-Type: ' . $headers['Content-Type']);

      if ($this->settings['Content-Length'] && $headers['Content-Length'])
        header('Content-Length: ' . $headers['Content-Length']);

      if ($headers['ETag'] && $this->settings['ETag']) {
        header('ETag: "' . $headers['ETag'] . '"');
        $from_browser = getallheaders();
        if ( !empty($from_browser['If-None-Match']) ) {
          if ( $from_browser['If-None-Match'] == '"' . $headers['ETag'] . '"') {
            header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
            die;
          }
        }
      }

      echo $this->compress && ( '' !== $one['content'] ) ? gzuncompress($one['content']) : $one['content'];
      if ($this->settings['timer']) {
        $this->timer[] = microtime(1);
        echo ' ' . sprintf('CACHED: %16.13f', $this->timer[1] - $this->timer[0]);
      }

      die;
    } /* end if $one = $result->fetch() */

  } /* end function check() */

} /* end class */

$litecache = new Litecache();
