<?php
/*
 * Plugin Name:       Form Leads
 * Plugin URI:        https://example.com/plugins/the-basics/
 * Description:       Handle the basics with this plugin.
 * Version:           1.10.3
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Ali Sufyan
 * Author URI:        https://author.example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       my-basics-plugin
 * Domain Path:       /languages
 */

add_action('admin_menu', 'custom_dashboard_data_menu');

function custom_dashboard_data_menu() {
    add_menu_page(
        'Form Leads',
        'Form Leads',
        'manage_options',
        'custom-dashboard-data',
        'custom_dashboard_data_page',
        'dashicons-editor-table',
        25
    );
}

add_action('admin_enqueue_scripts', 'custom_dashboard_data_styles');

function custom_dashboard_data_styles() {
    wp_enqueue_style('form-lead-style', plugins_url('css/form-lead-style.css', __FILE__));
}

add_action('admin_init', 'handle_delete_all_rows');

function handle_delete_all_rows() {
    if (isset($_POST['action']) && $_POST['action'] === 'delete_all' && check_admin_referer('delete_all_rows')) {
        global $wpdb;

        $table_name = isset($_GET['table']) ? sanitize_key($_GET['table']) : '';

        $valid_tables = array(
            'consultation_form', 'contact_form_sec', 'popupsubmit_form', 
            'signup_form', 'solution_form', 'popup_form', 
            'logo_step_form', 'web_step_form'
        );

        if (in_array($table_name, $valid_tables)) {
            $wpdb->query("DELETE FROM {$table_name}");

            wp_redirect(add_query_arg(array('page' => 'custom-dashboard-data', 'table' => $table_name), admin_url('admin.php')));
            exit;
        }
    }
}


