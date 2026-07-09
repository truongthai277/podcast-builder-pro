<?php
/**
 * Helper: escape XML special characters for RSS
 */
if ( ! function_exists( 'esc_xml' ) ) {
    function esc_xml( string $text ): string {
        return htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
    }
}
