<?php
/**
 * AJAX Handlers — Generate podcast, update directory, queue actions
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_AJAX {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init(): void {
        $actions = [
            'pbp_generate_episode',
            'pbp_get_status',
            'pbp_update_directory',
            'pbp_add_directory',
            'pbp_delete_directory',
            'pbp_queue_post',
            'pbp_activate_license',
            'pbp_deactivate_license',
            'pbp_get_voices',
            'pbp_get_models',
            'pbp_test_api',
        ];

        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ $this, str_replace( 'pbp_', 'handle_', $action ) ] );
        }
    }

    // ----------------------------------------------------------------
    // Generate Episode (main action)
    // ----------------------------------------------------------------
    public function handle_generate_episode(): void {
        $this->verify_nonce( 'pbp_meta_nonce' );
        $this->verify_capability( 'edit_posts' );

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) {
            wp_send_json_error( [ 'message' => 'Post ID không hợp lệ.' ] );
        }

        // Check license (bypass if dev mode)
        if ( ! PBP_License::instance()->check() ) {
            wp_send_json_error( [ 'message' => 'License chưa được kích hoạt. Vui lòng kích hoạt tại trang License.' ] );
        }

        // Run pipeline
        $result = PBP_Queue::instance()->generate_episode( $post_id );

        if ( $result['success'] ) {
            wp_send_json_success( [
                'message'    => '✅ Podcast đã được tạo thành công!',
                'audio_url'  => get_post_meta( $post_id, '_pbp_audio_url', true ),
                'duration'   => get_post_meta( $post_id, '_pbp_duration', true ),
                'status'     => 'done',
                'reload'     => true,
            ] );
        } else {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }
    }

    // ----------------------------------------------------------------
    // Get episode status (polling)
    // ----------------------------------------------------------------
    public function handle_get_status(): void {
        $this->verify_nonce( 'pbp_meta_nonce' );

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        $status  = get_post_meta( $post_id, '_pbp_status', true ) ?: 'not_generated';
        $error   = get_post_meta( $post_id, '_pbp_error_msg', true );

        wp_send_json_success( [
            'status'    => $status,
            'audio_url' => get_post_meta( $post_id, '_pbp_audio_url', true ),
            'error'     => $error,
        ] );
    }

    // ----------------------------------------------------------------
    // Directory: Update status / profile URL / notes
    // ----------------------------------------------------------------
    public function handle_update_directory(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $id   = (int) ( $_POST['id'] ?? 0 );
        $data = [
            'status'      => sanitize_text_field( $_POST['status'] ?? '' ),
            'profile_url' => esc_url_raw( $_POST['profile_url'] ?? '' ),
            'notes'       => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        ];

        if ( PBP_Directory::update( $id, $data ) ) {
            wp_send_json_success( [ 'message' => 'Đã cập nhật.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Không thể cập nhật.' ] );
        }
    }

    // ----------------------------------------------------------------
    // Directory: Add custom
    // ----------------------------------------------------------------
    public function handle_add_directory(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $id = PBP_Directory::add_custom( [
            'label'      => sanitize_text_field( $_POST['label'] ?? '' ),
            'submit_url' => esc_url_raw( $_POST['submit_url'] ?? '' ),
            'da'         => (int) ( $_POST['da'] ?? 0 ),
        ] );

        if ( $id ) {
            wp_send_json_success( [ 'id' => $id, 'message' => 'Directory đã được thêm.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Không thể thêm.' ] );
        }
    }

    // ----------------------------------------------------------------
    // Directory: Delete
    // ----------------------------------------------------------------
    public function handle_delete_directory(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( PBP_Directory::delete( $id ) ) {
            wp_send_json_success( [ 'message' => 'Đã xóa.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Không thể xóa.' ] );
        }
    }

    // ----------------------------------------------------------------
    // Queue: Manually queue a post
    // ----------------------------------------------------------------
    public function handle_queue_post(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'edit_posts' );

        $post_id = (int) ( $_POST['post_id'] ?? 0 );
        if ( PBP_Queue::instance()->add_to_queue( $post_id ) ) {
            wp_send_json_success( [ 'message' => "Post #$post_id đã được thêm vào queue." ] );
        } else {
            wp_send_json_error( [ 'message' => 'Post đã có trong queue.' ] );
        }
    }

    // ----------------------------------------------------------------
    // License: Activate
    // ----------------------------------------------------------------
    public function handle_activate_license(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $key    = sanitize_text_field( $_POST['license_key'] ?? '' );
        $result = PBP_License::instance()->activate( $key );
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result );
        }
    }

    // ----------------------------------------------------------------
    // License: Deactivate
    // ----------------------------------------------------------------
    public function handle_deactivate_license(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $result = PBP_License::instance()->deactivate();
        wp_send_json_success( $result );
    }

    // ----------------------------------------------------------------
    // Dynamic: Get voices for provider
    // ----------------------------------------------------------------
    public function handle_get_voices(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $provider = sanitize_key( $_POST['provider'] ?? '' );
        $voices   = PBP_Settings::tts_voices( $provider );
        wp_send_json_success( $voices );
    }

    // ----------------------------------------------------------------
    // Dynamic: Get models for AI provider
    // ----------------------------------------------------------------
    public function handle_get_models(): void {
        $this->verify_nonce( 'pbp_admin_nonce' );
        $provider = sanitize_key( $_POST['provider'] ?? '' );
        $models   = PBP_Settings::ai_models( $provider );
        wp_send_json_success( $models );
    }

    // ----------------------------------------------------------------
    // Debug: Test API connectivity
    // ----------------------------------------------------------------
    public function handle_test_api(): void {
        ob_start(); // buffer any stray output (PHP notices etc.)

        $this->verify_nonce( 'pbp_admin_nonce' );
        $this->verify_capability( 'manage_options' );

        $provider = sanitize_key( $_POST['provider'] ?? 'gemini' );
        $key      = sanitize_text_field( $_POST['api_key'] ?? '' );

        if ( empty( $key ) ) {
            ob_end_clean();
            wp_send_json_error( [ 'message' => 'Vui lòng nhập API key.' ] );
            return;
        }

        if ( $provider === 'gemini' ) {
            $model = 'gemini-2.0-flash';
            $url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
            $body  = wp_json_encode( [
                'contents'         => [ [ 'role' => 'user', 'parts' => [ [ 'text' => 'Say "OK" in one word.' ] ] ] ],
                'generationConfig' => [ 'maxOutputTokens' => 10 ],
            ] );
            $resp  = wp_remote_post( $url, [
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body'    => $body,
                'timeout' => 15,
            ] );

            ob_end_clean();

            if ( is_wp_error( $resp ) ) {
                wp_send_json_error( [ 'message' => 'Lỗi kết nối: ' . $resp->get_error_message() ] );
                return;
            }

            $code = wp_remote_retrieve_response_code( $resp );
            $raw  = wp_remote_retrieve_body( $resp );
            $json = json_decode( $raw, true );

            if ( $code === 200 && isset( $json['candidates'] ) ) {
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '(trống)';
                wp_send_json_success( [ 'message' => '✅ Gemini API hoạt động! Phản hồi: "' . esc_html( trim( $text ) ) . '"' ] );
            } else {
                $err = ( is_array( $json ) && isset( $json['error']['message'] ) )
                    ? $json['error']['message']
                    : ( empty( $raw ) ? "HTTP {$code} - Không có nội dung phản hồi" : $raw );
                wp_send_json_error( [ 'message' => "HTTP {$code}: " . esc_html( substr( $err, 0, 200 ) ) ] );
            }

        } elseif ( $provider === 'openai' ) {
            $resp = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $key,
                ],
                'body'    => wp_json_encode( [
                    'model'      => 'gpt-4o-mini',
                    'messages'   => [ [ 'role' => 'user', 'content' => 'Say "OK" in one word.' ] ],
                    'max_tokens' => 10,
                ] ),
                'timeout' => 15,
            ] );

            ob_end_clean();

            if ( is_wp_error( $resp ) ) {
                wp_send_json_error( [ 'message' => 'Lỗi kết nối: ' . $resp->get_error_message() ] );
                return;
            }

            $code = wp_remote_retrieve_response_code( $resp );
            $raw  = wp_remote_retrieve_body( $resp );
            $json = json_decode( $raw, true );

            if ( $code === 200 ) {
                $text = $json['choices'][0]['message']['content'] ?? '(trống)';
                wp_send_json_success( [ 'message' => '✅ OpenAI API hoạt động! Phản hồi: "' . esc_html( trim( $text ) ) . '"' ] );
            } else {
                $err = ( is_array( $json ) && isset( $json['error']['message'] ) )
                    ? $json['error']['message']
                    : ( empty( $raw ) ? "HTTP {$code}" : $raw );
                wp_send_json_error( [ 'message' => "HTTP {$code}: " . esc_html( substr( $err, 0, 200 ) ) ] );
            }

        } else {
            ob_end_clean();
            wp_send_json_error( [ 'message' => 'Provider không hỗ trợ test tự động. Hãy test thủ công.' ] );
        }
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function verify_nonce( string $action ): void {
        $nonce = $_POST['nonce'] ?? $_REQUEST['nonce'] ?? '';
        if ( ! wp_verify_nonce( $nonce, $action ) ) {
            ob_clean();
            wp_send_json_error( [ 'message' => 'Nonce không hợp lệ. Vui lòng tải lại trang.' ], 403 );
            exit;
        }
    }

    private function verify_capability( string $cap ): void {
        if ( ! current_user_can( $cap ) ) {
            ob_clean();
            wp_send_json_error( [ 'message' => 'Bạn không có quyền thực hiện thao tác này.' ], 403 );
            exit;
        }
    }
}
