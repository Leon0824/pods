<?php
/**
 * @package Pods\Global\Functions\General
 */
/**
 * Standardize queries and error reporting. It replaces @wp_ with $wpdb->prefix.
 *
 * @see PodsData::query
 *
 * @param string $sql SQL Query
 * @param string $error (optional) The failure message
 * @param string $results_error (optional) Throw an error if a records are found
 * @param string $no_results_error (optional) Throw an error if no records are found
 *
 * @internal param string $query The SQL query
 * @return array|bool|mixed|null|void
 * @since 2.0.0
 */
function pods_query ( $sql, $error = 'Database Error', $results_error = null, $no_results_error = null ) {
    $podsdata = pods_data();
    $sql = apply_filters( 'pods_query_sql', $sql, $error, $results_error, $no_results_error );
    $sql = $podsdata->get_sql($sql);

    if ( is_array( $error ) ) {
        if ( !is_array( $sql ) )
            $sql = array( $sql, $error );

        $error = 'Database Error';
    }

    if ( 1 == pods_var( 'pods_debug_sql_all', 'get', 0 ) && is_user_logged_in() && ( is_super_admin() || current_user_can( 'delete_users' ) || current_user_can( 'pods' ) ) ) {
        $debug_sql = $sql;

        echo '<textarea cols="100" rows="24">';

        if ( is_array( $debug_sql ) )
            print_r( $debug_sql );
        else
            echo $debug_sql;

        echo '</textarea>';
    }

    return $podsdata->query( $sql, $error, $results_error, $no_results_error );
}

/**
 * Standardize filters / actions
 *
 * @param string $scope Scope of the filter / action (ui for PodsUI, api for PodsAPI, etc..)
 * @param string $name Name of filter / action to run
 * @param mixed $args (optional) Arguments to send to filter / action
 * @param object $obj (optional) Object to reference for filter / action
 *
 * @return mixed
 * @since 2.0.0
 * @todo Need to figure out how to handle $scope = 'pods' for the Pods class
 */
function pods_do_hook ( $scope, $name, $args = null, &$obj = null ) {
    // Add filter name
    array_unshift( $args, "pods_{$scope}_{$name}" );

    // Add object
    $args[] = $obj;

    // Run apply_filters and give it all the arguments
    $args = call_user_func_array( 'apply_filters', $args );

    return $args;
}

/**
 * Message / Notice handling for Admin UI
 *
 * @param string $message The notice / error message shown
 * @param string $type Message type
 */
function pods_message ( $message, $type = null ) {
    if ( empty( $type ) || !in_array( $type, array( 'notice', 'error' ) ) )
        $type = 'notice';

    $class = '';

    if ( 'notice' == $type )
        $class = 'updated';
    elseif ( 'error' == $type )
        $class = 'error';

    echo '<div id="message" class="' . $class . ' fade"><p>' . $message . '</p></div>';
}

/**
 * Error Handling which throws / displays errors
 *
 * @param string $error The error message to be thrown / displayed
 * @param object / boolean $obj If object, if $obj->display_errors is set, and is set to true: display errors;
 *                              If boolean, and is set to true: display errors
 * @throws Exception
 * @return mixed|void
 */
function pods_error ( $error, $obj = null ) {
    $display_errors = false;

    if ( is_object( $obj ) && isset( $obj->display_errors ) && true === $obj->display_errors )
        $display_errors = true;
    elseif ( is_bool( $obj ) && true === $obj )
        $display_errors = true;

    if ( is_object( $error ) && 'Exception' == get_class( $error ) ) {
        $error = $error->getMessage();
        $display_errors = false;
    }

    if ( is_array( $error ) ) {
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
            $error = __( 'The following issues occured:', 'pods' ) . "\n\n- " . implode( "\n- ", $error );
        else
            $error = __( 'The following issues occured:', 'pods' ) . "\n<ul><li>" . implode( "</li>\n<li>", $error ) . "</li></ul>";
    }
    elseif ( is_object( $error ) )
        $error = __( 'An unknown error has occurred', 'pods' );

    // log error in WP
    $log_error = new WP_Error( 'pods-error-' . md5( $error ), $error );

    // throw error as Exception and return false if silent
    if ( false !== $display_errors && !empty( $error ) ) {
        $exception_bypass = apply_filters( 'pods_error_exception', null, $error );

        if ( null !== $exception_bypass )
            return $exception_bypass;

        set_exception_handler( 'pods_error' );

        throw new Exception( $error );
    }

    $die_bypass = apply_filters( 'pods_error_die', null, $error );

    if ( null !== $die_bypass )
        return $die_bypass;

    // die with error
    if ( !defined( 'DOING_AJAX' ) && !headers_sent() && ( is_admin() || false !== strpos( $_SERVER[ 'REQUEST_URI' ], 'wp-comments-post.php' ) ) )
        wp_die( $error );
    else
        die( "<e>$error</e>" );
}

/**
 * Debug variable used in pods_debug to count the instances debug is used
 */
global $pods_debug;
$pods_debug = 0;
/**
 * Debugging common issues using this function saves a few lines and is compatible with
 *
 * @param mixed $debug The error message to be thrown / displayed
 * @param boolean $die If set to true, a die() will occur, if set to (int) 2 then a wp_die() will occur
 * @param string $prefix
 * @internal param bool $identifier If set to true, an identifying # will be output
 */
function pods_debug ( $debug = '_null', $die = false, $prefix = '_null' ) {
    global $pods_debug;

    $pods_debug++;

    ob_start();

    if ( '_null' !== $prefix )
        var_dump( $prefix );

    if ( '_null' !== $debug )
        var_dump( $debug );
    else
        var_dump( 'Pods Debug #' . $pods_debug );

    $debug = ob_get_clean();

    if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX )
        $debug = esc_html( $debug );

    if ( false === strpos( $debug, "<pre class='xdebug-var-dump' dir='ltr'>" ) )
        $debug = '<e><pre>' . $debug . '</pre>';
    else
        $debug = '<e>' . $debug;

    if ( 2 === $die )
        wp_die( $debug );
    elseif ( true === $die )
        die( $debug );

    echo $debug;
}

