<?php
/**
 * Report item list.
 *
 * @category   Reporting
 * @package    Pandora FMS
 * @subpackage Community
 * @version    1.0.0
 * @license    See below
 *
 *    ______                 ___                    _______ _______ ________
 *   |   __ \.-----.--.--.--|  |.-----.----.-----. |    ___|   |   |     __|
 *  |    __/|  _  |     |  _  ||  _  |   _|  _  | |    ___|       |__     |
 * |___|   |___._|__|__|_____||_____|__| |___._| |___|   |__|_|__|_______|
 *
 * ============================================================================
 * Copyright (c) 2005-2021 Artica Soluciones Tecnologicas
 * Please see http://pandorafms.org for full contribution list
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation for version 2.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * ============================================================================
 */

global $config;

// Login check.
check_login();

if (! check_acl($config['id_user'], 0, 'RW')) {
    db_pandora_audit(
        AUDIT_LOG_ACL_VIOLATION,
        'Trying to access report builder'
    );
    include 'general/noaccess.php';
    exit;
}

require_once $config['homedir'].'/include/functions_agents.php';
enterprise_include_once('include/functions_metaconsole.php');


switch ($config['dbtype']) {
    case 'mysql':
    case 'postgresql':
        $type_escaped = 'type';
    break;

    case 'oracle':
        $type_escaped = db_escape_key_identifier(
            'type'
        );
    break;

    default:
        // Default.
    break;
}

$report_w = check_acl($config['id_user'], 0, 'RW');
$report_m = check_acl($config['id_user'], 0, 'RM');

if (is_metaconsole()) {
    $agents = [];
    $agents = metaconsole_get_report_agents($idReport);
    $modules = [];
    $modules = metaconsole_get_report_modules($idReport);
    $types = [];
    $types = metaconsole_get_report_types($idReport);
} else {
    // FORM FILTER.
    switch ($config['dbtype']) {
        case 'mysql':
        case 'postgresql':
            $rows = db_get_all_rows_sql(
                '
				SELECT t5.nombre, t5.id_agente
				FROM
					(
					SELECT t1.*, id_agente
					FROM treport_content t1
						LEFT JOIN tagente_modulo t2
							ON t1.id_agent_module = id_agente_modulo
					) t4
					INNER JOIN tagente t5
						ON (t4.id_agent = t5.id_agente OR t4.id_agente = t5.id_agente)
				WHERE t4.id_report = '.$idReport
            );
        break;

        case 'oracle':
            $rows = db_get_all_rows_sql(
                '
				SELECT t5.nombre, t5.id_agente
				FROM
					(
					SELECT t1.*, id_agente
					FROM treport_content t1
						LEFT JOIN tagente_modulo t2
							ON t1.id_agent_module = id_agente_modulo
					) t4
					INNER JOIN tagente t5
						ON (t4.id_agent = t5.id_agente OR t4.id_agente = t5.id_agente)
				WHERE t4.id_report = '.$idReport
            );
        break;

        default:
             // Default.
        break;
    }

    if ($rows === false) {
        $rows = [];
    }

    $agents = [];
    foreach ($rows as $row) {
        $alias = db_get_value('alias', 'tagente', 'id_agente', $row['id_agente']);
        $agents[$row['id_agente']] = $alias;
    }

    switch ($config['dbtype']) {
        case 'mysql':
        case 'postgresql':
            $rows = db_get_all_rows_sql(
                '
				SELECT t1.id_agent_module, t2.nombre
				FROM treport_content t1
					INNER JOIN tagente_modulo t2
						ON t1.id_agent_module = t2.id_agente_modulo
				WHERE t1.id_report = '.$idReport
            );
        break;

        case 'oracle':
            $rows = db_get_all_rows_sql(
                '
				SELECT t1.id_agent_module, t2.nombre
				FROM treport_content t1
					INNER JOIN tagente_modulo t2
						ON t1.id_agent_module = t2.id_agente_modulo
				WHERE t1.id_report = '.$idReport
            );
        break;

        default:
            // Default.
        break;
    }

    if ($rows === false) {
        $rows = [];
    }

    $modules = [];
    foreach ($rows as $row) {
        $modules[$row['id_agent_module']] = $row['nombre'];
    }

    // Filter report items created from metaconsole in normal console list and the opposite.
    if (is_metaconsole()) {
        $where_types = ' AND ((server_name IS NOT NULL AND length(server_name) != 0) OR '.$type_escaped.' IN (\'general\',\'SLA\',\'exception\',\'top_n\'))';
    } else {
        $where_types = ' AND ((server_name IS NULL OR length(server_name) = 0) OR '.$type_escaped.' IN (\'general\',\'SLA\',\'exception\',\'top_n\'))';
    }

    $rows = db_get_all_rows_sql(
        '
		SELECT DISTINCT('.$type_escaped.')
		FROM treport_content
		WHERE id_report = '.$idReport.$where_types
    );
    if ($rows === false) {
        $rows = [];
    }

    $types = [];
    foreach ($rows as $row) {
        if ($row['type'] == 'automatic_custom_graph') {
            $types['custom_graph'] = get_report_name($row['type']);
        } else {
            $types[$row['type']] = get_report_name($row['type']);
        }
    }
}

