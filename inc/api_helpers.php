<?php

function api_json($data, $status = 200) {
    http_response_code((int)$status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error($message, $status = 400, $extra = array()) {
    $out = array_merge(array(
        'ok' => false,
        'error' => $message
    ), $extra);

    api_json($out, $status);
}

function api_success($data = array(), $status = 200) {
    api_json(array(
        'ok' => true,
        'data' => $data
    ), $status);
}

function api_request_method() {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function api_lang() {
    if (!empty($_GET['lang']) && in_array($_GET['lang'], array('fa', 'en'), true)) {
        return $_GET['lang'];
    }

    if (function_exists('current_lang')) {
        return current_lang();
    }

    return 'fa';
}

function api_listing_transform($row, $lang = 'fa') {
    $title = function_exists('localized_field') ? localized_field($row, 'title', 'title_en', $lang) : ($row['title'] ?? '');
    $body  = function_exists('localized_field') ? localized_field($row, 'body', 'body_en', $lang) : ($row['body'] ?? '');
    $city  = function_exists('localized_field') ? localized_field($row, 'city', 'city_en', $lang) : ($row['city'] ?? '');

    $images = function_exists('listing_images_lang') ? listing_images_lang((int)$row['id'], $lang) : array();

    $imageUrls = array();
    foreach ($images as $img) {
        if (!empty($img['path'])) {
            $imageUrls[] = UPLOAD_URL . $img['path'];
        }
    }

    return array(
        'id' => (int)$row['id'],
        'category_id' => (int)$row['category_id'],
        'title' => $title,
        'body' => $body,
        'city' => $city,
        'status' => $row['status'] ?? '',
        'approved_at' => $row['approved_at'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'url' => function_exists('listing_url') ? listing_url((int)$row['id']) : null,
        'full_url' => function_exists('listing_full_url') ? listing_full_url((int)$row['id']) : null,
        'images' => $imageUrls
    );
}

function api_category_transform($row, $lang = 'fa') {
    $name = function_exists('localized_field') ? localized_field($row, 'name', 'name_en', $lang) : ($row['name'] ?? '');

    return array(
        'id' => (int)$row['id'],
        'name' => $name,
        'slug' => $row['slug'] ?? ''
    );
}