/**
 * Determine if Developer Mode is enabled
 *
 * @return bool Whether Developer Mode is enabled
 *
 * @since 2.3
 */
function pods_developer () {
    if ( defined( 'PODS_DEVELOPER' ) && PODS_DEVELOPER )
        return true;

    return false;
}

/**
 * Marks a function as deprecated and informs when it has been used.
 *
 * There is a hook deprecated_function_run that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated
 * function.
 *
 * The current behavior is to trigger a user error if WP_DEBUG is true.
 *
 * This function is to be used in every function that is deprecated.
 *
 * @uses do_action() Calls 'deprecated_function_run' and passes the function name, what to use instead,
 *   and the version the function was deprecated in.
 * @uses apply_filters() Calls 'deprecated_function_trigger_error' and expects boolean value of true to do
 *   trigger or false to not trigger error.
 *
 * @param string $function The function that was called
 * @param string $version The version of WordPress that deprecated the function
 * @param string $replacement Optional. The function that should have been called
 *
 * @since 2.0.0
 */
function pods_deprecated ( $function, $version, $replacement = null ) {
    if ( !version_compare( $version, PODS_VERSION, '<=' ) && !version_compare( $version . '-a-0', PODS_VERSION, '<=' ) )
        return;

    do_action( 'deprecated_function_run', $function, $replacement, $version );

    // Allow plugin to filter the output error trigger
    if ( WP_DEBUG && apply_filters( 'deprecated_function_trigger_error', true ) ) {
        if ( !is_null( $replacement ) )
            $error = __( '%1$s has been <strong>deprecated</strong> since Pods version %2$s! Use %3$s instead.', 'pods' );
        else
            $error = __( '%1$s has been <strong>deprecated</strong> since Pods version %2$s with no alternative available.', 'pods' );

        trigger_error( sprintf( $error, $function, $version, $replacement ) );
    }
}

/**
 * Inline help
 *
 * @param string $text Help text
 * @param string $url Documentation URL
 *
 * @since 2.0.0
 */
function pods_help ( $text, $url = null ) {
    if ( !wp_script_is( 'jquery-qtip', 'registered' ) )
        wp_register_script( 'jquery-qtip', PODS_URL . 'ui/js/jquery.qtip.min.js', array( 'jquery' ), '2.0-2011-10-02' );
    if ( !wp_script_is( 'jquery-qtip', 'queue' ) && !wp_script_is( 'jquery-qtip', 'to_do' ) && !wp_script_is( 'jquery-qtip', 'done' ) )
        wp_enqueue_script( 'jquery-qtip' );

    if ( !wp_style_is( 'pods-qtip', 'registered' ) )
        wp_register_style( 'pods-qtip', PODS_URL . 'ui/css/jquery.qtip.min.css', array(), '2.0-2011-10-02' );
    if ( !wp_style_is( 'pods-qtip', 'queue' ) && !wp_style_is( 'pods-qtip', 'to_do' ) && !wp_style_is( 'pods-qtip', 'done' ) )
        wp_enqueue_style( 'pods-qtip' );

    if ( !wp_script_is( 'pods-qtip-init', 'registered' ) )
        wp_register_script( 'pods-qtip-init', PODS_URL . 'ui/js/qtip.js', array(
            'jquery',
            'jquery-qtip'
        ), PODS_VERSION );
    if ( !wp_script_is( 'pods-qtip-init', 'queue' ) && !wp_script_is( 'pods-qtip-init', 'to_do' ) && !wp_script_is( 'pods-qtip-init', 'done' ) )
        wp_enqueue_script( 'pods-qtip-init' );

    if ( is_array( $text ) ) {
        if ( isset( $text[ 1 ] ) )
            $url = $text[ 1 ];

        $text = $text[ 0 ];
    }

    if ( 'help' == $text )
        return;

    if ( 0 < strlen( $url ) )
        $text .= '<br /><br /><a href="' . $url . '" target="_blank">' . __( 'Find out more', 'pods' ) . ' &raquo;</a>';

    echo '<img src="' . PODS_URL . 'ui/images/help.png" alt="' . esc_attr( $text ) . '" class="pods-icon pods-qtip" />';
}

/**
 * Build a unique slug
 *
 * @param string $slug The slug value
 * @param string $column_name The column name
 * @param string|array $pod The Pod name or array of Pod data
 * @param int $pod_id The Pod ID
 * @param int $id The item ID
 * @param object $obj (optional)
 *
 * @return string The unique slug name
 * @since 1.7.2
 */
function pods_unique_slug ( $slug, $column_name, $pod, $pod_id = 0, $id = 0, $obj = null, $strict = true ) {
    $slug = pods_create_slug( $slug, $strict );

    $pod_data = array();

    if ( is_array( $pod ) ) {
        $pod_data = $pod;
        $pod_id = pods_var( 'id', $pod_data, 0 );
        $pod = pods_var( 'name', $pod_data );
    }

    $pod_id = absint( $pod_id );
    $id = absint( $id );

    if ( empty( $pod_data ) )
        $pod_data = pods_api()->load_pod( array( 'id' => $pod_id, 'name' => $pod ), false );

    if ( empty( $pod_data ) || empty( $pod_id ) || empty( $pod ) )
        return $slug;

    if ( 'table' != $pod_data[ 'storage' ] || !in_array( $pod_data[ 'type' ], array( 'pod', 'table' ) ) )
        return $slug;

    $check_sql = "
        SELECT DISTINCT `t`.`{$column_name}` AS `slug`
        FROM `@wp_pods_{$pod}` AS `t`
        WHERE `t`.`{$column_name}` = %s AND `t`.`id` != %d
        LIMIT 1
    ";

    $slug_check = pods_query( array( $check_sql, $slug, $id ), $obj );

    if ( !empty( $slug_check ) || apply_filters( 'pods_unique_slug_is_bad_flat_slug', false, $slug, $column_name, $pod, $pod_id, $id, $pod_data, $obj ) ) {
        $suffix = 2;

        do {
            $alt_slug = substr( $slug, 0, 200 - ( strlen( $suffix ) + 1 ) ) . "-{$suffix}";

            $slug_check = pods_query( array( $check_sql, $alt_slug, $id ), $obj );

            $suffix++;
        }
        while ( !empty( $slug_check ) || apply_filters( 'pods_unique_slug_is_bad_flat_slug', false, $alt_slug, $column_name, $pod, $pod_id, $id, $pod_data, $obj ) );

        $slug = $alt_slug;
    }

    $slug = apply_filters( 'pods_unique_slug', $slug, $id, $column_name, $pod, $pod_id, $obj );

    return $slug;
}

