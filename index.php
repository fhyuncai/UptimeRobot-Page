<?php

/**
 * UptimeRobot-Page
 * A status page based on UptimeRobot
 * Version: 1.0.3
 * Update time: 2020-01-29
 * Author: FHYunCai(https://yuncaioo.com)
 **/

$page['title'] = 'Service Status'; //Page title
$cache['filename'] = 'uptimerobot.json'; //Cache filename
$cache['timeout'] = 15; //Cache timeout (Minutes)
$setting['uptimerobot_apikey'] = ''; //Your UptimeRobot APIkey 
$setting['cron_key'] = 'fhyuncai'; //Cron key

date_default_timezone_set("PRC");

$page['title'] = getenv('Page_Title')?getenv('Page_Title'):$page['title'];
$cache['filename'] = getenv('Cache_Filename')?getenv('Cache_Filename'):$cache['filename'];
$cache['timeout'] = getenv('Cache_Timeout')?getenv('Cache_Timeout'):$cache['timeout'];
$setting['uptimerobot_apikey'] = getenv('UptimeRobot_APIKey')?getenv('UptimeRobot_APIKey'):$setting['uptimerobot_apikey'];
$setting['cron_key'] = getenv('Cron_Key')?getenv('Cron_Key'):$setting['cron_key'];

function curl_uptimerobot(){
    global $setting;
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.uptimerobot.com/v2/getMonitors',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'api_key='.$setting['uptimerobot_apikey'].'&format=json&logs=1',
        CURLOPT_HTTPHEADER => array(
            'cache-control: no-cache',
            'content-type: application/x-www-form-urlencoded'
        ),
    ));
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if($err){
        return 'error';
    }else{
        return $response;
    }
}

if(file_exists(__DIR__.'/'.$cache['filename'])){
    $json_last_time = json_decode(file_get_contents(__DIR__.'/'.$cache['filename']))->time;
    if($json_last_time < time()-($cache['timeout']*60) || $_GET['cron'] == $setting['cron_key']){
        $json_curl = curl_uptimerobot();
        if($json_curl == 'error'){
            die('Curl error!');
        }else{
            $json_put = file_put_contents(__DIR__.'/'.$cache['filename'],json_encode(array('time' => time(),'content' => json_decode($json_curl))));
            $json_last_time = time();
        }
    }
}else{
    $json_curl = curl_uptimerobot();
    if($json_curl == 'error'){
        die('Curl error!');
    }else{
        $json_put = file_put_contents(__DIR__.'/'.$cache['filename'],json_encode(array('time' => time(),'content' => json_decode($json_curl))));
        $json_last_time = time();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title><?php echo $page['title'] ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/mdui@0.4.3/dist/css/mdui.min.css"/>
    <style>h1{font-weight:400;font-family:Helvetica;font-size:40px;margin-top:0px}.main-container{padding-top:30px;padding-bottom:80px}.icon-status{border-radius:100%}.icon{height:1em;width:1em;display:block;background-repeat:no-repeat;display:inline-block;margin: 7px 5px 0 0}</style>
</head>
<body class="mdui-color-blue-grey-50">
    <div class="mdui-container main-container">
        <div class="mdui-col-md-3"></div>
        <div class="mdui-col-md-6"><h1 class="mdui-text-color-theme"><?php echo $page['title'] ?></h1>
        <p>Last check at <?php echo date('Y-m-d H:i',$json_last_time) ?></p>
        <div class="mdui-table-fluid">
<?php
if($_GET['cron'] != $setting['cron_key']){

    $json_decode = json_decode(file_get_contents(__DIR__.'/'.$cache['filename']))->content;

    $group = array();
    foreach($json_decode->monitors as $monitor){
        $monitor_name_arr = explode('/',$monitor->friendly_name);
        if(!in_array($monitor_name_arr[0],$group)){
            if(!count($group) == 0):
?>
                </tbody>
            </table>
<?php
            endif;
            $group[] = $monitor_name_arr[0];
?>
            <table class="mdui-table mdui-table-hoverable">
                <thead>
                    <tr>
                        <th><?php echo $monitor_name_arr[0] ?></th>
                        <th class="mdui-table-col-numeric"></th>
                    </tr>
                </thead>
                <tbody>
<?php
        }
        switch ($monitor->status) {
            case 2:
                $status = 'Operational';
                $status_color = '6AC259';
            break;
            case 8:
                $status = 'Seems down';
                $status_color = 'FFDD57';
            break;
            case 9:
                $status = "Down";
                $status_color = 'F05228';
            break;
            default:
                $status = "Paused";
                $status_color = "111";
            break;
        }
?>
                    <tr>
                        <td><div class="icon icon-status" style="background-color:#'.$status_color.'"></div><?php echo $monitor_name_arr[1] ?></td>
                        <td><?php echo $status ?></td>
                    </tr>
<?php
    }
?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/mdui@0.4.3/dist/js/mdui.min.js"></script>
</body>
</html>
<?php
}
?>
