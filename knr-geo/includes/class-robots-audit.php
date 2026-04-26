<?php
/**
 * Big GEO - AI Crawler Robots.txt Audit & Fix
 * Three-tier detection: Virtual WP robots.txt, Physical file, Custom URL
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BIG_GEO_Robots_Audit {

    /**
     * Known AI bots to audit
     */
    private $ai_bots = array(
        'GPTBot'          => 'ChatGPT / OpenAI',
        'ClaudeBot'       => 'Anthropic Claude',
        'PerplexityBot'   => 'Perplexity AI',
        'Google-Extended' => 'Google Gemini / Bard',
        'Amazonbot'       => 'Amazon Alexa AI',
        'cohere-ai'       => 'Cohere AI',
    );

    /**
     * Detect the robots.txt situation and return the tier
     *
     * @return string  'virtual' | 'physical' | 'custom'
     */
    public function detect_tier() {
        $custom_url = get_option( 'big_geo_custom_robots_url', '' );
        if ( ! empty( $custom_url ) ) {
            return 'custom';
        }
        if ( file_exists( ABSPATH . 'robots.txt' ) ) {
            return 'physical';
        }
        return 'virtual';
    }

    /**
     * Run the full audit: fetch robots.txt and check each AI bot
     *
     * @return array
     */
    public function run_audit() {
        $tier = $this->detect_tier();
        $robots_content = $this->fetch_robots_content( $tier );

        $results = array(
            'tier'          => $tier,
            'bots'          => array(),
            'all_allowed'   => true,
            'robots_raw'    => $robots_content,
        );

        if ( empty( $robots_content ) ) {
            // No robots.txt - all bots are allowed by default
            foreach ( $this->ai_bots as $bot => $label ) {
                $results['bots'][] = array(
                    'bot'     => $bot,
                    'label'   => $label,
                    'status'  => 'allowed',
                    'message' => 'No robots.txt - allowed by default',
                );
            }
            return $results;
        }

        foreach ( $this->ai_bots as $bot => $label ) {
            $blocked = $this->is_bot_blocked( $bot, $robots_content );
            if ( $blocked ) {
                $results['all_allowed'] = false;
            }
            $results['bots'][] = array(
                'bot'     => $bot,
                'label'   => $label,
                'status'  => $blocked ? 'blocked' : 'allowed',
                'message' => $blocked ? 'Bot is blocked by robots.txt' : 'Bot is allowed',
            );
        }

        return $results;
    }

    /**
     * Fetch robots.txt content based on tier
     */
    private function fetch_robots_content( $tier ) {
        if ( $tier === 'physical' ) {
            return file_get_contents( ABSPATH . 'robots.txt' );
        }

        if ( $tier === 'custom' ) {
            $url = get_option( 'big_geo_custom_robots_url', '' );
            if ( empty( $url ) ) return '';
            $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
            if ( is_wp_error( $response ) ) return '';
            return wp_remote_retrieve_body( $response );
        }

        // Virtual tier - use WordPress-generated robots.txt
        $robots_url = home_url( '/robots.txt' );
        $response = wp_remote_get( $robots_url, array( 'timeout' => 10 ) );
        if ( is_wp_error( $response ) ) return '';
        return wp_remote_retrieve_body( $response );
    }

    /**
     * Check if a specific bot is blocked in robots.txt content
     *
     * @param string $bot_name
     * @param string $robots_content
     * @return bool
     */
    private function is_bot_blocked( $bot_name, $robots_content ) {
        $lines = explode( "\n", $robots_content );
        $current_agents = array();
        $in_relevant_block = false;

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( stripos( $line, 'User-agent:' ) === 0 ) {
                $agent = trim( substr( $line, strlen( 'User-agent:' ) ) );
                $current_agents = array( $agent );
                $in_relevant_block = ( $agent === '*' || stripos( $agent, $bot_name ) !== false );
                continue;
            }

            if ( $in_relevant_block && stripos( $line, 'Disallow:' ) === 0 ) {
                $path = trim( substr( $line, strlen( 'Disallow:' ) ) );
                if ( $path === '/' ) {
                    return true;
                }
            }

            if ( empty( $line ) ) {
                $current_agents = array();
                $in_relevant_block = false;
            }
        }

        return false;
    }

    /**
     * Tier 1: Apply virtual fix via robots_txt WordPress filter
     * Hook is registered here and saved to options
     *
     * @return array
     */
    public function apply_virtual_fix() {
        update_option( 'big_geo_robots_fix_active', '1' );
        // The actual hook is registered via settings-page.php after option is set
        return array(
            'success' => true,
            'message' => 'Virtual fix activated. AI bots will be allowed via robots_txt filter hook.',
            'tier'    => 'virtual',
        );
    }

    /**
     * Tier 2: Write physical robots.txt with AI bot allow rules appended
     *
     * @return array
     */
    public function write_physical_robots() {
        $robots_path = ABSPATH . 'robots.txt';

        // Read existing content
        $existing = '';
        if ( file_exists( $robots_path ) ) {
            $existing = file_get_contents( $robots_path );
        } else {
            // Generate base WordPress robots.txt
            $existing = $this->get_wordpress_base_robots();
        }

        // Build AI bot allow rules
        $ai_rules = $this->build_ai_allow_rules();

        // Check if rules already exist
        if ( strpos( $existing, '# Big GEO - AI Crawlers' ) !== false ) {
            return array(
                'success' => false,
                'message' => 'AI bot rules already exist in robots.txt.',
            );
        }

        $new_content = rtrim( $existing ) . "\n\n" . $ai_rules;

        // Try WP_Filesystem first
        if ( function_exists( 'WP_Filesystem' ) ) {
            global $wp_filesystem;
            WP_Filesystem();
            if ( $wp_filesystem && $wp_filesystem->wp_is_writable( ABSPATH ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.filesystem_operations_is_writable
                $wp_filesystem->put_contents( $robots_path, $new_content, FS_CHMOD_FILE );
                return array(
                    'success'  => true,
                    'message'  => 'robots.txt updated successfully via WP_Filesystem.',
                    'content'  => $new_content,
                );
            }
        }

        // Fallback: file_put_contents
        if ( wp_is_writable( ABSPATH ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.filesystem_operations_is_writable
            file_put_contents( $robots_path, $new_content );
            return array(
                'success' => true,
                'message' => 'robots.txt updated successfully.',
                'content' => $new_content,
            );
        }

        // Not writable - return content for manual download
        return array(
            'success'  => false,
            'manual'   => true,
            'message'  => 'File not writable. Copy the content below and upload via FTP/File Manager.',
            'content'  => $new_content,
        );
    }

    /**
     * Generate the full corrected robots.txt content (for download/copy)
     *
     * @return string
     */
    public function generate_corrected_robots() {
        $robots_path = ABSPATH . 'robots.txt';
        $existing = '';
        if ( file_exists( $robots_path ) ) {
            $existing = file_get_contents( $robots_path );
        } else {
            $existing = $this->get_wordpress_base_robots();
        }
        return rtrim( $existing ) . "\n\n" . $this->build_ai_allow_rules();
    }

    /**
     * Build the AI bot allow rules block
     */
    public function build_ai_allow_rules() {
        $rules = "# Big GEO - AI Crawlers (Auto-generated)\n";
        foreach ( array_keys( $this->ai_bots ) as $bot ) {
            $rules .= "User-agent: {$bot}\n";
            $rules .= "Allow: /\n\n";
        }
        return trim( $rules );
    }

    /**
     * Get base WordPress robots.txt content
     */
    private function get_wordpress_base_robots() {
        $robots = "User-agent: *\n";
        $robots .= 'Disallow: ' . wp_parse_url( admin_url(), PHP_URL_PATH ) . "\n";
        $robots .= 'Sitemap: ' . get_sitemap_url( 'index' ) . "\n";
        return $robots;
    }

    /**
     * Get the list of AI bots
     */
    public function get_ai_bots() {
        return $this->ai_bots;
    }

    /**
     * Hook into robots_txt filter for virtual fix (Tier 1)
     * Called from settings-page.php
     */
    public static function inject_ai_bots_filter( $output, $public ) {
        if ( '1' !== get_option( 'big_geo_robots_fix_active', '0' ) ) {
            return $output;
        }
        $ai_bots = array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'Amazonbot', 'cohere-ai' );
        $additions = "\n# Big GEO - AI Crawlers\n";
        foreach ( $ai_bots as $bot ) {
            $additions .= "User-agent: {$bot}\nAllow: /\n\n";
        }
        return $output . $additions;
    }
}