$agentFilter = get_parameter('agent_filter', 0);
$moduleFilter = get_parameter('module_filter', 0);
$typeFilter = get_parameter('type_filter', 0);

$filterEnable = true;
$urlFilter = '';
if (($agentFilter == 0) && ($moduleFilter == 0) && ($typeFilter == 0)) {
    $filterEnable = false;
}

$urlFilter = '&agent_filter='.$agentFilter.'&module_filter='.$moduleFilter.'&type_filter='.$typeFilter;

if (!defined('METACONSOLE')) {
    $table = new stdClass();
    $table->width = '100%';
    $table->class = 'databox filters';
    $table->data[0][0] = __('Agents');
    $table->data[0][0] .= html_print_select($agents, 'agent_filter', $agentFilter, '', __('All'), 0, true);
    $table->data[0][1] = __('Modules');
    $table->data[0][1] .= html_print_select($modules, 'module_filter', $moduleFilter, '', __('All'), 0, true);
    $table->data[0][2] = __('Type');
    $table->data[0][2] .= html_print_select($types, 'type_filter', $typeFilter, '', __('All'), 0, true);
    $form = '<form method="post" action ="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=filter&id_report='.$idReport.'">';
    $form .= html_print_table($table, true);
    $form .= '<div class="action-buttons" style="width: '.$table->width.'">';
    $form .= html_print_submit_button(__('Filter'), 'filter', false, 'class="sub upd"', true);
    $form .= html_print_input_hidden('action', 'filter', true);
    $form .= '</div>';
    $form .= '</form>';

    ui_toggle($form, __('Filters'));
} else {
    $table = new stdClass();
    $table->width = '96%';
    $table->class = 'databox_filters';
    $table->cellpadding = 0;
    $table->cellspacing = 0;
    $table->data[0][0] = __('Agents');
    $table->data[0][1] = html_print_select(
        $agents,
        'agent_filter',
        $agentFilter,
        '',
        __('All'),
        0,
        true
    );
    $table->data[0][2] = __('Modules');
    $table->data[0][3] = html_print_select(
        $modules,
        'module_filter',
        $moduleFilter,
        '',
        __('All'),
        0,
        true
    );
    $table->data[0][4] = __('Type');
    $table->data[0][5] = html_print_select(
        $types,
        'type_filter',
        $typeFilter,
        '',
        __('All'),
        0,
        true
    );
    $table->style[6] = 'text-align:right;';
    $table->data[0][6] = html_print_submit_button(
        __('Filter'),
        'filter',
        false,
        'class="sub upd"',
        true
    ).html_print_input_hidden('action', 'filter', true);

    $filters = '<form class="filters_form" method="post" action ="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=
				list_items&action=filter&id_report='.$idReport.'">';

    $filters .= html_print_table($table, true);
    $filters .= '</form>';
    ui_toggle($filters, __('Show Options'));
}

$where = '1=1';
if ($typeFilter != '0') {
    if ($typeFilter == 'custom_graph') {
        $where .= ' AND (type = "'.$typeFilter.'"
			OR type = "automatic_custom_graph") ';
    } else {
        $where .= ' AND type = "'.$typeFilter.'"';
    }
}

if ($agentFilter != 0) {
    $where .= ' AND id_agent = '.$agentFilter;
}

if ($moduleFilter != 0) {
    $where .= ' AND id_agent_module = '.$moduleFilter;
}

