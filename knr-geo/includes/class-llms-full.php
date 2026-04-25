<?php
/**
 * Big GEO - llms-full.txt Generator
 * Generates llms-full.txt with full post content as clean Markdown
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BIG_GEO_LLMS_Full {

    public function register_rewrite() {
        add_rewrite_rule( '^llms-full\.txt$', 'index.php?big_geo_llms_full=1', 'top' );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_action( 'template_redirect', array( $this, 'serve_file' ) );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'big_geo_llms_full';
        return $vars;
    }

    public function serve_file() {
        if ( ! get_query_var( 'big_geo_llms_full' ) ) {
            return;
        }
        if ( get_option( 'big_geo_llms_full_enabled', '0' ) !== '1' ) {
            wp_die( 'llms-full.txt is disabled.', '', array( 'response' => 404 ) );
        }
        $content = $this->generate();
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'X-Robots-Tag: noindex' );
        echo $content;
        exit;
    }

    /**
     * Generate llms-full.txt content
     *
     * @return string
     */
    public function generate() {
        $cached = get_transient( 'big_geo_llms_full_cache' );
        if ( false !== $cached ) {
            return $cached;
        }

        $lines = array();

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

        $post_types = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
        if ( ! is_array( $post_types ) ) {
            $post_types = array( 'post', 'page' );
        }

        $strip_shortcodes = get_option( 'big_geo_strip_shortcodes', '1' ) === '1';

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

            foreach ( $posts as $post ) {
                $url      = get_permalink( $post->ID );
                $date     = get_the_date( 'Y-m-d', $post->ID );
                $cats     = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
                $cat_str  = is_array( $cats ) ? implode( ', ', $cats ) : '';
                $excerpt  = has_excerpt( $post->ID ) ? get_the_excerpt( $post ) : '';

                // Post header
                $lines[] = '---';
                $lines[] = '# ' . $post->post_title;
                $lines[] = 'URL: ' . $url;
                $lines[] = 'Date: ' . $date;
                if ( ! empty( $cat_str ) ) {
                    $lines[] = 'Category: ' . $cat_str;
                }
                if ( ! empty( $excerpt ) ) {
                    $lines[] = 'Excerpt: ' . $excerpt;
                }
                $lines[] = '';

                // Post content
                $content = $post->post_content;

                // Strip shortcodes
                if ( $strip_shortcodes ) {
                    $content = strip_shortcodes( $content );
                }

                // Strip HTML and clean up
                $content = $this->strip_to_markdown( $content );

                $lines[] = $content;
                $lines[] = '';
            }
        }

        $output = implode( "\n", $lines );
        set_transient( 'big_geo_llms_full_cache', $output, HOUR_IN_SECONDS );

        return $output;
    }

    /**
     * Strip HTML and convert basic elements to Markdown-friendly plain text
     *
     * @param string $html
     * @return string
     */
    private function strip_to_markdown( $html ) {
        // Apply WordPress content filters (handles blocks, etc)
        $html = apply_filters( 'the_content', $html );

        // Remove script and style blocks
        $html = preg_replace( '#<script[^>]*>.*?</script>#si', '', $html );
        $html = preg_replace( '#<style[^>]*>.*?</style>#si', '', $html );

        // Convert headings
        $html = preg_replace( '#<h1[^>]*>(.*?)</h1>#si', "# $1\n", $html );
        $html = preg_replace( '#<h2[^>]*>(.*?)</h2>#si', "## $1\n", $html );
        $html = preg_replace( '#<h3[^>]*>(.*?)</h3>#si', "### $1\n", $html );
        $html = preg_replace( '#<h[4-6][^>]*>(.*?)</h[4-6]>#si', "#### $1\n", $html );

        // Convert paragraphs
        $html = preg_replace( '#<p[^>]*>(.*?)</p>#si', "$1\n\n", $html );

        // Convert list items
        $html = preg_replace( '#<li[^>]*>(.*?)</li>#si', "- $1\n", $html );

        // Convert line breaks
        $html = preg_replace( '#<br\s*/?>\s*#si', "\n", $html );

        // Strip remaining HTML tags
        $html = wp_strip_all_tags( $html );

        // Decode HTML entities
        $html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );

        // Clean up excessive whitespace
        $html = preg_replace( '#[ \t]+#', ' ', $html );
        $html = preg_replace( '#\n{3,}#', "\n\n", $html );
        $html = trim( $html );

        return $html;
    }
}