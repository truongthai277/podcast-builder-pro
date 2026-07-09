<?php
/**
 * RSS Feed Generator — chuẩn iTunes/Apple Podcasts
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Feed {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Render RSS Feed (called from template_redirect)
    // ----------------------------------------------------------------
    public function render(): void {
        header( 'Content-Type: application/rss+xml; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );
        echo $this->build_feed();
    }

    // ----------------------------------------------------------------
    // Build full RSS XML
    // ----------------------------------------------------------------
    public function build_feed(): string {
        $episodes = $this->get_episodes();
        $info     = $this->get_podcast_info();
        $feed_url = home_url( '/' . PBP_Settings::get( 'feed_slug', 'podcast-feed' ) . '/' );

        ob_start();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        ?>
<rss version="2.0"
     xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:podcast="https://podcastindex.org/namespace/1.0">

  <channel>
    <title><?php echo esc_xml( $info['title'] ); ?></title>
    <link><?php echo esc_url( $info['site_url'] ); ?></link>
    <description><?php echo esc_xml( $info['description'] ); ?></description>
    <language><?php echo esc_xml( $info['language'] ); ?></language>
    <lastBuildDate><?php echo esc_xml( gmdate( 'r' ) ); ?></lastBuildDate>
    <atom:link href="<?php echo esc_url( $feed_url ); ?>" rel="self" type="application/rss+xml"/>

    <itunes:author><?php echo esc_xml( $info['author'] ); ?></itunes:author>
    <itunes:email><?php echo esc_xml( $info['email'] ); ?></itunes:email>
    <itunes:owner>
      <itunes:name><?php echo esc_xml( $info['author'] ); ?></itunes:name>
      <itunes:email><?php echo esc_xml( $info['email'] ); ?></itunes:email>
    </itunes:owner>
    <itunes:category text="<?php echo esc_attr( $info['category'] ); ?>"/>
    <itunes:explicit><?php echo esc_xml( $info['explicit'] ); ?></itunes:explicit>
    <itunes:type>episodic</itunes:type>
    <?php if ( $info['image_url'] ) : ?>
    <itunes:image href="<?php echo esc_url( $info['image_url'] ); ?>"/>
    <image>
      <url><?php echo esc_url( $info['image_url'] ); ?></url>
      <title><?php echo esc_xml( $info['title'] ); ?></title>
      <link><?php echo esc_url( $info['site_url'] ); ?></link>
    </image>
    <?php endif; ?>

    <?php foreach ( $episodes as $episode ) : ?>
    <item>
      <title><?php echo esc_xml( $episode['title'] ); ?></title>
      <link><?php echo esc_url( $episode['url'] ); ?></link>
      <guid isPermaLink="false"><?php echo esc_xml( 'pbp-ep-' . $episode['id'] ); ?></guid>
      <pubDate><?php echo esc_xml( gmdate( 'r', strtotime( $episode['date'] ) ) ); ?></pubDate>
      <description><![CDATA[<?php echo $episode['show_notes']; ?>]]></description>
      <content:encoded><![CDATA[<?php echo $episode['show_notes']; ?>]]></content:encoded>
      <itunes:summary><?php echo esc_xml( wp_trim_words( wp_strip_all_tags( $episode['show_notes'] ), 55 ) ); ?></itunes:summary>
      <itunes:author><?php echo esc_xml( $info['author'] ); ?></itunes:author>
      <?php if ( $info['image_url'] ) : ?>
      <itunes:image href="<?php echo esc_url( $info['image_url'] ); ?>"/>
      <?php endif; ?>
      <?php if ( $episode['audio_url'] ) : ?>
      <enclosure
        url="<?php echo esc_url( $episode['audio_url'] ); ?>"
        length="<?php echo intval( $episode['audio_size'] ); ?>"
        type="audio/mpeg"/>
      <?php endif; ?>
      <?php if ( $episode['duration'] ) : ?>
      <itunes:duration><?php echo esc_xml( $episode['duration'] ); ?></itunes:duration>
      <?php endif; ?>
      <itunes:episodeType>full</itunes:episodeType>
      <itunes:explicit><?php echo esc_xml( $info['explicit'] ); ?></itunes:explicit>
    </item>
    <?php endforeach; ?>

  </channel>
</rss>
        <?php
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Get podcast episodes from DB
    // ----------------------------------------------------------------
    private function get_episodes(): array {
        $per_page = (int) PBP_Settings::get( 'episodes_per_page', 50 );

        $query = new WP_Query( [
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => '_pbp_status',
                    'value'   => 'done',
                    'compare' => '=',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key'     => '_pbp_exclude',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key'     => '_pbp_exclude',
                        'value'   => '1',
                        'compare' => '!=',
                    ],
                ],
            ],
            'no_found_rows'  => true,
        ] );

        $episodes = [];
        foreach ( $query->posts as $post ) {
            $audio_url  = get_post_meta( $post->ID, '_pbp_audio_url', true );
            $show_notes = get_post_meta( $post->ID, '_pbp_show_notes', true );
            $duration   = get_post_meta( $post->ID, '_pbp_duration', true );
            $audio_size = get_post_meta( $post->ID, '_pbp_audio_size', true );

            if ( empty( $audio_url ) ) continue; // Skip episodes without audio

            $episodes[] = [
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'url'        => get_permalink( $post ),
                'date'       => $post->post_date_gmt,
                'show_notes' => $show_notes ?: wp_trim_words( $post->post_content, 100 ),
                'audio_url'  => $audio_url,
                'audio_size' => (int) $audio_size,
                'duration'   => $duration,
            ];
        }

        wp_reset_postdata();
        return $episodes;
    }

    // ----------------------------------------------------------------
    // Podcast channel info
    // ----------------------------------------------------------------
    private function get_podcast_info(): array {
        $image_id  = (int) PBP_Settings::get( 'podcast_image_id', 0 );
        $image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

        return [
            'title'       => PBP_Settings::get( 'podcast_title', get_bloginfo( 'name' ) . ' Podcast' ),
            'description' => PBP_Settings::get( 'podcast_desc', get_bloginfo( 'description' ) ),
            'author'      => PBP_Settings::get( 'podcast_author', get_bloginfo( 'name' ) ),
            'email'       => PBP_Settings::get( 'podcast_email', get_option( 'admin_email' ) ),
            'language'    => PBP_Settings::get( 'podcast_language', 'vi' ),
            'category'    => PBP_Settings::get( 'podcast_category', 'Technology' ),
            'image_url'   => $image_url,
            'explicit'    => PBP_Settings::get( 'podcast_explicit', 'false' ),
            'site_url'    => home_url(),
        ];
    }

    // ----------------------------------------------------------------
    // Get feed URL (public helper)
    // ----------------------------------------------------------------
    public static function get_feed_url(): string {
        return home_url( '/' . PBP_Settings::get( 'feed_slug', 'podcast-feed' ) . '/' );
    }

    // ----------------------------------------------------------------
    // Episode count (for admin display)
    // ----------------------------------------------------------------
    public static function get_episode_count(): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
             WHERE meta_key = '_pbp_status' AND meta_value = 'done'"
        );
    }
}
