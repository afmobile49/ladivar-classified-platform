CREATE TABLE admins(
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL
  );

CREATE TABLE categories (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      name TEXT NOT NULL,
      slug TEXT NOT NULL UNIQUE,
      sort_order INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL
    , name_en TEXT NOT NULL DEFAULT '');

CREATE TABLE listing_images (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      listing_id INTEGER NOT NULL,
      path TEXT NOT NULL,
      created_at TEXT NOT NULL, lang_scope TEXT NOT NULL DEFAULT 'both', sort_order INTEGER NOT NULL DEFAULT 0,
      FOREIGN KEY(listing_id) REFERENCES listings(id) ON DELETE CASCADE
    );

CREATE TABLE listings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      category_id INTEGER NOT NULL,
      title TEXT NOT NULL,
      body TEXT NOT NULL,
      city TEXT DEFAULT '',
      status TEXT NOT NULL DEFAULT 'pending', -- pending|approved|rejected
      created_at TEXT NOT NULL,
      approved_at TEXT, sort_order INT NOT NULL DEFAULT 0, user_id INTEGER NULL, edit_token TEXT NULL, list_view_count INTEGER NOT NULL DEFAULT 0, detail_view_count INTEGER NOT NULL DEFAULT 0, title_en TEXT NOT NULL DEFAULT '', body_en TEXT NOT NULL DEFAULT '', city_en TEXT NOT NULL DEFAULT '', source_lang TEXT NOT NULL DEFAULT 'fa',
      FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE CASCADE
    );

CREATE TABLE settings(
    k TEXT PRIMARY KEY,
    v TEXT NOT NULL
  );

CREATE TABLE side_ads (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      position TEXT NOT NULL, -- right|left
      title TEXT NOT NULL DEFAULT '',
      html TEXT NOT NULL,
      is_active INTEGER NOT NULL DEFAULT 1,
      sort_order INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL
    , title_en TEXT NOT NULL DEFAULT '', html_en TEXT NOT NULL DEFAULT '', image_path TEXT NOT NULL DEFAULT '', image_path_en TEXT NOT NULL DEFAULT '', image_scope TEXT NOT NULL DEFAULT 'both');

CREATE TABLE site_settings (
      k TEXT PRIMARY KEY,
      v TEXT NOT NULL
    );

CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL
      , is_active INTEGER NOT NULL DEFAULT 1);
