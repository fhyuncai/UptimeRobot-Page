<?php

/**
 * UptimeRobot-Page
 * Status page based on UptimeRobot
 * Version: 2.0alpha4
 * Update time: 2024-03-28
 * Author: FHYunCai (https://yuncaioo.com)
 * Link: https://github.com/fhyuncai/UptimeRobot-Page
 **/

date_default_timezone_set('PRC');

/* Configuration */
$_configPageTitle = 'Service Status'; // 页面标题
$_configCacheExpirationDate = 15; // 缓存过期时间
$_configUptimerobotSecret = ''; // UptimeRobot API 密钥
$_configCronKey = 'fhyuncai_5e31d4'; // 监控密钥
/* Configuration */

if (getenv('UptimePageTitle') !== false) $_configPageTitle = getenv('UptimePageTitle');
if (getenv('UptimePageExpDate') !== false) $_configCacheExpirationDate = getenv('UptimePageExpDate');
if (getenv('UptimePageApiSecret') !== false) $_configUptimerobotSecret = getenv('UptimePageApiSecret');
if (getenv('UptimePageCronKey') !== false) $_configCronKey = getenv('UptimePageCronKey');

if (empty($_configUptimerobotSecret)) die('You must configure API Secret');

$recordTimeStart = explode(' ', microtime());

if (isset($_GET['cron']) && $_GET['cron'] == $_configCronKey) {
    updateData();
    $recordTimeEnd = explode(' ', microtime());
    echo 'update success (' . round($recordTimeEnd[0] + $recordTimeEnd[1] - ($recordTimeStart[0] + $recordTimeStart[1]), 5) . ')' . PHP_EOL;
} else {
    if (is_file(__DIR__ . '/cache_uptimepage.php')) {
        $cacheData = require(__DIR__ . '/cache_uptimepage.php');
        $lastUpdate = $cacheData['time'];
        if (time() < $lastUpdate + $_configCacheExpirationDate * 60) {
            echo $cacheData['content'];
            $recordTimeEnd = explode(' ', microtime());
            echo '<!--Exec:' . round($recordTimeEnd[0] + $recordTimeEnd[1] - ($recordTimeStart[0] + $recordTimeStart[1]), 5) . '-->';
        } else {
            echo updateData();
        }
    } else {
        echo updateData();
    }
}


