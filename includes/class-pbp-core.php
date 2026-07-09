<?php
/**
 * Core class — khởi tạo toàn bộ plugin
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Core {

    /** @var PBP_Core|null Singleton instance */
    private static $instance = null;

    /** Singleton */
    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Boot
    // ----------------------------------------------------------------
    public function boot(): void {
        $this->load_includes();
        $this->register_hooks();
    }

    private function load_includes(): void {
        require_once PBP_PATH . 'includes/class-pbp-settings.php';
        require_once PBP_PATH . 'includes/class-pbp-meta.php';
        require_once PBP_PATH . 'includes/class-pbp-ai.php';
        require_once PBP_PATH . 'includes/class-pbp-tts.php';
        require_once PBP_PATH . 'includes/class-pbp-shownotes.php';
        require_once PBP_PATH . 'includes/class-pbp-feed.php';
        require_once PBP_PATH . 'includes/class-pbp-directory.php';
        require_once PBP_PATH . 'includes/class-pbp-queue.php';
        require_once PBP_PATH . 'includes/class-pbp-license.php';
        require_once PBP_PATH . 'includes/class-pbp-admin.php';
        require_once PBP_PATH . 'includes/class-pbp-ajax.php';
    }

    private function register_hooks(): void {
        // Post meta registration
        add_action( 'init', [ $this, 'register_post_meta' ] );

        // Rewrite rules for RSS feed
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_redirect', [ $this, 'handle_feed_request' ] );

        // Init sub-modules
        PBP_Settings::instance()->init();
        PBP_Meta::instance()->init();
        PBP_Admin::instance()->init();
        PBP_Queue::instance()->init();
        PBP_Directory::instance()->init();
        PBP_AJAX::instance()->init();

        // Flush rewrite on activation
        add_action( 'admin_init', [ $this, 'maybe_flush_rewrite' ] );
    }

    // ----------------------------------------------------------------
    // Post Meta
    // ----------------------------------------------------------------
    public function register_post_meta(): void {
        $meta_fields = [
            '_pbp_status'       => 'string',   // pending|processing|done|error
            '_pbp_script'       => 'string',   // AI-generated script
            '_pbp_audio_url'    => 'string',   // Final audio URL
            '_pbp_audio_size'   => 'integer',  // File size in bytes
            '_pbp_duration'     => 'string',   // HH:MM:SS
            '_pbp_show_notes'   => 'string',   // Show notes HTML
            '_pbp_backlink_url' => 'string',   // Override backlink URL
            '_pbp_anchor_text'  => 'string',   // Override anchor text
            '_pbp_episode_num'  => 'integer',  // Episode number
            '_pbp_generated_at' => 'string',   // ISO 8601 datetime
            '_pbp_error_msg'    => 'string',   // Last error message
            '_pbp_exclude'      => 'boolean',  // Exclude from feed
        ];

        foreach ( $meta_fields as $key => $type ) {
            register_post_meta( 'post', $key, [
                'show_in_rest'  => true,
                'single'        => true,
                'type'          => $type,
                'auth_callback' => function () {
                    return current_user_can( 'edit_posts' );
                },
            ] );
        }
    }

    // ----------------------------------------------------------------
    // RSS Feed Endpoint
    // ----------------------------------------------------------------
    public function add_rewrite_rules(): void {
        $slug = get_option( 'pbp_feed_slug', 'podcast-feed' );
        add_rewrite_rule( '^' . $slug . '/?$', 'index.php?pbp_feed=1', 'top' );
    }

    public function add_query_vars( array $vars ): array {
        $vars[] = 'pbp_feed';
        return $vars;
    }

    public function handle_feed_request(): void {
        if ( get_query_var( 'pbp_feed' ) !== '1' ) {
            return;
        }
        PBP_Feed::instance()->render();
        exit;
    }

    public function maybe_flush_rewrite(): void {
        if ( get_option( 'pbp_flush_rewrite' ) ) {
            flush_rewrite_rules();
            delete_option( 'pbp_flush_rewrite' );
        }
    }

    // ----------------------------------------------------------------
    // Activation / Deactivation
    // ----------------------------------------------------------------
    public static function activate(): void {
        // Load required classes that are not yet available during activation hook
        // (load_includes() only runs inside boot() → plugins_loaded)
        $includes = [
            'class-pbp-settings.php',
            'class-pbp-directory.php',
        ];
        foreach ( $includes as $file ) {
            $path = PBP_PATH . 'includes/' . $file;
            if ( file_exists( $path ) ) {
                require_once $path;
            }
        }

        self::create_tables();

        // Seed default directory list
        PBP_Directory::seed_defaults();

        // Signal to flush rewrite rules on next admin load
        update_option( 'pbp_flush_rewrite', 1 );
        update_option( 'pbp_db_version', PBP_DB_VERSION );
    }

    public static function deactivate(): void {
        wp_clear_scheduled_hook( 'pbp_auto_generate' );
        flush_rewrite_rules();
    }

    // ----------------------------------------------------------------
    // Database
    // ----------------------------------------------------------------
    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        // Directory tracking table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pbp_directory (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            platform    VARCHAR(80)  NOT NULL,
            label       VARCHAR(120) NOT NULL,
            submit_url  VARCHAR(255) NOT NULL DEFAULT '',
            da          TINYINT(3)   NOT NULL DEFAULT 0,
            status      ENUM('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending',
            profile_url VARCHAR(255) NOT NULL DEFAULT '',
            notes       TEXT,
            submitted_at DATETIME    DEFAULT NULL,
            approved_at  DATETIME    DEFAULT NULL,
            created_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY platform (platform)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Queue table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pbp_queue (
            id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id     BIGINT(20) UNSIGNED NOT NULL,
            status      ENUM('queued','processing','done','failed') NOT NULL DEFAULT 'queued',
            attempts    TINYINT(3) NOT NULL DEFAULT 0,
            scheduled_at DATETIME DEFAULT NULL,
            processed_at DATETIME DEFAULT NULL,
            error_msg   TEXT,
            created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY status (status)
        ) $charset;";

        dbDelta( $sql2 );
    }
}
