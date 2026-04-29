<?php

declare(strict_types=1);
/**
 * REST API controller for knowledge collections, sources, search, stats, and upload.
 *
 * @package SdAiAgent
 * @license GPL-2.0-or-later
 */

namespace SdAiAgent\REST;

use SdAiAgent\Knowledge\Knowledge;
use SdAiAgent\Knowledge\KnowledgeDatabase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages knowledge collections, sources, search, stats, and upload via REST.
 */
#[Handler(
	container: 'sd-ai-agent',
	context: Handler::CTX_REST,
	strategy: Handler::INIT_IMMEDIATELY,
)]
final class KnowledgeController {

	use PermissionTrait;

	/**
	 * Register REST routes.
	 */
	#[Action( tag: 'rest_api_init', priority: 10 )]
	public function register_routes(): void {

		// Knowledge endpoints.
		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/collections',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'handle_list_collections' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'handle_create_collection' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'name'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'slug'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_title',
						),
						'description'   => array(
							'required'          => false,
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'auto_index'    => array(
							'required' => false,
							'type'     => 'boolean',
							'default'  => false,
						),
						'source_config' => array(
							'required' => false,
							'type'     => 'object',
							'default'  => array(),
						),
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'handle_update_collection' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id'            => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'name'          => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
						'auto_index'    => array(
							'required' => false,
							'type'     => 'boolean',
						),
						'source_config' => array(
							'required' => false,
							'type'     => 'object',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'handle_delete_collection' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/sources',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_list_sources' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/collections/(?P<id>\d+)/index',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_index_collection' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_knowledge_upload' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/sources/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'handle_delete_source' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_knowledge_search' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'q'          => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'collection' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			RestController::NAMESPACE,
			'/knowledge/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_knowledge_stats' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Handle GET /knowledge/collections — list all collections.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_list_collections(): WP_REST_Response {
		$collections = KnowledgeDatabase::list_collections();

		$list = array_map(
			function ( $c ) {
				return array(
					// @phpstan-ignore-next-line
					'id'              => (int) $c->id,
					// @phpstan-ignore-next-line
					'name'            => $c->name,
					// @phpstan-ignore-next-line
					'slug'            => $c->slug,
					// @phpstan-ignore-next-line
					'description'     => $c->description,
					// @phpstan-ignore-next-line
					'auto_index'      => (bool) (int) $c->auto_index,
					// @phpstan-ignore-next-line
					'source_config'   => $c->source_config,
					// @phpstan-ignore-next-line
					'status'          => $c->status,
					// @phpstan-ignore-next-line
					'chunk_count'     => (int) $c->chunk_count,
					// @phpstan-ignore-next-line
					'last_indexed_at' => $c->last_indexed_at,
					// @phpstan-ignore-next-line
					'created_at'      => $c->created_at,
					// @phpstan-ignore-next-line
					'updated_at'      => $c->updated_at,
				);
			},
			$collections
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /knowledge/collections — create a collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_create_collection( WP_REST_Request $request ) {
		$slug = $request->get_param( 'slug' );

		// Check for duplicate slug.
		// @phpstan-ignore-next-line
		$existing = KnowledgeDatabase::get_collection_by_slug( $slug );
		if ( $existing ) {
			return new WP_Error(
				'sd_ai_agent_collection_exists',
				__( 'A collection with this slug already exists.', 'sd-ai-agent' ),
				array( 'status' => 409 )
			);
		}

		$id = KnowledgeDatabase::create_collection(
			array(
				'name'          => $request->get_param( 'name' ),
				'slug'          => $slug,
				'description'   => $request->get_param( 'description' ),
				'auto_index'    => $request->get_param( 'auto_index' ),
				'source_config' => $request->get_param( 'source_config' ),
			)
		);

		if ( ! $id ) {
			return new WP_Error(
				'sd_ai_agent_collection_create_failed',
				__( 'Failed to create collection.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		if ( ! $collection ) {
			return new WP_Error( 'sd_ai_agent_collection_not_found', __( 'Collection not found after creation.', 'sd-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'              => (int) $collection->id,
				'name'            => $collection->name,
				'slug'            => $collection->slug,
				'description'     => $collection->description,
				'auto_index'      => (bool) (int) $collection->auto_index,
				'source_config'   => $collection->source_config,
				'status'          => $collection->status,
				'chunk_count'     => 0,
				'last_indexed_at' => null,
				'created_at'      => $collection->created_at,
				'updated_at'      => $collection->updated_at,
			),
			201
		);
	}

	/**
	 * Handle PATCH /knowledge/collections/{id} — update a collection.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_update_collection( WP_REST_Request $request ) {
		$id   = self::get_int_param( $request, 'id' );
		$data = array();

		if ( $request->has_param( 'name' ) ) {
			$data['name'] = $request->get_param( 'name' );
		}
		if ( $request->has_param( 'description' ) ) {
			$data['description'] = $request->get_param( 'description' );
		}
		if ( $request->has_param( 'auto_index' ) ) {
			$data['auto_index'] = $request->get_param( 'auto_index' );
		}
		if ( $request->has_param( 'source_config' ) ) {
			$data['source_config'] = $request->get_param( 'source_config' );
		}

		$updated = KnowledgeDatabase::update_collection( $id, $data );

		if ( ! $updated ) {
			return new WP_Error(
				'sd_ai_agent_collection_update_failed',
				__( 'Failed to update collection.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		$collection = KnowledgeDatabase::get_collection( $id );

		if ( ! $collection ) {
			return new WP_Error( 'sd_ai_agent_collection_not_found', __( 'Collection not found after update.', 'sd-ai-agent' ), array( 'status' => 500 ) );
		}

		return new WP_REST_Response(
			array(
				'id'              => (int) $collection->id,
				'name'            => $collection->name,
				'slug'            => $collection->slug,
				'description'     => $collection->description,
				'auto_index'      => (bool) (int) $collection->auto_index,
				'source_config'   => $collection->source_config,
				'status'          => $collection->status,
				'chunk_count'     => (int) $collection->chunk_count,
				'last_indexed_at' => $collection->last_indexed_at,
				'created_at'      => $collection->created_at,
				'updated_at'      => $collection->updated_at,
			),
			200
		);
	}

	/**
	 * Handle DELETE /knowledge/collections/{id} — delete collection + all data.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_collection( WP_REST_Request $request ) {
		$id      = self::get_int_param( $request, 'id' );
		$deleted = KnowledgeDatabase::delete_collection( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'sd_ai_agent_collection_delete_failed',
				__( 'Failed to delete collection.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle GET /knowledge/collections/{id}/sources — list sources.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_list_sources( WP_REST_Request $request ): WP_REST_Response {
		$id      = self::get_int_param( $request, 'id' );
		$sources = KnowledgeDatabase::get_sources_for_collection( $id );

		$list = array_map(
			function ( $s ) {
				return array(
					// @phpstan-ignore-next-line
					'id'            => (int) $s->id,
					// @phpstan-ignore-next-line
					'collection_id' => (int) $s->collection_id,
					// @phpstan-ignore-next-line
					'source_type'   => $s->source_type,
					// @phpstan-ignore-next-line
					'source_id'     => $s->source_id ? (int) $s->source_id : null,
					// @phpstan-ignore-next-line
					'source_url'    => $s->source_url,
					// @phpstan-ignore-next-line
					'title'         => $s->title,
					// @phpstan-ignore-next-line
					'status'        => $s->status,
					// @phpstan-ignore-next-line
					'chunk_count'   => (int) $s->chunk_count,
					// @phpstan-ignore-next-line
					'error_message' => $s->error_message,
					// @phpstan-ignore-next-line
					'created_at'    => $s->created_at,
					// @phpstan-ignore-next-line
					'updated_at'    => $s->updated_at,
				);
			},
			$sources
		);

		return new WP_REST_Response( $list, 200 );
	}

	/**
	 * Handle POST /knowledge/collections/{id}/index — trigger indexing.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_index_collection( WP_REST_Request $request ) {
		$id     = self::get_int_param( $request, 'id' );
		$result = Knowledge::reindex_collection( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Handle POST /knowledge/upload — upload and index a document.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_knowledge_upload( WP_REST_Request $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'sd_ai_agent_no_file', __( 'No file uploaded.', 'sd-ai-agent' ), array( 'status' => 400 ) );
		}

		$collection_id = self::get_int_param( $request, 'collection_id' );

		if ( ! $collection_id ) {
			return new WP_Error( 'sd_ai_agent_no_collection', __( 'Collection ID is required.', 'sd-ai-agent' ), array( 'status' => 400 ) );
		}

		$collection = KnowledgeDatabase::get_collection( $collection_id );
		if ( ! $collection ) {
			return new WP_Error( 'sd_ai_agent_collection_not_found', __( 'Collection not found.', 'sd-ai-agent' ), array( 'status' => 404 ) );
		}

		// Use WordPress media handling to create an attachment.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Index the attachment.
		$result = Knowledge::index_attachment( $attachment_id, $collection_id );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'attachment_id' => $attachment_id,
					'status'        => 'error',
					'error'         => $result->get_error_message(),
				),
				200
			);
		}

		return new WP_REST_Response(
			array(
				'attachment_id' => $attachment_id,
				'status'        => 'indexed',
			),
			201
		);
	}

	/**
	 * Handle DELETE /knowledge/sources/{id} — delete a source.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_delete_source( WP_REST_Request $request ) {
		$id      = self::get_int_param( $request, 'id' );
		$deleted = Knowledge::delete_source( $id );

		if ( ! $deleted ) {
			return new WP_Error(
				'sd_ai_agent_source_delete_failed',
				__( 'Failed to delete source.', 'sd-ai-agent' ),
				array( 'status' => 500 )
			);
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Handle GET /knowledge/search — search chunks.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_knowledge_search( WP_REST_Request $request ): WP_REST_Response {
		$query      = $request->get_param( 'q' );
		$collection = $request->get_param( 'collection' );

		$options = array( 'limit' => 10 );
		if ( $collection ) {
			$options['collection'] = $collection;
		}

		// @phpstan-ignore-next-line
		$results = Knowledge::search( $query, $options );

		return new WP_REST_Response( $results, 200 );
	}

	/**
	 * Handle GET /knowledge/stats — get knowledge base statistics.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_knowledge_stats(): WP_REST_Response {
		$collections  = KnowledgeDatabase::list_collections();
		$total_chunks = KnowledgeDatabase::get_total_chunk_count();

		$per_collection = array();
		foreach ( $collections as $c ) {
			$per_collection[] = array(
				// @phpstan-ignore-next-line
				'id'              => (int) $c->id,
				// @phpstan-ignore-next-line
				'name'            => $c->name,
				// @phpstan-ignore-next-line
				'slug'            => $c->slug,
				// @phpstan-ignore-next-line
				'chunk_count'     => (int) $c->chunk_count,
				// @phpstan-ignore-next-line
				'last_indexed_at' => $c->last_indexed_at,
			);
		}

		return new WP_REST_Response(
			array(
				'total_collections' => count( $collections ),
				'total_chunks'      => $total_chunks,
				'collections'       => $per_collection,
			),
			200
		);
	}
}