/**
 * Check whether or not WordPress is a specific version minimum and/or maximum
 *
 * @param string $minimum_version Minimum WordPress version
 * @param string $comparison Comparison operator
 * @param string $maximum_version Maximum WordPress version
 *
 * @return bool
 */
function pods_wp_version ( $minimum_version, $comparison = '<=', $maximum_version = null ) {
    global $wp_version;

    if ( !empty( $minimum_version ) && !version_compare( $minimum_version, $wp_version, $comparison ) )
        return false;

    if ( !empty( $maximum_version ) && !version_compare( $wp_version, $maximum_version, $comparison ) )
        return false;

    return true;
}

/**
 * Run a Pods Helper
 *
 * @param string $helper_name Helper Name
 * @param string $value Value to run Helper on
 * @param string $name Field name.
 *
 * @return bool
 * @since 1.7.5
 */
function pods_helper ( $helper_name, $value = null, $name = null ) {
    return pods()->helper( $helper_name, $value, $name );
}

/**
 * Get current URL of any page
 *
 * @return string
 * @since 1.9.6
 */
if ( !function_exists( 'get_current_url' ) ) {
    /**
     * @return mixed|void
     */
    function get_current_url () {
        $url = 'http';

        if ( isset( $_SERVER[ 'HTTPS' ] ) && 'off' != $_SERVER[ 'HTTPS' ] && 0 != $_SERVER[ 'HTTPS' ] )
            $url = 'https';

        $url .= '://' . $_SERVER[ 'HTTP_HOST' ] . $_SERVER[ 'REQUEST_URI' ];

        return apply_filters( 'get_current_url', $url );
    }
}

/**
 * Find out if the current page has a valid $pods
 *
 * @param object $object The Pod Object currently checking (optional)
 *
 * @return bool
 * @since 2.0.0
 */
function is_pod ( $object = null ) {
    global $pods;
    if ( is_object( $object ) && isset( $object->pod ) && !empty( $object->pod ) )
        return true;
    if ( is_object( $pods ) && isset( $pods->pod ) && !empty( $pods->pod ) )
        return true;
    return false;
}

/**
 * See if the current user has a certain privilege
 *
 * @param mixed $privs The privilege name or names (array if multiple)
 * @param string $method The access method ("AND", "OR")
 *
 * @return bool
 * @since 1.2.0
 */
function pods_access ( $privs, $method = 'OR' ) {
    // Convert $privs to an array
    $privs = (array) $privs;

    // Convert $method to uppercase
    $method = strtoupper( $method );

    $check = apply_filters( 'pods_access', null, $privs, $method );
    if ( null !== $check && is_bool( $check ) )
        return $check;

    if ( !is_user_logged_in() )
        return false;

    if ( is_super_admin() || current_user_can( 'delete_users' ) || current_user_can( 'pods' ) || current_user_can( 'pods_content' ) )
        return true;

    // Store approved privs when using "AND"
    $approved_privs = array();

    // Loop through the user's roles
    foreach ( $privs as $priv ) {
        if ( 0 === strpos( $priv, 'pod_' ) )
            $priv = pods_str_replace( 'pod_', 'pods_edit_', $priv, 1 );

        if ( 0 === strpos( $priv, 'manage_' ) )
            $priv = pods_str_replace( 'manage_', 'pods_', $priv, 1 );

        if ( current_user_can( $priv ) ) {
            if ( 'OR' == $method )
                return true;

            $approved_privs[ $priv ] = true;
        }
    }
    if ( 'AND' == strtoupper( $method ) ) {
        foreach ( $privs as $priv ) {
            if ( 0 === strpos( $priv, 'pod_' ) )
                $priv = pods_str_replace( 'pod_', 'pods_edit_', $priv, 1 );

            if ( 0 === strpos( $priv, 'manage_' ) )
                $priv = pods_str_replace( 'manage_', 'pods_', $priv, 1 );

            if ( isset( $approved_privs[ $priv ] ) )
                return false;
        }

        return true;
    }

    return false;
}

/**
 * Shortcode support for use anywhere that support WP Shortcodes
 *
 * @param array $tags An associative array of shortcode properties
 * @param string $content A string that represents a template override
 *
 * @return string
 * @since 1.6.7
 */
