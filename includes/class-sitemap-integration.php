<?php
/**
 * Sitemap Integration
 *
 * Integrates topline posts into PRC sitemaps.
 *
 * @package PRC_PDF_Extraction
 */

namespace PRC\Platform\PDF_Extraction;

/**
 * Sitemap Integration class
 */
class Sitemap_Integration {
	/**
	 * The loader instance
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Constructor
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( Loader $loader ) {
		$this->loader = $loader;

		$this->loader->add_filter( 'prc_sitemap_supported_post_types', $this, 'add_to_sitemap' );
		$this->loader->add_filter( 'prc_sitemap_entry', $this, 'add_alternate_formats', 10, 2 );
	}

	/**
	 * Add topline to supported post types
	 *
	 * @param array $post_types Supported post types.
	 * @return array Modified post types.
	 */
	public function add_to_sitemap( $post_types ) {
		$post_types[] = Content_Type::get_post_type();
		return $post_types;
	}

	/**
	 * Add alternate format URLs to sitemap entries
	 *
	 * Adds <xhtml:link rel="alternate"> tags for text, markdown, and topline formats.
	 *
	 * @param array $url_data Sitemap URL entry data.
	 * @param int   $post_id Post ID.
	 * @return array Modified URL entry.
	 */
	public function add_alternate_formats( $url_data, $post_id ) {
		// Only add alternates for topline posts
		if ( 'topline' !== get_post_type( $post_id ) ) {
			return $url_data;
		}

		// Get the parent post ID to build alternate URLs
		$post = get_post( $post_id );
		if ( ! $post || ! $post->post_parent ) {
			return $url_data;
		}

		$parent_id = $post->post_parent;

		// Initialize alternates array if not exists
		if ( ! isset( $url_data['alternates'] ) ) {
			$url_data['alternates'] = array();
		}

		// Add alternate format URLs
		$formats = array(
			'text'     => 'text/plain',
			'markdown' => 'text/markdown',
			'topline'  => 'text/markdown',
		);

		foreach ( $formats as $format => $mime_type ) {
			$url_data['alternates'][] = array(
				'href' => home_url( "/topline/{$parent_id}/{$format}" ),
				'type' => $mime_type,
				'rel'  => 'alternate',
			);
		}

		return $url_data;
	}
}
