=== SQLite Cache ===
Contributors: andreyk
Tags: cache, performance, sqlite
Stable tag: 0.6
Author: Andrey K.
Author URI: http://andrey.eto-ya.com/
Plugin URI: http://andrey.eto-ya.com/wp-sqlite-cache
Requires at least: 3.8.1
Tested up to: 4.2.2
License: GPL2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Provides SQLite cache storage that applied before WordPress core load.
Doesn't create multiple html files but stores all pages in one file.

== Description ==

On first request to a given URL whole html code of a page is being stored in
the SQLite database. On next requests to the same page WordPress core will
not be loaded at all but the content of a page will be retrieved from the
the SQLite storage therefore duration of PHP execution decreases in dozen
of times.

Many sites on the same webserver may use common SQLite storage.
Compatible with WordPress Multisite. Requires PHP SQLite PDO.

In comparison with plugins generating static html pages this plugin does not
create multiple directories and html files but stores all generated pages
in one file. Moreover, many wordpress sites on the same webserver may use
common SQLite storage. Keeps 404, 301, 302, 304 HTTP statuses, optionally
supports ETag, Expires, Content-Length, Content-Type (with charset) headers.

== Installation ==

* Upload the plugin from the WordPress plugin installation page or
unpack `sqlite-cache` folder to the plugins directory
(usually `wp-content/plugins/`).

* Activate the plugin through the WordPress Plugins manager, then you
will see `SQLite Cache` item in the `Settings` submenu of WordPress admin menu.

* Forms on the the plugin settings page:
1. Define a directory where the plugin settings and the cache storage
will be located.
1. Define cache expiration time and HTTP headers the cached pages will be
delivered with.
1. Third form is for cleaning cache. Note the cache will be cleared every
time when you submit the domain settings form so you don't need to clear cache
after changing settings.

* Add two lines into `index.php` file (you will be notified on the settings page)
to define where the cache storage is located and include the cache engine file.

Done! For testing, enable `Show performance time` checkbox to ensure
the cache engine works.

== Frequently Asked Questions ==

= I'm the owner of a site and I see `Edit page` link near the page content. Will it be cached? =

No. The cache engine is not used for authorized users, for those who has
commenter cookie and for visitors with active PHP sessions.

= What I'll see after posting a comment? =

The result of a page after POST request method will isn't being cached
so you will see your comment or a notification about pending comment. Also,
see the previous question.

= What else is not cached? =

* Any URL containing `wp-`, `.php`, `/files/` and `blogs.dir`.
* Results of POST and HEAD request methods.

= Do I need clear cache when I edit an existing post or page or add a comment? =

Cache entry of a single post page or page will be cleared automatically after
post/page update or publishing a comment. Also, the blog list page cache is
cleared on post updates. You need to purge cache after modifying menus, widgets,
switching theme, changes of theme options.

= Can I disable cache for some pages? =

Yes, you can define a list of URL patterns to exclude them from cache.

= Does the `Contact Form 7` work on cached pages? =

Yes, it works by itself but additional functionalities such as CAPTHCHAs
might be not working. 

= How the plugin handles with 404 Not Found response and redirects? =

The plugin caches `404 Not Found` HTTP status code with the same
expiration period as other pages as well as redirect codes 301 and 302
with Location header.

= How to deactivate the plugin? =

Click on Deactivate link on the plugins list, then the settings file
(yourdomain.ini) will be deleted so cache engine will not be applied for
the current domain. However it stays working for other sites installed
in the same directory until it is included in the `index.php` and their
setting files exist.

== Other notes ==

= As the cache script doesn't load the WordPress core where does it save it's settings? =

To get the plugin working you need to define the SQLite storage location in
the `index.php` of the wordpress installation directory (note, not in wp-config.php
but index.php). Besides of the SQLite file, this directory contains general
settings file (compression setting) and a subfolder for per domain settings.
In the admin area (plugin setting page) and to remember after deactivation where
the cache located, `litecache_path` WordPress option is used.

= How the plugin handles URLs with and without `www.` prefix? =

The presence of `www.` prefix is defined in `Settings` - `General`.
The plugin stores `with_www` parameter in `domains/yourdomain.ini` file in the
cache directory and redirects requests correspondingly; these redirects are
being processed before cache usage.

= Is it WordPress Multisite compatible? =

Yes, but for subdomains mode only (blogname.example.com), not for
example.com/blogname. A superadmin of a multisite network has access to
the first setting form (cache location), blog admins have access to the
second form and purge form.

== Changelog ==

0.6
First public version.