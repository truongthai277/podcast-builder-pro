<?php
/**
 * Show Notes Builder — tạo show notes có backlink
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_ShowNotes {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Build show notes HTML
    // ----------------------------------------------------------------
    /**
     * @param int    $post_id
     * @param string $script   AI-generated script
     * @param array  $options  Override per-post options
     * @return string HTML
     */
    public function build( int $post_id, string $script, array $options = [] ): string {
        $post         = get_post( $post_id );
        $backlink_url = $options['backlink_url'] ?? PBP_Settings::get( 'default_backlink_url', get_permalink( $post_id ) );
        $anchor_text  = $options['anchor_text']  ?? PBP_Settings::get( 'default_anchor_text', 'Đọc bài viết đầy đủ' );

        // If backlink_url is empty, default to post permalink
        if ( empty( $backlink_url ) ) {
            $backlink_url = get_permalink( $post_id );
        }

        // Extract intro from script (first HOST_A line)
        $intro = $this->extract_intro( $script );

        // Build summary from post excerpt or content
        $summary = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );

        ob_start();
        ?>
<div class="pbp-show-notes">

  <p><?php echo esc_html( $intro ?: $summary ); ?></p>

  <hr>

  <h3>📖 Nội dung tập này</h3>
  <p><?php echo esc_html( $summary ); ?></p>

  <hr>

  <h3>🔗 Tài nguyên &amp; Đọc thêm</h3>
  <ul>
    <li>
      <strong><?php echo esc_html( $anchor_text ); ?>:</strong>
      <a href="<?php echo esc_url( $backlink_url ); ?>" target="_blank" rel="noopener">
        <?php echo esc_html( $backlink_url ); ?>
      </a>
    </li>
    <li>
      <strong>Website:</strong>
      <a href="<?php echo esc_url( home_url() ); ?>" target="_blank" rel="noopener">
        <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
      </a>
    </li>
  </ul>

  <hr>

  <p><em>
    Cảm ơn bạn đã lắng nghe <?php echo esc_html( PBP_Settings::get( 'podcast_title', get_bloginfo( 'name' ) . ' Podcast' ) ); ?>.
    Nếu thấy hữu ích, hãy subscribe và chia sẻ với bạn bè!
  </em></p>

</div>
        <?php
        return trim( ob_get_clean() );
    }

    // ----------------------------------------------------------------
    // Extract first HOST_A line as intro
    // ----------------------------------------------------------------
    private function extract_intro( string $script ): string {
        $lines = explode( "\n", $script );
        foreach ( $lines as $line ) {
            if ( preg_match( '/^\[HOST_A\]:\s*(.+)$/i', $line, $m ) ) {
                return trim( $m[1] );
            }
        }
        return '';
    }

    // ----------------------------------------------------------------
    // Plain text version (for itunes:summary)
    // ----------------------------------------------------------------
    public function build_plain( int $post_id, string $script ): string {
        return wp_strip_all_tags( $this->build( $post_id, $script ) );
    }
}
