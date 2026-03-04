<?php
/**
 * Admin functionality for Toplines
 *
 * @package PRC_PDF_Extraction
 */

namespace PRC\Platform\PDF_Extraction;

/**
 * Admin class - handles admin-specific functionality
 */
class Admin {
	/**
	 * Loader instance
	 *
	 * @var Loader
	 */
	private $loader;

	/**
	 * Constructor
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$this->loader = $loader;
		$this->register_hooks();
	}

	/**
	 * Register hooks
	 */
	private function register_hooks() {
		$this->loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_block_editor_assets' );
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_editor_assets() {
		global $post;

		if ( ! $post || Content_Type::get_post_type() !== $post->post_type ) {
			return;
		}

		$asset_file = PRC_PDF_EXTRACTION_DIR . '/build/parent-post-panel.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			// Fallback if not built yet - use inline script
			$this->enqueue_inline_fallback();
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'prc-parent-article-panel',
			PRC_TOPLINES_URL . 'build/parent-post-panel.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'prc-parent-article-panel',
			false,
			array(),
			$asset['version']
		);

		// Add inline styles
		wp_add_inline_style(
			'wp-edit-post',
			'.prc-parent-article-panel .components-panel__body-title {
				border-left: 4px solid #007cba;
			}'
		);
	}

	/**
	 * Fallback inline script if build files don't exist
	 */
	private function enqueue_inline_fallback() {
		global $post;

		$parent_id = $post->post_parent;
		if ( ! $parent_id ) {
			return;
		}

		$parent = get_post( $parent_id );
		if ( ! $parent ) {
			return;
		}

		$edit_link       = get_edit_post_link( $parent_id, 'raw' );
		$view_link       = get_permalink( $parent_id );
		$post_type_label = get_post_type_object( $parent->post_type )->labels->singular_name ?? $parent->post_type;

		$parent_data = array(
			'id'        => $parent_id,
			'title'     => $parent->post_title,
			'editLink'  => $edit_link,
			'viewLink'  => $view_link,
			'postType'  => $post_type_label,
		);

		wp_add_inline_script(
			'wp-edit-post',
			'window.prcParentArticle = ' . wp_json_encode( $parent_data ) . ';',
			'before'
		);

		wp_add_inline_style(
			'wp-edit-post',
			'.prc-parent-article-display {
				background: #f0f0f1;
				padding: 12px;
				border-radius: 4px;
				margin-bottom: 16px;
			}
			.prc-parent-article-display__title {
				font-weight: 600;
				margin-bottom: 4px;
			}
			.prc-parent-article-display__meta {
				color: #646970;
				font-size: 12px;
				margin-bottom: 8px;
			}
			.prc-parent-article-display__links a {
				margin-right: 12px;
			}'
		);
	}
}
