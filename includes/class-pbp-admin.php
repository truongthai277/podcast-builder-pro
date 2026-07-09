<?php
/**
 * Admin UI — menu pages, settings views, dashboard
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Admin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        PBP_License::instance()->check( true ); // Show notice if inactive
    }

    // ----------------------------------------------------------------
    // Admin menu
    // ----------------------------------------------------------------
    public function register_menu(): void {
        add_menu_page(
            'Podcast Builder Pro',
            '🎙️ Podcast Builder',
            'manage_options',
            'podcast-builder-pro',
            [ $this, 'page_dashboard' ],
            'dashicons-rss',
            30
        );

        add_submenu_page( 'podcast-builder-pro', 'Dashboard',         'Dashboard',        'manage_options', 'podcast-builder-pro',           [ $this, 'page_dashboard' ] );
        add_submenu_page( 'podcast-builder-pro', 'Episodes',          'Episodes',         'manage_options', 'podcast-builder-pro-episodes',  [ $this, 'page_episodes' ] );
        add_submenu_page( 'podcast-builder-pro', 'RSS Feed',          'RSS Feed',         'manage_options', 'podcast-builder-pro-feed',      [ $this, 'page_feed' ] );
        add_submenu_page( 'podcast-builder-pro', 'Directory Tracker', 'Directory Tracker','manage_options', 'podcast-builder-pro-directory', [ $this, 'page_directory' ] );
        add_submenu_page( 'podcast-builder-pro', 'Auto Queue',        'Auto Queue',       'manage_options', 'podcast-builder-pro-queue',     [ $this, 'page_queue' ] );
        add_submenu_page( 'podcast-builder-pro', 'Settings',          'Settings ⚙️',      'manage_options', 'podcast-builder-pro-settings',  [ $this, 'page_settings' ] );
        add_submenu_page( 'podcast-builder-pro', 'License',           'License 🔑',       'manage_options', 'podcast-builder-pro-license',   [ $this, 'page_license' ] );
    }

    // ----------------------------------------------------------------
    // Enqueue admin assets
    // ----------------------------------------------------------------
    public function enqueue( string $hook ): void {
        if ( strpos( $hook, 'podcast-builder-pro' ) === false ) return;

        wp_enqueue_style( 'pbp-admin', PBP_URL . 'admin/css/admin.css', [], PBP_VERSION );
        wp_enqueue_script( 'pbp-admin', PBP_URL . 'admin/js/admin.js', [ 'jquery' ], PBP_VERSION, true );
        wp_localize_script( 'pbp-admin', 'PBP_Admin', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pbp_admin_nonce' ),
            'feed_url' => PBP_Feed::get_feed_url(),
        ] );
    }

    // ----------------------------------------------------------------
    // PAGE: Dashboard
    // ----------------------------------------------------------------
    public function page_dashboard(): void {
        $ep_count  = PBP_Feed::get_episode_count();
        $q_stats   = PBP_Queue::get_stats();
        $dir_stats = PBP_Directory::get_stats();
        $feed_url  = PBP_Feed::get_feed_url();
        $license   = PBP_License::instance()->get_status_label();
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'Dashboard' ); ?>
            <div class="pbp-dashboard-grid">

                <div class="pbp-card pbp-card-highlight">
                    <div class="pbp-card-icon">📡</div>
                    <div class="pbp-card-body">
                        <h3>RSS Feed URL</h3>
                        <div class="pbp-feed-url-box">
                            <input type="text" value="<?php echo esc_attr( $feed_url ); ?>" readonly class="widefat" id="pbp-feed-url">
                            <button class="button" onclick="pbpCopyFeedUrl()">📋 Copy</button>
                        </div>
                        <div class="pbp-feed-actions">
                            <a href="<?php echo esc_url( $feed_url ); ?>" target="_blank" class="button button-secondary">Xem Feed</a>
                            <a href="https://podcastvalidator.com/?url=<?php echo urlencode( $feed_url ); ?>" target="_blank" class="button">Validate Feed</a>
                        </div>
                    </div>
                </div>

                <div class="pbp-stats-grid">
                    <div class="pbp-stat-card">
                        <div class="pbp-stat-number"><?php echo intval( $ep_count ); ?></div>
                        <div class="pbp-stat-label">Episodes</div>
                    </div>
                    <div class="pbp-stat-card">
                        <div class="pbp-stat-number pbp-text-warning"><?php echo intval( $q_stats['queued'] ); ?></div>
                        <div class="pbp-stat-label">Đang queue</div>
                    </div>
                    <div class="pbp-stat-card">
                        <div class="pbp-stat-number pbp-text-success"><?php echo intval( $dir_stats['approved'] ); ?></div>
                        <div class="pbp-stat-label">Directory Live</div>
                    </div>
                    <div class="pbp-stat-card">
                        <div class="pbp-stat-number"><?php echo intval( $dir_stats['total'] ); ?></div>
                        <div class="pbp-stat-label">Directories</div>
                    </div>
                </div>

                <div class="pbp-card">
                    <h3>🚀 Quick Start</h3>
                    <ol class="pbp-quick-start">
                        <li>⚙️ <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-settings'); ?>">Cấu hình API key AI & TTS</a></li>
                        <li>📝 Mở một bài viết bất kỳ → nhấn <strong>"Generate Podcast"</strong></li>
                        <li>📡 Copy RSS Feed URL ở trên → submit lên <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-directory'); ?>">directories</a></li>
                        <li>⚡ Bật <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-queue'); ?>">Auto Queue</a> để tự động hóa hoàn toàn</li>
                    </ol>
                </div>

                <div class="pbp-card">
                    <h3>📊 Queue Status</h3>
                    <table class="pbp-mini-table">
                        <tr><td>⏳ Đang queue</td><td><strong><?php echo intval($q_stats['queued']); ?></strong></td></tr>
                        <tr><td>🔄 Processing</td><td><strong><?php echo intval($q_stats['processing']); ?></strong></td></tr>
                        <tr><td>✅ Done</td><td><strong><?php echo intval($q_stats['done']); ?></strong></td></tr>
                        <tr><td>❌ Failed</td><td><strong><?php echo intval($q_stats['failed']); ?></strong></td></tr>
                    </table>
                </div>

                <div class="pbp-card">
                    <h3>📁 Directory Status</h3>
                    <table class="pbp-mini-table">
                        <tr><td>⏳ Chưa submit</td><td><strong><?php echo intval($dir_stats['pending']); ?></strong></td></tr>
                        <tr><td>📤 Đã submit</td><td><strong><?php echo intval($dir_stats['submitted']); ?></strong></td></tr>
                        <tr><td>✅ Live</td><td><strong><?php echo intval($dir_stats['approved']); ?></strong></td></tr>
                        <tr><td>❌ Rejected</td><td><strong><?php echo intval($dir_stats['rejected']); ?></strong></td></tr>
                    </table>
                </div>

                <div class="pbp-card">
                    <h3>🔑 License</h3>
                    <p><?php echo esc_html( $license ); ?></p>
                    <?php if ( PBP_Settings::get('license_expires') ) : ?>
                    <p class="pbp-text-muted">Hết hạn: <?php echo esc_html( PBP_Settings::get('license_expires') ); ?></p>
                    <?php endif; ?>
                    <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-license'); ?>" class="button">Quản lý License</a>
                </div>

            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: Episodes
    // ----------------------------------------------------------------
    public function page_episodes(): void {
        $posts = get_posts( [
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'meta_query'  => [ [ 'key' => '_pbp_status', 'compare' => 'EXISTS' ] ],
        ] );
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'Episodes' ); ?>
            <table class="wp-list-table widefat fixed striped pbp-episodes-table">
                <thead>
                    <tr>
                        <th>Bài viết</th>
                        <th>Trạng thái</th>
                        <th>Audio</th>
                        <th>Thời lượng</th>
                        <th>Ngày tạo</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $posts as $post ) :
                        $status   = get_post_meta( $post->ID, '_pbp_status', true );
                        $audio    = get_post_meta( $post->ID, '_pbp_audio_url', true );
                        $duration = get_post_meta( $post->ID, '_pbp_duration', true );
                        $gen_at   = get_post_meta( $post->ID, '_pbp_generated_at', true );
                        $status_icons = [
                            'done'       => '<span class="pbp-badge pbp-badge-success">✅ Done</span>',
                            'processing' => '<span class="pbp-badge pbp-badge-warning">🔄 Processing</span>',
                            'queued'     => '<span class="pbp-badge pbp-badge-info">⏳ Queued</span>',
                            'error'      => '<span class="pbp-badge pbp-badge-error">❌ Error</span>',
                        ];
                        $badge = $status_icons[$status] ?? '<span class="pbp-badge">⚫ ' . esc_html($status) . '</span>';
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo get_edit_post_link($post->ID); ?>"><?php echo esc_html($post->post_title); ?></a></strong>
                            <br><small><?php echo esc_html(get_permalink($post->ID)); ?></small>
                        </td>
                        <td><?php echo $badge; ?></td>
                        <td>
                            <?php if ($audio) : ?>
                                <audio controls style="width:200px;height:32px;">
                                    <source src="<?php echo esc_url($audio); ?>" type="audio/mpeg">
                                </audio>
                            <?php else : ?>
                                <em class="pbp-text-muted">—</em>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $duration ? esc_html($duration) : '—'; ?></td>
                        <td><?php echo $gen_at ? esc_html( human_time_diff( strtotime($gen_at), time() ) . ' trước' ) : '—'; ?></td>
                        <td>
                            <a href="<?php echo get_edit_post_link($post->ID); ?>" class="button button-small">Sửa</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: RSS Feed
    // ----------------------------------------------------------------
    public function page_feed(): void {
        $feed_url = PBP_Feed::get_feed_url();
        $count    = PBP_Feed::get_episode_count();
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'RSS Feed' ); ?>
            <div class="pbp-card">
                <h3>📡 RSS Feed URL của bạn</h3>
                <div class="pbp-feed-url-box">
                    <input type="text" value="<?php echo esc_attr($feed_url); ?>" readonly class="widefat" id="pbp-feed-url">
                    <button class="button button-primary" onclick="pbpCopyFeedUrl()">📋 Copy URL</button>
                </div>
                <p>Tổng số episodes trong feed: <strong><?php echo intval($count); ?></strong></p>
                <div style="margin-top:16px;display:flex;gap:12px;flex-wrap:wrap;">
                    <a href="<?php echo esc_url($feed_url); ?>" target="_blank" class="button">🔍 Xem Feed XML</a>
                    <a href="https://podcastvalidator.com/?url=<?php echo urlencode($feed_url); ?>" target="_blank" class="button">✅ Validate Feed</a>
                    <a href="https://podcastindex.org/add-feed" target="_blank" class="button">📤 Submit lên Podcast Index</a>
                </div>
            </div>

            <div class="pbp-card" style="margin-top:20px;">
                <h3>📋 Hướng dẫn submit RSS Feed</h3>
                <ol style="line-height:2;">
                    <li>Copy URL feed ở trên</li>
                    <li>Truy cập <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-directory'); ?>">Directory Tracker</a></li>
                    <li>Click vào từng platform → paste URL feed → submit</li>
                    <li>Sau khi được duyệt (~24h-7 ngày), cập nhật trạng thái → "✅ Đã duyệt"</li>
                    <li>Từ đó, mỗi tập mới sẽ tự động xuất hiện trên tất cả platforms đã đăng ký</li>
                </ol>
            </div>

            <div class="pbp-card" style="margin-top:20px;">
                <h3>⚙️ Cấu hình Feed</h3>
                <form method="post" action="options.php">
                    <?php settings_fields('pbp_settings_group'); ?>
                    <?php $s = get_option('pbp_settings', []); ?>
                    <table class="form-table">
                        <tr>
                            <th>Feed Slug</th>
                            <td>
                                <?php echo esc_html(home_url('/')); ?>
                                <input type="text" name="pbp_settings[feed_slug]"
                                       value="<?php echo esc_attr( PBP_Settings::get('feed_slug','podcast-feed') ); ?>"
                                       style="width:200px;">
                                /
                                <p class="description">Thay đổi slug → cần <a href="<?php echo admin_url('options-permalink.php'); ?>">Flush Rewrite Rules</a></p>
                            </td>
                        </tr>
                        <tr>
                            <th>Episodes per page</th>
                            <td>
                                <input type="number" name="pbp_settings[episodes_per_page]"
                                       value="<?php echo intval( PBP_Settings::get('episodes_per_page', 50) ); ?>"
                                       min="10" max="200" style="width:100px;">
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Lưu cấu hình Feed'); ?>
                </form>
            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: Directory Tracker
    // ----------------------------------------------------------------
    public function page_directory(): void {
        $directories   = PBP_Directory::get_all();
        $status_labels = PBP_Directory::status_labels();
        $feed_url      = PBP_Feed::get_feed_url();
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'Directory Tracker' ); ?>

            <div class="pbp-card pbp-card-info">
                <p>📡 <strong>RSS Feed URL của bạn:</strong>
                   <code id="pbp-feed-url-dir"><?php echo esc_html($feed_url); ?></code>
                   <button onclick="pbpCopyFeedUrl('pbp-feed-url-dir')" class="button button-small">Copy</button>
                   — Copy và paste vào từng platform khi submit.
                </p>
            </div>

            <div class="pbp-card" style="margin-top:16px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;">📋 Danh sách Directories</h3>
                    <button class="button button-primary" id="pbp-add-dir-btn">+ Thêm Directory</button>
                </div>

                <table class="wp-list-table widefat fixed striped pbp-dir-table">
                    <thead>
                        <tr>
                            <th style="width:25%">Platform</th>
                            <th style="width:8%">DA</th>
                            <th style="width:18%">Trạng thái</th>
                            <th style="width:25%">Profile URL</th>
                            <th style="width:15%">Hành động</th>
                        </tr>
                    </thead>
                    <tbody id="pbp-dir-tbody">
                        <?php foreach ( $directories as $dir ) : ?>
                        <tr data-id="<?php echo intval($dir['id']); ?>">
                            <td>
                                <strong><?php echo esc_html($dir['label']); ?></strong><br>
                                <a href="<?php echo esc_url($dir['submit_url']); ?>" target="_blank" class="pbp-link-sm">
                                    Trang đăng ký ↗
                                </a>
                            </td>
                            <td>
                                <span class="pbp-da-badge pbp-da-<?php echo $dir['da'] >= 80 ? 'high' : ($dir['da'] >= 60 ? 'med' : 'low'); ?>">
                                    <?php echo intval($dir['da']); ?>
                                </span>
                            </td>
                            <td>
                                <select class="pbp-dir-status" data-id="<?php echo intval($dir['id']); ?>">
                                    <?php foreach ( $status_labels as $val => $lbl ) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($dir['status'], $val); ?>>
                                        <?php echo esc_html($lbl); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="url" class="pbp-dir-profile widefat"
                                       data-id="<?php echo intval($dir['id']); ?>"
                                       value="<?php echo esc_attr($dir['profile_url']); ?>"
                                       placeholder="https://...">
                            </td>
                            <td>
                                <button class="button button-small pbp-dir-save" data-id="<?php echo intval($dir['id']); ?>">💾 Lưu</button>
                                <?php if ( ! in_array($dir['platform'], array_column( PBP_Directory::get_default_directories(), 'platform' ), true) ) : ?>
                                <button class="button button-small pbp-dir-delete" data-id="<?php echo intval($dir['id']); ?>">🗑</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Directory Modal -->
            <div id="pbp-add-dir-modal" style="display:none;" class="pbp-modal">
                <div class="pbp-modal-inner">
                    <h3>➕ Thêm Directory Mới</h3>
                    <table class="form-table">
                        <tr><th>Tên platform</th><td><input type="text" id="pbp-new-label" class="widefat" placeholder="Tên platform..."></td></tr>
                        <tr><th>URL đăng ký</th><td><input type="url" id="pbp-new-url" class="widefat" placeholder="https://..."></td></tr>
                        <tr><th>Domain Authority (DA)</th><td><input type="number" id="pbp-new-da" min="0" max="100" value="0" style="width:80px;"></td></tr>
                    </table>
                    <div style="margin-top:16px;display:flex;gap:8px;">
                        <button class="button button-primary" id="pbp-add-dir-submit">Thêm</button>
                        <button class="button" id="pbp-add-dir-cancel">Hủy</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: Auto Queue
    // ----------------------------------------------------------------
    public function page_queue(): void {
        $stats    = PBP_Queue::get_stats();
        $items    = PBP_Queue::get_items( 30 );
        $enabled  = PBP_Settings::get( 'auto_queue', false );
        $next_run = wp_next_scheduled( 'pbp_auto_generate' );
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'Auto Queue' ); ?>
            <div class="pbp-card">
                <h3>⚡ Auto-Generate Queue</h3>
                <div class="pbp-queue-status <?php echo $enabled ? 'pbp-status-on' : 'pbp-status-off'; ?>">
                    <?php echo $enabled ? '🟢 Đang hoạt động' : '⚫ Đang tắt'; ?>
                    <?php if ($next_run) echo ' — Lần chạy tiếp: ' . esc_html( human_time_diff($next_run, time()) ) . ' nữa'; ?>
                </div>
                <form method="post" action="options.php">
                    <?php settings_fields('pbp_settings_group'); ?>
                    <table class="form-table">
                        <tr>
                            <th>Bật Auto Queue</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="pbp_settings[auto_queue]" value="1" <?php checked($enabled); ?>>
                                    Tự động đưa bài viết mới vào queue và tạo podcast theo lịch
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>Khoảng thời gian</th>
                            <td>
                                <select name="pbp_settings[auto_interval]">
                                    <?php
                                    $intervals = [
                                        'hourly'         => 'Mỗi giờ',
                                        'pbp_every_6_hours' => 'Mỗi 6 giờ',
                                        'twicedaily'     => '2 lần / ngày',
                                        'daily'          => 'Mỗi ngày',
                                        'weekly'         => 'Mỗi tuần',
                                    ];
                                    $cur = PBP_Settings::get('auto_interval','daily');
                                    foreach ($intervals as $v => $l) echo "<option value='" . esc_attr($v) . "' " . selected($cur, $v, false) . ">" . esc_html($l) . "</option>";
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Số bài mỗi lần chạy</th>
                            <td>
                                <input type="number" name="pbp_settings[auto_batch_size]"
                                       value="<?php echo intval( PBP_Settings::get('auto_batch_size', 3) ); ?>"
                                       min="1" max="10" style="width:80px;">
                                <p class="description">Giữ nhỏ (1-3) để tránh timeout hosting</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Số từ tối thiểu</th>
                            <td>
                                <input type="number" name="pbp_settings[auto_min_words]"
                                       value="<?php echo intval( PBP_Settings::get('auto_min_words', 300) ); ?>"
                                       min="100" style="width:100px;">
                                từ
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Lưu cấu hình Queue'); ?>
                </form>
            </div>

            <div class="pbp-stats-grid" style="margin-top:20px;">
                <div class="pbp-stat-card"><div class="pbp-stat-number pbp-text-warning"><?php echo intval($stats['queued']); ?></div><div class="pbp-stat-label">Queued</div></div>
                <div class="pbp-stat-card"><div class="pbp-stat-number"><?php echo intval($stats['processing']); ?></div><div class="pbp-stat-label">Processing</div></div>
                <div class="pbp-stat-card"><div class="pbp-stat-number pbp-text-success"><?php echo intval($stats['done']); ?></div><div class="pbp-stat-label">Done</div></div>
                <div class="pbp-stat-card"><div class="pbp-stat-number pbp-text-error"><?php echo intval($stats['failed']); ?></div><div class="pbp-stat-label">Failed</div></div>
            </div>

            <div class="pbp-card" style="margin-top:20px;">
                <h3>📋 Queue Items</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr><th>Bài viết</th><th>Trạng thái</th><th>Số lần thử</th><th>Thêm vào</th><th>Lỗi</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><a href="<?php echo get_edit_post_link($item['post_id']); ?>"><?php echo esc_html($item['post_title'] ?? '#'.$item['post_id']); ?></a></td>
                            <td><?php echo esc_html($item['status']); ?></td>
                            <td><?php echo intval($item['attempts']); ?></td>
                            <td><?php echo esc_html($item['created_at']); ?></td>
                            <td><small><?php echo esc_html($item['error_msg'] ?? ''); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: Settings
    // ----------------------------------------------------------------
    public function page_settings(): void {
        $ai_provider  = PBP_Settings::get('ai_provider', 'gemini');
        $tts_provider = PBP_Settings::get('tts_provider', 'openai');
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'Settings' ); ?>
            <form method="post" action="options.php" id="pbp-settings-form">
                <?php settings_fields('pbp_settings_group'); ?>

                <!-- TAB NAVIGATION -->
                <nav class="pbp-tabs">
                    <a href="#tab-ai"      class="pbp-tab active" data-tab="tab-ai">🤖 AI & TTS</a>
                    <a href="#tab-podcast" class="pbp-tab"        data-tab="tab-podcast">🎙️ Podcast Info</a>
                    <a href="#tab-backlink" class="pbp-tab"       data-tab="tab-backlink">🔗 Backlink</a>
                </nav>

                <!-- TAB: AI & TTS -->
                <div id="tab-ai" class="pbp-tab-content pbp-card" style="margin-top:0;border-top:none;border-radius:0 0 8px 8px;">
                    <h3>🤖 AI Script Generator</h3>
                    <table class="form-table">
                        <tr>
                            <th>AI Provider</th>
                            <td>
                                <select name="pbp_settings[ai_provider]" id="pbp-ai-provider" class="pbp-provider-select">
                                    <?php foreach (PBP_Settings::ai_providers() as $v => $l) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($ai_provider,$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>API Key</th>
                            <td>
                                <input type="password" name="pbp_settings[ai_api_key]"
                                       id="pbp-ai-api-key-field"
                                       value="<?php echo esc_attr( PBP_Settings::get_api_key('ai_api_key') ); ?>"
                                       class="regular-text" autocomplete="off" placeholder="sk-... hoặc AIza...">
                                <button type="button" class="button" id="pbp-test-ai-key"
                                        style="margin-left:8px;">🧪 Test API</button>
                                <span id="pbp-api-test-result" style="margin-left:10px;font-weight:600;"></span>
                                <p class="description" id="pbp-ai-key-hint">API key từ provider đã chọn</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Model</th>
                            <td>
                                <select name="pbp_settings[ai_model]" id="pbp-ai-model">
                                    <?php foreach (PBP_Settings::ai_models($ai_provider) as $v => $l) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected(PBP_Settings::get('ai_model',''),$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Temperature</th>
                            <td>
                                <input type="range" name="pbp_settings[ai_temperature]" min="0" max="1" step="0.1"
                                       value="<?php echo esc_attr(PBP_Settings::get('ai_temperature','0.7')); ?>"
                                       oninput="this.nextElementSibling.textContent=this.value">
                                <span><?php echo esc_html(PBP_Settings::get('ai_temperature','0.7')); ?></span>
                                <p class="description">0 = chính xác, 1 = sáng tạo</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Ngôn ngữ kịch bản</th>
                            <td>
                                <select name="pbp_settings[ai_language]">
                                    <option value="vi" <?php selected(PBP_Settings::get('ai_language','vi'),'vi'); ?>>🇻🇳 Tiếng Việt</option>
                                    <option value="en" <?php selected(PBP_Settings::get('ai_language','vi'),'en'); ?>>🇺🇸 English</option>
                                </select>
                            </td>
                        </tr>
                    </table>

                    <hr>
                    <h3>🎙️ Text-to-Speech</h3>
                    <table class="form-table">
                        <tr>
                            <th>TTS Provider</th>
                            <td>
                                <select name="pbp_settings[tts_provider]" id="pbp-tts-provider" class="pbp-provider-select">
                                    <?php foreach (PBP_Settings::tts_providers() as $v => $l) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected($tts_provider,$v); ?>><?php echo esc_html($l); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr id="pbp-tts-key-row">
                            <th>TTS API Key</th>
                            <td>
                                <input type="password" name="pbp_settings[tts_api_key]"
                                       value="<?php echo esc_attr( PBP_Settings::get_api_key('tts_api_key') ); ?>"
                                       class="regular-text" autocomplete="off">
                            </td>
                        </tr>
                        <tr>
                            <th>Voice A (Host A)</th>
                            <td>
                                <select name="pbp_settings[tts_voice_a]" id="pbp-voice-a">
                                    <?php foreach (PBP_Settings::tts_voices($tts_provider) as $v) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected(PBP_Settings::get('tts_voice_a',''),$v); ?>><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Voice B (Host B)</th>
                            <td>
                                <select name="pbp_settings[tts_voice_b]" id="pbp-voice-b">
                                    <?php foreach (PBP_Settings::tts_voices($tts_provider) as $v) : ?>
                                    <option value="<?php echo esc_attr($v); ?>" <?php selected(PBP_Settings::get('tts_voice_b',''),$v); ?>><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Tốc độ đọc</th>
                            <td>
                                <input type="range" name="pbp_settings[tts_speed]" min="0.5" max="2.0" step="0.1"
                                       value="<?php echo esc_attr(PBP_Settings::get('tts_speed','1.0')); ?>"
                                       oninput="this.nextElementSibling.textContent=this.value+'x'">
                                <span><?php echo esc_html(PBP_Settings::get('tts_speed','1.0')); ?>x</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB: Podcast Info -->
                <div id="tab-podcast" class="pbp-tab-content pbp-card" style="display:none;margin-top:0;border-top:none;border-radius:0 0 8px 8px;">
                    <h3>🎙️ Thông tin Podcast</h3>
                    <table class="form-table">
                        <?php
                        $fields = [
                            'podcast_title'  => [ 'Tên Podcast', 'text', '' ],
                            'podcast_desc'   => [ 'Mô tả Podcast', 'textarea', '' ],
                            'podcast_author' => [ 'Tác giả', 'text', '' ],
                            'podcast_email'  => [ 'Email liên hệ', 'email', '' ],
                        ];
                        foreach ($fields as $key => [$label, $type, $placeholder]) :
                            $val = PBP_Settings::get($key,'');
                        ?>
                        <tr>
                            <th><?php echo esc_html($label); ?></th>
                            <td>
                                <?php if ($type === 'textarea') : ?>
                                <textarea name="pbp_settings[<?php echo esc_attr($key); ?>]" class="large-text" rows="3"><?php echo esc_textarea($val); ?></textarea>
                                <?php else : ?>
                                <input type="<?php echo esc_attr($type); ?>" name="pbp_settings[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($val); ?>" class="regular-text">
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Ngôn ngữ</th>
                            <td>
                                <input type="text" name="pbp_settings[podcast_language]"
                                       value="<?php echo esc_attr(PBP_Settings::get('podcast_language','vi')); ?>"
                                       style="width:80px;" placeholder="vi">
                                <p class="description">Ví dụ: vi, en, ja</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Category (iTunes)</th>
                            <td>
                                <select name="pbp_settings[podcast_category]">
                                    <?php $cur = PBP_Settings::get('podcast_category','Technology');
                                    foreach (PBP_Settings::podcast_categories() as $cat) : ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($cur,$cat); ?>><?php echo esc_html($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Cover Image</th>
                            <td>
                                <?php
                                $img_id = (int) PBP_Settings::get('podcast_image_id',0);
                                $img_src = $img_id ? wp_get_attachment_image_src($img_id,'thumbnail')[0] : '';
                                ?>
                                <?php if ($img_src) : ?>
                                <img src="<?php echo esc_url($img_src); ?>" style="width:100px;height:100px;object-fit:cover;margin-bottom:8px;display:block;">
                                <?php endif; ?>
                                <input type="hidden" name="pbp_settings[podcast_image_id]" id="pbp-cover-img-id" value="<?php echo intval($img_id); ?>">
                                <button type="button" class="button" id="pbp-select-cover">Chọn ảnh cover</button>
                                <p class="description">Khuyến nghị 3000×3000px, JPG/PNG. iTunes yêu cầu ít nhất 1400×1400px.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Explicit Content</th>
                            <td>
                                <select name="pbp_settings[podcast_explicit]">
                                    <option value="false" <?php selected(PBP_Settings::get('podcast_explicit','false'),'false'); ?>>Không (Clean)</option>
                                    <option value="true" <?php selected(PBP_Settings::get('podcast_explicit','false'),'true'); ?>>Có (Explicit)</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- TAB: Backlink -->
                <div id="tab-backlink" class="pbp-tab-content pbp-card" style="display:none;margin-top:0;border-top:none;border-radius:0 0 8px 8px;">
                    <h3>🔗 Cấu hình Backlink Mặc định</h3>
                    <p class="description">Các giá trị này được dùng cho tất cả bài viết. Bạn có thể override per-post trong meta box.</p>
                    <table class="form-table">
                        <tr>
                            <th>Default Backlink URL</th>
                            <td>
                                <input type="url" name="pbp_settings[default_backlink_url]"
                                       value="<?php echo esc_attr(PBP_Settings::get('default_backlink_url', home_url())); ?>"
                                       class="regular-text" placeholder="https://yoursite.com/...">
                                <p class="description">URL mà backlink trong show notes sẽ trỏ về. Có thể là homepage, landing page, hay bài viết cụ thể.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Default Anchor Text</th>
                            <td>
                                <input type="text" name="pbp_settings[default_anchor_text]"
                                       value="<?php echo esc_attr(PBP_Settings::get('default_anchor_text','Đọc bài viết đầy đủ')); ?>"
                                       class="regular-text">
                                <p class="description">Text hiển thị của backlink. Ví dụ: "Đọc thêm", "Tìm hiểu thêm tại", "Full article"...</p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button('💾 Lưu tất cả Settings', 'primary large'); ?>
            </form>
        </div>
        <script>
        (function($){
            $('#pbp-test-ai-key').on('click', function(){
                var key      = $('#pbp-ai-api-key-field').val().trim();
                var provider = $('#pbp-ai-provider').val() || 'gemini';
                var $res     = $('#pbp-api-test-result');
                if (!key) { $res.css('color','#b91c1c').text('⚠️ Nhập API key trước.'); return; }
                $(this).prop('disabled', true).text('⏳ Đang test...');
                $res.css('color','#64748b').text('Đang gọi API...');
                $.post(ajaxurl, {
                    action:   'pbp_test_api',
                    nonce:    PBP_Admin.nonce,
                    provider: provider,
                    api_key:  key,
                }, function(res) {
                    if (res.success) {
                        $res.css('color','#15803d').text(res.data.message);
                    } else {
                        $res.css('color','#b91c1c').text(res.data ? res.data.message : 'Lỗi không xác định.');
                    }
                }).fail(function(xhr){
                    $res.css('color','#b91c1c').text('HTTP ' + xhr.status + ' — kiểm tra PHP error log.');
                }).always(function(){
                    $('#pbp-test-ai-key').prop('disabled', false).text('🧪 Test API');
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    // ----------------------------------------------------------------
    // PAGE: License
    // ----------------------------------------------------------------
    public function page_license(): void {
        $status         = PBP_Settings::get( 'license_status', 'inactive' );
        $key            = PBP_Settings::get( 'license_key', '' );
        $label          = PBP_License::instance()->get_status_label();
        $current_domain = PBP_License::instance()->get_current_domain();
        $expires_saved  = PBP_Settings::get( 'license_expires', '' );

        // --- Handle key generation form (POST action) ---
        $generated_key = '';
        if ( isset( $_POST['pbp_gen_key_nonce'] ) && wp_verify_nonce( $_POST['pbp_gen_key_nonce'], 'pbp_generate_key' )
             && current_user_can( 'manage_options' ) ) {
            $gen_domain  = sanitize_text_field( $_POST['gen_domain'] ?? '*' );
            $gen_expires = sanitize_text_field( $_POST['gen_expires'] ?? '' );
            $generated_key = PBP_License::generate_key(
                $gen_domain  ?: '*',
                $gen_expires ?: ''
            );
        }
        ?>
        <div class="wrap pbp-wrap">
            <?php $this->render_header( 'License' ); ?>

            <!-- ================================================ -->
            <!-- CARD: Current Status                              -->
            <!-- ================================================ -->
            <div class="pbp-card pbp-card-license">
                <div class="pbp-license-status-badge pbp-status-<?php echo esc_attr( $status ); ?>">
                    <?php
                    $emoji_map = [
                        'active'   => '✅',
                        'inactive' => '⚫',
                        'invalid'  => '❌',
                        'expired'  => '⏰',
                    ];
                    echo esc_html( ( $emoji_map[ $status ] ?? '❓' ) . ' ' . $label );
                    ?>
                </div>

                <?php if ( $status === 'active' ) : ?>
                <div class="pbp-license-info" style="margin-top:16px;">
                    <table class="pbp-mini-table">
                        <tr><td>🔑 License Key</td><td><code><?php echo esc_html( substr( $key, 0, 10 ) . '...' . substr( $key, -6 ) ); ?></code></td></tr>
                        <tr><td>🌍 Domain</td><td><code><?php echo esc_html( $current_domain ); ?></code></td></tr>
                        <?php if ( $expires_saved ) : ?>
                        <tr><td>📅 Hết hạn</td><td><?php echo esc_html( $expires_saved ); ?></td></tr>
                        <?php else : ?>
                        <tr><td>📅 Hết hạn</td><td><em>Vĩnh viễn</em></td></tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div style="margin-top:16px;">
                    <button id="pbp-deactivate-btn" class="button button-secondary">🔓 Hủy kích hoạt</button>
                </div>

                <?php else : ?>

                <!-- Activate form -->
                <div class="pbp-license-form" style="margin-top:16px;">
                    <h3>🔑 Kích hoạt License</h3>
                    <p>Domain hiện tại: <code><?php echo esc_html( $current_domain ); ?></code></p>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="pbp-license-key-input" class="regular-text"
                               placeholder="eyJ..." style="min-width:360px;font-family:monospace;">
                        <button id="pbp-activate-btn" class="button button-primary button-large">
                            ⚡ Kích hoạt
                        </button>
                    </div>
                    <div id="pbp-license-msg" style="margin-top:12px;font-weight:600;"></div>
                </div>
                <?php endif; ?>

                <!-- Dev mode hint -->
                <div style="margin-top:24px;padding-top:16px;border-top:1px solid #eee;">
                    <p class="description">
                        💡 <strong>Dev Mode:</strong>
                        Thêm <code>define( 'PBP_DEV_MODE', true );</code> vào <code>wp-config.php</code>
                        để bypass license trong môi trường local/staging.
                    </p>
                </div>
            </div>

            <!-- ================================================ -->
            <!-- CARD: Key Generator (dành cho chủ plugin)        -->
            <!-- ================================================ -->
            <?php if ( current_user_can( 'manage_options' ) ) : ?>
            <div class="pbp-card" style="margin-top:24px;">
                <h3>🛠️ Tạo License Key (Chủ Plugin)</h3>
                <p class="description" style="margin-bottom:16px;">
                    Tạo key offline, ký bằng HMAC-SHA256. Không cần server bên ngoài.
                    <strong>Chỉ người biết <code>HMAC_SECRET</code> mới tạo được key hợp lệ.</strong>
                </p>

                <form method="post" action="">
                    <?php wp_nonce_field( 'pbp_generate_key', 'pbp_gen_key_nonce' ); ?>
                    <table class="form-table">
                        <tr>
                            <th>Domain</th>
                            <td>
                                <input type="text" name="gen_domain"
                                       value="<?php echo esc_attr( $current_domain ); ?>"
                                       class="regular-text" placeholder="example.com">
                                <p class="description">
                                    Nhập <code>*</code> để key hoạt động trên mọi domain.
                                    Để trống = dùng domain hiện tại (<code><?php echo esc_html( $current_domain ); ?></code>).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>Ngày hết hạn</th>
                            <td>
                                <input type="date" name="gen_expires" value=""
                                       style="width:180px;">
                                <p class="description">Để trống = key vĩnh viễn không hết hạn.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button( '🔑 Tạo License Key', 'primary', 'pbp_gen_submit' ); ?>
                </form>

                <?php if ( $generated_key ) : ?>
                <div class="pbp-notice pbp-notice-success" style="margin-top:16px;padding:16px;background:#f0fdf4;border-left:4px solid #16a34a;border-radius:4px;">
                    <strong>✅ License Key được tạo thành công!</strong>
                    <div style="margin-top:10px;">
                        <textarea id="pbp-gen-key-output" class="widefat" rows="3"
                                  style="font-family:monospace;font-size:12px;word-break:break-all;"
                                  readonly><?php echo esc_textarea( $generated_key ); ?></textarea>
                        <button type="button" class="button" style="margin-top:6px;"
                                onclick="var t=document.getElementById('pbp-gen-key-output');t.select();document.execCommand('copy');this.textContent='✅ Đã copy!';">
                            📋 Copy Key
                        </button>
                    </div>
                    <p class="description" style="margin-top:8px;">
                        ⚠️ <strong>Lưu key này lại ngay!</strong> Mỗi lần nhấn "Tạo" sẽ ra key khác (do nonce ngẫu nhiên).
                        Paste key vào ô kích hoạt ở trên để test.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- CARD: Hướng dẫn -->
            <div class="pbp-card" style="margin-top:20px;">
                <h3>📖 Hướng dẫn sử dụng License</h3>
                <ol style="line-height:2.2;">
                    <li>
                        <strong>Đổi <code>HMAC_SECRET</code></strong> trong
                        <code>class-pbp-license.php</code> thành chuỗi ngẫu nhiên của bạn trước khi deploy.
                        <br><em>Ví dụ: <code>openssl rand -hex 32</code> trong terminal.</em>
                    </li>
                    <li>
                        <strong>Tạo key</strong> bằng form ở trên (chọn domain + ngày hết hạn).
                        Key vĩnh viễn cho mọi domain: để trống ngày hết hạn, nhập <code>*</code> vào domain.
                    </li>
                    <li>
                        <strong>Copy key</strong> và paste vào ô "Kích hoạt License" ở phần trên → nhấn Kích hoạt.
                    </li>
                    <li>
                        Khi bán plugin cho khách: tạo key với domain cụ thể của họ → gửi key qua email.
                        Plugin verify offline, không cần server.
                    </li>
                </ol>
                <div style="background:#fef9c3;border-left:4px solid #ca8a04;padding:12px;border-radius:4px;margin-top:12px;">
                    <strong>⚠️ Bảo mật HMAC_SECRET:</strong>
                    Ai có chuỗi này có thể tự tạo key tùy ý. Không commit lên GitHub public.
                    Có thể đặt qua constant trong wp-config.php thay vì hardcode trong file PHP.
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php
    }

    // ----------------------------------------------------------------
    // Shared header
    // ----------------------------------------------------------------
    private function render_header( string $page ): void {
        ?>
        <div class="pbp-page-header">
            <div class="pbp-page-header-inner">
                <h1>🎙️ Podcast Builder Pro <span class="pbp-version">v<?php echo PBP_VERSION; ?></span></h1>
                <p class="pbp-page-subtitle"><?php echo esc_html($page); ?></p>
            </div>
            <a href="<?php echo admin_url('admin.php?page=podcast-builder-pro-settings'); ?>" class="button">⚙️ Settings</a>
        </div>
        <?php
    }
}