function custom_dashboard_data_page() {
    global $wpdb;

    // Define table names with custom display names
    $table_names = array(
        'consultation_form' => 'Consultation Form',
        'contact_form_sec' => 'Contact Us Form',
        'popupsubmit_form' => 'Coupon Form',
        'signup_form' => 'Signup Form',
        'solution_form' => 'Solution Form',
        'popup_form' => 'Book Your Call',
        'logo_step_form' => 'Logo Brief Form',
        'web_step_form' => 'Web Brief Form'
    );

    // Define tabs
    $tabs = array(
        'signup_forms' => 'Signup Forms',
        'contact_form' => 'Contact Form'
    );

    // Determine which tab is selected
    $selected_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'signup_forms';

    // Pagination variables
    $per_page = 100; // Number of rows per page
    $current_page = isset($_GET['paged']) ? absint($_GET['paged']) : 1; // Current page number

    // Initialize empty data variable and column headers
    $data = array();
    $column_headers = array();
    $total_rows = 0;

    if ($selected_tab == 'signup_forms') {
        $all_table_data = array();
        $table_columns = array();

        // Exclude contact_form_sec from signup_forms tab
        $signup_form_tables = array_diff(array_keys($table_names), array('contact_form_sec'));

        foreach ($signup_form_tables as $name) {
            // Get columns for the table
            $columns = $wpdb->get_col("DESCRIBE {$name}", 0);
            $table_columns[$name] = $columns;

            // Fetch data from the table, ordering by created_at DESC
            $table_data = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$name} ORDER BY created_at DESC",
                OBJECT
            ));
            if ($table_data) {
                foreach ($table_data as $row) {
                    $row->table_name = $name; // Add table name to each row
                    $all_table_data[] = $row;
                }
            }
        }

        // Determine all unique column names across tables
        foreach ($table_columns as $columns) {
            foreach ($columns as $column) {
                if (!in_array($column, $column_headers)) {
                    $column_headers[] = $column;
                }
            }
        }

        // Sort all data by created_at DESC
        usort($all_table_data, function($a, $b) {
            return strcmp($b->created_at, $a->created_at);
        });

        // Slice data for pagination
        $data = array_slice($all_table_data, ($current_page - 1) * $per_page, $per_page);
        $total_rows = count($all_table_data);
    } elseif ($selected_tab == 'contact_form') {
        // Fetch data specifically from the contact_form_sec table
        $table_name = 'contact_form_sec';

        // Get columns for the table
        $table_columns = $wpdb->get_col("DESCRIBE {$table_name}", 0);
        $column_headers = $table_columns;

        // Fetch data from the table, ordering by id DESC with pagination
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name} ORDER BY id DESC LIMIT %d OFFSET %d",
            $per_page,
            ($current_page - 1) * $per_page
        ), OBJECT);
        $total_rows = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    }

    if ($wpdb->last_error) {
        echo '<div class="error"><p>Error retrieving data: ' . esc_html($wpdb->last_error) . '</p></div>';
        return;
    }

    // Display page content
    ?>
    <div class="wrap formdatatablediv">
        <h1 class="wp-heading-inline">Form Data Leads</h1>
        <hr class="wp-header-end">

        <ul class="sub">
            <?php
            // Display navigation links for each tab
            foreach ($tabs as $tab_key => $tab_label) {
                $class = ($tab_key === $selected_tab) ? 'current' : '';
                echo '<li><a href="?page=custom-dashboard-data&tab=' . urlencode($tab_key) . '" class="' . $class . '">' . esc_html($tab_label) . '</a></li>';
            }
            ?>
        </ul>

        <div class="tablediv">
            
            <!-- Separate Export CSV Form -->
            <form method="post" action="" style="margin-bottom: 0; position: absolute; top: 15%; right: 20px;">
                <?php wp_nonce_field('export_csv_data'); ?>
                <input type="hidden" name="action" value="export_csv">
                <input type="submit" class="button action" value="Export CSV">
            </form>
            
            <form method="post" action="">
                <?php wp_nonce_field('delete_selected_rows'); ?>
                <input type="hidden" name="action" value="delete_selected">
                <input type="submit" class="button action" value="Delete Selected" onclick="return confirm('Are you sure you want to delete the selected data?');">
                <input type="button" id="load_table" class="button action" value="Load Table">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" /></th>
                            <?php
                            // Output table headers dynamically
                            foreach ($column_headers as $header) {
                                echo '<th>' . esc_html($header) . '</th>';
                            }
                            // Add Table Name column
                            echo '<th>Table Name</th>';
                            echo '<th>Action</th>';
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data as $row) : ?>
                            <tr>
                                <td><input type="checkbox" name="delete_ids[]" value="<?php echo esc_attr($row->id); ?>"></td>
                                <?php foreach ($column_headers as $header) : ?>
                                    <td><?php echo isset($row->$header) ? esc_html($row->$header) : ''; ?></td>
                                <?php endforeach; ?>
                                <!-- Table Name Column -->
                                <td><?php echo esc_html($row->table_name); ?></td>
                                <td>
                                    <?php
                                    $delete_url = wp_nonce_url(add_query_arg(array('action' => 'delete_row', 'id' => $row->id, 'table' => $row->table_name), admin_url('admin.php?page=custom-dashboard-data&tab=' . urlencode($selected_tab))), 'delete_row_' . $row->id);
                                    ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button button-small">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>

            <?php if ($total_rows > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <span class="displaying-num"><?php echo esc_html(sprintf(_n('%s item', '%s items', $total_rows), number_format_i18n($total_rows))); ?></span>
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => ' <',
                            'next_text' => '> ',
                            'total' => ceil($total_rows / $per_page),
                            'current' => $current_page,
                        ));
                        if ($page_links) {
                            echo '<span class="pagination-links">' . $page_links . '</span>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <?php if (empty($data)) : ?>
            <p>No data found.</p>
        <?php endif; ?>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var selectAllCheckbox = document.getElementById('select-all');
            var checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
            var loadButton = document.getElementById('load_table');

            selectAllCheckbox.addEventListener('change', function() {
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });

            loadButton.addEventListener('click', function() {
                window.location.href = window.location.href; // Reload the page
            });
        });
        </script>
    </div>
    <?php
}