function pods_shortcode ( $tags, $content = null ) {
    $defaults = array(
        'name' => null,
        'id' => null,
        'slug' => null,
        'select' => null,
        'order' => null,
        'orderby' => null,
        'limit' => null,
        'where' => null,
        'having' => null,
        'groupby' => null,
        'search' => true,
        'pagination' => true,
        'page' => null,
        'filters' => false,
        'filters_label' => null,
        'filters_location' => 'before',
        'pagination' => false,
        'pagination_label' => null,
        'pagination_location' => 'after',
        'field' => null,
        'col' => null,
        'template' => null,
        'helper' => null,
        'form' => null,
        'fields' => null,
        'label' => null,
        'thank_you' => null,
        'view' => null,
        'cache_mode' => 'none',
        'expires' => 0
    );

    if ( !empty( $tags ) )
        $tags = array_merge( $defaults, $tags );
    else
        $tags = $defaults;

    $tags = apply_filters( 'pods_shortcode', $tags );

    if ( empty( $content ) )
        $content = null;

    if ( 0 < strlen( $tags[ 'view' ] ) )
        return pods_view( $tags[ 'view' ], null, (int) $tags[ 'expires' ], $tags[ 'cache_mode' ] );

    if ( empty( $tags[ 'name' ] ) ) {
        if ( in_the_loop() || is_singular() ) {
            $pod = pods( get_post_type(), get_the_ID(), false );

            if ( !empty( $pod ) ) {
                $tags[ 'name' ] = get_post_type();
                $id = $tags[ 'id' ] = get_the_ID();
            }
        }

        if ( empty( $tags[ 'name' ] ) )
            return '<p>Please provide a Pod name</p>';
    }

    if ( !empty( $tags[ 'col' ] ) ) {
        $tags[ 'field' ] = $tags[ 'col' ];

        unset( $tags[ 'col' ] );
    }

    if ( !empty( $tags[ 'order' ] ) ) {
        $tags[ 'orderby' ] = $tags[ 'order' ];

        unset( $tags[ 'order' ] );
    }

    if ( empty( $content ) && empty( $tags[ 'template' ] ) && empty( $tags[ 'field' ] ) && empty( $tags[ 'form' ] ) ) {
        return '<p>Please provide either a template or field name</p>';
    }

    if ( !isset( $id ) ) {
        // id > slug (if both exist)
        $id = empty( $tags[ 'slug' ] ) ? null : pods_evaluate_tags( $tags[ 'slug' ] );

        if ( !empty ( $tags[ 'id' ] ) ) {
            $id = $tags[ 'id' ];

            if ( is_numeric( $id ) )
                $id = absint( $id );
        }
    }

    if ( !isset( $pod ) )
        $pod = pods( $tags[ 'name' ], $id );

    $found = 0;

    if ( !empty( $tags[ 'form' ] ) )
        return $pod->form( $tags[ 'fields' ], $tags[ 'label' ], $tags[ 'thank_you' ] );
    elseif ( empty( $id ) ) {
        $params = array();

        if ( 0 < strlen( $tags[ 'orderby' ] ) )
            $params[ 'orderby' ] = $tags[ 'orderby' ];

        if ( !empty( $tags[ 'limit' ] ) )
            $params[ 'limit' ] = $tags[ 'limit' ];

        if ( 0 < strlen( $tags[ 'where' ] ) )
            $params[ 'where' ] = pods_evaluate_tags( $tags[ 'where' ] );

        if ( 0 < strlen( $tags[ 'having' ] ) )
            $params[ 'having' ] = pods_evaluate_tags( $tags[ 'having' ] );

        if ( 0 < strlen( $tags[ 'groupby' ] ) )
            $params[ 'groupby' ] = $tags[ 'groupby' ];

        if ( 0 < strlen( $tags[ 'select' ] ) )
            $params[ 'select' ] = $tags[ 'select' ];

        if ( empty( $tags[ 'search' ] ) )
            $params[ 'search' ] = false;

        if ( 0 < absint( $tags[ 'page' ] ) )
            $params[ 'page' ] = absint( $tags[ 'page' ] );

        if ( empty( $tags[ 'pagination' ] ) )
            $params[ 'pagination' ] = false;

        if ( !empty( $tags[ 'cache_mode' ] ) && 'none' != $tags[ 'cache_mode' ] ) {
            $params[ 'cache_mode' ] = $tags[ 'cache_mode' ];
            $params[ 'expires' ] = (int) $tags[ 'expires' ];
        }

        $params = apply_filters( 'pods_shortcode_findrecords_params', $params );

        $pod->find( $params );

        $found = $pod->total();
    }
    elseif ( !empty( $tags[ 'field' ] ) ) {
        if ( empty( $tags[ 'helper' ] ) )
            $val = $pod->display( $tags[ 'field' ] );
        else
            $val = $pod->helper( $tags[ 'helper' ], $pod->field( $tags[ 'field' ] ), $tags[ 'field' ] );

        return $val;
    }

    ob_start();

    if ( empty( $id ) && false !== $tags[ 'filters' ] && 'before' == $tags[ 'filters_location' ] )
        echo $pod->filters( $tags[ 'filters' ], $tags[ 'filters_label' ] );

    if ( empty( $id ) && 0 < $found && false !== $tags[ 'pagination' ] && 'before' == $tags[ 'pagination_location' ] )
        echo $pod->pagination( $tags[ 'pagination_label' ] );

    echo $pod->template( $tags[ 'template' ], $content );

    if ( empty( $id ) && 0 < $found && false !== $tags[ 'pagination' ] && 'after' == $tags[ 'pagination_location' ] )
        echo $pod->pagination( $tags[ 'pagination_label' ] );

    if ( empty( $id ) && false !== $tags[ 'filters' ] && 'after' == $tags[ 'filters_location' ] )
        echo $pod->filters( $tags[ 'filters' ], $tags[ 'filters_label' ] );

    return ob_get_clean();
}

/**
 * Form Shortcode support for use anywhere that support WP Shortcodes
 *
 * @param array $tags An associative array of shortcode properties
 * @param string $content Not currently used
 *
 * @return string
 * @since 2.3
 */
function pods_shortcode_form ( $tags, $content = null ) {
    $tags[ 'form' ] = 1;

    return pods_shortcode( $tags );
}

/**
 * Check if Pods is compatible with WP / PHP / MySQL or not
 *
 * @param string $wp (optional) Wordpress version
 * @param string $php (optional) PHP Version
 * @param string $mysql (optional) MySQL Version
 *
 * @return bool
 *
 * @since 1.10
 */