switch ($config['dbtype']) {
    case 'mysql':
        $items = db_get_all_rows_sql(
            'SELECT *
			FROM treport_content
			WHERE '.$where.' AND id_report = '.$idReport.'
			ORDER BY `order`
			LIMIT '.$offset.', '.$config['block_size']
        );
    break;

    case 'postgresql':
        $items = db_get_all_rows_sql(
            'SELECT *
			FROM treport_content
			WHERE '.$where.' AND id_report = '.$idReport.'
			ORDER BY "order"
			LIMIT '.$config['block_size'].' OFFSET '.$offset
        );
    break;

    case 'oracle':
        $set = [];
        $set['limit'] = $config['block_size'];
        $set['offset'] = $offset;
        $items = oracle_recode_query(
            'SELECT *
			FROM treport_content
			WHERE '.$where.' AND id_report = '.$idReport.'
			ORDER BY "order"',
            $set,
            'AND',
            false
        );
        // Delete rnum row generated by oracle_recode_query() function.
        if ($items !== false) {
            for ($i = 0; $i < count($items); $i++) {
                unset($items[$i]['rnum']);
            }
        }
    break;

    default:
        // Default.
    break;
}

$countItems = db_get_sql(
    'SELECT COUNT(id_rc)
	FROM treport_content
	WHERE '.$where.' AND id_report = '.$idReport
);
$table = new stdClass();

$table->style[0] = 'text-align: right;';


if ($items) {
    $table->width = '98%';
    if (defined('METACONSOLE')) {
        $table->width = '100%';
        $table->class = 'databox data';
        $arrow_up = 'images/sort_up.png';
        $arrow_down = 'images/sort_down.png';
    } else {
        $table->class = 'info_table';
        $arrow_up = 'images/sort_up_black.png';
        $arrow_down = 'images/sort_down_black.png';
    }

    $table->size = [];
    $table->size[0] = '5px';
    $table->size[1] = '15%';
    $table->size[4] = '8%';
    $table->size[6] = '120px';
    $table->size[7] = '30px';

    $table->head[0] = '<span title="'.__('Position').'">'.__('P.').'</span>';
    $table->head[1] = __('Type');
    if (!$filterEnable) {
        $table->head[1] .= ' <span class="sort_arrow"><a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=up&field=type&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_up,
            true,
            [
                'title' => __('Ascendent'),
                'class' => 'invert_filter',
            ]
        ).'</a>'.'<a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=down&field=type&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_down,
            true,
            [
                'title' => __('Descent'),
                'class' => 'invert_filter',
            ]
        ).'</a></span>';
    }

    $table->head[2] = __('Agent');
    if (!$filterEnable) {
        $table->head[2] .= ' <span class="sort_arrow"><a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=up&field=agent&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_up,
            true,
            [
                'title' => __('Ascendent'),
                'class' => 'invert_filter',
            ]
        ).'</a>'.'<a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=down&field=agent&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_down,
            true,
            [
                'title' => __('Descent'),
                'class' => 'invert_filter',
            ]
        ).'</a></span>';
    }

    $table->head[3] = __('Module');

    if (!$filterEnable) {
        $table->head[3] .= ' <span class="sort_arrow"><a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=up&field=module&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_up,
            true,
            ['title' => __('Ascendent')]
        ).'</a>'.'<a onclick="return message_check_sort_items();" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=order&dir=down&field=module&id_report='.$idReport.$urlFilter.'&pure='.$config['pure'].'">'.html_print_image(
            $arrow_down,
            true,
            ['title' => __('Descent')]
        ).'</a></span>';
    }

    $table->head[4] = __('Time lapse');
    $table->head[5] = __('Name or Description');
    if (check_acl($config['id_user'], 0, 'RM')) {
        $table->head[6] = '<span title="'.__('Options').'">'.__('Op.').'</span>';
        if ($report_w || $report_m) {
            $table->head[6] .= html_print_checkbox(
                'all_delete',
                0,
                false,
                true,
                false,
                'check_all_checkboxes();'
            );
        }
    }

    $table->head[7] = __('Sort');

    $table->align[6] = 'left';
    $table->align[7] = 'center';
} else {
    ui_print_info_message([ 'no_close' => true, 'message' => __('No items.') ]);
}

$lastPage = true;
if (((($offset == 0) && ($config['block_size'] > $countItems))
    || ($countItems >= ($config['block_size'] + $offset)))
    && ($countItems > $config['block_size'])
) {
    $lastPage = false;
}

$count = 0;
$rowPair = true;

if ($items === false) {
    $items = [];
}

