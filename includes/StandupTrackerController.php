<?php

namespace WeLabs\WpErpAppHelper;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class StandupTrackerController {

    protected $namespace = 'erp-app/v1';
    protected $rest_base = 'standup';

    public function register_routes() {
        // List historical records grouped by date
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/history', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_history' ],
					'permission_callback' => [ $this, 'check_permission' ],
				],
			]
        );

        // Get employees with their shift/status for a specific date
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/employees', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_employees_for_date' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'date' => [
							'required' => true,
							'type'     => 'string',
							'format'   => 'date',
						],
					],
				],
			]
        );

        // Save records
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/save', [
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_standup' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'date' => [
							'required' => true,
							'type'     => 'string',
							'format'   => 'date',
						],
						'records' => [ // Array of { employee_id: 1, status: 'present' }
							'required' => true,
							'type'     => 'array',
						],
					],
				],
			]
        );

        // Delete records for a particular date
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/delete', [
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_standup' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'date' => [
							'required' => true,
							'type'     => 'string',
							'format'   => 'date',
						],
					],
				],
			]
        );

        // Get aggregate report for a month
        register_rest_route(
            $this->namespace, '/' . $this->rest_base . '/report', [
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_report' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'args'                => [
						'month' => [
							'required' => true,
							'type'     => 'string',
						],
					],
				],
			]
        );
    }

    public function check_permission() {
        return current_user_can( 'erp_manage_standup' );
    }

    /**
     * Helper to validate if date is not in the future
     */
    private function is_not_future( $date_string ) {
        $date = new \DateTime( $date_string );
        $now = new \DateTime();
        $now->setTime( 23, 59, 59 ); // End of today
        return ( $date <= $now );
    }

    public function get_history( WP_REST_Request $request ) {
        global $wpdb;
        $table = "{$wpdb->prefix}erp_standup_tracker";

        $month = $request->get_param( 'month' );
        if ( ! $month ) {
            $month = gmdate( 'Y-m' );
        }

        // Group by date to get stats
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a $wpdb->prefix-constructed identifier.
        $sql = $wpdb->prepare(
            "
            SELECT standup_date,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                   SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                   SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count
            FROM $table
            WHERE standup_date LIKE %s
            GROUP BY standup_date
            ORDER BY standup_date DESC
        ", $month . '%'
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        return rest_ensure_response( $results );
    }

    public function get_employees_for_date( WP_REST_Request $request ) {
        $date = $request->get_param( 'date' );

        // Validation - Cannot be future
        if ( ! $this->is_not_future( $date ) ) {
            return new WP_Error( 'invalid_date', 'Standup cannot be tracked for future dates.', [ 'status' => 400 ] );
        }

        global $wpdb;

        // Fetch employees who have a shift on this date
        // Similar to erp_att_get_single_day_attendance
        $sql = $wpdb->prepare(
            "
            SELECT 
                ds.user_id as employee_id,
                umeta.meta_value as first_name,
                umeta2.meta_value as last_name,
                st.status as standup_status
            FROM {$wpdb->prefix}erp_attendance_date_shift AS ds
            LEFT JOIN {$wpdb->prefix}erp_attendance_shifts AS shift ON ds.shift_id = shift.id
            LEFT JOIN {$wpdb->prefix}usermeta AS umeta ON (umeta.user_id = ds.user_id AND umeta.meta_key = 'first_name')
            LEFT JOIN {$wpdb->prefix}usermeta AS umeta2 ON (umeta2.user_id = ds.user_id AND umeta2.meta_key = 'last_name')
            LEFT JOIN {$wpdb->prefix}erp_standup_tracker AS st ON (st.employee_id = ds.user_id AND st.standup_date = %s)
            WHERE shift.status = 1 AND ds.date = %s
        ", $date, $date
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );

        // Format names
        foreach ( $results as &$row ) {
            $row['name'] = trim( $row['first_name'] . ' ' . $row['last_name'] );
        }

        return rest_ensure_response( $results );
    }

    public function save_standup( WP_REST_Request $request ) {
        $date    = $request->get_param( 'date' );
        $records = $request->get_param( 'records' );

        if ( ! $this->is_not_future( $date ) ) {
            return new WP_Error( 'invalid_date', 'Standup cannot be tracked for future dates.', [ 'status' => 400 ] );
        }

        global $wpdb;
        $table    = "{$wpdb->prefix}erp_standup_tracker";
        $user_id  = get_current_user_id();
        $datetime = current_time( 'mysql' );

        $wpdb->query( 'START TRANSACTION' );

        foreach ( $records as $record ) {
            $emp_id = absint( $record['employee_id'] );
            $status = sanitize_text_field( $record['status'] );

            if ( ! in_array( $status, [ 'present', 'absent', 'leave' ], true ) ) {
                continue;
            }

            // Check if exist
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a $wpdb->prefix-constructed identifier.
            $exist = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM $table WHERE employee_id = %d AND standup_date = %s", $emp_id, $date ) );

            if ( $exist ) {
                $wpdb->update(
                    $table,
                    [
                        'status'     => $status,
                        'updated_at' => $datetime,
                    ],
                    [ 'id' => $exist->id ],
                    [ '%s', '%s' ],
                    [ '%d' ]
                );
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'employee_id'  => $emp_id,
                        'standup_date' => $date,
                        'status'       => $status,
                        'created_by'   => $user_id,
                        'created_at'   => $datetime,
                        'updated_at'   => $datetime,
                    ],
                    [ '%d', '%s', '%s', '%d', '%s', '%s' ]
                );
            }
        }

        $wpdb->query( 'COMMIT' );

        return rest_ensure_response(
            [
				'success' => true,
				'message' => 'Standup records saved successfully.',
			]
        );
    }

    public function delete_standup( WP_REST_Request $request ) {
        $date = $request->get_param( 'date' );

        if ( ! $this->is_not_future( $date ) ) {
            return new WP_Error( 'invalid_date', 'Standup records cannot be deleted for future dates.', [ 'status' => 400 ] );
        }

        global $wpdb;
        $table = "{$wpdb->prefix}erp_standup_tracker";

        $deleted = $wpdb->delete( $table, [ 'standup_date' => $date ], [ '%s' ] );

        if ( false === $deleted ) {
            return new WP_Error( 'delete_failed', 'Failed to delete standup records.', [ 'status' => 500 ] );
        }

        return rest_ensure_response(
            [
				'success' => true,
				'message' => 'Standup records deleted successfully.',
			]
        );
    }

    public function get_report( WP_REST_Request $request ) {
        $month = $request->get_param( 'month' );
        global $wpdb;
        $table    = "{$wpdb->prefix}erp_standup_tracker";
        $usermeta = "{$wpdb->prefix}usermeta";

        // Total Working Days for the month (unique days with entries)
        $total_working_days = $wpdb->get_var(
            $wpdb->prepare(
                "
            SELECT COUNT(DISTINCT standup_date) 
            FROM $table 
            WHERE standup_date LIKE %s
        ", $month . '%'
            )
        );

        // Aggregate counts per employee
        $sql = $wpdb->prepare(
            "
            SELECT 
                st.employee_id,
                TRIM(CONCAT(um1.meta_value, ' ', um2.meta_value)) as name,
                SUM(CASE WHEN st.status = 'present' THEN 1 ELSE 0 END) as attend,
                SUM(CASE WHEN st.status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN st.status = 'leave' THEN 1 ELSE 0 END) as `leave`
            FROM $table st
            LEFT JOIN $usermeta um1 ON (um1.user_id = st.employee_id AND um1.meta_key = 'first_name')
            LEFT JOIN $usermeta um2 ON (um2.user_id = st.employee_id AND um2.meta_key = 'last_name')
            WHERE st.standup_date LIKE %s
            GROUP BY st.employee_id
            ORDER BY name ASC
        ", $month . '%'
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );

        return rest_ensure_response(
            [
				'total_working_days' => (int) $total_working_days,
				'stats'              => $results,
			]
        );
    }
}
