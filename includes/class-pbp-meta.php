<?php
/**
 * Post Meta Box — hiển thị Podcast Builder controls trong mỗi bài viết
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Meta {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
    }

    // ----------------------------------------------------------------
    // Register meta box
    // ----------------------------------------------------------------
    public function add_meta_box(): void {
        add_meta_box(
            'pbp_podcast_box',
            '🎙️ Podcast Builder Pro',
            [ $this, 'render_meta_box' ],
            'post',
            'normal',
            'high'
        );
    }

    // ----------------------------------------------------------------
    // Enqueue scripts only on post edit screen
    // ----------------------------------------------------------------
    public function enqueue_scripts( string $hook ): void {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;

        wp_enqueue_script(
            'pbp-meta-box',
            PBP_URL . 'admin/js/meta-box.js',
            [ 'jquery' ],
            PBP_VERSION,
            true
        );
        wp_localize_script( 'pbp-meta-box', 'PBP', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pbp_meta_nonce' ),  // dedicated AJAX nonce
            'post_id'  => get_the_ID(),
            'strings'  => [
                'generating'    => 'Đang tạo podcast... (~30-120 giây)',
                'done'          => '✅ Đã tạo thành công!',
                'error'         => '❌ Lỗi: ',
                'confirm_regen' => 'Bài viết này đã có podcast. Tạo lại sẽ ghi đè dữ liệu cũ. Tiếp tục?',
            ],
        ] );

        wp_enqueue_style( 'pbp-meta-box', PBP_URL . 'admin/css/meta-box.css', [], PBP_VERSION );
    }

    // ----------------------------------------------------------------
    // Render meta box HTML
    // ----------------------------------------------------------------
    public function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( 'pbp_save_meta', 'pbp_meta_nonce' );

        $status       = get_post_meta( $post->ID, '_pbp_status', true ) ?: 'not_generated';
        $audio_url    = get_post_meta( $post->ID, '_pbp_audio_url', true );
        $duration     = get_post_meta( $post->ID, '_pbp_duration', true );
        $backlink_url = get_post_meta( $post->ID, '_pbp_backlink_url', true );
        $anchor_text  = get_post_meta( $post->ID, '_pbp_anchor_text', true );
        $generated_at = get_post_meta( $post->ID, '_pbp_generated_at', true );
        $error_msg    = get_post_meta( $post->ID, '_pbp_error_msg', true );
        $script       = get_post_meta( $post->ID, '_pbp_script', true );
        $show_notes   = get_post_meta( $post->ID, '_pbp_show_notes', true );
        $exclude      = (bool) get_post_meta( $post->ID, '_pbp_exclude', true );
        $tts_provider = PBP_Settings::get( 'tts_provider', 'openai' );

        $status_icons = [
            'not_generated' => [ 'icon' => '⚫', 'label' => 'Chưa tạo', 'class' => 'pbp-status-none' ],
            'queued'        => [ 'icon' => '⏳', 'label' => 'Đang chờ trong queue', 'class' => 'pbp-status-queued' ],
            'processing'    => [ 'icon' => '🔄', 'label' => 'Đang xử lý...', 'class' => 'pbp-status-processing' ],
            'done'          => [ 'icon' => '✅', 'label' => 'Đã tạo xong', 'class' => 'pbp-status-done' ],
            'error'         => [ 'icon' => '❌', 'label' => 'Lỗi', 'class' => 'pbp-status-error' ],
        ];
        $s = $status_icons[ $status ] ?? $status_icons['not_generated'];
        ?>
        <div class="pbp-meta-wrap" id="pbp-meta-wrap">

            <!-- STATUS BAR -->
            <div class="pbp-status-bar <?php echo esc_attr( $s['class'] ); ?>">
                <span class="pbp-status-icon"><?php echo esc_html( $s['icon'] ); ?></span>
                <span class="pbp-status-label"><?php echo esc_html( $s['label'] ); ?></span>
                <?php if ( $generated_at ) : ?>
                    <span class="pbp-status-date">— <?php echo esc_html( human_time_diff( strtotime( $generated_at ), time() ) ); ?> trước</span>
                <?php endif; ?>
                <?php if ( $audio_url && $duration ) : ?>
                    <span class="pbp-status-duration">🕐 <?php echo esc_html( $duration ); ?></span>
                <?php endif; ?>
            </div>

            <?php if ( $error_msg ) : ?>
            <div class="pbp-notice pbp-notice-error">
                ❌ <?php echo esc_html( $error_msg ); ?>
            </div>
            <?php endif; ?>

            <!-- AUDIO PLAYER (if done) -->
            <?php if ( $audio_url ) : ?>
            <div class="pbp-audio-row">
                <label>🎵 <strong>Audio:</strong></label>
                <audio controls style="width:100%;margin-top:6px;">
                    <source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
                </audio>
                <a href="<?php echo esc_url( $audio_url ); ?>" target="_blank" class="pbp-link-sm">Mở URL audio</a>
            </div>
            <?php endif; ?>

            <!-- SETTINGS ROW -->
            <div class="pbp-fields-grid">

                <!-- Audio source override -->
                <?php if ( $tts_provider === 'manual' ) : ?>
                <div class="pbp-field pbp-field-full">
                    <label>🎵 Audio URL bên ngoài</label>
                    <input type="url" name="pbp_audio_url_manual" value="<?php echo esc_attr( $audio_url ); ?>"
                           placeholder="https://..." class="widefat">
                </div>
                <?php endif; ?>

                <div class="pbp-field">
                    <label for="pbp_backlink_url">🔗 Backlink URL <small>(override)</small></label>
                    <input type="url" id="pbp_backlink_url" name="pbp_backlink_url"
                           value="<?php echo esc_attr( $backlink_url ); ?>"
                           placeholder="<?php echo esc_attr( PBP_Settings::get( 'default_backlink_url', home_url() ) ); ?>"
                           class="widefat">
                </div>

                <div class="pbp-field">
                    <label for="pbp_anchor_text">⚓ Anchor Text <small>(override)</small></label>
                    <input type="text" id="pbp_anchor_text" name="pbp_anchor_text"
                           value="<?php echo esc_attr( $anchor_text ); ?>"
                           placeholder="<?php echo esc_attr( PBP_Settings::get( 'default_anchor_text', 'Đọc bài viết đầy đủ' ) ); ?>"
                           class="widefat">
                </div>
            </div>

            <!-- ACTION BUTTONS -->
            <div class="pbp-actions">
                <button type="button" id="pbp-btn-generate" class="button button-primary pbp-btn-generate"
                        data-post-id="<?php echo intval( $post->ID ); ?>"
                        data-status="<?php echo esc_attr( $status ); ?>">
                    <?php echo $status === 'done' ? '🔄 Tạo lại Podcast' : '🚀 Generate Podcast'; ?>
                </button>

                <?php if ( $script ) : ?>
                <button type="button" class="button pbp-btn-toggle" data-target="pbp-script-area">
                    📝 Xem Script
                </button>
                <?php endif; ?>

                <?php if ( $show_notes ) : ?>
                <button type="button" class="button pbp-btn-toggle" data-target="pbp-shownotes-area">
                    📋 Xem Show Notes
                </button>
                <?php endif; ?>

                <?php if ( $audio_url ) : ?>
                <a href="<?php echo esc_url( PBP_Feed::get_feed_url() ); ?>" target="_blank" class="button">
                    📡 Xem RSS Feed
                </a>
                <?php endif; ?>
            </div>

            <!-- PROGRESS BAR (hidden by default) -->
            <div class="pbp-progress" id="pbp-progress" style="display:none;">
                <div class="pbp-progress-bar"><div class="pbp-progress-fill"></div></div>
                <p class="pbp-progress-msg">Đang kết nối AI...</p>
            </div>

            <!-- SCRIPT PREVIEW (collapsed) -->
            <?php if ( $script ) : ?>
            <div class="pbp-collapsible" id="pbp-script-area" style="display:none;">
                <label><strong>📝 AI Script</strong></label>
                <textarea class="widefat" rows="8" name="pbp_script"
                          style="font-family:monospace;font-size:12px;"><?php echo esc_textarea( $script ); ?></textarea>
            </div>
            <?php endif; ?>

            <!-- SHOW NOTES PREVIEW (collapsed) -->
            <?php if ( $show_notes ) : ?>
            <div class="pbp-collapsible" id="pbp-shownotes-area" style="display:none;">
                <label><strong>📋 Show Notes</strong></label>
                <textarea class="widefat" rows="8" name="pbp_show_notes"
                          style="font-size:12px;"><?php echo esc_textarea( $show_notes ); ?></textarea>
            </div>
            <?php endif; ?>

            <!-- EXCLUDE FROM FEED -->
            <div class="pbp-exclude-row">
                <label>
                    <input type="checkbox" name="pbp_exclude" value="1" <?php checked( $exclude ); ?>>
                    Loại trừ bài này khỏi RSS feed
                </label>
            </div>

        </div><!-- /.pbp-meta-wrap -->
        <?php
    }

    // ----------------------------------------------------------------
    // Save meta
    // ----------------------------------------------------------------
    public function save_meta( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['pbp_meta_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['pbp_meta_nonce'], 'pbp_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        // Backlink URL
        if ( isset( $_POST['pbp_backlink_url'] ) ) {
            $url = esc_url_raw( wp_unslash( $_POST['pbp_backlink_url'] ) );
            update_post_meta( $post_id, '_pbp_backlink_url', $url );
        }

        // Anchor text
        if ( isset( $_POST['pbp_anchor_text'] ) ) {
            update_post_meta( $post_id, '_pbp_anchor_text', sanitize_text_field( wp_unslash( $_POST['pbp_anchor_text'] ) ) );
        }

        // Manual audio URL
        if ( isset( $_POST['pbp_audio_url_manual'] ) && ! empty( $_POST['pbp_audio_url_manual'] ) ) {
            update_post_meta( $post_id, '_pbp_audio_url', esc_url_raw( wp_unslash( $_POST['pbp_audio_url_manual'] ) ) );
        }

        // Script & show notes (editable)
        if ( isset( $_POST['pbp_script'] ) ) {
            update_post_meta( $post_id, '_pbp_script', sanitize_textarea_field( wp_unslash( $_POST['pbp_script'] ) ) );
        }
        if ( isset( $_POST['pbp_show_notes'] ) ) {
            update_post_meta( $post_id, '_pbp_show_notes', wp_kses_post( wp_unslash( $_POST['pbp_show_notes'] ) ) );
        }

        // Exclude
        $exclude = isset( $_POST['pbp_exclude'] ) ? true : false;
        update_post_meta( $post_id, '_pbp_exclude', $exclude );
    }
}