$count = 0;
foreach ($items as $item) {
    if ($rowPair) {
        $table->rowclass[$count] = 'rowPair';
    } else {
        $table->rowclass[$count] = 'rowOdd';
    }

    $rowPair = !$rowPair;

    $row = [];

    $row[0] = ($count + $offset + 1);
    // The 1 is for do not start in 0.
    if ($filterEnable) {
        $row[0] = '';
    }

    $row[1] = get_report_name($item['type']);

    $server_name = $item['server_name'];

    if (is_metaconsole()) {
        $connection = metaconsole_get_connection($server_name);
        if (metaconsole_load_external_db($connection) != NOERR) {
            // ui_print_error_message ("Error connecting to ".$server_name);
        }
    }

    if ($item['type'] == 'custom_graph') {
        $custom_graph_name = db_get_row_sql('SELECT name FROM tgraph WHERE id_graph = '.$item['id_gs']);
        $row[1] = get_report_name($item['type']).' ('.io_safe_output($custom_graph_name['name']).')';
    }


    if ($item['id_agent'] == 0) {
        $is_inventory_item = $item['type'] == 'inventory' || $item['type'] == 'inventory_changes';

        // Due to SLA or top N or general report items.
        if (!$is_inventory_item && ($item['id_agent_module'] == '' || $item['id_agent_module'] == 0)) {
            $row[2] = '';
            $row[3] = '';
        } else {
            // The inventory items have the agents and modules in json format in the field external_source.
            if ($is_inventory_item) {
                $external_source = json_decode($item['external_source'], true);
                $agents = $external_source['id_agents'];
                $modules = io_safe_output($external_source['inventory_modules']);

                $agent_name_db = [];
                foreach ($agents as $a) {
                    $alias = db_get_value('alias', 'tagente', 'id_agente', $a);
                    $agent_name_db[] = $alias;
                }

                $agent_name_db = implode('<br>', $agent_name_db);

                $module_name_db = implode('<br>', $modules);
            } else {
                $agent_id = agents_get_agent_id_by_module_id($item['id_agent_module']);
                $agent_name = agents_get_name($agent_id);
                $agent_alias = agents_get_alias($agent_id);
                $agent_name_db = '<span title='.$agent_name.'>'.$alias.'</span>';
                $module_name_db = db_get_value_filter('nombre', 'tagente_modulo', ['id_agente_modulo' => $item['id_agent_module']]);
                $module_name_db = ui_print_truncate_text(io_safe_output($module_name_db), 'module_small');
            }

            $row[2] = $agent_name_db;
            $row[3] = $module_name_db;
        }
    } else {
        $alias = agents_get_alias($item['id_agent']);
        $row[2] = '<span title='.agents_get_name($item['id_agent']).'>'.$alias.'</span>';

        if ($item['id_agent_module'] == '') {
            $row[3] = '';
        } else {
            $module_name_db = db_get_value_filter('nombre', 'tagente_modulo', ['id_agente_modulo' => $item['id_agent_module']]);

            $row[3] = ui_print_truncate_text(io_safe_output($module_name_db), 'module_small');
        }
    }

    if ($item['period'] > 0) {
        $row[4] = human_time_description_raw($item['period']);
    } else {
        $row[4] = '-';
    }

    $style = json_decode(io_safe_output($item['style']), true);


    // Macros
    $items_macro = [];

    if (!empty($item['id_agent'])) {
        $id_agent = $item['id_agent'];
        // Add macros name.
        $agent_description = agents_get_description($id_agent);
        $agent_group = agents_get_agent_group($id_agent);
        $agent_address = agents_get_address($id_agent);
        $agent_alias = agents_get_alias($id_agent);

        $items_macro_agent = [
            'id_agent'          => $id_agent,
            'agent_description' => $agent_description,
            'agent_group'       => $agent_group,
            'agent_address'     => $agent_address,
            'agent_alias'       => $agent_alias,
        ];

        $items_macro = array_merge($items_macro, $items_macro_agent);
    }

    if (!empty($item['id_agent_module'])) {
        $id_agent_module = $item['id_agent_module'];
        $module_name = modules_get_agentmodule_name(
            $id_agent_module
        );
        $module_description = modules_get_agentmodule_descripcion(
            $id_agent_module
        );

        $items_macro_module = [
            'id_agent_module'    => $id_agent_module,
            'module_name'        => $module_name,
            'module_description' => $module_description,
        ];

        $items_macro = array_merge($items_macro, $items_macro_module);
    }



    if (($style['name_label'] ?? null) != '') {
        $text = empty($style['name_label']) ? $item['description'] : $style['name_label'];
    } else {
        if ($item['name'] == '' && $item['description'] == '') {
            $text = '-';
        } else {
            $text = empty($item['name']) ? $item['description'] : $item['name'];
        }
    }

        // Apply macros.
        $items_macro['type'] = $item['type'];
        $text = reporting_label_macro(
            $items_macro,
            ($text ?? '')
        );
    $row[5] = ui_print_truncate_text($text, 'description', true, true);


    $row[6] = '';

    if (check_acl($config['id_user'], $item['id_group'], 'RM')) {
        $table->cellclass[][6] = 'action_buttons';
        $row[6] .= '<a href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=item_editor&action=edit&id_report='.$idReport.'&id_item='.$item['id_rc'].'">'.html_print_image(
            'images/config.png',
            true,
            [
                'title' => __('Edit'),
                'class' => 'invert_filter',
            ]
        ).'</a>';
        $row[6] .= '&nbsp;';
        $row[6] .= '<a  onClick="if (!confirm (\'Are you sure?\')) return false;" href="index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=delete&id_report='.$idReport.'&id_item='.$item['id_rc'].$urlFilter.'">'.html_print_image('images/cross.png', true, ['title' => __('Delete'), 'class' => 'invert_filter']).'</a>';
        $row[6] .= '&nbsp;';
        $row[6] .= html_print_checkbox_extended(
            'delete_multiple[]',
            $item['id_rc'],
            false,
            false,
            '',
            'class="check_delete"',
            true
        );
    }

    $row[7] = '';
    // You can sort the items if the filter is not enable.
    if (!$filterEnable) {
        $row[7] .= html_print_checkbox_extended('sorted_items[]', $item['id_rc'], false, false, '', 'class="selected_check"', true);
    }

    $table->data[] = $row;
    $count++;
    // Restore db connection.
    if (($config['metaconsole'] == 1) && ($server_name != '') && defined('METACONSOLE')) {
        metaconsole_restore_db();
    }
}