function pods_compatible ( $wp = null, $php = null, $mysql = null ) {
    global $wp_version, $wpdb;

    if ( null === $wp )
        $wp = $wp_version;

    if ( null === $php )
        $php = phpversion();

    if ( null === $mysql )
        $mysql = $wpdb->db_version();

    $compatible = true;

    if ( !version_compare( $wp, PODS_WP_VERSION_MINIMUM, '>=' ) ) {
        $compatible = false;

        add_action( 'admin_notices', 'pods_version_notice_wp' );

        if ( !function_exists( 'pods_version_notice_php' ) ) {
            function pods_version_notice_wp () {
?>
    <div class="error fade">
        <p><strong><?php _e( 'NOTICE', 'pods' ); ?>:</strong> Pods <?php echo PODS_VERSION_FULL; ?> <?php _e( 'requires a minimum of', 'pods' ); ?>
            <strong>WordPress <?php echo PODS_WP_VERSION_MINIMUM; ?>+</strong> <?php _e( 'to function. You are currently running', 'pods' ); ?>
            <strong>WordPress <?php echo get_bloginfo( "version" ); ?></strong> - <?php _e( 'Please upgrade your WordPress to continue.', 'pods' ); ?>
        </p>
    </div>
<?php
            }
        }
    }
    if ( !version_compare( $php, PODS_PHP_VERSION_MINIMUM, '>=' ) ) {
        $compatible = false;

        add_action( 'admin_notices', 'pods_version_notice_php' );

        if ( !function_exists( 'pods_version_notice_php' ) ) {
            function pods_version_notice_php () {
?>
    <div class="error fade">
        <p><strong><?php _e( 'NOTICE', 'pods' ); ?>:</strong> Pods <?php echo PODS_VERSION_FULL; ?> <?php _e( 'requires a minimum of', 'pods' ); ?>
            <strong>PHP <?php echo PODS_PHP_VERSION_MINIMUM; ?>+</strong> <?php _e( 'to function. You are currently running', 'pods' ); ?>
            <strong>PHP <?php echo phpversion(); ?></strong> - <?php _e( 'Please upgrade (or have your Hosting Provider upgrade it for you) your PHP version to continue.', 'pods' ); ?>
        </p>
    </div>
<?php
            }
        }
    }
    if ( !@version_compare( $mysql, PODS_MYSQL_VERSION_MINIMUM, '>=' ) ) {
        $compatible = false;

        add_action( 'admin_notices', 'pods_version_notice_mysql' );

        if ( !function_exists( 'pods_version_notice_mysql' ) ) {
            function pods_version_notice_mysql () {
                global $wpdb;
                $mysql = $wpdb->db_version();
?>
    <div class="error fade">
        <p><strong><?php _e( 'NOTICE', 'pods' ); ?>:</strong> Pods <?php echo PODS_VERSION_FULL; ?> <?php _e( 'requires a minimum of', 'pods' ); ?>
            <strong>MySQL <?php echo PODS_MYSQL_VERSION_MINIMUM; ?>+</strong> <?php _e( 'to function. You are currently running', 'pods' ); ?>
            <strong>MySQL <?php echo $mysql; ?></strong> - <?php _e( 'Please upgrade (or have your Hosting Provider upgrade it for you) your MySQL version to continue.', 'pods' ); ?>
        </p>
    </div>
<?php
            }
        }
    }

    return $compatible;
}

/**
 * Check if a Function exists or File exists in Theme / Child Theme
 *
 * @param string $function_or_file Function or file name to look for.
 * @param string $function_name (optional) Function name to look for.
 * @param string $file_dir (optional) Drectory to look into
 * @param string $file_name (optional) Filename to look for
 *
 * @return mixed
 *
 * @since 1.12
 */
function pods_function_or_file ( $function_or_file, $function_name = null, $file_dir = null, $file_name = null ) {
    $found = false;
    $function_or_file = (string) $function_or_file;
    if ( false !== $function_name ) {
        if ( null === $function_name )
            $function_name = $function_or_file;
        $function_name = str_replace( array(
            '__',
            '__',
            '__'
        ), '_', preg_replace( '/[^a-z^A-Z^_][^a-z^A-Z^0-9^_]*/', '_', (string) $function_name ) );
        if ( function_exists( 'pods_custom_' . $function_name ) )
            $found = array( 'function' => 'pods_custom_' . $function_name );
        elseif ( function_exists( $function_name ) )
            $found = array( 'function' => $function_name );
    }
    if ( false !== $file_name && false === $found ) {
        if ( null === $file_name )
            $file_name = $function_or_file;
        $file_name = str_replace( array(
            '__',
            '__',
            '__'
        ), '_', preg_replace( '/[^a-z^A-Z^0-9^_]*/', '_', (string) $file_name ) ) . '.php';
        $custom_location = apply_filters( 'pods_file_directory', null, $function_or_file, $function_name, $file_dir, $file_name );
        if ( defined( 'PODS_FILE_DIRECTORY' ) && false !== PODS_FILE_DIRECTORY )
            $custom_location = PODS_FILE_DIRECTORY;
        if ( !empty( $custom_location ) && locate_template( trim( $custom_location, '/' ) . '/' . ( !empty( $file_dir ) ? $file_dir . '/' : '' ) . $file_name ) )
            $found = array( 'file' => trim( $custom_location, '/' ) . '/' . ( !empty( $file_dir ) ? $file_dir . '/' : '' ) . $file_name );
        elseif ( locate_template( 'pods/' . ( !empty( $file_dir ) ? $file_dir . '/' : '' ) . $file_name ) )
            $found = array( 'file' => 'pods/' . ( !empty( $file_dir ) ? $file_dir . '/' : '' ) . $file_name );
        elseif ( locate_template( 'pods-' . ( !empty( $file_dir ) ? $file_dir . '-' : '' ) . $file_name ) )
            $found = array( 'file' => 'pods-' . ( !empty( $file_dir ) ? $file_dir . '-' : '' ) . $file_name );
        elseif ( locate_template( 'pods/' . ( !empty( $file_dir ) ? $file_dir . '-' : '' ) . $file_name ) )
            $found = array( 'file' => 'pods/' . ( !empty( $file_dir ) ? $file_dir . '-' : '' ) . $file_name );
    }

    return apply_filters( 'pods_function_or_file', $found, $function_or_file, $function_name, $file_name );
}

/**
 * Redirects to another page.
 *
 * @param string $location The path to redirect to
 * @param int $status Status code to use
 *
 * @since 2.0.0
 */
function pods_redirect ( $location, $status = 302 ) {
    if ( !headers_sent() ) {
        wp_redirect( $location, $status );
        die();
    }
    else {
        die( '<script type="text/javascript">'
            . 'document.location = "' . str_replace( '&amp;', '&', esc_js( $location ) ) . '";'
            . '</script>' );
    }
}

