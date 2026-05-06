<?php

namespace WeLabs\WpErpAppHelper\Notes;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * NotesController — per-user notes under wp-erp-app-helper/v1/notes
 */
class NotesController {

    protected $namespace = 'erp-app/v1';
    protected $rest_base = 'notes';

    /** @var NotesRepository */
    private $repo;

    public function __construct( NotesRepository $repo = null ) {
        $this->repo = $repo ?: new NotesRepository();
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
                    'args'                => $this->item_args(),
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/export',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [ $this, 'export' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
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
                    'args'                => $this->item_args(),
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [ $this, 'delete_item' ],
                    'permission_callback' => [ $this, 'permission_callback' ],
                ],
            ]
        );

        foreach ( [ 'pin', 'unpin', 'archive', 'unarchive' ] as $action ) {
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>\d+)/' . $action,
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [ $this, $action . '_item' ],
                        'permission_callback' => [ $this, 'permission_callback' ],
                    ],
                ]
            );
        }
    }

    public function permission_callback() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'Authentication required.', 'wp-erp-app-helper' ), [ 'status' => 401 ] );
        }
        return true;
    }

    private function item_args() {
        return [
            'title'          => [
                'type'     => 'string',
                'required' => false,
            ],
            'content'        => [
                'type'     => 'string',
                'required' => false,
            ],
            'label_ids'      => [
                'type'     => 'array',
                'required' => false,
                'items'    => [ 'type' => 'integer' ],
            ],
            'attachment_ids' => [
                'type'     => 'array',
                'required' => false,
                'items'    => [ 'type' => 'integer' ],
            ],
        ];
    }

    public function list_items( WP_REST_Request $request ) {
        $user_id = get_current_user_id();

        $args = $this->parse_filters( $request );

        list( $rows, $total ) = $this->repo->query( $user_id, $args );

        $items    = array_map( [ $this->repo, 'present' ], $rows );
        $per_page = $args['per_page'];

        $response = new WP_REST_Response( $items );
        $response->header( 'X-WP-Total', (string) $total );
        $response->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per_page ) ) );

        return $response;
    }

    public function get_item( WP_REST_Request $request ) {
        $row = $this->repo->find( get_current_user_id(), (int) $request['id'] );
        if ( ! $row ) {
            return new WP_Error( 'rest_note_not_found', __( 'Note not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }
        return new WP_REST_Response( $this->repo->present( $row ), 200 );
    }

    public function create_item( WP_REST_Request $request ) {
        $result = $this->repo->create(
            get_current_user_id(),
            [
                'title'          => $request->get_param( 'title' ),
                'content'        => $request->get_param( 'content' ),
                'label_ids'      => $request->get_param( 'label_ids' ),
                'attachment_ids' => $request->get_param( 'attachment_ids' ),
            ]
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $this->repo->present( $result ), 201 );
    }

    public function update_item( WP_REST_Request $request ) {
        $params  = $request->get_params();
        $allowed = array_intersect_key( $params, array_flip( [ 'title', 'content', 'label_ids', 'attachment_ids' ] ) );

        $result = $this->repo->update( get_current_user_id(), (int) $request['id'], $allowed );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response( $this->repo->present( $result ), 200 );
    }

    public function delete_item( WP_REST_Request $request ) {
        $result = $this->repo->delete( get_current_user_id(), (int) $request['id'] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return new WP_REST_Response(
            [
                'deleted'  => true,
                'previous' => $this->repo->present( $result ),
            ],
            200
        );
    }

    public function pin_item( WP_REST_Request $request ) {
        return $this->flag_response( $this->repo->set_pinned( get_current_user_id(), (int) $request['id'], true ) );
    }

    public function unpin_item( WP_REST_Request $request ) {
        return $this->flag_response( $this->repo->set_pinned( get_current_user_id(), (int) $request['id'], false ) );
    }

    public function archive_item( WP_REST_Request $request ) {
        return $this->flag_response( $this->repo->set_archived( get_current_user_id(), (int) $request['id'], true ) );
    }

    public function unarchive_item( WP_REST_Request $request ) {
        return $this->flag_response( $this->repo->set_archived( get_current_user_id(), (int) $request['id'], false ) );
    }

    private function flag_response( $result ) {
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return new WP_REST_Response( $this->repo->present( $result ), 200 );
    }

    private function parse_filters( WP_REST_Request $request ) {
        $page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
        $per_page = (int) $request->get_param( 'per_page' );
        $per_page = $per_page > 0 ? min( 100, $per_page ) : 20;

        $pinned   = $request->get_param( 'pinned' );
        $archived = $request->get_param( 'archived' );

        return [
            'label'     => $request->get_param( 'label' ),
            'date_from' => (string) $request->get_param( 'date_from' ),
            'date_to'   => (string) $request->get_param( 'date_to' ),
            'pinned'    => $pinned !== null && $pinned !== '' ? rest_sanitize_boolean( $pinned ) : null,
            'archived'  => $archived !== null && $archived !== '' ? rest_sanitize_boolean( $archived ) : false,
            'search'    => (string) $request->get_param( 'search' ),
            'page'      => $page,
            'per_page'  => $per_page,
        ];
    }

    /**
     * GET /notes/export?format=json|csv
     */
    public function export( WP_REST_Request $request ) {
        $user_id = get_current_user_id();
        $format  = strtolower( (string) $request->get_param( 'format' ) ) ?: 'json';

        if ( ! in_array( $format, [ 'json', 'csv' ], true ) ) {
            return new WP_Error( 'rest_invalid_param', __( 'format must be json or csv.', 'wp-erp-app-helper' ), [ 'status' => 400 ] );
        }

        $args             = $this->parse_filters( $request );
        $args['per_page'] = NotesRepository::EXPORT_CAP;
        $args['page']     = 1;

        list( $rows, $total ) = $this->repo->query( $user_id, $args );
        $items                = array_map( [ $this->repo, 'present' ], $rows );

        $filename = 'notes-' . gmdate( 'Y-m-d' ) . '.' . $format;
        $headers  = [
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-WP-Total'          => (string) $total,
        ];
        if ( $total > NotesRepository::EXPORT_CAP ) {
            $headers['Warning'] = '199 - "Export capped at ' . NotesRepository::EXPORT_CAP . ' notes; refine filters for the rest."';
        }

        if ( $format === 'json' ) {
            $body = wp_json_encode( $items );
            return $this->binary_response( $body, 'application/json', $headers );
        }

        // CSV.
        $body = $this->to_csv( $items );
        return $this->binary_response( $body, 'text/csv; charset=utf-8', $headers );
    }

    private function binary_response( $body, $content_type, array $headers ) {
        $headers['Content-Type'] = $content_type;
        $response                = new WP_REST_Response( null, 200, $headers );
        // Stream raw body via REST server.
        add_filter( 'rest_pre_serve_request', function ( $served, $result ) use ( $body, $content_type, $headers ) {
            if ( $served ) {
                return $served;
            }
            foreach ( $headers as $k => $v ) {
                header( $k . ': ' . $v );
            }
            echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- body is JSON or CSV.
            return true;
        }, 10, 2 );

        return $response;
    }

    private function to_csv( array $items ) {
        $headers = [ 'id', 'title', 'content', 'labels', 'is_pinned', 'is_archived', 'attachments', 'created_at', 'updated_at' ];

        $fh = fopen( 'php://temp', 'w+' );
        fputcsv( $fh, $headers );

        foreach ( $items as $item ) {
            $labels = [];
            foreach ( (array) $item['labels'] as $l ) {
                $labels[] = $l['name'] . ' (' . $l['color'] . ')';
            }

            $attachments = [];
            foreach ( (array) $item['attachments'] as $a ) {
                $attachments[] = $a['url'];
            }

            $row = [
                $item['id'],
                $this->csv_safe( $item['title'] ),
                $this->csv_safe( wp_strip_all_tags( $item['content'] ) ),
                $this->csv_safe( implode( '|', $labels ) ),
                $item['is_pinned'] ? 1 : 0,
                $item['is_archived'] ? 1 : 0,
                $this->csv_safe( implode( '|', $attachments ) ),
                $item['created_at'],
                $item['updated_at'],
            ];
            fputcsv( $fh, $row );
        }

        rewind( $fh );
        $out = stream_get_contents( $fh );
        fclose( $fh );

        return $out;
    }

    /**
     * Prefix any cell starting with =, +, -, @ with a single quote (CSV
     * formula-injection guard).
     */
    private function csv_safe( $value ) {
        $s = (string) $value;
        if ( $s === '' ) {
            return $s;
        }
        $first = $s[0];
        if ( $first === '=' || $first === '+' || $first === '-' || $first === '@' ) {
            return "'" . $s;
        }
        return $s;
    }
}
