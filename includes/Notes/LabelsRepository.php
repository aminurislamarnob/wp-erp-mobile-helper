<?php

namespace WeLabs\WpErpAppHelper\Notes;

use WP_Error;

/**
 * LabelsRepository — per-user label library.
 *
 * Every public method takes $user_id and adds it to the WHERE clause; the
 * controller layer is the only thing that decides which user is "current".
 */
class LabelsRepository {

    const NAME_MAX        = 50;
    const DESCRIPTION_MAX = 200;
    const PER_USER_LIMIT  = 100;

    /**
     * Create a new label for $user_id.
     *
     * @return array|WP_Error label row on success
     */
    public function create( $user_id, array $data ) {
        global $wpdb;

        $name        = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
        $color       = isset( $data['color'] ) ? (string) $data['color'] : '';
        $description = isset( $data['description'] ) ? trim( (string) $data['description'] ) : '';

        $valid = $this->validate( $name, $color, $description );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }
        $color = $valid['color'];

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM `' . Schema::labels_table() . '` WHERE user_id = %d',
                $user_id
            )
        );
        if ( $count >= self::PER_USER_LIMIT ) {
            return new WP_Error(
                'rest_label_limit_reached',
                sprintf( __( 'You have reached the maximum of %d labels.', 'wp-erp-app-helper' ), self::PER_USER_LIMIT ),
                [ 'status' => 400 ]
            );
        }

        $existing = $this->find_by_name( $user_id, $name );
        if ( $existing ) {
            return new WP_Error(
                'rest_label_name_conflict',
                __( 'A label with this name already exists.', 'wp-erp-app-helper' ),
                [
                    'status'           => 409,
                    'conflicting_id'   => (int) $existing['id'],
                    'conflicting_name' => $existing['name'],
                ]
            );
        }

        $now    = current_time( 'mysql', true );
        $result = $wpdb->insert(
            Schema::labels_table(),
            [
                'user_id'     => $user_id,
                'name'        => $name,
                'color'       => $color,
                'description' => $description,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s' ]
        );

        if ( ! $result ) {
            return new WP_Error( 'rest_label_create_failed', __( 'Failed to create label.', 'wp-erp-app-helper' ), [ 'status' => 500 ] );
        }

        return $this->find( $user_id, (int) $wpdb->insert_id );
    }

    /**
     * Update label fields (name, color, description). Partial update — only
     * provided keys are applied.
     *
     * @return array|WP_Error
     */
    public function update( $user_id, $id, array $data ) {
        global $wpdb;

        $current = $this->find( $user_id, $id );
        if ( ! $current ) {
            return new WP_Error( 'rest_label_not_found', __( 'Label not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $name        = array_key_exists( 'name', $data ) ? trim( (string) $data['name'] ) : $current['name'];
        $color       = array_key_exists( 'color', $data ) ? (string) $data['color'] : $current['color'];
        $description = array_key_exists( 'description', $data ) ? trim( (string) $data['description'] ) : (string) $current['description'];

        $valid = $this->validate( $name, $color, $description );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }
        $color = $valid['color'];

        if ( strcasecmp( $name, $current['name'] ) !== 0 ) {
            $existing = $this->find_by_name( $user_id, $name );
            if ( $existing && (int) $existing['id'] !== (int) $id ) {
                return new WP_Error(
                    'rest_label_name_conflict',
                    __( 'A label with this name already exists.', 'wp-erp-app-helper' ),
                    [
                        'status'           => 409,
                        'conflicting_id'   => (int) $existing['id'],
                        'conflicting_name' => $existing['name'],
                    ]
                );
            }
        }

        $wpdb->update(
            Schema::labels_table(),
            [
                'name'        => $name,
                'color'       => $color,
                'description' => $description,
                'updated_at'  => current_time( 'mysql', true ),
            ],
            [ 'id' => $id, 'user_id' => $user_id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d', '%d' ]
        );

        return $this->find( $user_id, $id );
    }

    /**
     * Delete label and detach it from every linked note.
     *
     * @return array|WP_Error previous label
     */
    public function delete( $user_id, $id ) {
        global $wpdb;

        $previous = $this->find( $user_id, $id );
        if ( ! $previous ) {
            return new WP_Error( 'rest_label_not_found', __( 'Label not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $wpdb->delete( Schema::relationships_table(), [ 'label_id' => $id ], [ '%d' ] );
        $wpdb->delete( Schema::labels_table(), [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] );

        return $previous;
    }

    /**
     * Find one label by id, scoped to user.
     *
     * @return array|null
     */
    public function find( $user_id, $id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `' . Schema::labels_table() . '` WHERE id = %d AND user_id = %d',
                $id,
                $user_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Find labels by ids, scoped to user. Preserves DB order.
     *
     * @return array
     */
    public function find_many( $user_id, array $ids ) {
        global $wpdb;

        $ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
        $ids = array_filter( $ids, function ( $i ) {
            return $i > 0;
        } );
        if ( empty( $ids ) ) {
            return [];
        }

        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql          = 'SELECT * FROM `' . Schema::labels_table() . '` WHERE user_id = %d AND id IN (' . $placeholders . ')';
        $args         = array_merge( [ $user_id ], $ids );

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

        return $rows ?: [];
    }

    /**
     * List labels for a user.
     *
     * @return array{0: array<int,array>, 1: int}
     */
    public function query( $user_id, array $args = [] ) {
        global $wpdb;

        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 50 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        $search = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';

        $where  = 'WHERE user_id = %d';
        $params = [ $user_id ];

        if ( $search !== '' ) {
            $where   .= ' AND name LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM `' . Schema::labels_table() . "` $where", $params ) );

        $sql  = 'SELECT * FROM `' . Schema::labels_table() . "` $where ORDER BY name ASC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );

        return [ $rows ?: [], $total ];
    }

    /**
     * @return array|null
     */
    private function find_by_name( $user_id, $name ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `' . Schema::labels_table() . '` WHERE user_id = %d AND LOWER(name) = LOWER(%s) LIMIT 1',
                $user_id,
                $name
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Shared validator for create + update. Normalizes color to lowercase hex.
     *
     * @return array{name:string,color:string,description:string}|WP_Error
     */
    private function validate( $name, $color, $description ) {
        if ( $name === '' || mb_strlen( $name ) > self::NAME_MAX ) {
            return new WP_Error(
                'rest_invalid_param',
                sprintf( __( 'Label name must be 1–%d characters.', 'wp-erp-app-helper' ), self::NAME_MAX ),
                [ 'status' => 400 ]
            );
        }

        if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Color must be a 6-character hex string like #aabbcc.', 'wp-erp-app-helper' ),
                [ 'status' => 400 ]
            );
        }

        if ( mb_strlen( $description ) > self::DESCRIPTION_MAX ) {
            return new WP_Error(
                'rest_invalid_param',
                sprintf( __( 'Description must be ≤ %d characters.', 'wp-erp-app-helper' ), self::DESCRIPTION_MAX ),
                [ 'status' => 400 ]
            );
        }

        return [
            'name'        => $name,
            'color'       => strtolower( $color ),
            'description' => $description,
        ];
    }

    /**
     * Format a row for REST output.
     */
    public static function present( array $row ) {
        return [
            'id'          => (int) $row['id'],
            'name'        => (string) $row['name'],
            'color'       => (string) $row['color'],
            'description' => isset( $row['description'] ) ? (string) $row['description'] : '',
            'created_at'  => mysql_to_rfc3339( $row['created_at'] ),
            'updated_at'  => mysql_to_rfc3339( $row['updated_at'] ),
        ];
    }
}
