<?php
/**
 * License System — Offline HMAC-signed key (no external server needed)
 *
 * Key format (base64url):  {payload_json}|{hmac_sha256}
 * Payload JSON fields:
 *   - domain   : string  — domain duoc phep dung (* = any domain)
 *   - expires  : string  — ISO date "YYYY-MM-DD" hoac "" (vinh vien)
 *   - product  : string  — "podcast-builder-pro"
 *   - issued   : string  — ISO date issued
 *   - nonce    : string  — random 8 chars (uniqueness)
 *
 * @package Podcast_Builder_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PBP_License {

    private static ?self $instance = null;

    /**
     * Secret key dung de ky/verify HMAC.
     * DOI THANH CHUOI NGAU NHIEN DAI CUA BAN truoc khi deploy.
     * Giu bi mat — ai co key nay co the tao license tuy y.
     */
    const HMAC_SECRET = 'Slice@0216111';

    const PRODUCT     = 'podcast-builder-pro';
    const CACHE_TTL   = DAY_IN_SECONDS;
    const GRACE_DAYS  = 7;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    // ----------------------------------------------------------------
    // Activate — verify offline, luu vao DB
    // ----------------------------------------------------------------
    public function activate( string $raw_key ): array {
        $raw_key = sanitize_text_field( trim( $raw_key ) );

        if ( empty( $raw_key ) ) {
            return [ 'success' => false, 'message' => 'Vui long nhap license key.' ];
        }

        $result = $this->verify_key( $raw_key );

        if ( $result['valid'] ) {
            PBP_Settings::set( 'license_key',     $raw_key );
            PBP_Settings::set( 'license_status',  'active' );
            PBP_Settings::set( 'license_expires',  $result['expires'] );
            PBP_Settings::set( 'license_domains',  1 );
            update_option( 'pbp_license_last_check', time() );
            set_transient( 'pbp_license_valid', true, self::CACHE_TTL );

            $msg = 'License da duoc kich hoat thanh cong!';
            if ( $result['expires'] ) {
                $msg .= ' Het han: ' . esc_html( $result['expires'] );
            } else {
                $msg .= ' (Vinh vien)';
            }
            return [ 'success' => true, 'message' => $msg ];
        }

        PBP_Settings::set( 'license_status', 'invalid' );
        delete_transient( 'pbp_license_valid' );
        return [ 'success' => false, 'message' => $result['error'] ];
    }

    // ----------------------------------------------------------------
    // Deactivate
    // ----------------------------------------------------------------
    public function deactivate(): array {
        PBP_Settings::set( 'license_status', 'inactive' );
        delete_transient( 'pbp_license_valid' );
        return [ 'success' => true, 'message' => 'License da duoc huy kich hoat.' ];
    }

    // ----------------------------------------------------------------
    // Validation (cached)
    // ----------------------------------------------------------------
    public function is_valid(): bool {
        if ( $this->is_dev_mode() ) return true;

        // Check transient cache
        $cached = get_transient( 'pbp_license_valid' );
        if ( $cached !== false ) {
            return (bool) $cached;
        }

        $status = PBP_Settings::get( 'license_status', 'inactive' );
        if ( $status !== 'active' ) return false;

        $raw_key = PBP_Settings::get( 'license_key', '' );
        $result  = $this->verify_key( $raw_key );

        if ( $result['valid'] ) {
            update_option( 'pbp_license_last_check', time() );
            set_transient( 'pbp_license_valid', true, self::CACHE_TTL );
            return true;
        }

        // Grace period
        if ( $result['grace_eligible'] ?? false ) {
            $last_check = (int) get_option( 'pbp_license_last_check', 0 );
            if ( ( time() - $last_check ) < ( self::GRACE_DAYS * DAY_IN_SECONDS ) ) {
                set_transient( 'pbp_license_valid', true, HOUR_IN_SECONDS );
                return true;
            }
        }

        set_transient( 'pbp_license_valid', false, HOUR_IN_SECONDS );
        PBP_Settings::set( 'license_status', 'expired' );
        return false;
    }

    // ----------------------------------------------------------------
    // Core: Verify key offline (HMAC-SHA256)
    // ----------------------------------------------------------------
    public function verify_key( string $raw_key ): array {
        // Decode base64url
        $decoded = base64_decode( strtr( $raw_key, '-_', '+/' ) );
        if ( $decoded === false ) {
            return $this->fail( 'Key khong dung dinh dang.' );
        }

        // Split payload | hmac
        $last_pipe = strrpos( $decoded, '|' );
        if ( $last_pipe === false ) {
            return $this->fail( 'Key khong dung cau truc.' );
        }

        $payload_json = substr( $decoded, 0, $last_pipe );
        $given_hmac   = substr( $decoded, $last_pipe + 1 );

        // Verify HMAC
        $expected_hmac = hash_hmac( 'sha256', $payload_json, self::HMAC_SECRET );
        if ( ! hash_equals( $expected_hmac, $given_hmac ) ) {
            return $this->fail( 'Key khong hop le hoac da bi chinh sua.' );
        }

        // Parse payload
        $payload = json_decode( $payload_json, true );
        if ( ! is_array( $payload ) ) {
            return $this->fail( 'Key bi hong (payload loi).' );
        }

        // Check product
        if ( ( $payload['product'] ?? '' ) !== self::PRODUCT ) {
            return $this->fail( 'Key khong phai cho plugin nay.' );
        }

        // Check domain
        $allowed_domain = $payload['domain'] ?? '';
        $current_domain = $this->get_domain();
        if ( $allowed_domain !== '*' && $allowed_domain !== $current_domain ) {
            return $this->fail( "Key duoc cap cho domain \"{$allowed_domain}\", khong phai \"{$current_domain}\"." );
        }

        // Check expiry
        $expires = $payload['expires'] ?? '';
        if ( $expires !== '' ) {
            $expiry_ts = strtotime( $expires . ' 23:59:59' );
            if ( $expiry_ts && time() > $expiry_ts ) {
                return [
                    'valid'           => false,
                    'error'           => "License da het han vao {$expires}.",
                    'grace_eligible'  => true,
                    'expires'         => $expires,
                ];
            }
        }

        return [
            'valid'   => true,
            'expires' => $expires,
            'domain'  => $allowed_domain,
            'issued'  => $payload['issued'] ?? '',
        ];
    }

    // ----------------------------------------------------------------
    // Generator — dung de TAO key (chay 1 lan qua WP CLI hoac admin)
    // ----------------------------------------------------------------
    /**
     * Tao license key moi.
     *
     * @param string $domain  Domain duoc phep, hoac '*' cho phep moi domain
     * @param string $expires Ngay het han 'YYYY-MM-DD', hoac '' de vinh vien
     * @return string  License key (base64url)
     */
    public static function generate_key( string $domain = '*', string $expires = '' ): string {
        $payload = wp_json_encode( [
            'product' => self::PRODUCT,
            'domain'  => $domain,
            'expires' => $expires,
            'issued'  => gmdate( 'Y-m-d' ),
            'nonce'   => substr( md5( uniqid( '', true ) ), 0, 8 ),
        ] );

        $hmac = hash_hmac( 'sha256', $payload, self::HMAC_SECRET );
        $raw  = $payload . '|' . $hmac;

        // Encode base64url (URL-safe, no padding)
        return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    private function fail( string $error ): array {
        return [ 'valid' => false, 'error' => $error, 'grace_eligible' => false ];
    }

    private function get_domain(): string {
        return wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'unknown';
    }

    public function get_current_domain(): string {
        return $this->get_domain();
    }

    public function get_status_label(): string {
        $status = PBP_Settings::get( 'license_status', 'inactive' );
        $labels = [
            'active'   => 'Dang hoat dong',
            'inactive' => 'Chua kich hoat',
            'invalid'  => 'Khong hop le',
            'expired'  => 'Da het han',
        ];
        return $labels[ $status ] ?? $status;
    }

    // ----------------------------------------------------------------
    // DEV MODE — bypass license check
    // ----------------------------------------------------------------
    public function is_dev_mode(): bool {
        return defined( 'PBP_DEV_MODE' ) && PBP_DEV_MODE === true;
    }

    public function check( bool $show_notice = false ): bool {
        if ( $this->is_dev_mode() ) return true;
        $valid = $this->is_valid();
        if ( ! $valid && $show_notice ) {
            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-warning"><p>Podcast Builder Pro: License chua duoc kich hoat. <a href="' . esc_url( admin_url( 'admin.php?page=podcast-builder-pro-license' ) ) . '">Kich hoat ngay</a></p></div>';
            } );
        }
        return $valid;
    }
}
