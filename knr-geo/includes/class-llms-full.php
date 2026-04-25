<?php
/**
 * Big GEO - llms-full.txt Generator
 * Generates physical llms-full.txt file with complete post content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BIG_GEO_LLMS_Full {

	/**
	 * Generate llms-full.txt content
	 */
	public function generate_content() {
		$post_types = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
		$description = get_option( 'big_geo_site_description', '' );
		$strip_shortcodes = get_option( 'big_geo_strip_shortcodes', '1' );
		
		$output = "";
		
		// Add custom intro description
		if ( ! empty( $description ) ) {
			$output .= "# " . get_bloginfo( 'name' ) . "\n";
			$output .= $description . "\n\n";
		}
		
		// Add site URL
		$output .= "Site: " . home_url() . "\n";
		$output .= "Generated: " . current_time( 'Y-m-d H:i:s' ) . "\n\n";
		$output .= "---\n\n";
		
		// Generate full content by post type
		foreach ( $post_types as $post_type ) {
			$posts = $this->get_posts_by_type( $post_type );
			
			if ( ! empty( $posts ) ) {
				$post_type_obj = get_post_type_object( $post_type );
				$output .= "# " . $post_type_obj->labels->name . "\n\n";
				
				foreach ( $posts as $post ) {
					$output .= $this->format_post_content( $post, $strip_shortcodes );
				}
			}
		}
		
		return $output;
	}
	
	/**
	 * Format single post content
	 */
	private function format_post_content( $post, $strip_shortcodes ) {
		$output = "";
		
		// Post title
		$output .= "## " . $post->post_title . "\n\n";
		
		// Meta information
		$output .= "**URL:** " . get_permalink( $post->ID ) . "\n";
		$output .= "**Published:** " . get_the_date( 'Y-m-d', $post->ID ) . "\n";
		
		// Categories/Taxonomy
		$categories = get_the_category( $post->ID );
		if ( ! empty( $categories ) ) {
			$cat_names = array_map( function( $cat ) {
				return $cat->name;
			}, $categories );
			$output .= "**Categories:** " . implode( ', ', $cat_names ) . "\n";
		}
		
		// Excerpt
		if ( ! empty( $post->post_excerpt ) ) {
			$output .= "**Excerpt:** " . $post->post_excerpt . "\n";
		}
		
		$output .= "\n";
		
		// Post content - stripped and cleaned
		$content = $post->post_content;
		
		// Strip shortcodes if enabled
		if ( $strip_shortcodes === '1' ) {
			$content = strip_shortcodes( $content );
		}
		
		// Convert to markdown-friendly text
		$content = $this->html_to_markdown( $content );
		
		$output .= $content . "\n\n";
		$output .= "---\n\n";
		
		return $output;
	}
	
	/**
	 * Convert HTML to clean markdown-style text
	 */
	private function html_to_markdown( $html ) {
		// Remove script and style tags
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		
		// Remove navigation, ads, and common unwanted elements
		$html = preg_replace( '/<nav\b[^>]*>.*?<\/nav>/is', '', $html );
		$html = preg_replace( '/<aside\b[^>]*>.*?<\/aside>/is', '', $html );
		
		// Convert headings
		$html = preg_replace( '/<h1[^>]*>(.*?)<\/h1>/is', "\n### $1\n", $html );
		$html = preg_replace( '/<h2[^>]*>(.*?)<\/h2>/is', "\n#### $1\n", $html );
		$html = preg_replace( '/<h3[^>]*>(.*?)<\/h3>/is', "\n##### $1\n", $html );
		
		// Convert lists
		$html = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $html );
		
		// Convert paragraphs to double newlines
		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $html );
		
		// Convert breaks
		$html = preg_replace( '/<br\s*\/?>/is', "\n", $html );
		
		// Strip remaining HTML tags
		$html = wp_strip_all_tags( $html );
		
		// Decode HTML entities
		$html = html_entity_decode( $html, ENT_QUOTES, 'UTF-8' );
		
		// Clean up excessive whitespace
		$html = preg_replace( '/\n{3,}/', "\n\n", $html );
		$html = preg_replace( '/[^\S\n]+/', ' ', $html );
		
		return trim( $html );
	}
	
	/**
	 * Get posts by post type
	 */
	private function get_posts_by_type( $post_type ) {
		$args = array(
			'post_type' => $post_type,
			'post_status' => 'publish',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'DESC',
		);
		
		$query = new WP_Query( $args );
		$posts = array();
		
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				
				// Skip if noindex
				if ( ! $this->is_noindex( get_the_ID() ) ) {
					$posts[] = get_post();
				}
			}
			wp_reset_postdata();
		}
		
		return $posts;
	}
	
	/**
	 * Check if post is marked noindex
	 */
	private function is_noindex( $post_id ) {
		// Check Yoast SEO
		if ( defined( 'WPSEO_VERSION' ) ) {
			$noindex = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
			if ( $noindex === '1' ) {
				return true;
			}
		}
		
		// Check Rank Math
		if ( class_exists( 'RankMath' ) ) {
			$robots = get_post_meta( $post_id, 'rank_math_robots', true );
			if ( is_array( $robots ) && in_array( 'noindex', $robots ) ) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Write llms-full.txt file to WordPress root
	 */
	public function write_file() {
		$content = $this->generate_content();
		$file_path = ABSPATH . 'llms-full.txt';
		
		// Use WP_Filesystem
		global $wp_filesystem;
		
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		WP_Filesystem();
		
		if ( ! $wp_filesystem ) {
			return array(
				'success' => false,
				'message' => 'Could not initialize WP_Filesystem'
			);
		}
		
		// Write file
		$result = $wp_filesystem->put_contents(
			$file_path,
			$content,
			FS_CHMOD_FILE
		);
		
		if ( $result ) {
			update_option( 'big_geo_llms_full_generated', current_time( 'mysql' ) );
			
			return array(
				'success' => true,
				'message' => 'llms-full.txt file generated successfully!',
				'file_url' => home_url( '/llms-full.txt' )
			);
		} else {
			return array(
				'success' => false,
				'message' => 'Failed to write llms-full.txt file. Check file permissions.'
			);
		}
	}
	
	/**
	 * Check if llms-full.txt file exists
	 */
	public function file_exists() {
		return file_exists( ABSPATH . 'llms-full.txt' );
	}
	
	/**
	 * Get file last modified time
	 */
	public function get_file_time() {
		if ( $this->file_exists() ) {
			return filemtime( ABSPATH . 'llms-full.txt' );
		}
		return false;
	}
}
