<?php

/**
 * UptimeRobot-Page
 * Status page based on UptimeRobot
 * Version: 1.42
 * Update time: 2022-07-17
 * Author: FHYunCai (https://yuncaioo.com)
 * Link: https://github.com/fhyuncai/UptimeRobot-Page
 **/

date_default_timezone_set('PRC');

/* Configuration Start */

define('DATADIR', 'uptimerobot'); // Data directory

// Note: The remaining configuration items are in the ([Data directory]/config.json)

/* Configuration End */

$record_time_s = explode(' ', microtime());

if (!is_dir(__DIR__ . '/' . DATADIR . '/cache/' . date("Y/m/d"))) mkdir(__DIR__ . '/' . DATADIR . '/cache/' . date("Y/m/d"), 0755, true);

if (!is_file(__DIR__ . '/' . DATADIR . '/config.json')) {
    file_put_contents(__DIR__ . '/' . DATADIR . '/config.json', json_encode(['page_title' => 'Service Status', 'cache_timeout' => 15, 'uptimerobot_apikey' => '', 'cron_key' => 'fhyuncai']));
}

if (empty(getenv('Page_Config'))) {
    $config_json = file_get_contents(__DIR__ . '/' . DATADIR . '/config.json');
} else {
    $config_json = getenv('Page_Config');
}
check_json($config_json);

$config_json = json_decode($config_json);
$config_arr = [
    'page_title' => $config_json->page_title ? $config_json->page_title : 'Service Status',
    'cache_timeout' => $config_json->cache_timeout ? $config_json->cache_timeout : 15,
    'uptimerobot_apikey' => $config_json->uptimerobot_apikey ? $config_json->uptimerobot_apikey : '',
    'cron_key' => $config_json->cron_key ? $config_json->cron_key : 'fhyuncai'
];

if (empty($config_arr['uptimerobot_apikey'])) die('You must configure the Uptimerobot key in file <i>' . DATADIR . '/config.json</i>');

//Read and update data
if (is_file(__DIR__ . '/' . DATADIR . '/cache/data.json') && $_GET['cron'] != $config_arr['cron_key']) {
    $json_last_time = json_decode(file_get_contents(__DIR__ . '/' . DATADIR . '/cache/data.json'))->time;
    if ($json_last_time < time() - ($config_arr['cache_timeout'] * 60)) {
        update_data();
        $json_last_time = time();
    }
} else {
    update_data();
    $json_last_time = time();
}


if ($_GET['cron'] != $config_arr['cron_key']) {
    if (!is_file(__DIR__ . '/' . DATADIR . '/cache/cache.json')) calculate();
    $dataArr = json_decode(file_get_contents(__DIR__ . '/' . DATADIR . '/cache/cache.json'), true);
    if (isset($dataArr['act_per_day_arr'])) $act_per_day_arr = $dataArr['act_per_day_arr'];
    if (isset($dataArr['act_per_arr'])) $act_per_arr = $dataArr['act_per_arr'];


    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport"content="width=device-width, initial-scale=1"><title>' . $config_arr['page_title'] . '</title><link rel="stylesheet"href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/css/bootstrap.min.css"><script src="https://cdn.jsdelivr.net/npm/jquery@3.2.1/dist/jquery.min.js"></script><script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script><script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.min.js"></script><style>.theme{background:#eef2f6}.main{max-width:600px}.title{margin:1em 0;margin-left:1px}.desc{font-size:80%;margin-left:1px}.item-desc{font-size:.8em;margin-top:.8em;margin-bottom:.5em}.icon-status{height:1em;width:1em;display:block;margin-top:auto!important;margin-bottom:auto!important;background-repeat:no-repeat;border-radius:100%}.icon-uptime{height:1.5em;width:100%;margin:0 1px;opacity:.75}.icon-status.up,.icon-uptime.up{background:#6ac259}.icon-status.seemdown,.icon-uptime.seemdown{background:#ffdd57}.icon-status.down,.icon-uptime.down{background:#f05228}.icon-status.pause,.icon-uptime.pause{background:#111}.icon-uptime.nodata{background:#e5e5e5}</style></head>';
    echo '<body class="theme"><div class="container main mb-4"><h4 class="font-weight-normal title">' . $config_arr['page_title'] . '</h4><p class="desc">Last check at ' . date('Y-m-d H:i', $json_last_time) . '<p>';
    echo '<ul class="list-group">';

    $json_decode = json_decode(file_get_contents(__DIR__ . '/' . DATADIR . '/cache/data.json'));

    $group = [];
    foreach ($json_decode->monitors as $monitor) {
        $monitor_name_arr = explode('/', $monitor->name);
        if (!in_array($monitor_name_arr[0], $group)) {
            $group[] = $monitor_name_arr[0];
            echo '<li class="list-group-item"><b>' . $monitor_name_arr[0] . '</b></li>';
        }
        switch ($monitor->status) {
            case 2:
                $status = ['status' => 'Operational', 'class' => 'up'];
                break;
            case 8:
                $status = ['status' => 'Seems down', 'class' => 'seemdown'];
                break;
            case 9:
                $status = ['status' => 'Down', 'class' => 'down'];
                break;
            default:
                $status = ['status' => 'Paused', 'class' => 'pause'];
                break;
        }
        echo '<li class="list-group-item"><div class="d-flex justify-content-between">' . $monitor_name_arr[1] . '<div class="icon-status ' . $status['class'] . '" data-toggle="icon-status" title="' . $status['status'] . '"></div></div>';
        $uptime_per = isset($act_per_arr[$monitor->name]) ? $act_per_arr[$monitor->name] : 100;
        echo '<div class="item-desc"><strong>' . $uptime_per . '%</strong> uptime for the last 30 days.</div>';
        echo '<div class="d-flex">';
        for ($i = 30; $i >= 1; $i--) { // 倒序排列
            $uptime_day_per = $act_per_day_arr[$monitor->name][$i];
            if (!isset($uptime_day_per)) {
                $uptime_status = 'nodata';
            } elseif ($uptime_day_per == 100) {
                $uptime_status = 'up';
            } elseif ($uptime_day_per >= 95 && $uptime_day_per < 100) {
                $uptime_status = 'seemdown';
            } elseif ($uptime_day_per > 0 && $uptime_day_per < 95) {
                $uptime_status = 'down';
            } elseif ($uptime_day_per == 0) {
                $uptime_status = 'pause';
            }
            echo '<div class="icon-uptime ' . $uptime_status . '"></div>';
        }
        echo '</div></li>';
    }
    echo '</ul></div><script>$(function(){$(\'[data-toggle="icon-status"]\').tooltip()});</script></body></html>';
    $record_time_e = explode(' ', microtime());
    echo '<!--Exec time: ' . round($record_time_e[0] + $record_time_e[1] - ($record_time_s[0] + $record_time_s[1]), 5) . '-->';
} else {
    $record_time_e = explode(' ', microtime());
    echo 'Success (' . round($record_time_e[0] + $record_time_e[1] - ($record_time_s[0] + $record_time_s[1]), 5) . ')';
}


