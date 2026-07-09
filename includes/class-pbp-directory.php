<?php
/**
 * Directory Tracker — quản lý danh sách podcast directories
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Directory {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        // Nothing to hook for now — UI handled by admin class
    }

    // ----------------------------------------------------------------
    // Default directory list
    // ----------------------------------------------------------------
    public static function get_default_directories(): array {
        return [
            [
                'platform'   => 'apple',
                'label'      => 'Apple Podcasts',
                'submit_url' => 'https://podcastsconnect.apple.com',
                'da'         => 100,
            ],
            [
                'platform'   => 'spotify',
                'label'      => 'Spotify for Podcasters',
                'submit_url' => 'https://podcasters.spotify.com',
                'da'         => 93,
            ],
            [
                'platform'   => 'amazon',
                'label'      => 'Amazon Music / Audible',
                'submit_url' => 'https://podcasters.amazon.com',
                'da'         => 88,
            ],
            [
                'platform'   => 'google',
                'label'      => 'Google Podcasts Manager',
                'submit_url' => 'https://podcastsmanager.google.com',
                'da'         => 94,
            ],
            [
                'platform'   => 'podcast_index',
                'label'      => 'Podcast Index',
                'submit_url' => 'https://podcastindex.org/apps',
                'da'         => 71,
            ],
            [
                'platform'   => 'iheartradio',
                'label'      => 'iHeartRadio',
                'submit_url' => 'https://www.iheart.com/content/submit-your-podcast/',
                'da'         => 82,
            ],
            [
                'platform'   => 'castbox',
                'label'      => 'Castbox',
                'submit_url' => 'https://castbox.fm/dashboard',
                'da'         => 57,
            ],
            [
                'platform'   => 'pocket_casts',
                'label'      => 'Pocket Casts',
                'submit_url' => 'https://pocketcasts.com/submit',
                'da'         => 72,
            ],
            [
                'platform'   => 'tunein',
                'label'      => 'TuneIn',
                'submit_url' => 'https://help.tunein.com/contact/add-podcast-S19TR3Sdf',
                'da'         => 91,
            ],
            [
                'platform'   => 'podchaser',
                'label'      => 'Podchaser',
                'submit_url' => 'https://www.podchaser.com/podcasts/claim',
                'da'         => 67,
            ],
            [
                'platform'   => 'listen_notes',
                'label'      => 'Listen Notes',
                'submit_url' => 'https://www.listennotes.com/submit/',
                'da'         => 68,
            ],
        ];
    }

    // ----------------------------------------------------------------
    // Seed default directories on activation
    // ----------------------------------------------------------------
    public static function seed_defaults(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_directory';

        // Only seed if table is empty
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        if ( $count > 0 ) return;

        foreach ( self::get_default_directories() as $dir ) {
            $wpdb->insert( $table, [
                'platform'   => $dir['platform'],
                'label'      => $dir['label'],
                'submit_url' => $dir['submit_url'],
                'da'         => $dir['da'],
                'status'     => 'pending',
            ], [ '%s', '%s', '%s', '%d', '%s' ] );
        }
    }

    // ----------------------------------------------------------------
    // CRUD
    // ----------------------------------------------------------------
    public static function get_all(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_directory';
        return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY da DESC", ARRAY_A ) ?: [];
    }

    public static function get( int $id ): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_directory';
        $row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
        return $row ?: null;
    }

    public static function update( int $id, array $data ): bool {
        global $wpdb;
        $table   = $wpdb->prefix . 'pbp_directory';
        $allowed = [ 'status', 'profile_url', 'notes', 'submitted_at', 'approved_at' ];
        $clean   = [];
        $formats = [];

        foreach ( $allowed as $field ) {
            if ( array_key_exists( $field, $data ) ) {
                $clean[ $field ]  = sanitize_text_field( $data[ $field ] );
                $formats[]        = '%s';
            }
        }

        if ( empty( $clean ) ) return false;

        // Auto-set timestamps
        if ( isset( $clean['status'] ) ) {
            if ( $clean['status'] === 'submitted' && empty( $clean['submitted_at'] ) ) {
                $clean['submitted_at'] = current_time( 'mysql', true );
                $formats[]             = '%s';
            }
            if ( $clean['status'] === 'approved' && empty( $clean['approved_at'] ) ) {
                $clean['approved_at'] = current_time( 'mysql', true );
                $formats[]            = '%s';
            }
        }

        $result = $wpdb->update( $table, $clean, [ 'id' => $id ], $formats, [ '%d' ] );
        return $result !== false;
    }

    public static function add_custom( array $data ): int {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_directory';

        $wpdb->insert( $table, [
            'platform'   => sanitize_key( $data['platform'] ?? 'custom_' . time() ),
            'label'      => sanitize_text_field( $data['label'] ?? '' ),
            'submit_url' => esc_url_raw( $data['submit_url'] ?? '' ),
            'da'         => (int) ( $data['da'] ?? 0 ),
            'status'     => 'pending',
        ], [ '%s', '%s', '%s', '%d', '%s' ] );

        return (int) $wpdb->insert_id;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $table  = $wpdb->prefix . 'pbp_directory';
        $result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
        return $result !== false;
    }

    // ----------------------------------------------------------------
    // Stats
    // ----------------------------------------------------------------
    public static function get_stats(): array {
        $all = self::get_all();
        $stats = [ 'total' => count( $all ), 'pending' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0 ];
        foreach ( $all as $row ) {
            $s = $row['status'] ?? 'pending';
            if ( isset( $stats[ $s ] ) ) $stats[ $s ]++;
        }
        return $stats;
    }

    // ----------------------------------------------------------------
    // Status labels
    // ----------------------------------------------------------------
    public static function status_labels(): array {
        return [
            'pending'   => '⏳ Chưa submit',
            'submitted' => '📤 Đã submit — chờ duyệt',
            'approved'  => '✅ Đã duyệt — Live',
            'rejected'  => '❌ Bị từ chối',
        ];
    }
}
