<?php

namespace WeLabs\WpErpAppHelper\Notes;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * LabelsController — per-user GitHub-style labels under wp-erp-app-helper/v1/labels
 */
class LabelsController {

    protected $namespace = 'erp-app/v1';
    protected $rest_base = 'labels';

    /** @var LabelsRepository */
    private $repo;

    public function __construct( LabelsRepository $repo = null ) {
        $this->repo = $repo ?: new LabelsRepository();
    }

    public function register_routes() {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'list_items' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [ $this, 'create_item' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->item_args( true ),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'get_item' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
                [
                    'methods'             => [ 'PUT', 'PATCH' ],
                    'callback'            => [ $this, 'update_item' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                    'args'                => $this->item_args( false ),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );
    }

    public function permission_callback() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'Authentication required.', 'wp-erp-app-helper' ), [ 'status' => 401 ] );
        }
        return true;
    }

    private function item_args( $require_required ) {
        return [
            'name'        => [
                'type'     => 'string',
                'required' => $require_required,
            ],
            'color'       => [
                'type'     => 'string',
                'required' => $require_required,
            ],
            'description' => [
                'type'     => 'string',
                'required' => false,
            ],
        ];
    }

    public function list_items( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        list( $rows, $total ) = $this->repo->query(
            $user_id,
            [
                'page'     => (int) $request->get_param( 'page' ) ?: 1,
                'per_page' => (int) $request->get_param( 'per_page' ) ?: 50,
                'search'   => (string) $request->get_param( 'search' ),
            ]
        );

        $items    = array_map( [ LabelsRepository::class, 'present' ], $rows );
        $page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 50 ) );

        $response = new WP_REST_Response( $items );
        $response->header( 'X-WP-Total', (string) $total );
        $response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );

        return $response;
    }

    public function get_item( WP_REST_Request $request ) {
        $row = $this->repo->find( get_current_user_id(), (int) $request['id'] );
        if ( ! $row ) {
            return new WP_Error( 'rest_label_not_found', __( 'Label not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }
        return new WP_REST_Response( LabelsRepository::present( $row ), 200 );
    }

    public function create_item( WP_REST_Request $request ) {
        $result = $this->repo->create(
            get_current_user_id(),
            [
                'name'        => $request->get_param( 'name' ),
                'color'       => $request->get_param( 'color' ),
                'description' => $request->get_param( 'description' ),
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( LabelsRepository::present( $result ), 201 );
    }

    public function update_item( WP_REST_Request $request ) {
        $params = $request->get_params();
        $allowed = array_intersect_key( $params, array_flip( [ 'name', 'color', 'description' ] ) );

        $result = $this->repo->update( get_current_user_id(), (int) $request['id'], $allowed );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( LabelsRepository::present( $result ), 200 );
    }

    public function delete_item( WP_REST_Request $request ) {
        $result = $this->repo->delete( get_current_user_id(), (int) $request['id'] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            [
                'deleted'  => true,
                'previous' => LabelsRepository::present( $result ),
            ],
            200
        );
    }
}
