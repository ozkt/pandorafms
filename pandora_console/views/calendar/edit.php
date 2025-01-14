<?php
/**
 * Calendar: edit page
 *
 * @category   View
 * @package    Pandora FMS
 * @subpackage Alert
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

// Extras required.
\ui_require_css_file('wizard');
\enterprise_include_once('meta/include/functions_alerts_meta.php');
\enterprise_hook('open_meta_frame');

if (\is_metaconsole() === true) {
    \alerts_meta_print_header($tabs);
} else {
    // Header.
    \ui_print_page_header(
        // Title.
        __('Calendars Edit'),
        // Icon.
        'images/gm_alerts.png',
        // Return.
        false,
        // Help.
        'alert_special_days',
        // Godmode.
        true,
        // Options.
        $tabs
    );
}

$is_management_allowed = \is_management_allowed();
if ($is_management_allowed === false) {
    if (\is_metaconsole() === false) {
        $url_link = '<a target="_blank" href="'.ui_get_meta_url($url).'">';
        $url_link .= __('metaconsole');
        $url_link .= '</a>';
    } else {
        $url_link = __('any node');
    }

    \ui_print_warning_message(
        __(
            'This node is configured with centralized mode. All alert calendar information is read only. Go to %s to manage it.',
            $url_link
        )
    );
}

if (empty($message) === false) {
    echo $message;
}

$return_all_group = false;

if (users_can_manage_group_all('LM') === true) {
    $return_all_group = true;
}

$inputs = [];

// Name.
$inputs[] = [
    'label'     => __('Name'),
    'arguments' => [
        'type'     => 'text',
        'name'     => 'name',
        'required' => true,
        'value'    => $calendar->name(),
    ],
];

// Group.
$inputs[] = [
    'label'     => __('Group'),
    'arguments' => [
        'type'           => 'select_groups',
        'returnAllGroup' => $return_all_group,
        'name'           => 'id_group',
        'selected'       => $calendar->id_group(),
        'required'       => true,
    ],
];

// Description.
$inputs[] = [
    'label'     => __('Description'),
    'arguments' => [
        'type'     => 'textarea',
        'name'     => 'description',
        'required' => false,
        'value'    => $calendar->description(),
        'rows'     => 50,
        'columns'  => 30,
    ],
];


if ($is_management_allowed === true) {
    // Submit.
    $inputs[] = [
        'arguments' => [
            'name'       => 'button',
            'label'      => (($create === true) ? __('Create') : __('Update')),
            'type'       => 'submit',
            'attributes' => 'class="sub next"',
        ],
    ];
}

// Print form.
HTML::printForm(
    [
        'form'   => [
            'action' => $url.'&op=edit&action=save&id='.$calendar->id(),
            'method' => 'POST',
        ],
        'inputs' => $inputs,
    ],
    false,
    true
);

\enterprise_hook('close_meta_frame');
