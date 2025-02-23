<?php
/**
 * Heatmap class.
 *
 * @category   Heatmap
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
 * Copyright (c) 2005-2022 Artica Soluciones Tecnologicas
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
class Heatmap
{

    /**
     * Heatmap type.
     *
     * @var integer
     */
    protected $type = null;

    /**
     * Heatmap filter.
     *
     * @var array
     */
    protected $filter = null;

    /**
     * Allowed methods to be called using AJAX request.
     *
     * @var array
     */
    protected $AJAXMethods = [
        'showHeatmap',
        'updateHeatmap',
        'getDataJson',
    ];

    /**
     * Heatmap random id.
     *
     * @var string
     */
    protected $randomId = null;

    /**
     * Heatmap refresh.
     *
     * @var integer
     */
    protected $refresh = null;

    /**
     * Heatmap width.
     *
     * @var integer
     */
    protected $width = null;

    /**
     * Heatmap height.
     *
     * @var integer
     */
    protected $height = null;

    /**
     * Heatmap search.
     *
     * @var string
     */
    protected $search = null;

    /**
     * Heatmap group.
     *
     * @var integer
     */
    protected $group = null;


    /**
     * Constructor function
     *
     * @param integer $type     Heatmap type.
     * @param array   $filter   Heatmap filter.
     * @param string  $randomId Heatmap random id.
     * @param integer $refresh  Heatmap refresh.
     * @param integer $width    Width.
     * @param integer $height   Height.
     * @param string  $search   Heatmap search.
     * @param integer $group    Heatmap group.
     */
    public function __construct(
        int $type=0,
        array $filter=[],
        string $randomId=null,
        int $refresh=300,
        int $width=0,
        int $height=0,
        string $search=null,
        int $group=1
    ) {
        $this->type = $type;
        $this->filter = $filter;
        (empty($randomId) === true) ? $this->randomId = uniqid() : $this->randomId = $randomId;
        $this->refresh = $refresh;
        $this->width = $width;
        $this->height = $height;
        $this->search = $search;
        $this->group = $group;
    }


    /**
     * Run.
     *
     * @return void
     */
    public function run()
    {
        ui_require_css_file('heatmap');

        $settings = [
            'type'     => 'POST',
            'dataType' => 'html',
            'url'      => ui_get_full_url(
                'ajax.php',
                false,
                false,
                false
            ),
            'data'     => [
                'page'     => 'operation/heatmap',
                'method'   => 'showHeatmap',
                'randomId' => $this->randomId,
                'type'     => $this->type,
                'filter'   => $this->filter,
                'refresh'  => $this->refresh,
                'search'   => $this->search,
                'group'    => $this->group,
            ],
        ];

        echo '<div id="div_'.$this->randomId.'" class="mainDiv">';
        ?>
            <script type="text/javascript">
                $(document).ready(function() {
                    const randomId = '<?php echo $this->randomId; ?>';
                    const refresh = '<?php echo $this->refresh; ?>';
                    let setting = <?php echo json_encode($settings); ?>;
                    setting['data']['height'] = $(`#div_${randomId}`).height() + 10;
                    setting['data']['width'] = $(`#div_${randomId}`).width();

                    // Initial charge.
                    ajaxRequest(
                        `div_${randomId}`,
                        setting
                    );

                    // Refresh.
                    setInterval(
                        function() {
                            refreshMap();
                        },
                        (refresh * 1000)
                    );

                    function refreshMap() {
                        $.ajax({
                            type: 'GET',
                            url: '<?php echo ui_get_full_url('ajax.php', false, false, false); ?>',
                            data: {
                                page: "operation/heatmap",
                                method: 'getDataJson',
                                randomId: randomId,
                                type: setting['data']['type'],
                                refresh: setting['data']['refresh'],
                                filter: setting['data']['filter'],
                                search: setting['data']['search'],
                                group: setting['data']['group']
                            },
                            dataType: 'json',
                            success: function(data) {
                                const total = Object.keys(data).length;
                                if (total === $(`#svg_${randomId} rect`).length) {
                                    // Object to array.
                                    let lista = Object.values(data);
                                    // randomly sort.
                                    lista = lista.sort(function() {return Math.random() - 0.5});

                                    const countPerSecond = total / refresh;

                                    let cont = 0;
                                    let limit = countPerSecond - 1;

                                    const timer = setInterval(
                                        function() {
                                            while (cont <= limit) {
                                                $(`#${randomId}_${lista[cont]['id']}`).removeClass();
                                                $(`#${randomId}_${lista[cont]['id']}`).addClass(`${lista[cont]['status']} hover`);

                                                cont++;
                                            }
                                            limit = limit + countPerSecond;
                                        },
                                        1000
                                    );

                                    setTimeout(
                                        function(){
                                            clearInterval(timer);
                                        },
                                        (refresh * 1000)
                                    );
                                } else {
                                    location.reload();
                                }
                            }
                        });
                    }
                });
            </script>
        <?php
        echo '</div>';
    }


    /**
     * Setter for filter
     *
     * @param array $filter Filter.
     *
     * @return void
     */
    public function setFilter(array $filter)
    {
        $this->filter = $filter;
    }


    /**
     * Setter for type
     *
     * @param integer $type Type.
     *
     * @return void
     */
    public function setType(int $type)
    {
        $this->type = $type;
    }


    /**
     * Setter for refresh
     *
     * @param integer $refresh Refresh.
     *
     * @return void
     */
    public function setRefresh(int $refresh)
    {
        $this->refresh = $refresh;
    }


    /**
     * Getter for randomId
     *
     * @return string
     */
    public function getRandomId()
    {
        return $this->randomId;
    }


    /**
     * Get all agents
     *
     * @return array
     */
    protected function getAllAgents()
    {
        $filter['disabled'] = 0;

        $alias = '';
        if (empty($this->search) === false) {
            $alias = ' AND alias LIKE "%'.$this->search.'%"';
        }

        $id_grupo = '';
        if (empty($this->filter) === false && current($this->filter) != 0) {
            $id_grupo = ' AND id_grupo IN ('.implode(',', $this->filter).')';
        }

        // All agents.
        $sql = sprintf(
            'SELECT DISTINCT id_agente as id,alias,id_grupo,normal_count,warning_count,critical_count, unknown_count,notinit_count,total_count,fired_count,
            (SELECT last_status_change FROM tagente_estado WHERE id_agente = tagente.id_agente ORDER BY last_status_change DESC LIMIT 1) AS last_status_change
            FROM tagente WHERE `disabled` = 0 %s %s ORDER BY id_grupo,id_agente ASC',
            $alias,
            $id_grupo
        );

        $result = db_get_all_rows_sql($sql);

        $agents = [];
        // Agent status.
        foreach ($result as $key => $agent) {
            if ($agent['total_count'] === 0 || $agent['total_count'] === $agent['notinit_count']) {
                $status = 'notinit';
            } else if ($agent['critical_count'] > 0) {
                $status = 'critical';
            } else if ($agent['warning_count'] > 0) {
                $status = 'warning';
            } else if ($agent['unknown_count'] > 0) {
                $status = 'unknown';
            } else {
                $status = 'normal';
            }

            if ($agent['last_status_change'] != 0) {
                $seconds = (time() - $agent['last_status_change']);

                if ($seconds >= SECONDS_1DAY) {
                    $status .= '_10';
                } else if ($seconds >= 77760) {
                    $status .= '_9';
                } else if ($seconds >= 69120) {
                    $status .= '_8';
                } else if ($seconds >= 60480) {
                    $status .= '_7';
                } else if ($seconds >= 51840) {
                    $status .= '_6';
                } else if ($seconds >= 43200) {
                    $status .= '_5';
                } else if ($seconds >= 34560) {
                    $status .= '_4';
                } else if ($seconds >= 25920) {
                    $status .= '_3';
                } else if ($seconds >= 17280) {
                    $status .= '_2';
                } else if ($seconds >= 8640) {
                    $status .= '_1';
                }
            }

            $agents[$key] = $agent;
            $agents[$key]['status'] = $status;
        }

        return $agents;
    }


    /**
     * Get all modules
     *
     * @return array
     */
    protected function getAllModulesByGroup()
    {
        $filter_group = '';
        if (empty($this->filter) === false && current($this->filter) != -1) {
            $filter_group = 'AND am.id_module_group IN ('.implode(',', $this->filter).')';
        }

        $filter_name = '';
        if (empty($this->search) === false) {
            $filter_name = 'AND nombre LIKE "%'.$this->search.'%"';
        }

        // All modules.
        $sql = sprintf(
            'SELECT am.id_agente_modulo AS id, ae.known_status AS `status`, am.id_module_group AS id_grupo, ae.last_status_change FROM tagente_modulo am
            INNER JOIN tagente_estado ae ON am.id_agente_modulo = ae.id_agente_modulo
            WHERE am.disabled = 0 %s %s GROUP BY am.id_module_group, am.id_agente_modulo',
            $filter_group,
            $filter_name
        );

        $result = db_get_all_rows_sql($sql);

        // Module status.
        foreach ($result as $key => $module) {
            $status = '';
            switch ($module['status']) {
                case AGENT_MODULE_STATUS_CRITICAL_BAD:
                case AGENT_MODULE_STATUS_CRITICAL_ALERT:
                case 1:
                case 100:
                    $status = 'critical';
                break;

                case AGENT_MODULE_STATUS_NORMAL:
                case AGENT_MODULE_STATUS_NORMAL_ALERT:
                case 0:
                case 300:
                    $status = 'normal';
                break;

                case AGENT_MODULE_STATUS_WARNING:
                case AGENT_MODULE_STATUS_WARNING_ALERT:
                case 2:
                case 200:
                    $status = 'warning';
                break;

                default:
                case AGENT_MODULE_STATUS_UNKNOWN:
                case 3:
                    $status = 'unknown';
                break;
                case AGENT_MODULE_STATUS_NOT_INIT:
                case 5:
                    $status = 'notinit';
                break;
            }

            if ($module['last_status_change'] != 0) {
                $seconds = (time() - $module['last_status_change']);

                if ($seconds >= SECONDS_1DAY) {
                    $status .= '_10';
                } else if ($seconds >= 77760) {
                    $status .= '_9';
                } else if ($seconds >= 69120) {
                    $status .= '_8';
                } else if ($seconds >= 60480) {
                    $status .= '_7';
                } else if ($seconds >= 51840) {
                    $status .= '_6';
                } else if ($seconds >= 43200) {
                    $status .= '_5';
                } else if ($seconds >= 34560) {
                    $status .= '_4';
                } else if ($seconds >= 25920) {
                    $status .= '_3';
                } else if ($seconds >= 17280) {
                    $status .= '_2';
                } else if ($seconds >= 8640) {
                    $status .= '_1';
                }
            }

            $result[$key]['status'] = $status;
        }

        return $result;
    }


    /**
     * Get all modules
     *
     * @return array
     */
    protected function getAllModulesByTag()
    {
        $filter_tag = '';
        if (empty($this->filter) === false && $this->filter[0] !== '0') {
            $tags = implode(',', $this->filter);
            $filter_tag .= ' AND tm.id_tag IN ('.$tags.')';
        }

        $filter_name = '';
        if (empty($this->search) === false) {
            $filter_name = 'AND nombre LIKE "%'.$this->search.'%"';
        }

        // All modules.
        $sql = sprintf(
            'SELECT ae.id_agente_modulo AS id, ae.known_status AS `status`, tm.id_tag AS id_grupo, ae.last_status_change FROM tagente_estado ae 
            INNER JOIN ttag_module tm ON tm.id_agente_modulo = ae.id_agente_modulo
            WHERE 1=1 %s %s GROUP BY tm.id_tag, ae.id_agente_modulo',
            $filter_tag,
            $filter_name
        );

        $result = db_get_all_rows_sql($sql);

        // Module status.
        foreach ($result as $key => $module) {
            $status = '';
            switch ($module['status']) {
                case AGENT_MODULE_STATUS_CRITICAL_BAD:
                case AGENT_MODULE_STATUS_CRITICAL_ALERT:
                case 1:
                case 100:
                    $status = 'critical';
                break;

                case AGENT_MODULE_STATUS_NORMAL:
                case AGENT_MODULE_STATUS_NORMAL_ALERT:
                case 0:
                case 300:
                    $status = 'normal';
                break;

                case AGENT_MODULE_STATUS_WARNING:
                case AGENT_MODULE_STATUS_WARNING_ALERT:
                case 2:
                case 200:
                    $status = 'warning';
                break;

                default:
                case AGENT_MODULE_STATUS_UNKNOWN:
                case 3:
                    $status = 'unknown';
                break;
                case AGENT_MODULE_STATUS_NOT_INIT:
                case 5:
                    $status = 'notinit';
                break;
            }

            if ($module['last_status_change'] != 0) {
                $seconds = (time() - $module['last_status_change']);

                if ($seconds >= SECONDS_1DAY) {
                    $status .= '_10';
                } else if ($seconds >= 77760) {
                    $status .= '_9';
                } else if ($seconds >= 69120) {
                    $status .= '_8';
                } else if ($seconds >= 60480) {
                    $status .= '_7';
                } else if ($seconds >= 51840) {
                    $status .= '_6';
                } else if ($seconds >= 43200) {
                    $status .= '_5';
                } else if ($seconds >= 34560) {
                    $status .= '_4';
                } else if ($seconds >= 25920) {
                    $status .= '_3';
                } else if ($seconds >= 17280) {
                    $status .= '_2';
                } else if ($seconds >= 8640) {
                    $status .= '_1';
                }
            }

            $result[$key]['status'] = $status;
        }

        return $result;
    }


    /**
     * GetData
     *
     * @return array
     */
    public function getData()
    {
        switch ($this->type) {
            case 2:
                $data = $this->getAllModulesByGroup();
            break;

            case 1:
                $data = $this->getAllModulesByTag();
            break;

            case 0:
            default:
                $data = $this->getAllAgents();
            break;
        }

        return $data;
    }


    /**
     * GetDataJson
     *
     * @return json
     */
    public function getDataJson()
    {
        $return = $this->getData();
        echo json_encode($return);
        return '';
    }


    /**
     * Get class by status
     *
     * @param integer $status Status.
     *
     * @return string
     */
    protected function statusColour(int $status)
    {
        switch ($status) {
            case AGENT_STATUS_CRITICAL:
                $return = 'critical';
            break;

            case AGENT_STATUS_WARNING:
                $return = 'warning';
            break;

            case AGENT_STATUS_UNKNOWN:
                $return = 'unknown';
            break;

            case AGENT_STATUS_NOT_INIT:
                $return = 'notinit';
            break;

            case AGENT_STATUS_NORMAL:
            default:
                $return = 'normal';
            break;
        }

        return $return;
    }


    /**
     * Get max. number of y-axis
     *
     * @param integer $total    Total.
     * @param float   $relation Aspect relation.
     *
     * @return integer
     */
    protected function getYAxis(int $total, float $relation)
    {
        $yAxis = sqrt(($total / $relation));
        return $yAxis;

    }


    /**
     * Checks if target method is available to be called using AJAX.
     *
     * @param string $method Target method.
     *
     * @return boolean True allowed, false not.
     */
    public function ajaxMethod(string $method):bool
    {
        return in_array($method, $this->AJAXMethods);
    }


    /**
     * ShowHeatmap
     *
     * @return void
     */
    public function showHeatmap()
    {
        $result = $this->getData();

        if (empty($result) === true) {
            echo '<div style="position: absolute; top:70px; left:20px">'.__('No data found').'</div>';
            return;
        }

        $count_result = count($result);

        $scale = ($this->width / $this->height);
        $Yaxis = $this->getYAxis($count_result, $scale);
        if ($count_result <= 3) {
            $Xaxis = $count_result;
            $Yaxis = 1;
        } else {
            $Xaxis = (int) ceil($Yaxis * $scale);
            $Yaxis = ceil($Yaxis);
        }

        $viewBox = sprintf(
            '0 0 %d %d',
            $Xaxis,
            $Yaxis
        );

        echo '<svg id="svg_'.$this->randomId.'" width="'.$this->width.'"
            height="'.$this->height.'" viewBox="'.$viewBox.'">';

        $groups = [];
        $contX = 0;
        $contY = 0;
        foreach ($result as $value) {
            echo '<rect id="'.$this->randomId.'_'.$value['id'].'" class="'.$value['status'].' hover"
                width="1" height="1" x ="'.$contX.' "y="'.$contY.'" />';

            $contX++;
            if ($contX >= $Xaxis) {
                $contY++;
                $contX = 0;
            }

            if (empty($groups[$value['id_grupo']]) === true) {
                $groups[$value['id_grupo']] = 1;
            } else {
                $groups[$value['id_grupo']] += 1;
            }
        }

        ?>
            <script type="text/javascript">
                $('rect').click(function() {
                    const type = <?php echo $this->type; ?>;
                    const hash = '<?php echo $this->randomId; ?>';
                    const id = this.id.replace(`${hash}_`, '');

                    $("#info_dialog").dialog({
                        resizable: true,
                        draggable: true,
                        modal: true,
                        closeOnEscape: true,
                        height: 400,
                        width: 530,
                        title: '<?php echo __('Info'); ?>',
                        open: function() {
                            $.ajax({
                                type: 'GET',
                                url: '<?php echo ui_get_full_url('ajax.php', false, false, false); ?>',
                                data: {
                                    page: "include/ajax/heatmap.ajax",
                                    getInfo: 1,
                                    type: type,
                                    id: id,
                                },
                                dataType: 'html',
                                success: function(data) {
                                    $('#info_dialog').empty();
                                    $('#info_dialog').append(data);
                                }
                            });
                        },
                    });
                });
            </script>
        <?php
        if (count($groups) > 1 && $this->group === 1) {
            $x_back = 0;
            $y_back = 0;

            if ($count_result <= 100) {
                $fontSize = 'small-size';
                $stroke = 'small-stroke';
            } else {
                $fontSize = 'big-size';
                $stroke = 'big-stroke';
            }

            echo '<polyline points="0,0 '.$Xaxis.',0" class="polyline '.$stroke.'" />';
            foreach ($groups as $key => $group) {
                $name = '';
                switch ($this->type) {
                    case 2:
                        $name = modules_get_modulegroup_name($key);
                    break;

                    case 1:
                        $name = tags_get_name($key);
                    break;

                    case 0:
                    default:
                        $name = groups_get_name($key);
                    break;
                }

                if (($x_back + $group) <= $Xaxis) {
                    $x_position = ($x_back + $group);
                    $y_position = $y_back;

                    if ($y_back === 0 && $x_back === 0) {
                        $points = sprintf(
                            '%d,%d %d,%d',
                            $x_back,
                            $y_back,
                            $x_back,
                            ($y_back + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';
                    }

                    $points = sprintf(
                        '%d,%d %d,%d %d,%d',
                        $x_back,
                        ($y_position + 1),
                        $x_position,
                        ($y_position + 1),
                        $x_position,
                        $y_back
                    );

                    echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                    // Name.
                    echo '<text x="'.((($x_position - $x_back) / 2) + $x_back).'" y="'.($y_position + 1).'"
                        class="'.$fontSize.'">'.$name.'</text>';

                    $x_back = $x_position;
                    if ($x_position === $Xaxis) {
                        $points = sprintf(
                            '%d,%d %d,%d',
                            $x_position,
                            $y_back,
                            $x_position,
                            ($y_back + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                        $y_back++;
                        $x_back = 0;
                    }
                } else {
                    $round = (int) floor(($x_back + $group) / $Xaxis);
                    $y_position = ($round + $y_back);

                    if ($round === 1) {
                        // One line.
                        $x_position = (($x_back + $group) - $Xaxis);

                        if ($x_position <= $x_back) {
                            // Bottom line.
                            $points = sprintf(
                                '%d,%d %d,%d',
                                $x_back,
                                $y_position,
                                $Xaxis,
                                ($y_position)
                            );

                            echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';
                        }

                        // Bottom of last line.
                        $points = sprintf(
                            '%d,%d %d,%d',
                            0,
                            ($y_position + 1),
                            $x_position,
                            ($y_position + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                        // Name.
                        echo '<text x="'.(($x_position) / 2).'" y="'.($y_position + 1).'"
                            class="'.$fontSize.'">'.$name.'</text>';

                        // Bottom-right of last line.
                        $points = sprintf(
                            '%d,%d %d,%d',
                            $x_position,
                            ($y_position),
                            $x_position,
                            ($y_position + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                        if ($x_position > $x_back) {
                            // Bottom-top of last line.
                            $points = sprintf(
                                '%d,%d %d,%d',
                                $x_position,
                                ($y_position),
                                $Xaxis,
                                ($y_position)
                            );

                            echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';
                        }
                    } else {
                        // Two or more lines.
                        $x_position = (($x_back + $group) - ($Xaxis * $round));

                        if ($x_position === 0) {
                            $x_position = $Xaxis;
                        }

                        // Bottom of last line.
                        $points = sprintf(
                            '%d,%d %d,%d',
                            0,
                            ($y_position + 1),
                            $x_position,
                            ($y_position + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                        // Bottom-right of last line.
                        $points = sprintf(
                            '%d,%d %d,%d',
                            $x_position,
                            ($y_position),
                            $x_position,
                            ($y_position + 1)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';

                        // Name.
                        echo '<text x="'.(($x_position) / 2).'" y="'.($y_position + 1).'"
                            class="'.$fontSize.'">'.$name.'</text>';

                        // Bottom-top of last line.
                        $points = sprintf(
                            '%d,%d %d,%d',
                            $x_position,
                            ($y_position),
                            $Xaxis,
                            ($y_position)
                        );

                        echo '<polyline points="'.$points.'" class="polyline '.$stroke.'" />';
                    }

                    if ($x_position === $Xaxis) {
                        $x_position = 0;
                    }

                    $x_back = $x_position;
                    $y_back = $y_position;
                }
            }
        }

        echo '</svg>';

        // Dialog.
        echo '<div id="info_dialog" style="padding:15px" class="invisible"></div>';
    }


}
