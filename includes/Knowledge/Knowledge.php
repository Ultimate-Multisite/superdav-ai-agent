<?php

declare(strict_types=1);
/**
 * Knowledge manager — facade for the knowledge base system.
 *
 * Orchestrates indexing, search, and context retrieval.
 *
 * @package GratisAiAgent
 * @license GPL-2.0-or-later
 */

namespace GratisAiAgent\Knowledge;

use GratisAiAgent\Models\Chunker;
use GratisAiAgent\Models\DocumentParser;
use WP_Error;

class Knowledge {

	/**
	 * Index a WordPress post into a collection.
	 *
	 * @param int $post_id       The post ID to index.
	 * @param int $collection_id The target collection ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function index_post( int $post_id, int $collection_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return new WP_Error( 'invalid_post', __( 'Post not found or not published.', 'gratis-ai-agent' ) );
		}

		// Build text content: title + plain-text body.
		$content = $post->post_title . "\n\n" . wp_strip_all_tags( $post->post_content );
		$content = trim( $content );

		if ( empty( $content ) ) {
			return new WP_Error( 'empty_content', __( 'Post has no text content to index.', 'gratis-ai-agent' ) );
		}

		// Compute hash for change detection.
		$hash = md5( $content );

		// Check for existing source.
		$existing = KnowledgeDatabase::find_source( $collection_id, 'post', $post_id );

		if ( $existing && $existing->content_hash === $hash ) {
			// Content unchanged — skip.
			return true;
		}

		// Create or update source record.
		if ( $existing ) {
			$source_id = (int) $existing->id;
			KnowledgeDatabase::delete_chunks_for_source( $source_id );
			KnowledgeDatabase::update_source(
				$source_id,
				[
					'title'        => $post->post_title,
					'content_hash' => $hash,
					'status'       => 'pending',
				]
			);
		} else {
			$source_id = KnowledgeDatabase::create_source(
				[
					'collection_id' => $collection_id,
					'source_type'   => 'post',
					'source_id'     => $post_id,
					'title'         => $post->post_title,
					'content_hash'  => $hash,
				]
			);

			if ( ! $source_id ) {
				return new WP_Error( 'db_error', __( 'Failed to create source record.', 'gratis-ai-agent' ) );
			}
		}

		// Build metadata.
		$metadata = [
			'post_type' => $post->post_type,
		];

		$categories = wp_get_post_categories( $post_id, [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
			$metadata['categories'] = $categories;
		}

		$tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
		if ( ! is_wp_error( $tags ) && ! empty( $tags ) ) {
			$metadata['tags'] = $tags;
		}

		// Chunk the content.
		$chunks = Chunker::chunk( $content );

		// Add metadata to each chunk.
		foreach ( $chunks as &$chunk ) {
			$chunk['metadata'] = $metadata;
		}
		unset( $chunk );

		// Insert chunks.
		$inserted = KnowledgeDatabase::insert_chunks( $collection_id, $source_id, $chunks );

		// Update source.
		KnowledgeDatabase::update_source(
			$source_id,
			[
				'chunk_count' => $inserted,
				'status'      => 'indexed',
			]
		);

		// Update collection.
		KnowledgeDatabase::recalculate_collection_chunk_count( $collection_id );
		KnowledgeDatabase::update_collection(
			$collection_id,
			[
				'last_indexed_at' => current_time( 'mysql', true ),
			]
		);

		return true;
	}

	/**
	 * Index a WordPress attachment into a collection.
	 *
	 * @param int $attachment_id The attachment ID.
	 * @param int $collection_id The target collection ID.
	 * @return bool|WP_Error
	 */
	public static function index_attachment( int $attachment_id, int $collection_id ) {
		$content = DocumentParser::extract_from_attachment( $attachment_id );

		if ( is_wp_error( $content ) ) {
			// Record the error in the source.
			$existing = KnowledgeDatabase::find_source( $collection_id, 'attachment', $attachment_id );
			if ( $existing ) {
				KnowledgeDatabase::update_source(
					(int) $existing->id,
					[
						'status'        => 'error',
						'error_message' => $content->get_error_message(),
					]
				);
			}
			return $content;
		}

		$hash  = md5( $content );
		$title = get_the_title( $attachment_id ) ?: basename( (string) get_attached_file( $attachment_id ) );

		// Check for existing source.
		$existing = KnowledgeDatabase::find_source( $collection_id, 'attachment', $attachment_id );

		if ( $existing && $existing->content_hash === $hash ) {
			return true;
		}

		if ( $existing ) {
			$source_id = (int) $existing->id;
			KnowledgeDatabase::delete_chunks_for_source( $source_id );
			KnowledgeDatabase::update_source(
				$source_id,
				[
					'title'        => $title,
					'content_hash' => $hash,
					'status'       => 'pending',
				]
			);
		} else {
			$source_id = KnowledgeDatabase::create_source(
				[
					'collection_id' => $collection_id,
					'source_type'   => 'attachment',
					'source_id'     => $attachment_id,
					'title'         => $title,
					'content_hash'  => $hash,
				]
			);

			if ( ! $source_id ) {
				return new WP_Error( 'db_error', __( 'Failed to create source record.', 'gratis-ai-agent' ) );
			}
		}

		$chunks   = Chunker::chunk( $content );
		$inserted = KnowledgeDatabase::insert_chunks( $collection_id, $source_id, $chunks );

		KnowledgeDatabase::update_source(
			$source_id,
			[
				'chunk_count' => $inserted,
				'status'      => 'indexed',
			]
		);

		KnowledgeDatabase::recalculate_collection_chunk_count( $collection_id );
		KnowledgeDatabase::update_collection(
			$collection_id,
			[
				'last_indexed_at' => current_time( 'mysql', true ),
			]
		);

		return true;
	}