if (defined('METACONSOLE')) {
    if ($items != false) {
        ui_pagination($countItems, 'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=edit&id_report='.$idReport.$urlFilter);
        html_print_table($table);
        ui_pagination($countItems, 'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=edit&id_report='.$idReport.$urlFilter);
        echo "<form action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=delete_items&id_report=".$idReport."'
		method='post' onSubmit='return added_ids_deleted_items_to_hidden_input();'>";
            echo "<div class='right w100p'>";

        if (check_acl($config['id_user'], 0, 'RM')) {
            html_print_input_hidden('ids_items_to_delete', '');
            html_print_submit_button(__('Delete'), 'delete_btn', false, 'class="sub delete right mrgn_btn_15px"');
        }

            echo '</div>';
        echo '</form>';
    }
} else {
    if ($items != false) {
        ui_pagination(
            $countItems,
            'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=edit&id_report='.$idReport.$urlFilter
        );
        html_print_table($table);
        ui_pagination(
            $countItems,
            'index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=edit&id_report='.$idReport.$urlFilter
        );

        echo "<form action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=delete_items&id_report=".$idReport."'
			method='post' onSubmit='return added_ids_deleted_items_to_hidden_input();'>";
            echo "<div class='pdd_b_20px right; w100p'>";

            html_print_input_hidden('ids_items_to_delete', '');
            html_print_submit_button(__('Delete'), 'delete_btn', false, 'class="sub delete right mrgn_btn_15px"');
            echo '</div>';
        echo '</form>';
    }
}

$table = new stdClass();
$table->width = '100%';
$table->colspan[0][0] = 3;
$table->size = [];
$table->size[0] = '25%';
$table->size[1] = '25%';
$table->size[2] = '25%';
$table->size[3] = '25%';
if (defined('METACONSOLE')) {
    $table->class = 'databox data';
    $table->head[0] = __('Sort items');
    $table->head_colspan[0] = 4;
    $table->headstyle[0] = 'text-align: center';
} else {
    $table->data[0][0] = '<b>'.__('Sort items').'</b>';
}

$table->data[1][0] = __('Sort selected items from position: ');
$table->data[1][1] = html_print_select_style(
    [
        'before' => __('Move before to'),
        'after'  => __('Move after to'),
    ],
    'move_to',
    '',
    '',
    '',
    '',
    0,
    true
);
$table->data[1][2] = html_print_input_text_extended(
    'position_to_sort',
    1,
    'text-position_to_sort',
    '',
    3,
    10,
    false,
    "only_numbers('position_to_sort');",
    '',
    true
);
$table->data[1][2] .= html_print_input_hidden('ids_items_to_sort', '', true);
$table->data[1][3] = html_print_submit_button(__('Sort'), 'sort_submit', false, 'class="sub upd"', true);

