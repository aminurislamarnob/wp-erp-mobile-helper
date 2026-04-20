<?php
namespace WeLabs\WpErpAppHelper;

/**
 * Roles class
 */
class Roles {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'erp_hr_get_roles', [ $this, 'register_team_lead_role' ] );
        add_filter( 'erp_hr_get_caps_for_role', [ $this, 'register_team_lead_caps' ], 10, 2 );
        
        // Ensure role is added to WordPress
        add_action( 'admin_init', [ $this, 'add_team_lead_role_to_wp' ] );
    }

    /**
     * Register Team Lead role in ERP
     *
     * @param array $roles
     * @return array
     */
    public function register_team_lead_role( $roles ) {
        $roles['erp_team_lead'] = [
            'name'         => __( 'Team Lead', 'wp-erp-app-helper' ),
            'public'       => false,
            'capabilities' => erp_hr_get_caps_for_role( 'erp_team_lead' ),
        ];

        return $roles;
    }

    /**
     * Register capabilities for Team Lead
     *
     * @param array $caps
     * @param string $role
     * @return array
     */
    public function register_team_lead_caps( $caps, $role ) {
        if ( 'erp_team_lead' === $role ) {
            $caps = [
                'read'                     => true,
                'upload_files'             => true,
                'erp_view_list'            => true,
                
                // employee
                'erp_list_employee'        => true,
                'erp_view_employee'        => true,
                'erp_edit_employee'        => true,

                // job
                'erp_view_jobinfo'         => true,

                // leave
                'erp_leave_create_request' => true,

                // announcement
                'erp_view_announcement'    => true,

                // experience
                'erp_create_experience'    => true,
                'erp_edit_experience'      => true,
                'erp_view_experience'      => true,
                'erp_delete_experience'    => true,

                // education
                'erp_create_education'     => true,
                'erp_edit_education'       =>       true,
                'erp_view_education'       => true,
                'erp_delete_education'     => true,

                // dependent
                'erp_create_dependent'     => true,
                'erp_edit_dependent'       => true,
                'erp_view_dependent'       => true,
                'erp_delete_dependent'     => true,

                // document
                'erp_create_document'      => true,
                'erp_edit_document'        => true,
                'erp_view_document'        => true,
                'erp_delete_document'      => true,

                // attendance
                'erp_view_attendance'      => true,

                // Include employee role cap so they are treated as employees in core checks
                'employee'                 => true,
            ];
        }

        return $caps;
    }

    /**
     * Add the role to WordPress if it doesn't exist
     */
    public function add_team_lead_role_to_wp() {
        if ( ! get_role( 'erp_team_lead' ) ) {
            add_role( 'erp_team_lead', __( 'Team Lead', 'wp-erp-app-helper' ), erp_hr_get_caps_for_role( 'erp_team_lead' ) );
        }
    }
}
