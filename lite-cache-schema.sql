CREATE TABLE 'html_cache' (
'hash' TEXT PRIMARY KEY NOT NULL DEFAULT '',
'domain' TEXT NOT NULL DEFAULT '',
'request_uri' TEXT NOT NULL DEFAULT '',
'content' BLOB NOT NULL DEFAULT '',
'expire' INTEGER NOT NULL DEFAULT (0),
'headers' BLOB NOT NULL DEFAULT ''
);

CREATE INDEX 'request_uri' ON 'html_cache' ('request_uri');
