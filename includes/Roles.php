<?php
namespace WeLabs\WpErpAppHelper;

/**
 * Roles class
 */
class Roles {

    /**
     * Custom ERP roles managed by this plugin.
     * slug => display name
     *
     * @var array<string, string>
     */
    private $custom_roles = [
        'erp_team_lead'    => 'Team Lead',
        'erp_project_lead' => 'Project Lead',
    ];

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'erp_hr_get_roles', [ $this, 'register_custom_roles' ] );
        add_filter( 'erp_hr_get_caps_for_role', [ $this, 'register_custom_role_caps' ], 10, 2 );
        add_action( 'admin_init', [ $this, 'add_custom_roles_to_wp' ] );
        add_action( 'erp_user_profile_role', [ $this, 'display_role_checkboxes' ] );
        add_action( 'erp_update_user', [ $this, 'save_role_checkboxes' ] );
    }

    /**
     * Register all custom roles in ERP.
     *
     * @param array $roles
     * @return array
     */
    public function register_custom_roles( $roles ) {
        foreach ( $this->custom_roles as $slug => $name ) {
            $roles[ $slug ] = [
                'name'         => __( $name, 'wp-erp-app-helper' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
                'public'       => false,
                'capabilities' => erp_hr_get_caps_for_role( $slug ),
            ];
        }

        return $roles;
    }

    /**
     * Register capabilities for any custom role managed by this plugin.
     *
     * @param array  $caps
     * @param string $role
     * @return array
     */
    public function register_custom_role_caps( $caps, $role ) {
        if ( 'erp_team_lead' === $role ) {
            $caps = $this->get_team_lead_caps();
        } elseif ( array_key_exists( $role, $this->custom_roles ) ) {
            $caps = $this->get_role_caps();
        }

        return $caps;
    }

    /**
     * Capability set for the Team Lead role.
     *
     * @return array<string, bool>
     */
    private function get_team_lead_caps() {
        return array_merge(
            $this->get_role_caps(),
            [ 'erp_manage_standup' => true ]
        );
    }

    /**
     * Shared capability set for all custom roles.
     *
     * @return array<string, bool>
     */
    private function get_role_caps() {
        return [
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
            'erp_edit_education'       => true,
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

    /**
     * Add all custom roles to WordPress if they don't exist yet, and sync caps for existing ones.
     */
    public function add_custom_roles_to_wp() {
        foreach ( $this->custom_roles as $slug => $name ) {
            $caps = erp_hr_get_caps_for_role( $slug );
            $role = get_role( $slug );

            if ( ! $role ) {
                add_role( $slug, __( $name, 'wp-erp-app-helper' ), $caps ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
            } else {
                foreach ( $caps as $cap => $grant ) {
                    if ( $grant ) {
                        $role->add_cap( $cap );
                    } else {
                        $role->remove_cap( $cap );
                    }
                }
            }
        }
    }

    /**
     * Display role checkboxes on the ERP user profile.
     *
     * @param \WP_User $profileuser
     * @return void
     */
    public function display_role_checkboxes( $profileuser ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        foreach ( $this->custom_roles as $slug => $name ) {
            $checked = in_array( $slug, $profileuser->roles, true ) ? 'checked' : '';
            $id      = str_replace( '_', '-', $slug ); ?>
            <label for="<?php echo esc_attr( $id ); ?>">
                <input type="checkbox" id="<?php echo esc_attr( $id ); ?>"
                        <?php echo esc_attr( $checked ); ?>
                        name="<?php echo esc_attr( $slug ); ?>"
                        value="<?php echo esc_attr( $slug ); ?>">
                <span class="description"><?php echo esc_html__( $name, 'wp-erp-app-helper' ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?></span>
            </label>
            <?php
        }
    }

    /**
     * Save role checkboxes from the ERP user profile.
     *
     * @param int $user_id
     * @return void
     */
    public function save_role_checkboxes( $user_id ) {
        if ( ! isset( $_REQUEST['_erp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_erp_nonce'] ), 'user_profile_update_role' ) ) {
            return;
        }

        if ( ! current_user_can( 'promote_user', $user_id ) ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );

        foreach ( $this->custom_roles as $slug => $name ) {
            $new_role = isset( $_POST[ $slug ] ) ? sanitize_text_field( wp_unslash( $_POST[ $slug ] ) ) : false;

            if ( $new_role ) {
                $user->add_role( $new_role );
            } else {
                $user->remove_role( $slug );
            }
        }
    }
}
