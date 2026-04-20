<?php
namespace WeLabs\WpErpAppHelper;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class HrmController {

    protected $namespace = 'erp-app/v1';
    protected $rest_base = 'hrm/employees';

    public function register_routes() {
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<user_id>[\d]+)/pending-leaves', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_pending_leaves' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        ] );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<user_id>[\d]+)/rejected-leaves', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_rejected_leaves' ],
                'permission_callback' => [ $this, 'check_permission' ],
            ]
        ] );
    }

    public function check_permission( $request ) {
        return current_user_can( 'erp_edit_employee', $request['user_id'] );
    }

    public function get_pending_leaves( WP_REST_Request $request ) {
        return $this->get_leaves_by_status( $request, 2 );
    }

    public function get_rejected_leaves( WP_REST_Request $request ) {
        return $this->get_leaves_by_status( $request, 3 );
    }

    protected function get_leaves_by_status( WP_REST_Request $request, $status ) {
        $user_id = (int) $request['user_id'];
        
        if ( ! function_exists( 'erp_hr_get_financial_year_from_date' ) ) {
            return new WP_Error( 'erp_not_found', 'WP endpoint ERP is not active.', [ 'status' => 500 ] );
        }

        $f_year = erp_hr_get_financial_year_from_date();

        if ( empty( $f_year ) ) {
            return new WP_Error( 'rest_invalid_financial_year', __( 'No financial year defined for current year.', 'wp-erp-app-helper' ), [ 'status' => 404 ] );
        }

        $args = [
            'user_id'   => $user_id,
            'f_year'    => $f_year->id,
            'status'    => $status,
            'orderby'   => 'created_at',
            'policy_id' => 0,
            'number'    => -1,
            'offset'    => 0,
        ];

        $leaves = erp_hr_get_leave_requests( $args );

        return rest_ensure_response( $leaves['data'] );
    }
}