/**
 * Check if a user has permission to be doing something based on standard permission options
 *
 * @param array $options
 *
 * @since 2.0.5
 */
function pods_permission ( $options ) {
    $permission = false;

    if ( isset( $options[ 'options' ] ) )
        $options = $options[ 'options' ];

    if ( is_user_logged_in() && ( is_super_admin() || current_user_can( 'delete_users' ) || current_user_can( 'manage_options' ) ) )
        $permission = true;
    elseif ( 1 == pods_var( 'restrict_capability', $options, 0 ) ) {
        $capabilities = explode( ',', pods_var( 'capability_allowed', $options ) );
        $capabilities = array_unique( array_filter( $capabilities ) );

        foreach( $capabilities as $capability ) {
            $must_have_capabilities = explode( '&&', $capability );
            $must_have_capabilities = array_unique( array_filter( $must_have_capabilities ) );

            $must_have_permission = true;

            foreach ( $must_have_capabilities as $must_have_capability ) {
                if ( !current_user_can( $must_have_capability ) ) {
                    $must_have_permission = false;

                    break;
                }
            }

            if ( $must_have_permission ) {
                $permission = true;

                break;
            }
        }
    }
    elseif ( 0 == pods_var( 'admin_only', $options, 0 ) )
        $permission = true;

    return $permission;
}

/**
 * Get a field value from a Pod
 *
 * @param string $pod The pod name
 * @param mixed $id (optional) The ID or slug, to load a single record; Provide array of $params to run 'find'
 * @param string|array $name The field name, or an associative array of parameters
 * @param boolean $single (optional) For tableless fields, to return the whole array or the just the first item
 *
 * @return mixed Field value
 */
function pods_field ( $pod, $id = false, $name = null, $single = false ) {
    // allow for pods_field( 'field_name' );
    if ( null === $name ) {
        $name = $pod;
        $single = (boolean) $id;

        $pod = get_post_type();
        $id = get_the_ID();
    }

    return pods( $pod, $id )->field( $name, $single );
}

/**
 * Get a field display value from a Pod
 *
 * @param string $pod The pod name
 * @param mixed $id (optional) The ID or slug, to load a single record; Provide array of $params to run 'find'
 * @param string|array $name The field name, or an associative array of parameters
 * @param boolean $single (optional) For tableless fields, to return the whole array or the just the first item
 *
 * @return mixed Field value
 */
function pods_field_display ( $pod, $id = false, $name = null, $single = false ) {
    // allow for pods_field_display( 'field_name' );
    if ( null === $name ) {
        $name = $pod;
        $single = (boolean) $id;

        $pod = get_post_type();
        $id = get_the_ID();
    }

    return pods( $pod, $id )->display( $name, $single );
}

/**
 * Get a field raw value from a Pod
 *
 * @param string $pod The pod name
 * @param mixed $id (optional) The ID or slug, to load a single record; Provide array of $params to run 'find'
 * @param string|array $name The field name, or an associative array of parameters
 * @param boolean $single (optional) For tableless fields, to return the whole array or the just the first item
 *
 * @return mixed Field value
 */
function pods_field_raw ( $pod, $id = false, $name = null, $single = false ) {
    // allow for pods_field_raw( 'field_name' );
    if ( null === $name ) {
        $name = $pod;
        $single = (boolean) $id;

        $pod = get_post_type();
        $id = get_the_ID();
    }

    return pods( $pod, $id )->raw( $name, $single );

}

/**
 * Set a cached value
 *
 * @see PodsView::set
 *
 * @param string $key Key for the cache
 * @param mixed $value Value to add to the cache
 * @param int $expires (optional) Time in seconds for the cache to expire, if 0 caching is disabled.
 * @param string $cache_mode (optional) Decides the caching method to use for the view.
 * @param string $group Key for the group
 *
 * @return bool|mixed|null|string|void
 *
 * @since 2.0.0
 */
function pods_view_set ( $key, $value, $expires = 0, $cache_mode = 'cache', $group = '' ) {
    require_once( PODS_DIR . 'classes/PodsView.php' );

    return PodsView::set( $key, $value, $expires, $cache_mode, $group );
}

/**
 * Get a cached value
 *
 * @see PodsView::get
 *
 * @param string $key Key for the cache
 * @param string $cache_mode (optional) Decides the caching method to use for the view.
 * @param string $group Key for the group
 *
 * @return bool|mixed|null|void
 *
 * @since 2.0.0
 */
function pods_view_get ( $key, $cache_mode = 'cache', $group = '' ) {
    require_once( PODS_DIR . 'classes/PodsView.php' );

    return PodsView::get( $key, $cache_mode, $group );
}

/**
 * Clear a cached value
 *
 * @see PodsView::clear
 *
 * @param string|bool $key Key for the cache
 * @param string $cache_mode (optional) Decides the caching method to use for the view.
 * @param string $group Key for the group
 *
 * @return bool
 *
 * @since 2.0.0
 */
function pods_view_clear ( $key = true, $cache_mode = 'cache', $group = '' ) {
    require_once( PODS_DIR . 'classes/PodsView.php' );

    return PodsView::clear( $key, $cache_mode, $group );
}

/**
 * Set a cached value
 *
 * @see PodsView::set
 *
 * @param string $key Key for the cache
 * @param mixed $value Value to add to the cache
 * @param string $group Key for the group
 * @param int $expires (optional) Time in seconds for the cache to expire, if 0 caching is disabled.
 *
 * @return bool|mixed|null|string|void
 *
 * @since 2.0.0
 */
function pods_cache_set ( $key, $value, $group = '', $expires = 0) {
    return pods_view_set( $key, $value, $expires, 'cache', $group );
}

/**
 * Clear a cached value
 *
 * @see PodsView::clear
 *
 * @param string $key Key for the cache
 * @param string $group Key for the group
 *
 * @return bool
 *
 * @since 2.0.0
 */