/* Functions */
function curl_uptimerobot()
{
    global $config_arr;
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.uptimerobot.com/v2/getMonitors',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'api_key=' . $config_arr['uptimerobot_apikey'] . '&format=json&logs=1',
        CURLOPT_HTTPHEADER => [
            'cache-control: no-cache',
            'content-type: application/x-www-form-urlencoded'
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
        die('Curl error');
    } else {
        return $response;
    }
}

function update_data()
{
    $json_curl = curl_uptimerobot();
    if (is_file(__DIR__ . '/' . DATADIR . '/cache/data.json')) {
        $json_old_time = json_decode(file_get_contents(__DIR__ . '/' . DATADIR . '/cache/data.json'))->time;
        copy(__DIR__ . '/' . DATADIR . '/cache/data.json', __DIR__ . '/' . DATADIR . '/cache/' . date("Y/m/d", $json_old_time) . '/' . $json_old_time . '.json');
    }
    $json_arr = json_decode($json_curl);
    foreach ($json_arr->monitors as $value) {
        $json_arr_monitors[] = ['name' => $value->friendly_name, 'url' => $value->url, 'status' => $value->status];
    }
    file_put_contents(__DIR__ . '/' . DATADIR . '/cache/data.json', json_encode(array('time' => time(), 'monitors' => $json_arr_monitors)));
    calculate();
    return;
}

function calculate()
{
    // Calculate availability
    for ($i = 1; $i <= 30; $i++) {
        $date = date("Y/m/d", strtotime('-' . $i . ' day'));
        if (is_dir(__DIR__ . '/' . DATADIR . '/cache/' . $date)) {
            $cache_file_arr = scandir(__DIR__ . '/' . DATADIR . '/cache/' . $date);
            foreach ($cache_file_arr as $value) {
                if ($value == '.' || $value == '..') continue;
                if (!strstr($value, '.json')) continue;
                $json_str = file_get_contents(__DIR__ . '/' . DATADIR . '/cache/' . $date . '/' . $value);
                check_json($json_str);
                foreach (json_decode($json_str)->monitors as $value) {
                    $cache_data_arr[$value->name][] = $value->status;
                }
            }
            if (isset($cache_data_arr)) {
                foreach ($cache_data_arr as $key => $value) {
                    $check_num = count($value);
                    $check_up = 0;
                    foreach ($value as $value) {
                        if ($value == 2) $check_up++;
                    }
                    $act_per_day_arr[$key][$i] = round($check_up / $check_num * 100, 2); // 每天可用性
                }
                unset($cache_data_arr);
            }
        }
    }
    $saveData = ['act_per_day_arr' => $act_per_day_arr];

    if (isset($act_per_day_arr)) {
        foreach ($act_per_day_arr as $key => $value) {
            $act_per_arr[$key] = round(array_sum($value) / count($value), 2); // 30天可用性
        }
        $saveData['act_per_arr'] = $act_per_arr;
    }

    file_put_contents(__DIR__ . '/' . DATADIR . '/cache/cache.json', json_encode($saveData));
}

function check_json($str)
{
    if (is_null(json_decode($str))) die('Json error');
    return;
}
