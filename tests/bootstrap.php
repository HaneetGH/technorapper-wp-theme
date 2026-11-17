<?php
/**
 * Minimal WordPress stubs so theme adapter tests can run with php tests/run.php.
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/technorapper-tests/');
}

$GLOBALS['technorapper_test_meta'] = array();
$GLOBALS['technorapper_test_filters'] = array();
$GLOBALS['technorapper_test_the_id'] = 0;

function __( $text, $domain = 'default' ) {
    unset($domain);
    return (string) $text;
}

function esc_html__( $text, $domain = 'default' ) {
    return esc_html(__($text, $domain));
}

function esc_html( $text ) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_attr( $text ) {
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function esc_url( $url ) {
    return esc_url_raw($url, array('http', 'https'));
}

function esc_url_raw( $url, $protocols = null ) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('/^\s*(javascript|data|vbscript):/i', $url)) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return '';
    }
    $scheme = strtolower($parts['scheme']);
    $allowed = $protocols ? array_map('strtolower', (array) $protocols) : array('http', 'https');
    if (!in_array($scheme, $allowed, true)) {
        return '';
    }
    return $url;
}

function wp_parse_url( $url, $component = -1 ) {
    if ($component === -1) {
        return parse_url($url);
    }
    return parse_url($url, $component);
}

function wp_strip_all_tags( $text ) {
    return trim(strip_tags((string) $text));
}

function home_url( $path = '' ) {
    return 'https://technorapper.test' . $path;
}

function get_the_ID() {
    return (int) $GLOBALS['technorapper_test_the_id'];
}

function get_post_meta( $post_id, $key, $single = false ) {
    $post_id = (int) $post_id;
    if (!isset($GLOBALS['technorapper_test_meta'][$post_id][$key])) {
        return $single ? '' : array();
    }
    $value = $GLOBALS['technorapper_test_meta'][$post_id][$key];
    return $single ? $value : array($value);
}

function apply_filters( $hook, $value ) {
    $args = func_get_args();
    if (empty($GLOBALS['technorapper_test_filters'][$hook])) {
        return $value;
    }
    foreach ($GLOBALS['technorapper_test_filters'][$hook] as $callback) {
        $value = call_user_func_array($callback, array_slice($args, 1));
    }
    return $value;
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    unset($priority, $accepted_args);
    $GLOBALS['technorapper_test_filters'][$hook][] = $callback;
}

function technorapper_test_reset() {
    $GLOBALS['technorapper_test_meta'] = array();
    $GLOBALS['technorapper_test_filters'] = array();
    $GLOBALS['technorapper_test_the_id'] = 0;
}

function technorapper_test_set_meta( $post_id, $key, $value ) {
    $GLOBALS['technorapper_test_meta'][(int) $post_id][$key] = $value;
}

require_once dirname(__DIR__) . '/inc/ai-disclosure.php';