	/**
	 * Re-index all posts matching a collection's source_config.
	 *
	 * @param int $collection_id Collection ID.
	 * @return array{indexed: int, skipped: int, errors: int}|WP_Error
	 */
	public static function reindex_collection( int $collection_id ) {
		$collection = KnowledgeDatabase::get_collection( $collection_id );

		if ( ! $collection ) {
			return new WP_Error( 'not_found', __( 'Collection not found.', 'gratis-ai-agent' ) );
		}

		$config     = $collection->source_config;
		$post_types = $config['post_types'] ?? [ 'post', 'page' ];

		$posts = get_posts(
			[
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		$stats = [
			'indexed' => 0,
			'skipped' => 0,
			'errors'  => 0,
		];

		foreach ( $posts as $post_id ) {
			$result = self::index_post( $post_id, $collection_id );

			if ( is_wp_error( $result ) ) {
				++$stats['errors'];
			} elseif ( true === $result ) {
				++$stats['indexed'];
			} else {
				++$stats['skipped'];
			}
		}

		KnowledgeDatabase::update_collection(
			$collection_id,
			[
				'last_indexed_at' => current_time( 'mysql', true ),
			]
		);

		return $stats;
	}

	/**
	 * Search the knowledge base.
	 *
	 * @param string               $query   Search query.
	 * @param array<string, mixed> $options Optional: collection_id, collection (slug), limit.
	 * @return list<array<string, mixed>> Search results.
	 */
	public static function search( string $query, array $options = [] ): array {
		$collection_id = $options['collection_id'] ?? null;
		$limit         = $options['limit'] ?? 10;

		// Resolve collection slug to ID if provided.
		if ( ! $collection_id && ! empty( $options['collection'] ) ) {
			$col = KnowledgeDatabase::get_collection_by_slug( $options['collection'] );
			if ( $col ) {
				$collection_id = (int) $col->id;
			}
		}

		$raw_results = KnowledgeDatabase::search_chunks( $query, $collection_id, $limit );

		$results = [];
		foreach ( $raw_results as $row ) {
			$source_url = $row->source_url;

			// Build URL for post sources.
			if ( 'post' === $row->source_type && $row->source_id ) {
				$source_url = get_permalink( (int) $row->source_id ) ?: $source_url;
			}

			$results[] = [
				'chunk_text'      => $row->chunk_text,
				'source_title'    => $row->source_title,
				'source_url'      => $source_url,
				'source_type'     => $row->source_type,
				'collection_name' => $row->collection_name,
				'score'           => (float) $row->relevance,
				'metadata'        => $row->metadata ? json_decode( $row->metadata, true ) : null,
			];
		}

		return $results;
	}

	/**
	 * Get formatted context for inclusion in a system prompt.
	 *
	 * @param string $query      The user's query.
	 * @param int    $max_tokens Approximate token budget for the context.
	 * @return string Formatted context string.
	 */
	public static function get_context_for_query( string $query, int $max_tokens = 2000 ): string {
		$results = self::search( $query, [ 'limit' => 10 ] );

		if ( empty( $results ) ) {
			return '';
		}

		$max_chars = $max_tokens * 4;
		$output    = '';
		$chars     = 0;

		foreach ( $results as $result ) {
			$source_label = $result['source_title'] ?? 'Unknown';
			if ( ! empty( $result['source_url'] ) ) {
				$source_label .= ' (' . $result['source_url'] . ')';
			}

			$entry = "**Source: {$source_label}**\n{$result['chunk_text']}\n\n";

			if ( $chars + strlen( $entry ) > $max_chars ) {
				break;
			}

			$output .= $entry;
			$chars  += strlen( $entry );
		}

		return trim( $output );
	}

	/**
	 * Delete a source and its chunks.
	 *
	 * @param int $source_id Source ID.
	 * @return bool
	 */
	public static function delete_source( int $source_id ): bool {
		return KnowledgeDatabase::delete_source( $source_id );
	}
}
