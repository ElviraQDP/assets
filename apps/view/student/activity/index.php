<?php

    // use GuzzleHttp\Client;

    $user = null;
    $activity = null;
    $token = "";
    if(!empty($_GET["assets_token"])) $token = $_GET["assets_token"];
    $activityEndpoint = 'https://'.$_SERVER['SERVER_NAME'].'/apis/activity/user/';
    if(!empty($_GET['user'])){
        $user = $_GET['user'];
        $activityEndpoint .= $user."?activities=student_activity&access_token=".$token;
        $data = trim(file_get_contents($activityEndpoint));
        if(!empty($data)){
            $activity = json_decode($data);
        }
    }

    function activity($slug){
        switch($slug){
            case "breathecode_login": return "Login into the BreatheCode Platform"; break;
            default: return "Uknown Activity $slug"; break;
        }
        return "Uknown Activity $slug";
    }

    function time_elapsed_string($datetime, $full = false) {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
?>
<!DOCTYPE html>
<html>
    <head>
        <title>BreatheCode Student Activity</title>
        <link rel="stylesheet" href="index.css" type="text/css" />
    </head>
    <body>
        <?php if(!$user){ ?>
            <div>You need to specify the user</div>
        <?php } else if(!is_array($activity->log)){ ?>
            <div>There was a problem retreving the user activity from <a href="<?php echo $activityEndpoint; ?>"><?php echo $activityEndpoint; ?></a></div>
        <?php } else { ?>
            <div class="intro">
                <h1>Activity Timeline for: <?php echo $activity->user->first_name; ?> <?php echo $activity->user->last_name; ?> </h1>
                <p>The following timeline has been generated based on the interaction between the user and the BreatheCode platform.</p>
            </div>
            <div class="timeline">
                <?php if(count($activity->log)==0){ ?>
                    <h2>No activity has been found for <?php echo $activity->user->first_name; ?> <?php echo $activity->user->last_name; ?></h2>
                <?php } else {?>
                    <ul>
                        <?php foreach($activity->log as $act){ ?>
                            <li>
                                <span class="eyebrow">
                                    <?php echo substr($act->created_at->date, 0, 10); ?>
                                    <span class="daysago"><?php echo time_elapsed_string($act->created_at->date); ?></span>
                                </span>
                                <h3><?php echo activity($act->slug); ?></h3>
                            </li>
                        <?php } ?>
                    </ul>
                <?php }?>
            </div>
        <?php }?>
    </body>
</html>