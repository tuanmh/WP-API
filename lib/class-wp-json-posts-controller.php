<?php

/**
 * Access posts
 */
class WP_JSON_Posts_Controller extends WP_JSON_Base_Posts_Controller {

	/**
	 * Get revisions for a specific post.
	 *
	 * @param WP_JSON_Request $request Full details about the request
	 * @return WP_Error|WP_HTTP_ResponseInterface
	 */
	public function get_item_revisions( $request ) {
		$request->set_query_params( array(
			'context' => 'view-revision',
		) );

		$id = (int) $request['id'];
		$parent = get_post( $id );

		if ( empty( $id ) || empty( $parent->ID ) ) {
			return new WP_Error( 'json_post_invalid_id', __( 'Invalid post ID.' ), array( 'status' => 404 ) );
		}

		if ( ! $this->check_edit_permission( $parent ) ) {
			return new WP_Error( 'json_cannot_view', __( 'Sorry, you cannot view the revisions for this post.' ), array( 'status' => 403 ) );
		}

		// Todo: Query args filter for wp_get_post_revisions
		$revisions = wp_get_post_revisions( $id );

		$struct = array();
		foreach ( $revisions as $revision ) {
			$struct[] = $this->prepare_item_for_response( $revision, $request );
		}

		$response = json_ensure_response( $struct );
		$response->link_header( 'alternate',  get_permalink( $id ), array( 'type' => 'text/html' ) );

		return $response;
	}

	/**
	 * Prepare a single post output for response
	 *
	 * @param WP_Post $post Post object
	 * @param WP_JSON_Request $request Request object
	 * @return array $data
	 */
	public function prepare_item_for_response( $post, $request ) {
		$data = array(
			'id'             => $post->ID,
			'title'          => array(
				'rendered'       => get_the_title( $post->ID ),
			),
			'content'        => array(
				'rendered'       => apply_filters( 'the_content', $post->post_content ),
			),
			'excerpt'        => array(
				'rendered'       => $this->prepare_excerpt_response( $post->post_excerpt ),
			),
			'type'           => $post->post_type,
			'format'         => get_post_format( $post->ID ),
			'parent'         => (int) $post->post_parent,
			'slug'           => $post->post_name,
			'link'           => get_permalink( $post->ID ),
			'guid'           => array(
				'rendered'       => apply_filters( 'get_the_guid', $post->guid ),
			),
			'author'         => (int) $post->post_author,
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'sticky'         => ( 'post' === $post->post_type && is_sticky( $post->ID ) ),
			'menu_order'     => (int) $post->menu_order,
			'date'           => $this->prepare_date_response( $post->post_date ),
			'modified'       => $this->prepare_date_response( $post->post_modified ),

		);

		if ( ( 'view' === $request['context'] || 'view-revision' === $request['context'] ) && 0 !== $post->post_parent ) {
			/**
			 * Avoid nesting too deeply.
			 *
			 * This gives post + post-extended + meta for the main post,
			 * post + meta for the parent and just meta for the grandparent
			 */
			$parent = get_post( $post->post_parent );
			$data['parent'] = $this->prepare_item_for_response( $parent, array(
				'context' => 'embed',
			) );
		}

		if ( 'edit' === $request['context'] ) {

			$data_raw = array(
				'title'        => array(
					'raw'          => $post->post_title,
				),
				'content'      => array(
					'raw'          => $post->post_content,
				),
				'excerpt'      => array(
					'raw'          => $post->post_excerpt,
				),
				'guid'         => array(
					'raw'          => $post->guid,
				),
				'status'       => $post->post_status,
				'password'     => $this->prepare_password_response( $post->post_password ),
				'date_gmt'     => $this->prepare_date_response( $post->post_date_gmt ),
				'modified_gmt' => $this->prepare_date_response( $post->post_modified_gmt ),
			);

			$data = array_merge_recursive( $data, $data_raw );

			// Consider future posts as published
			if ( 'future' == $data['status'] ) {
				$data['status'] = 'publish';
			}
		}

		// Fill in blank post format
		if ( empty( $data['format'] ) ) {
			$data['format'] = 'standard';
		}

		if ( 0 == $data['parent'] ) {
			$data['parent'] = null;
		}

		/**
		 * @TODO: reconnect the json_prepare_post() filter after all related
		 * routes are finished converting to new structure.
		 *
		 * return apply_filters( 'json_prepare_post', $data, $post, $request );
		 */

		return $data;
	}

}
