<?php
/**
 * Big GEO - llms.txt Generator
 * Generates llms.txt dynamically via WordPress rewrite rules
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BIG_GEO_LLMS_Txt {

    /**
     * Register the rewrite rule for /llms.txt
     */
    public function register_rewrite() {
        add_rewrite_rule( '^llms\.txt$', 'index.php?big_geo_llms_txt=1', 'top' );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_file' ) );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'big_geo_llms_txt';
        return $vars;
    }

    public function serve_file() {
        if ( ! get_query_var( 'big_geo_llms_txt' ) ) {
            return;
        }
        if ( get_option( 'big_geo_llms_txt_enabled', '1' ) !== '1' ) {
            wp_die( 'llms.txt is disabled.', '', array( 'response' => 404 ) );
        }
        $content = $this->generate();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        echo $content;
        exit;
    }

    /**
     * Generate llms.txt content
     *
     * @return string
     */
    public function generate() {
        $cached = get_transient( 'big_geo_llms_txt_cache' );
        if ( false !== $cached ) {
            return $cached;
        }

        $lines = array();

        // Site header block
        $site_name = get_bloginfo( 'name' );
        $site_desc = get_option( 'big_geo_site_description', '' );
        if ( empty( $site_desc ) ) {
            $site_desc = get_bloginfo( 'description' );
        }

        $lines[] = '# ' . $site_name;
        if ( ! empty( $site_desc ) ) {
            $lines[] = '> ' . $site_desc;
        }
        $lines[] = '';

        // Get enabled post types
        $post_types = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
        if ( ! is_array( $post_types ) ) {
            $post_types = array( 'post', 'page' );
        }

        // Get excluded URLs
        $excluded_raw = get_option( 'big_geo_excluded_urls', '' );
        $excluded_urls = array();
        if ( ! empty( $excluded_raw ) ) {
            $excluded_urls = array_filter( array_map( 'trim', explode( "\n", $excluded_raw ) ) );
        }

        foreach ( $post_types as $post_type ) {
            $posts = get_posts( array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );

            if ( empty( $posts ) ) {
                continue;
            }

            $obj = get_post_type_object( $post_type );
            $label = $obj ? $obj->labels->name : ucfirst( $post_type );
            $lines[] = '## ' . $label;
            $lines[] = '';

            foreach ( $posts as $post ) {
                $url = get_permalink( $post->ID );

                // Skip excluded URLs
                if ( in_array( $url, $excluded_urls, true ) ) {
                    continue;
                }

                // Skip noindex posts (Yoast / Rank Math / AIOSEO)
                if ( $this->is_noindex( $post->ID ) ) {
                    continue;
                }

                $lines[] = '- [' . esc_html( $post->post_title ) . '](' . esc_url( $url ) . ')';
            }

            $lines[] = '';
        }

        $content = implode( "\n", $lines );
        set_transient( 'big_geo_llms_txt_cache', $content, HOUR_IN_SECONDS );

        return $content;
    }

    /**
     * Check if a post is noindexed by popular SEO plugins
     *
     * @param int $post_id
     * @return bool
     */
    private function is_noindex( $post_id ) {
        // Yoast SEO
        $yoast_robots = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
        if ( '1' === (string) $yoast_robots ) {
            return true;
        }

        // Rank Math
        $rankmath = get_post_meta( $post_id, 'rank_math_robots', true );
        if ( is_array( $rankmath ) && in_array( 'noindex', $rankmath, true ) ) {
            return true;
        }

        // All in One SEO
        $aioseo = get_post_meta( $post_id, '_aioseo_noindex', true );
        if ( '1' === (string) $aioseo ) {
            return true;
        }

        return false;
    }
}