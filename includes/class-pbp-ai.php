<?php
/**
 * AI Script Generator — Google Gemini / OpenAI / OpenRouter
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_AI {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Main entry point
    // ----------------------------------------------------------------
    /**
     * Generate podcast script from a post.
     *
     * @param int    $post_id  WordPress post ID.
     * @param string $provider 'gemini'|'openai'|'openrouter'
     * @return array{success:bool, script:string, error:string}
     */
    public function generate_script( int $post_id, string $provider = '' ): array {
        if ( empty( $provider ) ) {
            $provider = PBP_Settings::get( 'ai_provider', 'gemini' );
        }

        $post = get_post( $post_id );
        if ( ! $post ) {
            return $this->error( 'Post không tồn tại.' );
        }

        $title   = $post->post_title;
        $content = $this->extract_content( $post );
        $lang    = PBP_Settings::get( 'ai_language', 'vi' );
        $prompt  = $this->build_prompt( $title, $content, $lang );

        switch ( $provider ) {
            case 'gemini':
                return $this->call_gemini( $prompt );
            case 'openai':
                return $this->call_openai( $prompt );
            case 'openrouter':
                return $this->call_openrouter( $prompt );
            default:
                return $this->error( "Provider không hợp lệ: $provider" );
        }
    }

    // ----------------------------------------------------------------
    // Content extraction
    // ----------------------------------------------------------------
    private function extract_content( WP_Post $post ): string {
        $content = $post->post_content;

        // Strip shortcodes
        $content = strip_shortcodes( $content );

        // Strip HTML
        $content = wp_strip_all_tags( $content );

        // Normalize whitespace
        $content = preg_replace( '/\s+/', ' ', $content );
        $content = trim( $content );

        // Truncate to ~4000 tokens (~16000 chars)
        if ( strlen( $content ) > 16000 ) {
            $content = substr( $content, 0, 16000 ) . '...';
        }

        return $content;
    }

    // ----------------------------------------------------------------
    // Prompt builder
    // ----------------------------------------------------------------
    private function build_prompt( string $title, string $content, string $lang = 'vi' ): string {
        $lang_note = ( $lang === 'vi' )
            ? 'Viết hoàn toàn bằng tiếng Việt tự nhiên, thân thiện, dễ nghe.'
            : 'Write entirely in natural, friendly English.';

        return <<<PROMPT
Bạn là chuyên gia sản xuất podcast chuyên nghiệp. Hãy chuyển bài viết dưới đây thành kịch bản podcast hội thoại giữa 2 người dẫn: [HOST_A] và [HOST_B].

YÊU CẦU:
- {$lang_note}
- Phong cách: tự nhiên, thân thiện, cuốn hút — như 2 người đang nói chuyện thật
- Cấu trúc: Mở đầu (giới thiệu chủ đề hấp dẫn 2-3 câu) → Nội dung chính (thảo luận, phân tích, ví dụ) → Kết thúc (tóm tắt + khuyến khích người nghe)
- Độ dài: 400-600 từ kịch bản
- Mỗi lượt nói: 1-3 câu, tự nhiên như hội thoại thật
- KHÔNG thêm chú thích, ghi chú hay hướng dẫn nào ngoài kịch bản

FORMAT OUTPUT (giữ đúng format này):
[HOST_A]: ...
[HOST_B]: ...
[HOST_A]: ...
...

TIÊU ĐỀ BÀI VIẾT:
{$title}

NỘI DUNG BÀI VIẾT:
{$content}

Hãy bắt đầu kịch bản ngay bây giờ:
PROMPT;
    }

    // ----------------------------------------------------------------
    // Google Gemini
    // ----------------------------------------------------------------
    private function call_gemini( string $prompt ): array {
        $api_key = PBP_Settings::get_api_key( 'ai_api_key' );
        $model   = PBP_Settings::get( 'ai_model', 'gemini-1.5-flash' );

        if ( empty( $api_key ) ) {
            return $this->error( 'Thiếu Gemini API key. Vui lòng cấu hình trong Settings.' );
        }

        $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        $body = wp_json_encode( [
            'contents'         => [
                [ 'parts' => [ [ 'text' => $prompt ] ] ],
            ],
            'generationConfig' => [
                'temperature'     => (float) PBP_Settings::get( 'ai_temperature', 0.7 ),
                'maxOutputTokens' => 2048,
            ],
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        return $this->parse_gemini_response( $response );
    }

    private function parse_gemini_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            return $this->error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            return $this->error( "Gemini API lỗi: $msg" );
        }

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ( empty( $text ) ) {
            return $this->error( 'Gemini trả về kết quả rỗng.' );
        }

        return [ 'success' => true, 'script' => trim( $text ), 'error' => '' ];
    }

    // ----------------------------------------------------------------
    // OpenAI GPT
    // ----------------------------------------------------------------
    private function call_openai( string $prompt ): array {
        $api_key = PBP_Settings::get_api_key( 'ai_api_key' );
        $model   = PBP_Settings::get( 'ai_model', 'gpt-4o-mini' );

        if ( empty( $api_key ) ) {
            return $this->error( 'Thiếu OpenAI API key.' );
        }

        $url  = 'https://api.openai.com/v1/chat/completions';
        $body = wp_json_encode( [
            'model'       => $model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'temperature' => (float) PBP_Settings::get( 'ai_temperature', 0.7 ),
            'max_tokens'  => 2048,
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        return $this->parse_openai_response( $response );
    }

    private function parse_openai_response( $response ): array {
        if ( is_wp_error( $response ) ) {
            return $this->error( $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? "HTTP $code";
            return $this->error( "OpenAI API lỗi: $msg" );
        }

        $text = $body['choices'][0]['message']['content'] ?? '';
        if ( empty( $text ) ) {
            return $this->error( 'OpenAI trả về kết quả rỗng.' );
        }

        return [ 'success' => true, 'script' => trim( $text ), 'error' => '' ];
    }

    // ----------------------------------------------------------------
    // OpenRouter
    // ----------------------------------------------------------------
    private function call_openrouter( string $prompt ): array {
        $api_key = PBP_Settings::get_api_key( 'ai_api_key' );
        $model   = PBP_Settings::get( 'ai_model', 'google/gemini-flash-1.5' );

        if ( empty( $api_key ) ) {
            return $this->error( 'Thiếu OpenRouter API key.' );
        }

        $url  = 'https://openrouter.ai/api/v1/chat/completions';
        $body = wp_json_encode( [
            'model'       => $model,
            'messages'    => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
            'temperature' => (float) PBP_Settings::get( 'ai_temperature', 0.7 ),
            'max_tokens'  => 2048,
        ] );

        $response = wp_remote_post( $url, [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer'  => home_url(),
                'X-Title'       => get_bloginfo( 'name' ),
            ],
            'body'    => $body,
            'timeout' => 90,
        ] );

        return $this->parse_openai_response( $response ); // Same format as OpenAI
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function error( string $message ): array {
        return [ 'success' => false, 'script' => '', 'error' => $message ];
    }

    /**
     * Parse script into segments for dual-voice TTS.
     * Returns array of [ 'voice' => 'A'|'B', 'text' => '...' ]
     */
    public static function parse_script_segments( string $script ): array {
        $lines    = explode( "\n", $script );
        $segments = [];

        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) continue;

            if ( preg_match( '/^\[HOST_A\]:\s*(.+)$/i', $line, $m ) ) {
                $segments[] = [ 'voice' => 'A', 'text' => trim( $m[1] ) ];
            } elseif ( preg_match( '/^\[HOST_B\]:\s*(.+)$/i', $line, $m ) ) {
                $segments[] = [ 'voice' => 'B', 'text' => trim( $m[1] ) ];
            }
        }

        return $segments;
    }
}
