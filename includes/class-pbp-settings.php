<?php
/**
 * Settings — quản lý tất cả cấu hình plugin
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_Settings {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    // ----------------------------------------------------------------
    // Defaults
    // ----------------------------------------------------------------
    public static function defaults(): array {
        return [
            // AI
            'ai_provider'       => 'gemini',
            'ai_api_key'        => '',
            'ai_model'          => 'gemini-2.0-flash',
            'ai_temperature'    => '0.7',
            'ai_language'       => 'vi',
            // TTS
            'tts_provider'      => 'openai',
            'tts_api_key'       => '',
            'tts_voice_a'       => 'nova',
            'tts_voice_b'       => 'alloy',
            'tts_speed'         => '1.0',
            // Podcast info
            'podcast_title'     => get_bloginfo( 'name' ) . ' Podcast',
            'podcast_desc'      => get_bloginfo( 'description' ),
            'podcast_author'    => get_bloginfo( 'name' ),
            'podcast_email'     => get_option( 'admin_email' ),
            'podcast_language'  => 'vi',
            'podcast_category'  => 'Technology',
            'podcast_image_id'  => 0,
            'podcast_explicit'  => 'false',
            // Backlink defaults
            'default_backlink_url'  => home_url(),
            'default_anchor_text'   => 'Đọc bài viết đầy đủ',
            // Feed
            'feed_slug'         => 'podcast-feed',
            'episodes_per_page' => 50,
            // Auto queue
            'auto_queue'        => false,
            'auto_categories'   => [],
            'auto_interval'     => 'daily',
            'auto_batch_size'   => 3,
            'auto_min_words'    => 300,
            // License
            'license_key'       => '',
            'license_status'    => 'inactive',
        ];
    }

    // ----------------------------------------------------------------
    // Getter (with default fallback)
    // ----------------------------------------------------------------
    public static function get( string $key, $fallback = null ) {
        $all = get_option( 'pbp_settings', [] );
        $defaults = self::defaults();

        if ( array_key_exists( $key, $all ) ) {
            return $all[ $key ];
        }
        if ( array_key_exists( $key, $defaults ) ) {
            return $defaults[ $key ];
        }
        return $fallback;
    }

    // ----------------------------------------------------------------
    // Setter
    // ----------------------------------------------------------------
    public static function set( string $key, $value ): void {
        $all = get_option( 'pbp_settings', [] );
        $all[ $key ] = $value;
        update_option( 'pbp_settings', $all );
    }

    // ----------------------------------------------------------------
    // API key helpers — lưu base64 để tránh lộ trong DB plain text
    // ----------------------------------------------------------------
    public static function get_api_key( string $key ): string {
        $raw = self::get( $key, '' );
        if ( empty( $raw ) ) return '';

        // Nếu key trông giống base64 hợp lệ thì decode, ngược lại dùng thẳng
        // (backward-compat: nếu admin đã lưu plain text trước khi fix này)
        $decoded = base64_decode( $raw, true );
        if ( $decoded !== false && base64_encode( $decoded ) === $raw ) {
            return $decoded;
        }
        return $raw; // plain text (stored before this fix)
    }

    public static function save_api_key( string $key, string $value ): void {
        self::set( $key, base64_encode( $value ) );
    }

    // ----------------------------------------------------------------
    // Register WordPress Settings API
    // ----------------------------------------------------------------
    public function register_settings(): void {
        register_setting( 'pbp_settings_group', 'pbp_settings', [
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function sanitize( $input ): array {
        $clean    = [];
        $defaults = self::defaults();

        foreach ( $defaults as $key => $default ) {
            if ( ! isset( $input[ $key ] ) ) {
                $clean[ $key ] = $default;
                continue;
            }

            $val = $input[ $key ];

            // API keys — lưu base64 để tránh lộ plain text trong DB
            if ( in_array( $key, [ 'ai_api_key', 'tts_api_key', 'license_key' ], true ) ) {
                $sanitized = sanitize_text_field( $val );
                // Chỉ encode nếu chưa phải base64 (tránh double-encode)
                $decoded = base64_decode( $sanitized, true );
                if ( $decoded !== false && base64_encode( $decoded ) === $sanitized ) {
                    $clean[ $key ] = $sanitized; // đã là base64
                } else {
                    $clean[ $key ] = base64_encode( $sanitized ); // encode mới
                }
                continue;
            }

            // Textarea fields — preserve newlines
            if ( in_array( $key, [ 'podcast_desc' ], true ) ) {
                $clean[ $key ] = sanitize_textarea_field( $val );
                continue;
            }

            if ( is_array( $default ) ) {
                $clean[ $key ] = array_map( 'absint', (array) $val );
            } elseif ( is_bool( $default ) ) {
                $clean[ $key ] = (bool) $val;
            } elseif ( is_int( $default ) ) {
                $clean[ $key ] = absint( $val );
            } else {
                $clean[ $key ] = sanitize_text_field( $val );
            }
        }

        return $clean;
    }

    // ----------------------------------------------------------------
    // Provider lists (used by admin UI)
    // ----------------------------------------------------------------
    public static function ai_providers(): array {
        return [
            'gemini'     => 'Google Gemini',
            'openai'     => 'OpenAI GPT',
            'openrouter' => 'OpenRouter',
        ];
    }

    public static function tts_providers(): array {
        return [
            'openai'      => 'OpenAI TTS',
            'google'      => 'Google Cloud TTS',
            'elevenlabs'  => 'ElevenLabs',
            'fish_audio'  => 'Fish Audio',
            'fpt_ai'      => 'FPT.AI (Tiếng Việt)',
            'yescale'     => 'YEScale',
            'openrouter'  => 'OpenRouter TTS',
            'manual'      => 'Upload thủ công / Link ngoài',
        ];
    }

    public static function tts_voices( string $provider ): array {
        $map = [
            'openai'     => [ 'alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer' ],
            'elevenlabs' => [ 'Rachel', 'Josh', 'Bella', 'Antoni', 'Elli', 'Thomas', 'Charlie', 'Emily', 'Clyde' ],
            'google'     => [ 'vi-VN-Standard-A', 'vi-VN-Standard-B', 'vi-VN-Standard-C', 'vi-VN-Standard-D', 'vi-VN-Wavenet-A', 'vi-VN-Wavenet-B', 'vi-VN-Wavenet-C', 'vi-VN-Wavenet-D' ],
            'fpt_ai'     => [ 'leminh', 'myan', 'lannhi', 'giahuy', 'linhsan', 'minhquang' ],
            'fish_audio' => [ 'default' ],
            'yescale'    => [ 'default' ],
            'openrouter' => [ 'default' ],
            'manual'     => [],
        ];
        return $map[ $provider ] ?? [];
    }

    public static function ai_models( string $provider ): array {
        $map = [
            'gemini'     => [
                'gemini-2.0-flash'           => 'Gemini 2.0 Flash (mới nhất, nhanh + rẻ)',
                'gemini-1.5-flash'           => 'Gemini 1.5 Flash (nhanh + rẻ)',
                'gemini-1.5-flash-8b'        => 'Gemini 1.5 Flash 8B (rẻ nhất)',
                'gemini-1.5-pro'             => 'Gemini 1.5 Pro (chất lượng cao)',
                'gemini-2.0-flash-lite'      => 'Gemini 2.0 Flash Lite (rẻ nhất)',
            ],
            'openai'     => [
                'gpt-4o-mini'        => 'GPT-4o Mini (nhanh + rẻ)',
                'gpt-4o'             => 'GPT-4o',
                'gpt-3.5-turbo'      => 'GPT-3.5 Turbo',
            ],
            'openrouter' => [
                'google/gemini-flash-1.5'     => 'Gemini Flash 1.5 (via OpenRouter)',
                'google/gemini-2.0-flash-001' => 'Gemini 2.0 Flash (via OpenRouter)',
                'openai/gpt-4o-mini'          => 'GPT-4o Mini (via OpenRouter)',
                'anthropic/claude-3-haiku'    => 'Claude 3 Haiku (via OpenRouter)',
            ],
        ];
        return $map[ $provider ] ?? [];
    }

    public static function podcast_categories(): array {
        return [
            'Arts', 'Business', 'Comedy', 'Education', 'Fiction',
            'Government', 'History', 'Health & Fitness', 'Kids & Family',
            'Leisure', 'Music', 'News', 'Religion & Spirituality',
            'Science', 'Society & Culture', 'Sports', 'Technology',
            'True Crime', 'TV & Film',
        ];
    }
}