echo "<form action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=sort_items&id_report=".$idReport."'
	method='post' onsubmit='return added_ids_sorted_items_to_hidden_input();'>";
html_print_table($table);
echo '</form>';

$table = new stdClass();
$table->width = '100%';
$table->colspan[0][0] = 3;
$table->size = [];
$table->size[0] = '25%';
$table->size[1] = '25%';
$table->size[2] = '25%';
$table->size[3] = '25%';
if (defined('METACONSOLE')) {
    $table->class = 'databox data';
    $table->head[0] = __('Delete items');
    $table->head_colspan[0] = 4;
    $table->headstyle[0] = 'text-align: center';
} else {
    $table->data[0][0] = '<b>'.__('Delete items').'</b>';
}

$table->data[1][0] = __('Delete selected items from position: ');
$table->data[1][1] = html_print_select_style(
    [
        'above' => __('Delete above to'),
        'below' => __('Delete below to'),
    ],
    'delete_m',
    '',
    '',
    '',
    '',
    0,
    true
);
$table->data[1][2] = html_print_input_text_extended(
    'position_to_delete',
    1,
    'text-position_to_delete',
    '',
    3,
    10,
    false,
    "only_numbers('position_to_delete');",
    '',
    true
);
$table->data[1][2] .= html_print_input_hidden('ids_items_to_delete', '', true);
$table->data[1][3] = html_print_submit_button(__('Delete'), 'delete_submit', false, 'class="sub upd"', true);

echo "<form action='index.php?sec=reporting&sec2=godmode/reporting/reporting_builder&tab=list_items&action=delete_items_pos&id_report=".$idReport."'
	method='post'>";
html_print_table($table);
echo '</form>';

?>
<script type="text/javascript">

function check_all_checkboxes() {
    if ($("input[name=all_delete]").prop("checked")) {
        $(".check_delete").prop("checked", true);
    }
    else {
        $(".check_delete").prop("checked", false);
    }
}

function toggleFormFilter() {
    if ($("#form_filter").css('display') == 'none') {
        $("#image_form_filter").attr('src', <?php echo "'".html_print_image('images/up.png', true, ['class' => 'invert_filter'], true)."'"; ?> );
        $("#form_filter").css('display','');
    }
    else {
        $("#image_form_filter").attr('src', <?php echo "'".html_print_image('images/down.png', true, ['class' => 'invert_filter'], true)."'"; ?> );
        $("#form_filter").css('display','none');
    }
}

function message_check_sort_items() {
    var return_value = false;
    
    return_value = confirm('<?php echo __("Are you sure to sort the items into the report?\\n. This action change the sorting of items into data base."); ?>');
    
    return return_value;
}

function added_ids_sorted_items_to_hidden_input() {
    var ids = '';
    var first = true;
    
    $("input.selected_check:checked").each(function(i, val) {
        if (!first)
            ids = ids + '|';
        first = false;
        
        ids = ids + $(val).val();
    });
    
    $("input[name='ids_items_to_sort']").val(ids);
    
    if (ids == '') {
        alert("<?php echo __('Please select any item to order'); ?>");
        
        return false;
    }
    else {
        return true;
    }
}

function only_numbers(name) {
    var value = $("input[name='" + name + "']").val();
    
    if (value == "") {
        // Do none it is a empty field.
        return;
    }
    
    value = parseInt(value);
    
    if (isNaN(value)) {
        value = 1;
    }
    
    $("input[name='" + name + "']").val(value);
}


function message_check_delete_items() {
    var return_value = false;
    
    return_value = confirm("<?php echo __("Are you sure to delete the items into the report?\\n"); ?>");
    
    return return_value;
}

function added_ids_deleted_items_to_hidden_input() {
   var success = message_check_delete_items();

   if(success === false){
    $(".check_delete").prop("checked", false);
       return false;
   }
    
    var ids = '';
    var first = true;
    
    $("input.check_delete:checked").each(function(i, val) {
        if (!first)
            ids = ids + ',';
        first = false;
        
        ids = ids + $(val).val();
    });

    $("input[name='ids_items_to_delete']").val(ids);
    
    if (ids == '') {
        alert("<?php echo __('Please select any item to delete'); ?>");
        
        return false;
    }
    else {
        return true;
    }
}
</script>