function pods_cache_get ( $key, $group = '' ) {
    return pods_view_get( $key, 'cache', $group );
}

/**
 * Get a cached value
 *
 * @see PodsView::get
 *
 * @param string|bool $key Key for the cache
 * @param string $group Key for the group
 *
 * @return bool|mixed|null|void
 *
 * @since 2.0.0
 */
function pods_cache_clear ( $key = true, $group = '' ) {
    return pods_view_clear( $key, 'cache', $group );
}

/**
 * Set a cached value
 *
 * @see PodsView::set
 *
 * @param string $key Key for the cache
 * @param mixed $value Value to add to the cache
 * @param int $expires (optional) Time in seconds for the cache to expire, if 0 caching is disabled.
 *
 * @return bool|mixed|null|string|void
 *
 * @since 2.0.0
 */
function pods_transient_set ( $key, $value, $expires = 0 ) {
    return pods_view_set( $key, $value, $expires, 'transient' );
}

/**
 * Get a cached value
 *
 * @see PodsView::get
 *
 * @param string $key Key for the cache
 *
 * @return bool|mixed|null|void
 *
 * @since 2.0.0
 */
function pods_transient_get ( $key ) {
    return pods_view_get( $key, 'transient' );
}

/**
 * Clear a cached value
 *
 * @see PodsView::clear
 *
 * @param string|bool $key Key for the cache
 *
 * @return bool
 *
 * @since 2.0.0
 */
function pods_transient_clear ( $key = true ) {
    return pods_view_clear( $key, 'transient' );
}

/**
 * Add a new Pod outside of the DB
 *
 * @see PodsMeta::register
 *
 * @param string $type The pod type ('post_type', 'taxonomy', 'media', 'user', 'comment')
 * @param string $name The pod name
 * @param array $object (optional) Pod array, including any 'fields' arrays
 *
 * @return array|boolean Pod data or false if unsuccessful
 * @since 2.1.0
 */
function pods_register_type ( $type, $name, $object = null ) {
    if ( empty( $object ) )
        $object = array();

    if ( !empty( $name ) )
        $object[ 'name' ] = $name;

    return pods_meta()->register( $type, $object );
}

/**
 * Add a new Pod field outside of the DB
 *
 * @see PodsMeta::register_field
 *
 * @param string $pod The pod name
 * @param string $name The name of the Pod
 * @param array $object (optional) Pod array, including any 'fields' arrays
 *
 * @return array|boolean Field data or false if unsuccessful
 * @since 2.1.0
 */
function pods_register_field ( $pod, $name, $field = null ) {
    if ( empty( $field ) )
        $field = array();

    if ( !empty( $name ) )
        $field[ 'name' ] = $name;

    return pods_meta()->register_field( $pod, $field );
}

/**
 * Add a new Pod field type
 *
 * @see PodsForm::register_field_type
 *
 * @param string $type The new field type identifier
 * @param string $file The new field type class file location
 *
 * @return array Field type array
 * @since 2.3.0
 */
function pods_register_field_type ( $type, $file = null ) {
    return PodsForm::register_field_type( $type, $file );
}

/**
 * Register a related object
 *
 * @param string $name Object name
 * @param string $label Object label
 * @param array $options Object options
 *
 * @return array|boolean Object array or false if unsuccessful
 * @since 2.3.0
 */
function pods_register_related_object ( $name, $label, $options = null ) {
    return PodsForm::field_method( 'pick', 'register_related_object', $name, $label, $options );
}

/**
 * Add a meta group of fields to add/edit forms
 *
 * @see PodsMeta::group_add
 *
 * @param string|array $pod The pod or type of element to attach the group to.f
 * @param string $label Title of the edit screen section, visible to user.
 * @param string|array $fields Either a comma separated list of text fields or an associative array containing field information.
 * @param string $context (optional) The part of the page where the edit screen section should be shown ('normal', 'advanced', or 'side').
 * @param string $priority (optional) The priority within the context where the boxes should show ('high', 'core', 'default' or 'low').
 * @param string $type (optional) Type of the post to attach to.
 *
 * @since 2.0.0
 * @link http://pods.io/docs/pods-group-add/
 */
function pods_group_add ( $pod, $label, $fields, $context = 'normal', $priority = 'default', $type = null ) {
    if ( !is_array( $pod ) && null !== $type ) {
        $pod = array(
            'name' => $pod,
            'type' => $type
        );
    }

    pods_meta()->group_add( $pod, $label, $fields, $context, $priority );
}

/**
 * Check if a plugin is active on non-admin pages (is_plugin_active() only available in admin)
 *
 * @param string $plugin Plugin name.
 *
 * @return bool
 *
 * @since 2.0.0
 */
function pods_is_plugin_active ( $plugin ) {
    if ( function_exists( 'is_plugin_active' ) )
        return is_plugin_active( $plugin );

    $active_plugins = (array) get_option( 'active_plugins', array() );

    if ( in_array( $plugin, $active_plugins ) )
        return true;

    return false;
}

/**
 * Check if Pods no conflict is on or not
 *
 * @param string $object_type
 *
 * @return bool
 */
function pods_no_conflict_check ( $object_type = 'post' ) {
    if ( 'post_type' == $object_type )
        $object_type = 'post';

    if ( !empty( PodsInit::$no_conflict ) && isset( PodsInit::$no_conflict[ $object_type ] ) && !empty( PodsInit::$no_conflict[ $object_type ] ) )
        return true;

    return false;
}

/**
 * Turn off conflicting / recursive actions for an object type that Pods hooks into
 *
 * @param string $object_type
 * @param string $object
 *
 * @return bool
 */
