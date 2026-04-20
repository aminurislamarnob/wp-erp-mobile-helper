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

        // User profile role section
        add_action( 'erp_user_profile_role', [ $this, 'display_role_checkbox' ] );
        add_action( 'erp_update_user', [ $this, 'save_role_checkbox' ] );
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

    /**
     * Display role checkbox on user profile
     *
     * @param WP_User $profileuser
     * @return void
     */
    public function display_role_checkbox( $profileuser ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $checked = in_array( 'erp_team_lead', $profileuser->roles ) ? 'checked' : ''; ?>
        <label for="erp-team-lead">
            <input type="checkbox" id="erp-team-lead" <?php echo esc_attr( $checked ); ?> name="erp_team_lead"
                   value="erp_team_lead">
            <span class="description"><?php esc_html_e( 'Team Lead', 'wp-erp-app-helper' ); ?></span>
        </label>
        <?php
    }

    /**
     * Save role checkbox
     *
     * @param int $user_id
     * @return void
     */
    public function save_role_checkbox( $user_id ) {
        // verify nonce (WP-ERP uses user_profile_update_role nonce)
        if ( ! isset( $_REQUEST['_erp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_erp_nonce'] ), 'user_profile_update_role' ) ) {
            return;
        }

        // Bail if current user cannot promote the passing user
        if ( ! current_user_can( 'promote_user', $user_id ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );
        $new_role = isset( $_POST['erp_team_lead'] ) ? sanitize_text_field( wp_unslash( $_POST['erp_team_lead'] ) ) : false;

        if ( $new_role ) {
            $user->add_role( $new_role );
        } else {
            $user->remove_role( 'erp_team_lead' );
        }
    }
}
