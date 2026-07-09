<?php
/**
 * Auto-Generate Queue — WP Cron processor
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Queue {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'pbp_auto_generate', [ $this, 'run' ] );
        add_action( 'pbp_process_single', [ $this, 'process_single' ], 10, 1 );

        // Register custom intervals
        add_filter( 'cron_schedules', [ $this, 'add_cron_intervals' ] );

        // Reschedule when settings change
        add_action( 'update_option_pbp_settings', [ $this, 'reschedule' ], 10, 2 );

        // Schedule on init if enabled
        add_action( 'init', [ $this, 'maybe_schedule' ] );
    }

    // ----------------------------------------------------------------
    // Cron intervals
    // ----------------------------------------------------------------
    public function add_cron_intervals( array $schedules ): array {
        $schedules['pbp_every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Every 6 hours',
        ];
        return $schedules;
    }

    // ----------------------------------------------------------------
    // Schedule management
    // ----------------------------------------------------------------
    public function maybe_schedule(): void {
        if ( ! PBP_Settings::get( 'auto_queue', false ) ) {
            $this->clear_schedule();
            return;
        }

        if ( ! wp_next_scheduled( 'pbp_auto_generate' ) ) {
            $this->schedule();
        }
    }

    public function schedule(): void {
        $interval = PBP_Settings::get( 'auto_interval', 'daily' );
        wp_schedule_event( time(), $interval, 'pbp_auto_generate' );
    }

    public function clear_schedule(): void {
        $timestamp = wp_next_scheduled( 'pbp_auto_generate' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'pbp_auto_generate' );
        }
    }

    public function reschedule( $old, $new ): void {
        $this->clear_schedule();
        if ( ! empty( $new['auto_queue'] ) ) {
            $this->schedule();
        }
    }

    // ----------------------------------------------------------------
    // Main queue runner (called by cron)
    // ----------------------------------------------------------------
    public function run(): void {
        if ( ! PBP_Settings::get( 'auto_queue', false ) ) return;

        // Step 1: Find eligible posts and add to queue
        $this->sync_eligible_posts();

        // Step 2: Process N items from queue
        $batch_size = (int) PBP_Settings::get( 'auto_batch_size', 3 );
        $this->process_batch( $batch_size );
    }

    // ----------------------------------------------------------------
    // Find eligible posts and queue them
    // ----------------------------------------------------------------
    private function sync_eligible_posts(): void {
        $categories = PBP_Settings::get( 'auto_categories', [] );
        $min_words  = (int) PBP_Settings::get( 'auto_min_words', 300 );

        $args = [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_pbp_status',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_pbp_status',
                        'value'   => [ 'done', 'processing', 'queued' ],
                        'compare' => 'NOT IN',
                    ],
                ],
            ],
        ];

        if ( ! empty( $categories ) ) {
            $args['category__in'] = array_map( 'intval', $categories );
        }

        $query = new WP_Query( $args );
        foreach ( $query->posts as $post ) {
            $word_count = str_word_count( wp_strip_all_tags( $post->post_content ) );
            if ( $word_count < $min_words ) continue;

            $this->add_to_queue( $post->ID );
        }
        wp_reset_postdata();
    }

    // ----------------------------------------------------------------
    // Queue operations
    // ----------------------------------------------------------------
    public function add_to_queue( int $post_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_queue';

        // Check if already in queue
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE post_id = %d AND status IN ('queued','processing')",
            $post_id
        ) );

        if ( $existing ) return false;

        $result = $wpdb->replace( $table, [
            'post_id'      => $post_id,
            'status'       => 'queued',
            'attempts'     => 0,
            'scheduled_at' => current_time( 'mysql', true ),
        ], [ '%d', '%s', '%d', '%s' ] );

        if ( $result ) {
            update_post_meta( $post_id, '_pbp_status', 'queued' );
        }

        return $result !== false;
    }

    public function process_batch( int $limit = 3 ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_queue';

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE status = 'queued' AND attempts < 3 ORDER BY created_at ASC LIMIT %d",
            $limit
        ), ARRAY_A );

        foreach ( $items as $item ) {
            $this->process_single( (int) $item['post_id'] );
        }
    }

    public function process_single( int $post_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_queue';

        // Mark as processing
        $wpdb->update( $table, [
            'status'   => 'processing',
            'attempts' => $wpdb->get_var( $wpdb->prepare(
                "SELECT attempts FROM {$table} WHERE post_id = %d", $post_id
            ) ) + 1,
        ], [ 'post_id' => $post_id ], [ '%s', '%d' ], [ '%d' ] );

        update_post_meta( $post_id, '_pbp_status', 'processing' );

        // Run generation
        $result = $this->generate_episode( $post_id );

        if ( $result['success'] ) {
            $wpdb->update( $table, [
                'status'       => 'done',
                'processed_at' => current_time( 'mysql', true ),
                'error_msg'    => null,
            ], [ 'post_id' => $post_id ], [ '%s', '%s', '%s' ], [ '%d' ] );
            update_post_meta( $post_id, '_pbp_status', 'done' );
        } else {
            $wpdb->update( $table, [
                'status'    => 'failed',
                'error_msg' => $result['error'],
            ], [ 'post_id' => $post_id ], [ '%s', '%s' ], [ '%d' ] );
            update_post_meta( $post_id, '_pbp_status', 'error' );
            update_post_meta( $post_id, '_pbp_error_msg', $result['error'] );
        }
    }

    // ----------------------------------------------------------------
    // Full episode generation pipeline
    // ----------------------------------------------------------------
    public function generate_episode( int $post_id ): array {
        // 1. Generate AI script
        $ai_result = PBP_AI::instance()->generate_script( $post_id );
        if ( ! $ai_result['success'] ) {
            return [ 'success' => false, 'error' => 'AI: ' . $ai_result['error'] ];
        }
        $script = $ai_result['script'];
        update_post_meta( $post_id, '_pbp_script', $script );

        // 2. Generate TTS audio
        $tts_provider = PBP_Settings::get( 'tts_provider', 'openai' );
        if ( $tts_provider !== 'manual' ) {
            $tts_result = PBP_TTS::instance()->generate( $post_id, $script );
            if ( ! $tts_result['success'] ) {
                return [ 'success' => false, 'error' => 'TTS: ' . $tts_result['error'] ];
            }
            update_post_meta( $post_id, '_pbp_audio_url', $tts_result['audio_url'] );
            update_post_meta( $post_id, '_pbp_audio_size', $tts_result['audio_size'] );
            update_post_meta( $post_id, '_pbp_duration', $tts_result['duration'] );
        }

        // 3. Build show notes
        $show_notes = PBP_ShowNotes::instance()->build( $post_id, $script );
        update_post_meta( $post_id, '_pbp_show_notes', $show_notes );

        // 4. Mark done & timestamp
        update_post_meta( $post_id, '_pbp_status', 'done' );
        update_post_meta( $post_id, '_pbp_generated_at', gmdate( 'c' ) );
        delete_post_meta( $post_id, '_pbp_error_msg' );

        do_action( 'pbp_episode_generated', $post_id );

        return [ 'success' => true, 'error' => '' ];
    }

    // ----------------------------------------------------------------
    // Queue stats
    // ----------------------------------------------------------------
    public static function get_stats(): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_queue';
        $rows  = $wpdb->get_results( "SELECT status, COUNT(*) as cnt FROM {$table} GROUP BY status", ARRAY_A );
        $stats = [ 'queued' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0 ];
        foreach ( $rows as $row ) {
            $stats[ $row['status'] ] = (int) $row['cnt'];
        }
        return $stats;
    }

    // ----------------------------------------------------------------
    // Get queue items (for admin UI)
    // ----------------------------------------------------------------
    public static function get_items( int $limit = 20, string $status = '' ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'pbp_queue';
        $where = $status ? $wpdb->prepare( 'WHERE status = %s', $status ) : '';
        return $wpdb->get_results(
            "SELECT q.*, p.post_title FROM {$table} q
             LEFT JOIN {$wpdb->posts} p ON q.post_id = p.ID
             {$where}
             ORDER BY q.created_at DESC LIMIT {$limit}",
            ARRAY_A
        ) ?: [];
    }
}