function pods_no_conflict_on ( $object_type = 'post', $object = null ) {
    if ( 'post_type' == $object_type )
        $object_type = 'post';

    if ( !empty( PodsInit::$no_conflict ) && isset( PodsInit::$no_conflict[ $object_type ] ) && !empty( PodsInit::$no_conflict[ $object_type ] ) )
        return true;

    if ( !is_object( PodsInit::$meta ) )
        return false;

    $no_conflict = array();

    // Filters = Usually get/update/delete meta functions
    // Actions = Usually insert/update/save/delete object functions
    if ( 'post' == $object_type ) {
        $no_conflict[ 'filter' ] = array(
            array( 'get_post_metadata', array( PodsInit::$meta, 'get_post_meta' ), 10, 4 ),
            array( 'add_post_metadata', array( PodsInit::$meta, 'add_post_meta' ), 10, 5 ),
            array( 'update_post_metadata', array( PodsInit::$meta, 'update_post_meta' ), 10, 5 ),
            array( 'delete_post_metadata', array( PodsInit::$meta, 'delete_post_meta' ), 10, 5 )
        );

        $no_conflict[ 'action' ] = array(
            array( 'save_post', array( PodsInit::$meta, 'save_post' ), 10, 2 )
        );
    }
    elseif ( 'taxonomy' == $object_type ) {
        $no_conflict[ 'filter' ] = array();

        $no_conflict[ 'action' ] = array(
            array( 'edit_term', array( PodsInit::$meta, 'save_taxonomy' ), 10, 3 ),
            array( 'create_term', array( PodsInit::$meta, 'save_taxonomy' ), 10, 3 )
        );
    }
    elseif ( 'media' == $object_type ) {
        $no_conflict[ 'filter' ] = array(
            array( 'wp_update_attachment_metadata', array( PodsInit::$meta, 'save_media' ), 10, 2 )
        );

        $no_conflict[ 'action' ] = array();
    }
    elseif ( 'user' == $object_type ) {
        $no_conflict[ 'filter' ] = array(
            array( 'get_user_metadata', array( PodsInit::$meta, 'get_user_meta' ), 10, 4 ),
            array( 'add_user_metadata', array( PodsInit::$meta, 'add_user_meta' ), 10, 5 ),
            array( 'update_user_metadata', array( PodsInit::$meta, 'update_user_meta' ), 10, 5 ),
            array( 'delete_user_metadata', array( PodsInit::$meta, 'delete_user_meta' ), 10, 5 )
        );

        $no_conflict[ 'action' ] = array(
            array( 'personal_options_update', array( PodsInit::$meta, 'save_user' ) ),
            array( 'edit_user_profile_update', array( PodsInit::$meta, 'save_user' ) )
        );
    }
    elseif ( 'comment' == $object_type ) {
        $no_conflict[ 'filter' ] = array(
            array( 'get_comment_metadata', array( PodsInit::$meta, 'get_comment_meta' ), 10, 4 ),
            array( 'add_comment_metadata', array( PodsInit::$meta, 'add_comment_meta' ), 10, 5 ),
            array( 'update_comment_metadata', array( PodsInit::$meta, 'update_comment_meta' ), 10, 5 ),
            array( 'delete_comment_metadata', array( PodsInit::$meta, 'delete_comment_meta' ), 10, 5 )
        );

        $no_conflict[ 'action' ] = array(
            array( 'pre_comment_approved', array( PodsInit::$meta, 'validate_comment' ), 10, 2 ),
            array( 'comment_post', array( PodsInit::$meta, 'save_comment' ) ),
            array( 'edit_comment', array( PodsInit::$meta, 'save_comment' ) )
        );
    }
    elseif ( 'setting' == $object_type ) {
        $no_conflict[ 'filter' ] = array();

        // @todo Better handle settings conflicts apart from each other
        /*if ( empty( $object ) ) {
            foreach ( PodsMeta::$settings as $setting_pod ) {
                foreach ( $setting_pod[ 'fields' ] as $option ) {
                    $no_conflict[ 'filter' ][] = array( 'pre_option_' . $setting_pod[ 'name' ] . '_' . $option[ 'name' ], array( PodsInit::$meta, 'get_option' ), 10, 1 );
                    $no_conflict[ 'filter' ][] = array( 'pre_update_option_' . $setting_pod[ 'name' ] . '_' . $option[ 'name' ], array( PodsInit::$meta, 'update_option' ), 10, 2 );
                }
            }
        }
        elseif ( isset( PodsMeta::$settings[ $object ] ) ) {
            foreach ( PodsMeta::$settings[ $object ][ 'fields' ] as $option ) {
                $no_conflict[ 'filter' ][] = array( 'pre_option_' . $object . '_' . $option[ 'name' ], array( PodsInit::$meta, 'get_option' ), 10, 1 );
                $no_conflict[ 'filter' ][] = array( 'pre_update_option_' . $object . '_' . $option[ 'name' ], array( PodsInit::$meta, 'update_option' ), 10, 2 );
            }
        }*/
    }

    $conflicted = false;

    foreach ( $no_conflict as $action_filter => $conflicts ) {
        foreach ( $conflicts as $args ) {
            if ( call_user_func_array( 'has_' . $action_filter, $args ) ) {
                call_user_func_array( 'remove_' . $action_filter, $args );

                $conflicted = true;
            }
        }
    }

    if ( $conflicted ) {
        PodsInit::$no_conflict[ $object_type ] = $no_conflict;

        return true;
    }

    return false;
}

/**
 * Turn on actions after running code during pods_conflict
 *
 * @param string $object_type
 *
 * @return bool
 */
function pods_no_conflict_off ( $object_type = 'post' ) {
    if ( 'post_type' == $object_type )
        $object_type = 'post';

    if ( empty( PodsInit::$no_conflict ) || !isset( PodsInit::$no_conflict[ $object_type ] ) || empty( PodsInit::$no_conflict[ $object_type ] ) )
        return false;

    if ( !is_object( PodsInit::$meta ) )
        return false;

    $no_conflict = PodsInit::$no_conflict[ $object_type ];

    $conflicted = false;

    foreach ( $no_conflict as $action_filter => $conflicts ) {
        foreach ( $conflicts as $args ) {
            if ( !call_user_func_array( 'has_' . $action_filter, $args ) ) {
                call_user_func_array( 'add_' . $action_filter, $args );

                $conflicted = true;
            }
        }
    }

    if ( $conflicted ) {
        unset( PodsInit::$no_conflict[ $object_type ] );

        return true;
    }

    return false;
}