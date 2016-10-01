<?php

// FUNCTIONS
// ------------------------------

function WriteInLog($message, $type=INFO) {
    $line = "[$type] $message";
    echo "$line\n";
    error_log($line, 0);
}

function RunQuery($db, $sql) {
    try {
        $query = $db->query($sql);
    } catch(PDOException $e) {
        WriteInLog($e, 'ERROR');
        die("/!\ An error occurred while executing the query");
    }
    return $query;
}

function RunPreparedQuery($db, $dataArray, $sql) {
    try {
        $query = $db->prepare($sql);
        $query->execute($dataArray);
    } catch(PDOException $e) {
        WriteInLog($e, 'ERROR');
        die("/!\ An error occurred while executing the prepared query");
    }
    return $query;
}

function IsNullOrEmptyString($string) {
    return (!isset($string) || trim($string) === '');
}

function RemoveSpaces($string) {
    $string = str_replace(' ', '', $string);
    $string = preg_replace('/\s+/', '', $string);
    return $string;
}

function ConvertTimestampToDatetime($timestamp) {
    return date("Y-m-d H:i:s", $timestamp);
}

function Slugify($text) {
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    // $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); --> doesn't work :(
    $unwanted = array('Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
                      'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
                      'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
                      'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
                      'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y' );
    $text = strtr($text, $unwanted);
    $text = strtolower($text);
    $text = preg_replace('~-+~', '-', $text);
    if(empty($text))
        return bin2hex(random_bytes(5));
    return $text;
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
     *   - m = PCRE_MULTILINE
     */

    $text = preg_replace('#\[h](.+)\[\/h]#im', "[b][size=20]$1[/size][/b]", $text);
    $text = preg_replace('#\[q](.+)\[\/q]#im', "$1", $text);
    $text = preg_replace('#\[justify](.+)\[\/justify]#im', "$1", $text);
    $text = preg_replace('#\[left](.+)\[\/left]#im', "$1", $text);
    $text = preg_replace('#\[right](.+)\[\/right]#im', "$1", $text);
    $text = preg_replace('#\[sup](.+)\[\/sup]#im', "$1", $text);
    $text = preg_replace('#\[sub](.+)\[\/sub]#im', "$1", $text);
    $text = preg_replace('#\[acronym](.+)\[\/acronym]#im', "$1", $text);
    $text = preg_replace('#\[video](.+)\[\/video]#i', "$1", $text);

    return $text;
}

function ConvertLinkFluxbb($text) {

    global $dbFlarum, $dbFluxbb, $dbPrefix;

    // convert link :
    // - https://domain.tld/viewtopic.php?id=xxx
    // - https://domain.tld/profile.php?id=xxx
    $text = preg_replace("/viewtopic\.php\?id=([0-9]+)&p=[0-9]+|viewtopic\.php\?id=([0-9]+)/", "d/$1$2", $text);

    // convert link :
    // - https://domain.tld/profile.php?id=xxx
    $text = preg_replace("/profile\.php\?id=([0-9]+)/", "u/$1", $text);

    // convert link :
    // - https://domain.tld/viewtopic.php?pid=xxx#pxxx
    $text = preg_replace_callback(
        "/viewtopic\.php\?pid=([0-9]+)#p[0-9]+|viewtopic\.php\?pid=([0-9]+)/",
        function($matches) use ($dbFlarum) {
            $pid = $matches[1];
            if (isset($matches[2])) {
                $pid = $matches[2];
            }
            $query = RunQuery($dbFlarum, "SELECT number, discussion_id FROM posts WHERE id = $pid");
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
        "/viewforum\.php\?id=([0-9]+)&p=[0-9]+|viewforum\.php\?id=([0-9]+)/",
        function($matches) use ($dbFluxbb, $dbPrefix) {
            $id = $matches[1];
            if (isset($matches[2])) {
                $id = $matches[2];
            }
            $query = RunQuery($dbFluxbb, 'SELECT forum_name FROM ' . $dbPrefix . 'forums WHERE id =' . $id);
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

function GetUserID($db, $username) {
    if(preg_match('/\s/', $username) || strpos($username, ' ')) {
        $username = RemoveSpaces($username);
    }
    $query = RunPreparedQuery($db, array(':username' => $username), "SELECT id FROM users WHERE username=:username");
    $row = $query->fetch(PDO::FETCH_ASSOC);
    return $row['id'];
}
