<?php
// Standalone regression test: php tests/redirect-index-repair.php [class-file]
define('ABSPATH', __DIR__ . '/');
$options = [];
$authorized = true;
$valid_nonce = true;
function add_action(...$args) {}
function current_user_can($cap) { return $GLOBALS['authorized']; }
function wp_verify_nonce(...$args) { return $GLOBALS['valid_nonce']; }
function wp_normalize_path($path) { return str_replace('\\', '/', $path); }
function untrailingslashit($path) { return rtrim($path, '/'); }
function trailingslashit($path) { return rtrim($path, '/') . '/'; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function update_option($key, $value) { $GLOBALS['options'][$key] = $value; }
function delete_option($key) { unset($GLOBALS['options'][$key]); }
function esc_html__($text, $domain) { return $text; }
function wp_remote_get(...$args) { throw new RuntimeException('Repair must not depend on HTTP availability.'); }
function verify($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}

$root = sys_get_temp_dir() . '/itmar-redirect-test-' . bin2hex(random_bytes(8));
mkdir($root);
$_SERVER['DOCUMENT_ROOT'] = $root;
require $argv[1] ?? dirname(__DIR__) . '/src/ItmarRedirectControl.php';
$control = \Itmar\WpsettingClassPackage\ItmarRedirectControl::get_instance();
$index = $root . '/index.php';
$base = ['siteurl' => 'https://example.test/wordpress', 'home' => 'https://example.test/', 'itmar_redirect_to_subdir' => 1];
$_POST = ['_wpnonce' => 'test', 'submit' => 'Save', 'itmar_redirect_to_subdir' => '1'];
try {
    $options = $base;
    $control->maybe_apply_redirect();
    verify(is_file($index) && str_contains(file_get_contents($index), "require __DIR__ . '/wordpress/wp-blog-header.php';"), 'Missing index is regenerated');
    verify($options === $base, 'Legacy enabled settings remain unchanged, including absent previous home');
    $original = file_get_contents($index);
    $options['itmar_redirect_prev_home'] = 'https://example.test/original/';
    $expected = $options;
    $options['itmar_redirect_error'] = 'old error';
    $control->maybe_apply_redirect();
    verify(file_get_contents($index) === $original && $options === $expected, 'Valid index and previous home are preserved; stale error cleared');

    foreach (["<?php // foreign index", str_replace('/wordpress/', '/another-site/', $original)] as $invalid) {
        file_put_contents($index, $invalid);
        $options = $base;
        $control->maybe_apply_redirect();
        verify(file_get_contents($index) === $invalid && isset($options['itmar_redirect_error']), 'Invalid existing index is preserved and reported');
        unset($options['itmar_redirect_error']);
        verify($options === $base, 'Validation failure leaves URL and enabled state unchanged');
    }

    unlink($index);
    $options = $base;
    $authorized = false;
    $control->maybe_apply_redirect();
    verify(!file_exists($index), 'Unauthorized request cannot repair');
    $authorized = true;
    $valid_nonce = false;
    $control->maybe_apply_redirect();
    verify(!file_exists($index), 'Invalid nonce cannot repair');
    $valid_nonce = true;
    unset($_POST['submit']);
    $control->maybe_apply_redirect();
    verify(!file_exists($index), 'Ordinary request cannot repair');
    $_POST['submit'] = 'Save';
    unset($_POST['itmar_redirect_to_subdir']);
    $options['itmar_redirect_to_subdir'] = 0;
    $control->maybe_apply_redirect();
    verify(!file_exists($index), 'Disabled setting does not generate index');

    file_put_contents($index, $original);
    $options = $expected;
    $control->maybe_apply_redirect();
    verify(!file_exists($index) && $options['home'] === $expected['itmar_redirect_prev_home'] && $options['itmar_redirect_to_subdir'] === 0, 'Disabling still removes generated index and restores previous home');
} finally {
    if (is_file($index)) { unlink($index); }
    rmdir($root);
}
