<?php

namespace WeLabs\WpErpAppHelper\Notes;

use WP_Error;

/**
 * NotesRepository — per-user notes with label and attachment joins.
 */
class NotesRepository {

    const TITLE_MAX            = 200;
    const LABELS_PER_NOTE_LIMIT = 20;
    const EXPORT_CAP            = 1000;

    /** @var LabelsRepository */
    private $labels;

    public function __construct( LabelsRepository $labels = null ) {
        $this->labels = $labels ?: new LabelsRepository();
    }

    /**
     * Create a new note.
     *
     * @return array|WP_Error
     */
    public function create( $user_id, array $data ) {
        global $wpdb;

        $title = isset( $data['title'] ) ? trim( (string) $data['title'] ) : '';
        if ( $title === '' || mb_strlen( $title ) > self::TITLE_MAX ) {
            return new WP_Error(
                'rest_invalid_param',
                sprintf( __( 'Title must be 1–%d characters.', 'wp-erp-app-helper' ), self::TITLE_MAX ),
                [ 'status' => 400 ]
            );
        }

        $content = isset( $data['content'] ) ? wp_kses_post( (string) $data['content'] ) : '';

        $now = current_time( 'mysql', true );
        $ok  = $wpdb->insert(
            Schema::notes_table(),
            [
                'user_id'    => $user_id,
                'title'      => $title,
                'content'    => $content,
                'is_pinned'  => 0,
                'is_archived' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%d', '%s', '%s', '%d', '%d', '%s', '%s' ]
        );

        if ( ! $ok ) {
            return new WP_Error( 'rest_note_create_failed', __( 'Failed to create note.', 'wp-erp-app-helper' ), [ 'status' => 500 ] );
        }

        $note_id = (int) $wpdb->insert_id;

        if ( ! empty( $data['label_ids'] ) ) {
            $res = $this->set_labels( $note_id, $user_id, (array) $data['label_ids'] );
            if ( is_wp_error( $res ) ) {
                $this->delete( $user_id, $note_id );
                return $res;
            }
        }

        if ( ! empty( $data['attachment_ids'] ) ) {
            $res = $this->set_attachments( $note_id, $user_id, (array) $data['attachment_ids'] );
            if ( is_wp_error( $res ) ) {
                $this->delete( $user_id, $note_id );
                return $res;
            }
        }

        return $this->find( $user_id, $note_id );
    }

    /**
     * @return array|null
     */
    public function find( $user_id, $id ) {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM `' . Schema::notes_table() . '` WHERE id = %d AND user_id = %d',
                $id,
                $user_id
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Update title / content / labels / attachments.
     *
     * @return array|WP_Error
     */
    public function update( $user_id, $id, array $data ) {
        global $wpdb;

        $current = $this->find( $user_id, $id );
        if ( ! $current ) {
            return new WP_Error( 'rest_note_not_found', __( 'Note not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $update = [];
        $format = [];

        if ( array_key_exists( 'title', $data ) ) {
            $title = trim( (string) $data['title'] );
            if ( $title === '' || mb_strlen( $title ) > self::TITLE_MAX ) {
                return new WP_Error(
                    'rest_invalid_param',
                    sprintf( __( 'Title must be 1–%d characters.', 'wp-erp-app-helper' ), self::TITLE_MAX ),
                    [ 'status' => 400 ]
                );
            }
            $update['title'] = $title;
            $format[]        = '%s';
        }

        if ( array_key_exists( 'content', $data ) ) {
            $update['content'] = wp_kses_post( (string) $data['content'] );
            $format[]          = '%s';
        }

        if ( ! empty( $update ) ) {
            $update['updated_at'] = current_time( 'mysql', true );
            $format[]             = '%s';

            $wpdb->update(
                Schema::notes_table(),
                $update,
                [ 'id' => $id, 'user_id' => $user_id ],
                $format,
                [ '%d', '%d' ]
            );
        }

        if ( array_key_exists( 'label_ids', $data ) ) {
            $res = $this->set_labels( $id, $user_id, (array) $data['label_ids'] );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
        }

        if ( array_key_exists( 'attachment_ids', $data ) ) {
            $res = $this->set_attachments( $id, $user_id, (array) $data['attachment_ids'] );
            if ( is_wp_error( $res ) ) {
                return $res;
            }
        }

        return $this->find( $user_id, $id );
    }

    /**
     * Delete note + clear join rows.
     *
     * @return array|WP_Error previous note
     */
    public function delete( $user_id, $id ) {
        global $wpdb;

        $previous = $this->find( $user_id, $id );
        if ( ! $previous ) {
            return new WP_Error( 'rest_note_not_found', __( 'Note not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $wpdb->delete( Schema::relationships_table(), [ 'note_id' => $id ], [ '%d' ] );
        $wpdb->delete( Schema::attachments_table(), [ 'note_id' => $id ], [ '%d' ] );
        $wpdb->delete( Schema::notes_table(), [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] );

        return $previous;
    }

    /**
     * Set pinned flag.
     *
     * @return array|WP_Error
     */
    public function set_pinned( $user_id, $id, $pinned ) {
        return $this->set_flag( $user_id, $id, 'is_pinned', $pinned );
    }

    /**
     * Set archived flag.
     *
     * @return array|WP_Error
     */
    public function set_archived( $user_id, $id, $archived ) {
        return $this->set_flag( $user_id, $id, 'is_archived', $archived );
    }

    private function set_flag( $user_id, $id, $column, $value ) {
        global $wpdb;

        $current = $this->find( $user_id, $id );
        if ( ! $current ) {
            return new WP_Error( 'rest_note_not_found', __( 'Note not found.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $wpdb->update(
            Schema::notes_table(),
            [
                $column      => $value ? 1 : 0,
                'updated_at' => current_time( 'mysql', true ),
            ],
            [ 'id' => $id, 'user_id' => $user_id ],
            [ '%d', '%s' ],
            [ '%d', '%d' ]
        );

        return $this->find( $user_id, $id );
    }

    /**
     * Replace the label set on a note.
     *
     * @return true|WP_Error
     */
    public function set_labels( $note_id, $user_id, array $label_ids ) {
        global $wpdb;

        $label_ids = array_values( array_unique( array_map( 'intval', $label_ids ) ) );
        $label_ids = array_values( array_filter( $label_ids, function ( $i ) {
            return $i > 0;
        } ) );

        if ( count( $label_ids ) > self::LABELS_PER_NOTE_LIMIT ) {
            return new WP_Error(
                'rest_invalid_param',
                sprintf( __( 'A note can have at most %d labels.', 'wp-erp-app-helper' ), self::LABELS_PER_NOTE_LIMIT ),
                [ 'status' => 400 ]
            );
        }

        if ( ! empty( $label_ids ) ) {
            $found = $this->labels->find_many( $user_id, $label_ids );
            if ( count( $found ) !== count( $label_ids ) ) {
                return new WP_Error(
                    'rest_invalid_label',
                    __( 'One or more label IDs are invalid or do not belong to you.', 'wp-erp-app-helper' ),
                    [ 'status' => 400 ]
                );
            }
        }

        $wpdb->delete( Schema::relationships_table(), [ 'note_id' => $note_id ], [ '%d' ] );

        foreach ( $label_ids as $label_id ) {
            $wpdb->insert(
                Schema::relationships_table(),
                [ 'note_id' => $note_id, 'label_id' => $label_id ],
                [ '%d', '%d' ]
            );
        }

        return true;
    }

    /**
     * @return array<int,array> resolved label rows for the note
     */
    public function get_labels( $note_id ) {
        global $wpdb;

        $sql  = 'SELECT l.* FROM `' . Schema::labels_table() . '` l '
              . 'INNER JOIN `' . Schema::relationships_table() . '` r ON r.label_id = l.id '
              . 'WHERE r.note_id = %d ORDER BY l.name ASC';
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $note_id ), ARRAY_A );

        return $rows ?: [];
    }

    /**
     * Replace the attachment set on a note.
     *
     * @return true|WP_Error
     */
    public function set_attachments( $note_id, $user_id, array $attachment_ids ) {
        global $wpdb;

        $attachment_ids = array_values( array_unique( array_map( 'intval', $attachment_ids ) ) );
        $attachment_ids = array_values( array_filter( $attachment_ids, function ( $i ) {
            return $i > 0;
        } ) );

        foreach ( $attachment_ids as $att_id ) {
            $post = get_post( $att_id );
            if ( ! $post || $post->post_type !== 'attachment' ) {
                return new WP_Error(
                    'rest_invalid_attachment',
                    __( 'One or more attachments do not exist.', 'wp-erp-app-helper' ),
                    [ 'status' => 400 ]
                );
            }
            if ( (int) $post->post_author !== (int) $user_id ) {
                return new WP_Error(
                    'rest_invalid_attachment',
                    __( 'One or more attachments do not belong to you.', 'wp-erp-app-helper' ),
                    [ 'status' => 400 ]
                );
            }
        }

        $wpdb->delete( Schema::attachments_table(), [ 'note_id' => $note_id ], [ '%d' ] );

        $now = current_time( 'mysql', true );
        foreach ( $attachment_ids as $att_id ) {
            $wpdb->insert(
                Schema::attachments_table(),
                [
                    'note_id'       => $note_id,
                    'attachment_id' => $att_id,
                    'created_at'    => $now,
                ],
                [ '%d', '%d', '%s' ]
            );
        }

        return true;
    }

    /**
     * @return array<int,array> resolved attachment objects (deleted media omitted)
     */
    public function get_attachments( $note_id ) {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT attachment_id FROM `' . Schema::attachments_table() . '` WHERE note_id = %d',
                $note_id
            ),
            ARRAY_A
        );

        $out = [];
        foreach ( (array) $rows as $r ) {
            $att_id = (int) $r['attachment_id'];
            $post   = get_post( $att_id );
            if ( ! $post || $post->post_type !== 'attachment' ) {
                continue;
            }
            $out[] = [
                'id'        => $att_id,
                'url'       => wp_get_attachment_url( $att_id ),
                'mime_type' => get_post_mime_type( $att_id ),
                'filename'  => wp_basename( get_attached_file( $att_id ) ),
            ];
        }

        return $out;
    }

    /**
     * List notes with filters.
     *
     * @return array{0: array<int,array>, 1: int}
     */
    public function query( $user_id, array $args = [] ) {
        global $wpdb;

        $page     = max( 1, (int) ( $args['page'] ?? 1 ) );
        $per_page = max( 1, min( 100, (int) ( $args['per_page'] ?? 20 ) ) );
        $offset   = ( $page - 1 ) * $per_page;

        // Filter parsing.
        $label_ids = [];
        if ( ! empty( $args['label'] ) ) {
            $label_ids = is_array( $args['label'] ) ? $args['label'] : [ $args['label'] ];
            $label_ids = array_values( array_unique( array_map( 'intval', $label_ids ) ) );
            $label_ids = array_values( array_filter( $label_ids, function ( $i ) {
                return $i > 0;
            } ) );
        }

        $date_from = isset( $args['date_from'] ) ? (string) $args['date_from'] : '';
        $date_to   = isset( $args['date_to'] ) ? (string) $args['date_to'] : '';
        $search    = isset( $args['search'] ) ? (string) $args['search'] : '';

        $pinned   = isset( $args['pinned'] ) ? (bool) $args['pinned'] : null;
        $archived = isset( $args['archived'] ) ? (bool) $args['archived'] : false;

        $where  = [ 'n.user_id = %d' ];
        $params = [ $user_id ];

        $where[]  = 'n.is_archived = %d';
        $params[] = $archived ? 1 : 0;

        if ( $pinned !== null ) {
            $where[]  = 'n.is_pinned = %d';
            $params[] = $pinned ? 1 : 0;
        }

        if ( $date_from !== '' ) {
            $where[]  = 'n.created_at >= %s';
            $params[] = $date_from . ' 00:00:00';
        }
        if ( $date_to !== '' ) {
            $where[]  = 'n.created_at <= %s';
            $params[] = $date_to . ' 23:59:59';
        }

        if ( $search !== '' ) {
            $where[]  = 'n.title LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $join     = '';
        $group_by = '';
        $having   = '';

        if ( ! empty( $label_ids ) ) {
            $placeholders = implode( ',', array_fill( 0, count( $label_ids ), '%d' ) );
            $join         = 'INNER JOIN `' . Schema::relationships_table() . '` r ON r.note_id = n.id';
            $where[]      = 'r.label_id IN (' . $placeholders . ')';
            $params       = array_merge( $params, $label_ids );
            $group_by     = 'GROUP BY n.id';
            $having       = 'HAVING COUNT(DISTINCT r.label_id) = ' . count( $label_ids );
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        // Count.
        if ( ! empty( $label_ids ) ) {
            $count_sql = 'SELECT COUNT(*) FROM (SELECT n.id FROM `' . Schema::notes_table() . "` n $join $where_sql $group_by $having) AS t";
        } else {
            $count_sql = 'SELECT COUNT(*) FROM `' . Schema::notes_table() . "` n $where_sql";
        }
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

        // Page.
        $sql = 'SELECT n.* FROM `' . Schema::notes_table() . "` n $join $where_sql $group_by $having "
             . 'ORDER BY n.is_pinned DESC, n.created_at DESC LIMIT %d OFFSET %d';

        $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, [ $per_page, $offset ] ) ), ARRAY_A );

        return [ $rows ?: [], $total ];
    }

    /**
     * Format a note row + joins for REST output.
     */
    public function present( array $row ) {
        $note_id     = (int) $row['id'];
        $labels      = array_map( [ LabelsRepository::class, 'present' ], $this->get_labels( $note_id ) );
        $attachments = $this->get_attachments( $note_id );

        return [
            'id'          => $note_id,
            'title'       => (string) $row['title'],
            'content'     => isset( $row['content'] ) ? (string) $row['content'] : '',
            'is_pinned'   => (bool) $row['is_pinned'],
            'is_archived' => (bool) $row['is_archived'],
            'labels'      => $labels,
            'attachments' => $attachments,
            'created_at'  => mysql_to_rfc3339( $row['created_at'] ),
            'updated_at'  => mysql_to_rfc3339( $row['updated_at'] ),
        ];
    }
}