add_action('admin_init', 'handle_admin_actions');

function handle_admin_actions() {
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete_row' && isset($_GET['id']) && check_admin_referer('delete_row_' . $_GET['id'])) {
        $table_name = isset($_GET['table']) ? sanitize_key($_GET['table']) : '';

        // Define valid table names
        $valid_tables = array(
            'consultation_form', 'contact_form_sec', 'popupsubmit_form', 
            'signup_form', 'solution_form', 'popup_form', 
            'logo_step_form', 'web_step_form'
        );

        if (in_array($table_name, $valid_tables)) {
            $id = absint($_GET['id']);
            $wpdb->delete($table_name, array('id' => $id), array('%d'));

            // Redirect to avoid form resubmission issues
            wp_redirect(add_query_arg(array('page' => 'custom-dashboard-data', 'tab' => 'signup_forms'), admin_url('admin.php')));
            exit;
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'delete_selected' && check_admin_referer('delete_selected_rows')) {
        $table_name = isset($_GET['tab']) && $_GET['tab'] === 'contact_form' ? 'contact_form_sec' : '';

        if (empty($table_name)) {
            // For 'signup_forms' tab, loop through all valid tables
            $valid_tables = array(
                'consultation_form', 'contact_form_sec', 'popupsubmit_form', 
                'signup_form', 'solution_form', 'popup_form', 
                'logo_step_form', 'web_step_form'
            );

            if (isset($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
                $ids = array_map('absint', $_POST['delete_ids']);
                foreach ($valid_tables as $table) {
                    $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
                    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ($ids_placeholder)", $ids));
                }
            }
        } elseif ($table_name) {
            // Delete from the specific 'contact_form_sec' table
            if (isset($_POST['delete_ids']) && is_array($_POST['delete_ids'])) {
                $ids = array_map('absint', $_POST['delete_ids']);
                $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ($ids_placeholder)", $ids));
            }
        }

        // Redirect to avoid form resubmission issues
        wp_redirect(add_query_arg(array('page' => 'custom-dashboard-data', 'tab' => $table_name === 'contact_form_sec' ? 'contact_form' : 'signup_forms'), admin_url('admin.php')));
        exit;
    }
}










add_action('admin_init', 'handle_export_csv');

function handle_export_csv() {
    if (isset($_POST['action']) && $_POST['action'] === 'export_csv' && check_admin_referer('export_csv_data')) {
        global $wpdb;

        // Get the current tab from the query parameters
        $selected_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'signup_forms';

        // Map tabs to table names
        $tab_table_map = array(
            'signup_forms' => array(
                'consultation_form', 'contact_form_sec', 'popupsubmit_form', 
                'signup_form', 'solution_form', 'popup_form', 
                'logo_step_form', 'web_step_form'
            ),
            'contact_form' => 'contact_form_sec'
        );

        if (isset($tab_table_map[$selected_tab])) {
            // Determine the table(s) to export
            if (is_array($tab_table_map[$selected_tab])) {
                $tables = $tab_table_map[$selected_tab];
            } else {
                $table_name = $tab_table_map[$selected_tab];
                $tables = array($table_name);
            }

            // Set the headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="websitedigitals-form-data.csv"');

            $output = fopen('php://output', 'w');
            $is_first_table = true;

            foreach ($tables as $table) {
                $data = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A);

                if ($data) {
                    if ($is_first_table) {
                        // Output column headers for the first table
                        fputcsv($output, array_keys($data[0]));
                        $is_first_table = false;
                    }

                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                }
            }

            fclose($output);
            exit;
        } else {
            wp_die('Invalid tab selected for export.');
        }
    }
}


