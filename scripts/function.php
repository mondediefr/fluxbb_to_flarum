<?php

// FUNCTIONS
// ------------------------------

use Cocur\Slugify\Slugify;

function WriteInLog($message, $type = 'INFO') {

    $red    = "\033[0;31m";
    $yellow = "\033[0;33m";
    $green  = "\033[0;32m";
    $normal = "\033[0m";

    if ($type == 'ERROR') {
        $line = $red . "[$type] " . $message . $normal . PHP_EOL;
    } else if ($type == 'WARN') {
        $line = $yellow . "[$type] " . $message . $normal . PHP_EOL;
    } else if ($type == 'SUCCESS') {
        $line = $green . "[$type] " . $message . $normal . PHP_EOL;
    } else {
        $line =  "[$type] " . $message . PHP_EOL;
    }

    echo $line;
    error_log("[$type] $message", 0);
}

function RunQuery($db, $sql) {
    try {
        $query = $db->query($sql);
    } catch(PDOException $e) {
        WriteInLog($e, 'ERROR');
        die('/!\ An error occurred while executing the query');
    }

    return $query;
}

function RunPreparedQuery($db, $dataArray, $sql) {
    try {
        $query = $db->prepare($sql);
        $query->execute($dataArray);
    } catch(PDOException $e) {
        WriteInLog($e, 'ERROR');
        die('/!\ An error occurred while executing the prepared query');
    }

    return $query;
}

function IsNullOrEmptyString($string) {
    return (!isset($string) || trim($string) === '');
}

function ConvertTimestampToDatetime($timestamp) {
    return date('Y-m-d H:i:s', $timestamp);
}

function Slugify($text, $options = null) {
    $slugify = new Slugify();
    $slugify->slugify($text, $options);
}

function ReplaceUnsupportedMarks($text) {

    /*
     * Supported bbcode tags :
     * https://github.com/flarum/flarum-ext-bbcode/blob/master/src/Listener/FormatBBCode.php#L32-L46
     *
     * This function replace all unsupported tags and ignore all those
     * not substitutable by flarum-text-bbcode extension
     *
     * PCRE PATTERNS :
     *   - i = PCRE_CASELESS
     *   - s = PCRE_DOTALL
     */

    $q       = '\[q]|\[\/q]';
    $em      = '\[em]|\[\/em]';
    $ins     = '\[ins]|\[\/ins]';
    $sup     = '\[sup]|\[\/sup]';
    $sub     = '\[sub]|\[\/sub]';
    $video   = '\[video]|\[\/video]';
    $left    = '\[left]|\[\/left]';
    $right   = '\[right]|\[\/right]';
    $justify = '\[justify]|\[\/justify]';
    $regex   = "/$q|$em|$ins|$sup|$sub|$video|$left|$right|$justify/";

    $text = preg_replace('#\[h](.+)\[\/h]#i', '[b][size=20]$1[/size][/b]', $text);
    $text = preg_replace('/\[acronym=.+](.+)\[\/acronym]|\[acronym]|\[\/acronym]/', '$1', $text);
    $text = preg_replace($regex, '', $text);

    return $text;
}

function ConvertLinkFluxbb($text) {

    global $dbFlarum, $dbFluxbb, $dbFluxbbPrefix, $dbFlarumPrefix;

    // convert link :
    // - https://domain.tld/viewtopic.php?id=xxx
    // - https://domain.tld/profile.php?id=xxx
    $text = preg_replace('/viewtopic\.php\?id=([0-9]+)&p=[0-9]+|viewtopic\.php\?id=([0-9]+)/', 'd/$1$2', $text);

    // convert link :
    // - https://domain.tld/profile.php?id=xxx
    $text = preg_replace('/profile\.php\?id=([0-9]+)/', 'u/$1', $text);

    // convert link :
    // - https://domain.tld/viewtopic.php?pid=xxx#pxxx
    $text = preg_replace_callback(
        '/viewtopic\.php\?pid=([0-9]+)#p[0-9]+|viewtopic\.php\?pid=([0-9]+)/',
        function($matches) use ($dbFlarum, $dbFlarumPrefix) {
            $pid = isset($matches[2]) ? $matches[2] : $matches[1];

            $query = RunPreparedQuery($dbFlarum, [':id' => $pid], "SELECT number, discussion_id FROM ${dbFlarumPrefix}posts WHERE id = :id");
            $info_id = $query->fetchAll(PDO::FETCH_ASSOC);
            $discussionId = intval($info_id[0]['discussion_id']);
            $number = intval($info_id[0]['number']);

            return "d/$discussionId/$number";
        },
        $text
    );

    // convert link :
    // - https://domain.tld/viewforum.php?id=xxx
    $text = preg_replace_callback(
        '/viewforum\.php\?id=([0-9]+)&p=[0-9]+|viewforum\.php\?id=([0-9]+)/',
        function($matches) use ($dbFluxbb, $dbFluxbbPrefix) {
            $id = $matches[1];
            if (isset($matches[2])) {
                $id = $matches[2];
            }
            $query = RunPreparedQuery($dbFluxbb, [':id' => $id], "SELECT forum_name FROM ${dbFluxbbPrefix}forums WHERE id = :id");
            $info_slug = $query->fetchAll(PDO::FETCH_ASSOC);
            $slug = $info_slug[0]['forum_name'];
            $slug = Slugify($slug, [
                'separator' => '-',
                'lowercase' => false
            ]);

            return "t/$slug";
        },
        $text
    );

    return $text;
}

function GetRandomColor() {
    return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
}

function GetUserID($username) {

    global $dbFlarum, $dbFlarumPrefix;

    if(!preg_match('/^[a-zA-Z0-9-_]+$/', $username)) {
        $username = Slugify($username, [
            'separator' => '',
            'lowercase' => true
        ]);
    }

    $query = RunPreparedQuery($dbFlarum, [':username' => $username], "SELECT id FROM ${dbFlarumPrefix}users WHERE username = :username");
    $row = $query->fetch(PDO::FETCH_ASSOC);

    return $row['id'];
}

function SendNotificationToUser($address, $username, $slug) {

    global $mailFrom, $mailHost, $mailPort, $mailEncr, $mailUser, $mailPass;

    if($mailHost) {
        $body = file_get_contents('/scripts/mail/body.html');
        $body = preg_replace('/{USERNAME}/', $username, $body);
        $body = preg_replace('/{SLUG}/', $slug, $body);

        $mail = new PHPMailer;
        // $mail->SMTPDebug = 3;
        $mail->isSMTP();
        $mail->SMTPAuth = true;
        $mail->Host = $mailHost;
        $mail->Username = $mailUser;
        $mail->Password = $mailPass;
        $mail->SMTPSecure = $mailEncr;
        $mail->Port = $mailPort;
        $mail->setFrom($mailFrom, 'Flarum migration script');
        $mail->addAddress($address);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = file_get_contents('/scripts/mail/title.txt');
        $mail->Body = $body;
        $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';

        if(!$mail->send()) {
            WriteInLog("Unable to send mail notification to ${address} . Error : " . $mail->ErrorInfo, 'ERROR');
        }
    }
}
