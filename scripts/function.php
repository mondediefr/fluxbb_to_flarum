<?php

// FUNCTIONS
// ------------------------------

use Cocur\Slugify\Slugify;
$slugify = new Slugify(['lowercase' => false]);

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

    global $slugify;
    $slug = $slugify->slugify($text, $options);

    if(!empty($slug)) {
        return $slug;
    } else {
        // if slugify librairie wipes all characters, fallback to the second method
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        $text = trim($text, '-');
        $unwanted = [
            'Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
            'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
            'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
            'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
            'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
        ];
        $text = strtr($text, $unwanted);
        $text = strtolower($text);
        $text = preg_replace('~-+~', '-', $text);
        // if empty again, fallback to random slug...
        return (empty($text)) ? bin2hex(random_bytes(5)) : $text;
    }

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
            $slug = Slugify($slug);

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
        $username = Slugify($username, '');
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
