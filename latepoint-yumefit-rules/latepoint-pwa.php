<?php
/**
 * Plugin Name: WP-Admin PWA
 * Description: Makes the WordPress admin installable as a standalone app (home-screen icon) that opens straight into LatePoint.
 * Version:     1.0
 *
 * Drop this file into wp-content/mu-plugins/ (create the folder if it doesn't exist).
 * mu-plugins load automatically — no activation needed.
 */

if (!defined('ABSPATH')) {
    exit;
}

const WP_ADMIN_PWA_MANIFEST = 'wp-admin-pwa-manifest.json';
const WP_ADMIN_PWA_SW = 'wp-admin-pwa-sw.js';

/**
 * Serve the manifest and service worker from the site root.
 * Using a request-URI check avoids touching rewrite rules / flushing permalinks.
 */
add_action('init', function () {
    $path = trim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

    if ($path === WP_ADMIN_PWA_MANIFEST) {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo wp_json_encode([
            'name' => get_bloginfo('name') . ' Admin',
            'short_name' => 'Yumefit',
            'start_url' => admin_url('admin.php?page=latepoint'),
            'scope' => parse_url(admin_url(), PHP_URL_PATH),
            'display' => 'standalone',
            'background_color' => '#1d2327', // WP admin dark grey
            'theme_color' => '#1d2327',
            'icons' => [
                [
                    // Uses the site's Site Icon (Settings > General) — WP serves it
                    // at the requested size. Falls back to the WP logo if unset.
                    'src' => apply_filters('wp_admin_pwa_icon_192', get_site_icon_url(192) ?: includes_url('images/w-logo-blue.png')),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                ],
                [
                    'src' => apply_filters('wp_admin_pwa_icon_512', get_site_icon_url(512) ?: includes_url('images/w-logo-blue.png')),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ]);
        exit;
    }

    if ($path === WP_ADMIN_PWA_SW) {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        // Minimal SW: just enough to satisfy Chrome's installability criteria.
        // Network-first with no offline caching — admin pages are full of
        // nonces and live data you don't want stale, so we deliberately
        // don't cache them.
        echo <<<'JS'
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', (event) => {
	event.respondWith(fetch(event.request).catch(() => Response.error()));
});
JS;
        exit;
    }
});

/**
 * Inject the manifest link, iOS meta tags, and register the service worker.
 * Applied to both the admin head and the login head, so the PWA stays
 * standalone even when an expired session bounces you to wp-login.php.
 */
$wp_admin_pwa_inject = function () {
    $manifest = home_url('/' . WP_ADMIN_PWA_MANIFEST);
    $sw = home_url('/' . WP_ADMIN_PWA_SW);
    $scope = parse_url(admin_url(), PHP_URL_PATH);
    $icon = apply_filters('wp_admin_pwa_icon_192', get_site_icon_url(192) ?: includes_url('images/w-logo-blue.png'));
    ?>
    <link rel="manifest" href="<?php echo esc_url($manifest); ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr(get_bloginfo('name')); ?> Admin">
    <link rel="apple-touch-icon" href="<?php echo esc_url($icon); ?>">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?php echo esc_js($sw); ?>', {scope: '<?php echo esc_js($scope); ?>'});
        }
    </script>
    <?php
};
add_action('admin_head', $wp_admin_pwa_inject);
add_action('login_head', $wp_admin_pwa_inject);