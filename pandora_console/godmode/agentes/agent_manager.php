<?php
/**
 * Extension to schedule tasks on Pandora FMS Console
 *
 * @category   Agent editor/ builder.
 * @package    Pandora FMS
 * @subpackage Classic agent management view.
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

// Begin.
enterprise_include('godmode/agentes/agent_manager.php');

require_once 'include/functions_clippy.php';
require_once 'include/functions_servers.php';
require_once 'include/functions_gis.php';
require_once $config['homedir'].'/include/functions_agents.php';
require_once $config['homedir'].'/include/functions_users.php';

if (is_ajax()) {
    global $config;

    $search_parents_2 = (bool) get_parameter('search_parents_2');

    if ($search_parents_2) {
        include_once 'include/functions_agents.php';

        $id_agent = (int) get_parameter('id_agent');
        $string = (string) get_parameter('q');
        // Field q is what autocomplete plugin gives.
        $filter = [];
        $filter[] = '(nombre LIKE "%'.$string.'%" OR direccion LIKE "%'.$string.'%" OR comentarios LIKE "%'.$string.'%" OR alias LIKE "%'.$string.'%")';
        $filter[] = 'id_agente != '.$id_agent;

        $agents = agents_get_agents(
            $filter,
            [
                'id_agente',
                'nombre',
                'direccion',
            ]
        );
        if ($agents === false) {
            $agents = [];
        }

        $data = [];
        foreach ($agents as $agent) {
            $data[] = [
                'id'   => $agent['id_agente'],
                'name' => io_safe_output($agent['nombre']),
                'ip'   => io_safe_output($agent['direccion']),
            ];
        }

        echo io_json_mb_encode($data);

        return;
    }

    $get_modules_json_for_multiple_snmp = (bool) get_parameter('get_modules_json_for_multiple_snmp', 0);
    $get_common_modules = (bool) get_parameter('get_common_modules', 1);
    if ($get_modules_json_for_multiple_snmp) {
        include_once 'include/graphs/functions_utils.php';

        $idSNMP = get_parameter('id_snmp');

        $id_snmp_serialize = get_parameter('id_snmp_serialize');
        $snmp = unserialize_in_temp($id_snmp_serialize, false);

        $oid_snmp = [];
        $out = false;
        foreach ($idSNMP as $id) {
            foreach ($snmp[$id] as $key => $value) {
                // Check if it has "ifXXXX" syntax and skip it.
                if (! preg_match('/if/', $key)) {
                    continue;
                }

                $oid_snmp[$value['oid']] = $key;
            }

            if ($out === false) {
                $out = $oid_snmp;
            } else {
                $commons = array_intersect($out, $oid_snmp);
                if ($get_common_modules) {
                    // Common modules is selected (default).
                    $out = $commons;
                } else {
                    // All modules is selected.
                    $array1 = array_diff($out, $oid_snmp);
                    $array2 = array_diff($oid_snmp, $out);
                    $out = array_merge($commons, $array1, $array2);
                }
            }

            $oid_snmp = [];
        }

        echo io_json_mb_encode($out);
    }

    // And and remove groups use the same function.
    $add_secondary_groups = get_parameter('add_secondary_groups');
    $remove_secondary_groups = get_parameter('remove_secondary_groups');
    if ($add_secondary_groups || $remove_secondary_groups) {
        $id_agent = get_parameter('id_agent');
        $groups_to_add = get_parameter('groups');
        if (enterprise_installed()) {
            if (empty($groups_to_add)) {
                return 0;
            }

            enterprise_include('include/functions_agents.php');
            $ret = enterprise_hook(
                'agents_update_secondary_groups',
                [
                    $id_agent,
                    (($add_secondary_groups) ? $groups_to_add : []),
                    (($remove_secondary_groups) ? $groups_to_add : []),
                ]
            );
            // Echo 0 in case of error. 0 Otherwise.
            echo ((bool) $ret === true) ? 1 : 0;
        }
    }

    return;
}



ui_require_javascript_file('openlayers.pandora');

$new_agent = (empty($id_agente)) ? true : false;

if (! isset($id_agente) && ! $new_agent) {
    db_pandora_audit(
        AUDIT_LOG_ACL_VIOLATION,
        'Trying to access agent manager witout an agent'
    );
    include 'general/noaccess.php';
    return;
}

if ($new_agent) {
    if (! empty($direccion_agente) && empty($nombre_agente)) {
        $nombre_agente = $direccion_agente;
    }

    $servers = servers_get_names();
    if (!empty($servers)) {
        $array_keys_servers = array_keys($servers);
        $server_name = reset($array_keys_servers);
    }
}

if (!$new_agent) {
    // Agent remote configuration editor.
    enterprise_include_once('include/functions_config_agents.php');
    if (enterprise_installed()) {
        $filename = config_agents_get_agent_config_filenames($id_agente);
    }
}

$disk_conf_delete = (bool) get_parameter('disk_conf_delete');
// Agent remote configuration DELETE.
if ($disk_conf_delete) {
    // TODO: Get this working on computers where the Pandora server(s) are not on the webserver
    // TODO: Get a remote_config editor working in the open version.
    @unlink($filename['md5']);
    @unlink($filename['conf']);
}

echo '<form autocomplete="new-password" name="conf_agent" id="form_agent" method="post" action="index.php?sec=gagente&sec2=godmode/agentes/configurar_agente">';

// Custom ID.
$custom_id_div = '<div class="label_select">';
$custom_id_div .= '<p class="input_label">'.__('Custom ID').': </p>';
$custom_id_div .= html_print_input_text(
    'custom_id',
    $custom_id,
    '',
    16,
    255,
    true,
    false,
    false,
    '',
    'agent_custom_id'
).'</div>';

if (!$new_agent && $alias != '') {
    $table_agent_name = '<div class="label_select"><p class="input_label">'.__('Agent name').'</p>';
    $table_agent_name .= '<div class="label_select_parent">';
    $table_agent_name .= '<div class="label_select_child_left w60p">'.html_print_input_text('agente', $nombre_agente, '', 50, 100, true).'</div>';
    $table_agent_name .= '<div class="label_select_child_right agent_options_agent_name w70p">';

    if ($id_agente) {
        $table_agent_name .= '<label>'.__('ID').'</label><input class="w50p" type="text" readonly value="'.$id_agente.'" />';
        $table_agent_name .= '<a href="index.php?sec=gagente&sec2=operation/agentes/ver_agente&id_agente='.$id_agente.'">';
        $table_agent_name .= html_print_image(
            'images/zoom.png',
            true,
            [
                'border' => 0,
                'title'  => __('Agent detail'),
                'class'  => 'invert_filter',
            ]
        );
        $table_agent_name .= '</a>';
    }

    $agent_options_update = 'agent_options_update';

    // Delete link from here.
    if (is_management_allowed() === true) {
        $table_agent_name .= "<a onClick=\"if (!confirm('".__('Are you sure?')."')) return false;\" href='index.php?sec=gagente&sec2=godmode/agentes/modificar_agente&borrar_agente=".$id_agente."&search=&offset=0&sort_field=&sort=none'>".html_print_image(
            'images/cross.png',
            true,
            [
                'title' => __('Delete agent'),
                'class' => 'invert_filter',
            ]
        ).'</a>';
    }

    // Remote configuration available.
    $remote_agent = false;
    if (isset($filename)) {
        if (file_exists($filename['md5'])) {
            $remote_agent = true;

            $agent_name = agents_get_name($id_agente);
            $agent_name = io_safe_output($agent_name);
            $agent_md5 = md5($agent_name, false);

            $table_agent_name .= '<a href="index.php?sec=gagente&sec2=godmode/agentes/configurar_agente&tab=remote_configuration&id_agente='.$id_agente.'&disk_conf='.$agent_md5.'">';
            $table_agent_name .= html_print_image(
                'images/application_edit.png',
                true,
                [
                    'border' => 0,
                    'title'  => __('This agent can be remotely configured'),
                    'class'  => 'invert_filter',
                ]
            );
            $table_agent_name .= '</a>';
        }
    }

    $table_agent_name .= '</div></div></div>';

    // QR code div.
    $table_qr_code = '<div class="box-shadow agent_qr white_box">';
    $table_qr_code .= '<p class="input_label">'.__('QR Code Agent view').'</p>';
    $table_qr_code .= '<div id="qr_container_image"></div>';
    if ($id_agente) {
        $table_qr_code .= "<a id='qr_code_agent_view' href='".ui_get_full_url('mobile/index.php?page=agent&id='.$id_agente).");'></a>";
    }

    // Add Custom id div.
    $table_qr_code .= '<br />';
    $table_qr_code .= $custom_id_div;
    $table_qr_code .= '</div>';
}

if ($new_agent) {
    $label_select_child_left = 'label_select_child_left';
    $label_select_parent = 'label_select_parent';
}

$table_alias = '<div class="label_select"><p class="input_label">'.__('Alias').'</p>';
$table_alias .= '<div class='.$label_select_parent.'>';
$table_alias .= '<div class='.$label_select_child_left.'>'.html_print_input_text('alias', $alias, '', 50, 100, true, false, true).'</div>';
if ($new_agent) {
    $table_alias .= '<div class="label_select_child_right">'.html_print_checkbox_switch('alias_as_name', 1, $config['alias_as_name'], true).__('Use alias as name').'</div>';
}

$table_alias .= '</div></div>';

$table_ip = '<div class="label_select"><p class="input_label">'.__('IP Address').'</p>';
$table_ip .= '<div class="label_select_parent">';
$table_ip .= '<div class="label_select_child_left">'.html_print_input_text('direccion', $direccion_agente, '', 16, 100, true).'</div>';
$table_ip .= '<div class="label_select_child_right">'.html_print_checkbox_switch('unique_ip', 1, $config['unique_ip'], true).__('Unique IP').'</div>';
$table_ip .= '</div></div>';

if ($id_agente) {
    $ip_all = agents_get_addresses($id_agente);

    $table_ip .= '<div class="label_select">';
    $table_ip .= '<div class="label_select_parent">';
    $table_ip .= '<div class="label_select_child_left">'.html_print_select($ip_all, 'address_list', $direccion_agente, '', '', 0, true).'</div>';
    $table_ip .= '<div class="label_select_child_right">'.html_print_checkbox_switch('delete_ip', 1, false, true).__('Delete selected').'</div>';
    $table_ip .= '</div></div>';
}

?>
<style type="text/css">
    #qr_code_agent_view img {
        display: inline !important;
    }
</style>
<?php
$groups = users_get_groups($config['id_user'], 'AR', false);

$modules = db_get_all_rows_sql(
    'SELECT id_agente_modulo as id_module, nombre as name FROM tagente_modulo 
								WHERE id_agente = '.$id_parent
);
$modules_values = [];
$modules_values[0] = __('Any');
if (is_array($modules)) {
    foreach ($modules as $m) {
        $modules_values[$m['id_module']] = $m['name'];
    }
}

$table_primary_group = '<div class="label_select"><p class="input_label">'.__('Primary group').'</p>';
$table_primary_group .= '<div class="label_select_parent">';
// Cannot change primary group if user have not permission for that group.
if (isset($groups[$grupo]) || $new_agent) {
    $table_primary_group .= html_print_input(
        [
            'type'           => 'select_groups',
            'returnAllGroup' => false,
            'name'           => 'grupo',
            'selected'       => $grupo,
            'return'         => true,
            'required'       => true,
            'privilege'      => 'AW',
        ]
    );
} else {
    $table_primary_group .= groups_get_name($grupo);
    $table_primary_group .= html_print_input_hidden('grupo', $grupo, true);
}

$table_primary_group .= '<div class="label_select_child_icons"><span id="group_preview">';
if ($id_agente === 0) {
    $hidden  = 'display: none;';
} else {
    $hidden = '';
}

$table_primary_group .= ui_print_group_icon($grupo, true, 'groups_small', $hidden);

$table_primary_group .= '</span></div></div></div>';

$table_interval = '<div class="label_select"><p class="input_label">'.__('Interval').'</p>';
$table_interval .= '<div class="label_select_parent">';
$table_interval .= html_print_extended_select_for_time(
    'intervalo',
    $intervalo,
    '',
    '',
    '0',
    10,
    true,
    false,
    true,
    'w40p'
);



if ($intervalo < SECONDS_5MINUTES) {
    $table_interval .= clippy_context_help('interval_agent_min');
}

$table_interval .= '</div></div>';

$table_os = '<div class="label_select"><p class="input_label">'.__('OS').'</p>';
$table_os .= '<div class="label_select_parent">';
$table_os .= html_print_select_from_sql(
    'SELECT id_os, name FROM tconfig_os',
    'id_os',
    $id_os,
    '',
    '',
    '0',
    true
);
$table_os .= '<div class="label_select_child_icons"> <span id="os_preview">';
$table_os .= ui_print_os_icon($id_os, false, true);
$table_os .= '</span></div></div></div>';

// Network server.
$servers = servers_get_names();
// Set the agent have not server.
if (array_key_exists($server_name, $servers) === false) {
    $server_name = 0;
}

$table_server = '<div class="label_select"><p class="input_label">'.__('Server').'</p>';
$table_server .= '<div class="label_select_parent">';
if ($new_agent) {
    // Set first server by default.
    $servers_get_names = $servers;
    $array_keys_servers_get_names = array_keys($servers_get_names);
    $server_name = reset($array_keys_servers_get_names);
}

$table_server .= html_print_select(
    $servers,
    'server_name',
    $server_name,
    '',
    __('None'),
    0,
    true
).'<div class="label_select_child_icons"></div></div></div>';


$table_satellite = '';
if ($remote_agent === true) {
    // Satellite server selector.
    $satellite_servers = db_get_all_rows_filter(
        'tserver',
        ['server_type' => SERVER_TYPE_ENTERPRISE_SATELLITE],
        [
            'id_server',
            'name',
        ]
    );

    $satellite_names = [];
    if (empty($satellite_servers) === false) {
        foreach ($satellite_servers as $s_server) {
            $satellite_names[$s_server['id_server']] = $s_server['name'];
        }

            $table_satellite = '<div class="label_select"><p class="input_label">'.__('Satellite').'</p>';
            $table_satellite .= '<div class="label_select_parent">';

            $table_satellite .= html_print_input(
                [
                    'type'          => 'select',
                    'fields'        => $satellite_names,
                    'name'          => 'satellite_server',
                    'selected'      => $satellite_server,
                    'nothing'       => __('None'),
                    'nothinf_value' => 0,
                    'return'        => true,
                ]
            ).'<div class="label_select_child_icons"></div></div></div>';
    }
}

// Description.
$table_description = '<div class="label_select"><p class="input_label">'.__('Description').'</p>';
$table_description .= html_print_textarea(
    'comentarios',
    3,
    10,
    $comentarios,
    '',
    true,
    'agent_description'
).'</div>';

// QR code.
echo '<div class="first_row">
        <div class="box-shadow agent_options '.$agent_options_update.' white_box">
            <div class="agent_options_column_left">'.$table_agent_name.$table_alias.$table_ip.$table_primary_group.'</div>
            <div class="agent_options_column_right">'.$table_interval.$table_os.$table_server.$table_satellite.$table_description.'</div>
        </div>';
if (!$new_agent && $alias != '') {
    echo $table_qr_code;
}

echo '</div>';

if (enterprise_installed()) {
    $adv_secondary_groups_label = '<div class="label_select">';
    $adv_secondary_groups_label .= '<p class="input_label">';
    $adv_secondary_groups_label .= __('Secondary groups');
    $adv_secondary_groups_label .= '</p>';
    $adv_secondary_groups_label .= '</div>';
    $select_agent_secondary = html_print_select_agent_secondary(
        $agent,
        $id_agente
    );

    // Safe operation mode.
    if ($id_agente) {
        $sql_modules = db_get_all_rows_sql(
            'SELECT id_agente_modulo as id_module, nombre as name FROM tagente_modulo 
									WHERE id_agente = '.$id_agente
        );
        $safe_mode_modules = [];
        $safe_mode_modules[0] = __('Any');
        if (is_array($sql_modules)) {
            foreach ($sql_modules as $m) {
                $safe_mode_modules[$m['id_module']] = $m['name'];
            }
        }

        $table_adv_safe = '<div class="label_select_simple label_simple_items"><p class="input_label input_label_simple">'.__('Safe operation mode').'</p>';
        $table_adv_safe .= html_print_checkbox_switch('safe_mode', 1, $safe_mode, true);
        $table_adv_safe .= __('Module').'&nbsp;'.html_print_select($safe_mode_modules, 'safe_mode_module', $safe_mode_module, '', '', 0, true).'</div>';
    }

    // Remote configuration.
    $table_adv_remote = '<div class="label_select"><p class="input_label">'.__('Remote configuration').'</p>';

    if (!$new_agent && isset($filename) && file_exists($filename['md5'])) {
        $table_adv_remote .= date('F d Y H:i:s', fileatime($filename['md5']));
        // Delete remote configuration.
        $table_adv_remote .= '<a href="index.php?sec=gagente&sec2=godmode/agentes/configurar_agente&tab=main&disk_conf_delete=1&id_agente='.$id_agente.'">';
        $table_adv_remote .= html_print_image(
            'images/cross.png',
            true,
            [
                'title' => __('Delete remote configuration file'),
                'style' => 'vertical-align: middle;',
                'class' => 'invert_filter',
            ]
        ).'</a>';
        $table_adv_remote .= '</a>';
    } else {
        $table_adv_remote .= '<em>'.__('Not available').'</em>';
    }

    $table_adv_remote .= '</div>';


    // Calculate cps value - agents.
    if ($id_agente) {
        $cps_val = service_agents_cps($id_agente);
    } else {
        // No agent defined, use received cps as base value.
        if ($cps >= 0) {
            $cps_val = $cps;
        }
    }

    $cps_html = '<div class="label_select"><div class="label_simple_items">';
    $cps_html .= html_print_checkbox_switch('cps', $cps_val, ($cps >= 0), true);
    $cps_html .= __('Cascade protection services').'&nbsp;';
    $cps_html .= '</div></div>';

    $table_adv_cascade .= $cps_html;
}

$table_adv_parent = '<div class="label_select"><label class="input_label">'.__('Parent').'</label>';
$params = [];
$params['return'] = true;
$params['show_helptip'] = true;
$params['input_name'] = 'id_parent';
$params['print_hidden_input_idagent'] = true;
$params['hidden_input_idagent_name'] = 'id_agent_parent';
$params['hidden_input_idagent_value'] = $id_parent;
$params['value'] = db_get_value('alias', 'tagente', 'id_agente', $id_parent);
$params['selectbox_id'] = 'cascade_protection_module';
$params['javascript_is_function_select'] = true;
$params['cascade_protection'] = true;
if ($id_agente !== 0) {
    // Deletes the agent's offspring.
    $params['delete_offspring_agents'] = $id_agente;
}

$table_adv_parent .= '<div class="label_simple_items">';
$table_adv_parent .= ui_print_agent_autocomplete_input($params);
if (enterprise_installed()) {
    $table_adv_parent .= html_print_checkbox_switch(
        'cascade_protection',
        1,
        $cascade_protection,
        true
    ).__('Cascade protection').'&nbsp;';

    $table_adv_parent .= __('Module').'&nbsp;'.html_print_select(
        $modules_values,
        'cascade_protection_module',
        $cascade_protection_module,
        '',
        '',
        0,
        true
    );
}

$table_adv_parent .= '</div></div>';

// Learn mode / Normal mode.
$table_adv_module_mode = '<div class="label_select"><p class="input_label">'.__('Module definition').'</p>';
$table_adv_module_mode .= '<div class="switch_radio_button">';
$table_adv_module_mode .= html_print_radio_button_extended(
    'modo',
    1,
    __('Learning mode'),
    $modo,
    false,
    'show_modules_not_learning_mode_context_help();',
    '',
    true
);
$table_adv_module_mode .= html_print_radio_button_extended(
    'modo',
    0,
    __('Normal mode'),
    $modo,
    false,
    'show_modules_not_learning_mode_context_help();',
    '',
    true
);
$table_adv_module_mode .= html_print_radio_button_extended(
    'modo',
    2,
    __('Autodisable mode'),
    $modo,
    false,
    'show_modules_not_learning_mode_context_help();',
    '',
    true
);
$table_adv_module_mode .= '</div></div>';

// Status (Disabled / Enabled).
$table_adv_status = '<div class="label_select_simple label_simple_one_item">';
$table_adv_status .= html_print_checkbox_switch(
    'disabled',
    1,
    $disabled,
    true
);
$table_adv_status .= '<p class="input_label input_label_simple">'.__('Disabled mode').'</p>';
$table_adv_status .= '</div>';

// Url address.
if (enterprise_installed()) {
    $table_adv_url = '<div class="label_select"><p class="input_label">'.__('Url address').'</p>';
    $table_adv_url .= html_print_input_text(
        'url_description',
        $url_description,
        '',
        45,
        255,
        true,
        false,
        false,
        '',
        '',
        '',
        // Autocomplete.
        'new-password'
    ).'</div>';
} else {
    $table_adv_url = '<div class="label_select"><p class="input_label">'.__('Url address').'</p></div>';
    $table_adv_url .= html_print_input_text(
        'url_description',
        $url_description,
        '',
        45,
        255,
        true
    ).'</div>';
}

$table_adv_quiet = '<div class="label_select_simple label_simple_one_item">';
$table_adv_quiet .= html_print_checkbox_switch('quiet', 1, $quiet, true);
$table_adv_quiet .= '<p class="input_label input_label_simple">'.__('Quiet').'</p>';
$table_adv_quiet .= '</div>';

$listIcons = gis_get_array_list_icons();

$arraySelectIcon = [];
foreach ($listIcons as $index => $value) {
    $arraySelectIcon[$index] = $index;
}

$path = 'images/gis_map/icons/';
// TODO set better method the path.
$table_adv_agent_icon = '<div class="label_select"><p class="input_label">'.__('Agent icon').'</p>';
if ($icon_path == '') {
    $display_icons = 'none';
    // Hack to show no icon. Use any given image to fix not found image errors.
    $path_without = 'images/spinner.gif';
    $path_default = 'images/spinner.gif';
    $path_ok = 'images/spinner.gif';
    $path_bad = 'images/spinner.gif';
    $path_warning = 'images/spinner.gif';
} else {
    $display_icons = '';
    $path_without = $path.$icon_path.'.default.png';
    $path_default = $path.$icon_path.'.default.png';
    $path_ok = $path.$icon_path.'.ok.png';
    $path_bad = $path.$icon_path.'.bad.png';
    $path_warning = $path.$icon_path.'.warning.png';
}

$table_adv_agent_icon .= html_print_select(
    $arraySelectIcon,
    'icon_path',
    $icon_path,
    'changeIcons();',
    __('None'),
    '',
    true
).html_print_image(
    $path_ok,
    true,
    [
        'id'    => 'icon_ok',
        'style' => 'display:'.$display_icons.';',
    ]
).html_print_image(
    $path_bad,
    true,
    [
        'id'    => 'icon_bad',
        'style' => 'display:'.$display_icons.';',
    ]
).html_print_image(
    $path_warning,
    true,
    [
        'id'    => 'icon_warning',
        'style' => 'display:'.$display_icons.';',
    ]
).'</div>';

if ($config['activate_gis']) {
    $table_adv_gis = '<div class="label_select_simple label_simple_one_item"><p class="input_label input_label_simple">'.__('Update new GIS data:').'</p>';
    if ($new_agent) {
        $update_gis_data = true;
    }

    $table_adv_gis .= html_print_checkbox_switch('update_gis_data', 1, $update_gis_data, true).'No / Yes</div>';
}


if (enterprise_installed()) {
    $advanced_div = '<div class="secondary_groups_list">';
} else {
    $advanced_div = '<div class="secondary_groups_list invisible" >';
}

// General display distribution.
$table_adv_options = $advanced_div;
$table_adv_options .= $adv_secondary_groups_label;
$table_adv_options .= $select_agent_secondary;
$table_adv_options .= '</div>';

$table_adv_options .= '<div class="agent_av_opt_right" >';
$table_adv_options .= $table_adv_parent;
$table_adv_options .= $table_adv_module_mode;
$table_adv_options .= $table_adv_cascade;

if ($new_agent) {
    // If agent is new, show custom id as old style format.
    $table_adv_options .= $custom_id_div;
}

$table_adv_options .= '</div>';

$table_adv_options .= '
        <div class="agent_av_opt_left" >
        '.$table_adv_gis.$table_adv_agent_icon.$table_adv_url.$table_adv_quiet.$table_adv_status.$table_adv_remote.$table_adv_safe.'
        </div>';

if (enterprise_installed()) {
    echo '<div class="ui_toggle">';
    ui_toggle(
        $table_adv_options,
        __('Advanced options'),
        '',
        '',
        true,
        false,
        'white_box white_box_opened',
        'no-border flex'
    );
    echo '</div>';
}

$table = new stdClass();
$table->width = '100%';
$table->class = 'custom_fields_table';

$table->head = [
    0 => __('Click to display'),
];
$table->class = 'info_table';
$table->style = [];
$table->style[0] = 'font-weight: bold;';
$table->data = [];
$table->rowstyle = [];

$fields = db_get_all_fields_in_table('tagent_custom_fields');

if ($fields === false) {
    $fields = [];
}

$i = 0;
foreach ($fields as $field) {
    $id_custom_field = $field['id_field'];

    $data[0] = '<div class="field_title" onclick="show_custom_field_row('.$id_custom_field.')">';
    $data[0] .= '<b>'.$field['name'].'</b>';
    $data[0] .= '</div>';
    $combo = [];
    $combo = $field['combo_values'];
    $combo = explode(',', $combo);
    $combo_values = [];
    foreach ($combo as $value) {
        $combo_values[$value] = $value;
    }

    $custom_value = db_get_value_filter(
        'description',
        'tagent_custom_data',
        [
            'id_field' => $field['id_field'],
            'id_agent' => $id_agente,
        ]
    );

    if ($custom_value === false) {
        $custom_value = '';
    }

    $table->rowstyle[$i] = 'cursor: pointer;user-select: none;';
    if (!empty($custom_value)) {
        $table->rowstyle[($i + 1)] = 'display: table-row;';
    } else {
        $table->rowstyle[($i + 1)] = 'display: none;';
    }

    if ($field['is_password_type']) {
        $data_field[1] = html_print_input_text_extended(
            'customvalue_'.$field['id_field'],
            $custom_value,
            'customvalue_'.$field['id_field'],
            '',
            30,
            100,
            $view_mode,
            '',
            '',
            true,
            true
        );
    } else {
        $data_field[1] = html_print_textarea(
            'customvalue_'.$field['id_field'],
            2,
            65,
            $custom_value,
            'class="min-height-30px',
            true
        );
    }

    if ($field['combo_values'] !== '') {
        $data_field[1] = html_print_input(
            [
                'type'              => 'select_search',
                'fields'            => $combo_values,
                'name'              => 'customvalue_'.$field['id_field'],
                'selected'          => $custom_value,
                'nothing'           => __('None'),
                'nothing_value'     => '',
                'return'            => true,
                'sort'              => false,
                'size'              => '400px',
                'dropdownAutoWidth' => true,
            ]
        );
    };

    $table->rowid[] = 'name_field-'.$id_custom_field;
    $table->data[] = $data;

    $table->rowid[] = 'field-'.$id_custom_field;
    $table->data[] = $data_field;
    $i += 2;
}

if (enterprise_installed()) {
    if (!empty($fields)) {
        echo '<div class="ui_toggle">';
        ui_toggle(
            html_print_table($table, true),
            __('Custom fields'),
            '',
            '',
            true,
            false,
            'white_box white_box_opened',
            'no-border'
        );
        echo '</div>';
    }
} else {
    echo '<div class="ui_toggle">';
    ui_toggle(
        $table_adv_options,
        __('Advanced options'),
        '',
        '',
        true,
        false,
        'white_box white_box_opened',
        'no-border flex'
    );
    if (!empty($fields)) {
        ui_toggle(
            html_print_table($table, true),
            __('Custom fields'),
            '',
            '',
            true,
            false,
            'white_box white_box_opened',
            'no-border'
        );
    }

    echo '<div class="action-buttons agent_manager" style="width: '.$table->width.'">';

    echo '</div>';
}

echo '<div class="action-buttons agent_manager" style="width: '.$table->width.'">';

// The context help about the learning mode.
if ($modo == 0) {
    echo "<span id='modules_not_learning_mode_context_help' class='pdd_r_10px'>";
} else {
    echo "<span id='modules_not_learning_mode_context_help' class='invisible'>";
}

echo clippy_context_help('modules_not_learning_mode');
echo '</span>';


if ($id_agente) {
    echo '<div class="action-buttons">';
    html_print_submit_button(
        __('Update'),
        'updbutton',
        false,
        'class="sub upd"'
    );
    html_print_input_hidden('update_agent', 1);
    html_print_input_hidden('id_agente', $id_agente);
} else {
    html_print_submit_button(
        __('Create'),
        'crtbutton',
        false,
        'class="sub wand"'
    );
    html_print_input_hidden('create_agent', 1);
}

echo '</div></form>';

ui_require_jquery_file('pandora.controls');
ui_require_jquery_file('ajaxqueue');
ui_require_jquery_file('bgiframe');
?>

<script type="text/javascript">
    // Show/Hide custom field row.
    function show_custom_field_row(id){
        if( $('#field-'+id).css('display') == 'none'){
            $('#field-'+id).css('display','table-row');
            $('#name_field-'+id).addClass('custom_field_row_opened');
        }
        else{
            $('#field-'+id).css('display','none');
            $('#name_field-'+id).removeClass('custom_field_row_opened');
        }
    }


    //Use this function for change 3 icons when change the selectbox
    function changeIcons() {
        var icon = $("#icon_path :selected").val();
        
        $("#icon_without_status").attr("src", "images/spinner.png");
        $("#icon_default").attr("src", "images/spinner.png");
        $("#icon_ok").attr("src", "images/spinner.png");
        $("#icon_bad").attr("src", "images/spinner.png");
        $("#icon_warning").attr("src", "images/spinner.png");
        
        if (icon.length == 0) {
            $("#icon_without_status").attr("style", "display:none;");
            $("#icon_default").attr("style", "display:none;");
            $("#icon_ok").attr("style", "display:none;");
            $("#icon_bad").attr("style", "display:none;");
            $("#icon_warning").attr("style", "display:none;");
        }
        else {
            $("#icon_without_status").attr("src",
                "<?php echo $path; ?>" + icon + ".default.png");
            $("#icon_default").attr("src",
                "<?php echo $path; ?>" + icon + ".default.png");
            $("#icon_ok").attr("src",
                "<?php echo $path; ?>" + icon + ".ok.png");
            $("#icon_bad").attr("src",
                "<?php echo $path; ?>" + icon + ".bad.png");
            $("#icon_warning").attr("src",
                "<?php echo $path; ?>" + icon + ".warning.png");
            $("#icon_without_status").attr("style", "");
            $("#icon_default").attr("style", "");
            $("#icon_ok").attr("style", "");
            $("#icon_bad").attr("style", "");
            $("#icon_warning").attr("style", "");
        }
    }
    
    function show_modules_not_learning_mode_context_help() {
        if ($("input[name='modo'][value=0]").is(':checked')) {
            $("#modules_not_learning_mode_context_help").show().css('padding-right','8px');
        }
        else {
            $("#modules_not_learning_mode_context_help").hide();
        }
    }


    $(document).ready (function() {

        var $id_agent = '<?php echo $id_agente; ?>';
        var previous_primary_group_select;
        $("#grupo").on('focus', function () {
            previous_primary_group_select = this.value;
        }).change(function() {
            if ($("#secondary_groups_selected option[value="+$("#grupo").val()+"]").length) {
                alert("<?php echo __('Secondary group cannot be primary too.'); ?>");
                $("#grupo").val(previous_primary_group_select);
            } else {
                previous_primary_group_select = this.value;
            }
        });

        $("select#id_os").pandoraSelectOS ();
        $('select#grupo').pandoraSelectGroupIcon ();

        

        var checked = $("#checkbox-cascade_protection").is(":checked");
        if (checked) {
            $("#cascade_protection_module").removeAttr("disabled");
        }
        else {
            $("#cascade_protection_module").attr("disabled", 'disabled');
        }

        $("#checkbox-cascade_protection").change(function () {
            var checked = $("#checkbox-cascade_protection").is(":checked");
    
            if (checked) {
                $("#cascade_protection_module").removeAttr("disabled");
            }
            else {
                $("#cascade_protection_module").val(0);
                $("#cascade_protection_module").attr("disabled", 'disabled');
            }
        });
        
        var safe_mode_checked = $("#checkbox-safe_mode").is(":checked");
        if (safe_mode_checked) {
            $("#safe_mode_module").removeAttr("disabled");
        }
        else {
            $("#safe_mode_module").attr("disabled", 'disabled');
        }
        
        $("#checkbox-safe_mode").change(function () {
            var safe_mode_checked = $("#checkbox-safe_mode").is(":checked");
    
            if (safe_mode_checked) {
                $("#safe_mode_module").removeAttr("disabled");
            }
            else {
                $("#safe_mode_module").val(0);
                $("#safe_mode_module").attr("disabled", 'disabled');
            }
        });

        if (typeof $id_agent !== 'undefined' && $id_agent !== '0') {
            paint_qrcode(
                "<?php echo ui_get_full_url('mobile/index.php?page=agent&id='.$id_agente); ?>",
                "#qr_code_agent_view",
                128,
                128
            );
        }
        $("#text-agente").prop('readonly', true);

    });
</script>
