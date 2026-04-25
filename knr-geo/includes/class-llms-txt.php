<?php
/**
 * Big GEO - llms.txt Generator
 * Generates physical llms.txt file in WordPress root
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BIG_GEO_LLMS_Txt {

	/**
	 * Generate llms.txt content
	 */
	public function generate_content() {
		$post_types = get_option( 'big_geo_post_types', array( 'post', 'page' ) );
		$description = get_option( 'big_geo_site_description', '' );
		
		$output = "";
		
		// Add custom intro description
		if ( ! empty( $description ) ) {
			$output .= "# " . get_bloginfo( 'name' ) . "\n";
			$output .= $description . "\n\n";
		}
		
		// Add site URL
		$output .= "Site: " . home_url() . "\n\n";
		
		// Generate post list by post type
		foreach ( $post_types as $post_type ) {
			$posts = $this->get_posts_by_type( $post_type );
			
			if ( ! empty( $posts ) ) {
				$post_type_obj = get_post_type_object( $post_type );
				$output .= "## " . $post_type_obj->labels->name . "\n\n";
				
				foreach ( $posts as $post ) {
					$output .= "- [" . $post->post_title . "](" . get_permalink( $post->ID ) . ")\n";
				}
				
				$output .= "\n";
			}
		}
		
		return $output;
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
	 * Write llms.txt file to WordPress root
	 */
	public function write_file() {
		$content = $this->generate_content();
		$file_path = ABSPATH . 'llms.txt';
		
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
			update_option( 'big_geo_llms_txt_generated', current_time( 'mysql' ) );
			
			return array(
				'success' => true,
				'message' => 'llms.txt file generated successfully!',
				'file_url' => home_url( '/llms.txt' )
			);
		} else {
			return array(
				'success' => false,
				'message' => 'Failed to write llms.txt file. Check file permissions.'
			);
		}
	}
	
	/**
	 * Check if llms.txt file exists
	 */
	public function file_exists() {
		return file_exists( ABSPATH . 'llms.txt' );
	}
	
	/**
	 * Get file last modified time
	 */
	public function get_file_time() {
		if ( $this->file_exists() ) {
			return filemtime( ABSPATH . 'llms.txt' );
		}
		return false;
	}
}
