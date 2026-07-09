<?php
/**
 * TTS — Text-to-Speech với nhiều provider
 * Hỗ trợ: OpenAI, Google, ElevenLabs, Fish Audio, FPT.AI, YEScale, Manual
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_TTS {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Main entry — generate audio từ script
    // ----------------------------------------------------------------
    /**
     * @param int    $post_id
     * @param string $script  Full podcast script (dual-voice format)
     * @param string $provider
     * @return array{success:bool, audio_url:string, audio_size:int, duration:string, error:string}
     */
    public function generate( int $post_id, string $script, string $provider = '' ): array {
        if ( empty( $provider ) ) {
            $provider = PBP_Settings::get( 'tts_provider', 'openai' );
        }

        $segments = PBP_AI::parse_script_segments( $script );

        // Nếu không parse được — dùng toàn bộ script as single voice
        if ( empty( $segments ) ) {
            $segments = [ [ 'voice' => 'A', 'text' => $script ] ];
        }

        switch ( $provider ) {
            case 'openai':     return $this->generate_openai( $post_id, $segments );
            case 'google':     return $this->generate_google( $post_id, $segments );
            case 'elevenlabs': return $this->generate_elevenlabs( $post_id, $segments );
            case 'fish_audio': return $this->generate_fish_audio( $post_id, $segments );
            case 'fpt_ai':     return $this->generate_fpt_ai( $post_id, $segments );
            case 'yescale':
            case 'openrouter': return $this->generate_yescale( $post_id, $segments );
            default:
                return $this->error( "Provider TTS không hợp lệ: $provider" );
        }
    }

    /**
     * Manual upload / external URL — không cần API
     */
    public function save_manual( int $post_id, string $source, string $type = 'url' ): array {
        if ( $type === 'url' ) {
            if ( ! filter_var( $source, FILTER_VALIDATE_URL ) ) {
                return $this->error( 'URL audio không hợp lệ.' );
            }
            update_post_meta( $post_id, '_pbp_audio_url', esc_url_raw( $source ) );
            return [ 'success' => true, 'audio_url' => $source, 'audio_size' => 0, 'duration' => '', 'error' => '' ];
        }

        // $source = attachment_id (đã upload qua WP Media)
        $url = wp_get_attachment_url( (int) $source );
        if ( ! $url ) {
            return $this->error( 'Attachment không tồn tại.' );
        }
        $path = get_attached_file( (int) $source );
        $size = $path ? filesize( $path ) : 0;

        update_post_meta( $post_id, '_pbp_audio_url', $url );
        update_post_meta( $post_id, '_pbp_audio_size', $size );

        return [ 'success' => true, 'audio_url' => $url, 'audio_size' => $size, 'duration' => '', 'error' => '' ];
    }

    // ----------------------------------------------------------------
    // OpenAI TTS
    // ----------------------------------------------------------------
    private function generate_openai( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu OpenAI TTS API key.' );

        $voice_a = PBP_Settings::get( 'tts_voice_a', 'nova' );
        $voice_b = PBP_Settings::get( 'tts_voice_b', 'alloy' );
        $speed   = (float) PBP_Settings::get( 'tts_speed', '1.0' );

        $audio_chunks = [];

        foreach ( $segments as $seg ) {
            $voice = ( $seg['voice'] === 'A' ) ? $voice_a : $voice_b;
            $text  = $seg['text'];

            $response = wp_remote_post( 'https://api.openai.com/v1/audio/speech', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode( [
                    'model'  => 'tts-1',
                    'input'  => $text,
                    'voice'  => $voice,
                    'speed'  => $speed,
                    'response_format' => 'mp3',
                ] ),
                'timeout' => 120,
            ] );

            if ( is_wp_error( $response ) ) {
                return $this->error( 'OpenAI TTS lỗi: ' . $response->get_error_message() );
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                return $this->error( 'OpenAI TTS lỗi HTTP ' . $code . ': ' . ( $body['error']['message'] ?? '' ) );
            }

            $audio_chunks[] = wp_remote_retrieve_body( $response );
        }

        // Merge all chunks
        $merged = implode( '', $audio_chunks );
        return $this->save_audio_to_library( $post_id, $merged, 'mp3' );
    }

    // ----------------------------------------------------------------
    // Google Cloud TTS
    // ----------------------------------------------------------------
    private function generate_google( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu Google TTS API key.' );

        $voice_a = PBP_Settings::get( 'tts_voice_a', 'vi-VN-Wavenet-A' );
        $voice_b = PBP_Settings::get( 'tts_voice_b', 'vi-VN-Wavenet-B' );
        $url     = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$api_key}";

        $audio_chunks = [];

        foreach ( $segments as $seg ) {
            $voice_name = ( $seg['voice'] === 'A' ) ? $voice_a : $voice_b;
            $lang_code  = substr( $voice_name, 0, 5 ); // vi-VN

            $body = wp_json_encode( [
                'input'       => [ 'text' => $seg['text'] ],
                'voice'       => [
                    'languageCode' => $lang_code,
                    'name'         => $voice_name,
                ],
                'audioConfig' => [ 'audioEncoding' => 'MP3' ],
            ] );

            $response = wp_remote_post( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $body,
                'timeout' => 120,
            ] );

            if ( is_wp_error( $response ) ) return $this->error( $response->get_error_message() );
            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code !== 200 ) {
                return $this->error( 'Google TTS lỗi: ' . ( $data['error']['message'] ?? "HTTP $code" ) );
            }

            $audio_chunks[] = base64_decode( $data['audioContent'] ?? '' );
        }

        $merged = implode( '', $audio_chunks );
        return $this->save_audio_to_library( $post_id, $merged, 'mp3' );
    }

    // ----------------------------------------------------------------
    // ElevenLabs
    // ----------------------------------------------------------------
    private function generate_elevenlabs( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu ElevenLabs API key.' );

        $voice_a = PBP_Settings::get( 'tts_voice_a', 'Rachel' );
        $voice_b = PBP_Settings::get( 'tts_voice_b', 'Josh' );

        // Get voice IDs (ElevenLabs uses IDs not names)
        $voice_ids = $this->get_elevenlabs_voice_ids( $api_key );

        $audio_chunks = [];

        foreach ( $segments as $seg ) {
            $voice_name = ( $seg['voice'] === 'A' ) ? $voice_a : $voice_b;
            $voice_id   = $voice_ids[ $voice_name ] ?? $voice_name;

            $response = wp_remote_post(
                "https://api.elevenlabs.io/v1/text-to-speech/{$voice_id}",
                [
                    'headers' => [
                        'xi-api-key'   => $api_key,
                        'Content-Type' => 'application/json',
                        'Accept'       => 'audio/mpeg',
                    ],
                    'body'    => wp_json_encode( [
                        'text'           => $seg['text'],
                        'model_id'       => 'eleven_multilingual_v2',
                        'voice_settings' => [ 'stability' => 0.5, 'similarity_boost' => 0.75 ],
                    ] ),
                    'timeout' => 120,
                ]
            );

            if ( is_wp_error( $response ) ) return $this->error( $response->get_error_message() );
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 200 ) {
                $data = json_decode( wp_remote_retrieve_body( $response ), true );
                return $this->error( 'ElevenLabs lỗi: ' . ( $data['detail']['message'] ?? "HTTP $code" ) );
            }

            $audio_chunks[] = wp_remote_retrieve_body( $response );
        }

        return $this->save_audio_to_library( $post_id, implode( '', $audio_chunks ), 'mp3' );
    }

    private function get_elevenlabs_voice_ids( string $api_key ): array {
        $cached = get_transient( 'pbp_elevenlabs_voices' );
        if ( $cached ) return $cached;

        $response = wp_remote_get( 'https://api.elevenlabs.io/v1/voices', [
            'headers' => [ 'xi-api-key' => $api_key ],
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) return [];
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $map  = [];
        foreach ( $data['voices'] ?? [] as $v ) {
            $map[ $v['name'] ] = $v['voice_id'];
        }
        set_transient( 'pbp_elevenlabs_voices', $map, 3600 );
        return $map;
    }

    // ----------------------------------------------------------------
    // Fish Audio
    // ----------------------------------------------------------------
    private function generate_fish_audio( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu Fish Audio API key.' );

        $full_text    = implode( ' ', array_column( $segments, 'text' ) );
        $response     = wp_remote_post( 'https://api.fish.audio/v1/tts', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'text'           => $full_text,
                'chunk_length'   => 200,
                'format'         => 'mp3',
                'mp3_bitrate'    => 128,
                'normalize'      => true,
                'latency'        => 'normal',
            ] ),
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) return $this->error( $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return $this->error( 'Fish Audio lỗi HTTP: ' . $code );
        }

        return $this->save_audio_to_library( $post_id, wp_remote_retrieve_body( $response ), 'mp3' );
    }

    // ----------------------------------------------------------------
    // FPT.AI TTS (Tiếng Việt)
    // ----------------------------------------------------------------
    private function generate_fpt_ai( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu FPT.AI API key.' );

        $voice_a = PBP_Settings::get( 'tts_voice_a', 'leminh' );
        $voice_b = PBP_Settings::get( 'tts_voice_b', 'myan' );

        $audio_chunks = [];

        foreach ( $segments as $seg ) {
            $voice = ( $seg['voice'] === 'A' ) ? $voice_a : $voice_b;

            $response = wp_remote_post( 'https://api.fpt.ai/hmi/tts/v5', [
                'headers' => [
                    'api-key'      => $api_key,
                    'voice'        => $voice,
                    'Content-Type' => 'application/json',
                    'speed'        => '',
                    'prosody'      => '',
                ],
                'body'    => $seg['text'],
                'timeout' => 120,
            ] );

            if ( is_wp_error( $response ) ) return $this->error( $response->get_error_message() );
            $code = wp_remote_retrieve_response_code( $response );
            $data = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( $code !== 200 || empty( $data['async'] ) ) {
                return $this->error( 'FPT.AI lỗi: ' . ( $data['error'] ?? "HTTP $code" ) );
            }

            // FPT.AI is async — poll the result URL
            $audio_url = $this->poll_fpt_ai( $data['async'], 30 );
            if ( ! $audio_url ) {
                return $this->error( 'FPT.AI timeout khi chờ kết quả audio.' );
            }

            // Download audio
            $dl = wp_remote_get( $audio_url, [ 'timeout' => 60 ] );
            if ( is_wp_error( $dl ) ) return $this->error( $dl->get_error_message() );

            $audio_chunks[] = wp_remote_retrieve_body( $dl );
        }

        return $this->save_audio_to_library( $post_id, implode( '', $audio_chunks ), 'mp3' );
    }

    private function poll_fpt_ai( string $async_url, int $max_seconds ): ?string {
        $start = time();
        while ( time() - $start < $max_seconds ) {
            sleep( 3 );
            $r    = wp_remote_get( $async_url, [ 'timeout' => 15 ] );
            $data = json_decode( wp_remote_retrieve_body( $r ), true );
            if ( ! empty( $data['url'] ) ) {
                return $data['url'];
            }
        }
        return null;
    }

    // ----------------------------------------------------------------
    // YEScale TTS
    // ----------------------------------------------------------------
    private function generate_yescale( int $post_id, array $segments ): array {
        $api_key = PBP_Settings::get_api_key( 'tts_api_key' );
        if ( empty( $api_key ) ) return $this->error( 'Thiếu YEScale API key.' );

        $full_text = implode( ' ', array_column( $segments, 'text' ) );

        $response = wp_remote_post( 'https://api.yescale.io/v1/audio/speech', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode( [
                'model'           => 'tts-1',
                'input'           => $full_text,
                'voice'           => PBP_Settings::get( 'tts_voice_a', 'nova' ),
                'response_format' => 'mp3',
            ] ),
            'timeout' => 120,
        ] );

        if ( is_wp_error( $response ) ) return $this->error( $response->get_error_message() );
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) return $this->error( "YEScale lỗi HTTP: $code" );

        return $this->save_audio_to_library( $post_id, wp_remote_retrieve_body( $response ), 'mp3' );
    }

    // ----------------------------------------------------------------
    // Save audio to WordPress Media Library
    // ----------------------------------------------------------------
    private function save_audio_to_library( int $post_id, string $binary, string $ext ): array {
        if ( empty( $binary ) ) {
            return $this->error( 'Audio binary rỗng — provider không trả về dữ liệu.' );
        }

        $upload_dir = wp_upload_dir();
        $filename   = "podcast-ep-{$post_id}-" . time() . ".{$ext}";
        $filepath   = $upload_dir['path'] . '/' . $filename;

        // Write file
        if ( false === file_put_contents( $filepath, $binary ) ) {
            return $this->error( 'Không thể ghi file audio vào server.' );
        }

        // Insert into media library
        $attachment_id = wp_insert_attachment( [
            'post_mime_type' => 'audio/mpeg',
            'post_title'     => 'Podcast Episode ' . $post_id,
            'post_status'    => 'inherit',
        ], $filepath, $post_id );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $filepath );
            return $this->error( 'Không thể thêm file vào Media Library.' );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $filepath ) );

        $url      = wp_get_attachment_url( $attachment_id );
        $filesize = filesize( $filepath );
        $duration = $this->get_mp3_duration( $filepath );

        return [
            'success'    => true,
            'audio_url'  => $url,
            'audio_size' => $filesize,
            'duration'   => $duration,
            'error'      => '',
        ];
    }

    // ----------------------------------------------------------------
    // MP3 duration estimator (without ffprobe)
    // ----------------------------------------------------------------
    private function get_mp3_duration( string $filepath ): string {
        // Rough estimate: 128kbps MP3 ≈ 16000 bytes/second
        $size    = filesize( $filepath );
        $seconds = (int) ( $size / 16000 );
        return sprintf( '%02d:%02d:%02d', $seconds / 3600, ( $seconds % 3600 ) / 60, $seconds % 60 );
    }

    private function error( string $message ): array {
        return [ 'success' => false, 'audio_url' => '', 'audio_size' => 0, 'duration' => '', 'error' => $message ];
    }
}