function updateData()
{
    global $_configPageTitle;

    $pageViewContent = '';
    $dates = [];
    $ranges = '';
    $group = [];

    $startTimeStamp = strtotime(date('Ymd', strtotime('-30 days')));
    $endTimeStamp = strtotime(date('Ymd'));

    for ($d = 0; $d < 30; $d++) {
        $dates[] = date('Ymd', strtotime("-{$d} days"));
    }
    $dates = array_reverse($dates);

    foreach ($dates as $value) {
        $ranges .= strtotime($value) . '_' . strtotime('+1 days', strtotime($value)) . '-';
    }
    $ranges .= $startTimeStamp . '_' . $endTimeStamp;

    $requestApi = requestUptimerobot($startTimeStamp, $endTimeStamp, $ranges);

    $dataArr = json_decode($requestApi, true);

    if (isset($dataArr['stat']) && $dataArr['stat'] == 'ok') {
        $pageViewContent .= '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport"content="width=device-width,initial-scale=1"><title>' . $_configPageTitle . '</title><link rel="stylesheet"href="https://cdn.staticfile.net/twitter-bootstrap/4.5.3/css/bootstrap.min.css"><style>.theme{background:#eef2f6}.main{max-width:600px}.title{margin:1em 0;margin-left:1px}.desc{font-size:80%;margin-left:1px}.item-desc{font-size:.78em;margin-top:.5em;margin-bottom:.5em}.icon-status{height:1em;width:1em;display:block;margin-top:auto!important;margin-bottom:auto!important;background-repeat:no-repeat;border-radius:100%}.icon-uptime{height:1.6em;width:100%;margin:0 1px;opacity:.75}.icon-status.up,.icon-uptime.up{background:#6ac259}.icon-status.seemdown,.icon-uptime.seemdown{background:#ffdd57}.icon-status.down,.icon-uptime.down{background:#f05228}.icon-status.pause,.icon-uptime.pause{background:#111}.icon-uptime.nodata{background:#e5e5e5}</style></head>';
        $pageViewContent .= '<body class="theme"><div class="container main mb-4"><h4 class="font-weight-normal title">' . $_configPageTitle . '</h4><p class="desc">状态更新于 ' . date('Y-m-d H:i') . '<p>';
        $pageViewContent .= '<ul class="list-group">';

        foreach ($dataArr['monitors'] as $monitor) {
            $ranges = array_map(function ($str) {
                return round($str, 2);
            }, explode('-', $monitor['custom_uptime_ranges']));
            //$ranges = explode('-', $monitor['custom_uptime_ranges']);
            $average = array_pop($ranges);

            $daily = [];
            $map = [];
            foreach ($dates as $key => $value) {
                $map[$value] = $key;
                $daily[$key] = [
                    'date' => $value,
                    'uptime' => round($ranges[$key], 2),
                    'down' => ['times' => 0, 'duration' => 0]
                ];
            }

            $total = ['times' => 0, 'duration' => 0];
            foreach ($monitor['logs'] as $key => $value) {
                $date = date('Ymd', $value['datetime']);
                if ($value['type'] == 1 && isset($daily[$map[$date]])) {
                    $total['duration'] += $value['duration'];
                    $total['times'] += 1;
                    $daily[$map[$date]]['down']['duration'] += $value['duration'];
                    $daily[$map[$date]]['down']['times'] += 1;
                }
            }

            $monitorNameArr = explode('/', $monitor['friendly_name']);
            if (!in_array($monitorNameArr[0], $group)) {
                $group[] = $monitorNameArr[0];
                $pageViewContent .= '<li class="list-group-item"><b>' . $monitorNameArr[0] . '</b></li>';
            }

            $monitorGroupId = array_search($monitorNameArr[0], $group, true);

            switch ($monitor['status']) {
                case 2:
                    $monitorStatus = ['正常', 'up'];
                    break;
                case 8:
                    $monitorStatus = ['无法访问', 'seemdown'];
                    break;
                case 9:
                    $monitorStatus = ['无法访问', 'down'];
                    break;
                default:
                    $monitorStatus = ['暂停监控', 'pause'];
                    break;
            }

            $pageViewContent .= '<li class="list-group-item"><div class="d-flex justify-content-between">' . $monitorNameArr[1] . '<div class="icon-status ' . $monitorStatus[1] . '" data-toggle="icon-status" title="' . $monitorStatus[0] . '"></div></div>';
            $pageViewContent .= '<div class="item-desc">最近 30 天可用率 <strong>' . (($average != 0 || $average == 0 && $total['times'] !== 0) ? $average : '-') . '%</strong></div>';
            $pageViewContent .= '<div class="d-flex">';

            $lastStatus = 0; // 0=nodata, 1=up, 2=seemdown, 3=down
            foreach ($daily as $value) {
                $statusDesc = date('Y-m-d ', strtotime($value['date']));
                if ($value['down']['times'] !== 0) $statusDesc .= '故障 ' . $value['down']['times'] . ' 次，';
                if ($value['down']['duration'] !== 0) $statusDesc .= '累计 ' . formatDuration($value['down']['duration']) . '，';

                if ($value['uptime'] >= 100) {
                    $lastStatus = 1;
                    $statusClass = 'up';
                    $statusDesc .= '可用率 ' . $value['uptime'] . '%';
                } elseif ($value['uptime'] <= 95) {
                    if ($value['down']['times'] === 0 && $lastStatus === 0) {
                        $lastStatus = 0;
                        $statusClass = 'nodata';
                        $statusDesc .= '无数据';
                    } else {
                        $lastStatus = 3;
                        $statusClass = 'down';
                        $statusDesc .= '可用率 ' . $value['uptime'] . '%';
                    }
                } else {
                    $lastStatus = 2;
                    $statusClass = 'seemdown';
                    $statusDesc .= '可用率 ' . $value['uptime'] . '%';
                }
                $pageViewContent .= '<div class="icon-uptime ' . $statusClass . '" data-toggle="icon-uptime" title="' . $statusDesc . '"></div>';
            }
            $pageViewContent .= '</div></li>';
        }

        $pageViewContent .= '</ul></div><script src="https://cdn.staticfile.net/jquery/3.2.1/jquery.min.js"></script><script src="https://cdn.staticfile.net/popper.js/1.16.1/umd/popper.min.js"></script><script src="https://cdn.staticfile.net/twitter-bootstrap/4.5.3/js/bootstrap.min.js"></script><script>$(function(){$(\'[data-toggle="icon-status"]\').tooltip();$(\'[data-toggle="icon-uptime"]\').tooltip();});</script></body></html>';
        //$recordTimeEnd = explode(' ', microtime());
        //$pageViewContent .= '<!--Exec:' . round($recordTimeEnd[0] + $recordTimeEnd[1] - ($recordTimeStart[0] + $recordTimeStart[1]), 5) . '-->';

        file_put_contents(__DIR__ . '/cache_uptimepage.php', '<?php return ' . var_export(['time' => time(), 'content' => $pageViewContent], true) . ';');

        return $pageViewContent;
    } else {
        return 'Error: ' . $requestApi . PHP_EOL;
    }
}

function requestUptimerobot($start, $end, $ranges)
{
    global $_configUptimerobotSecret;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.uptimerobot.com/v2/getMonitors');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_POST, 1);
    $postData = [
        'api_key' => $_configUptimerobotSecret,
        'format' => 'json',
        'logs' => 1,
        'logs_start_date' => $start,
        'logs_end_date' => $end,
        'custom_uptime_ranges' => $ranges
    ];
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if (!empty($err)) {
        return false;
    } else {
        return $response;
    }
}

function formatDuration(int $seconds) {
    $minutes = 0;
    $hours = 0;
    if ($seconds >= 60) {
        $minutes = round($seconds / 60);
        $seconds = round($seconds % 60);
        if ($minutes >= 60) {
            $hours = round($minutes / 60);
            $minutes = round($minutes % 60);
        }
    }
    $text = "{$seconds} 秒";
    if ($minutes > 0) $text = "{$minutes} 分 {$text}";
    if ($hours > 0) $text = "{$hours} 小时 {$text}";
    return $text;
}

function checkJson($str)
{
    if (is_null(json_decode($str))) return false;
    return true;
}
