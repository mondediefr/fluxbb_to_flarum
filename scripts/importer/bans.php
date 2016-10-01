<?php

WriteInLog("#################################");
WriteInLog("### [7/8] User bans migration ###");
WriteInLog("#################################");

$query = RunQuery($dbFluxbb, "SELECT * FROM " . $dbPrefix . "bans");
$bans = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog("Migrating " . $query->rowCount() . " bans...");

$bansMigrated = 0;
$bansIgnored = 0;

foreach ($bans as $ban) {

    $userId = GetUserID($dbFlarum, $ban['username']);

    if(IsNullOrEmptyString($userId)) {
        $bansIgnored++;
        WriteInLog("/!\ User '" . $ban['username'] . "' ignored (account not found)", "WARN");
        continue;
    }

    $duration = ($ban['expire']) ? ConvertTimestampToDatetime(intval($ban['expire'])) : date("Y-m-d H:i:s", strtotime('+50 years'));

    $query = RunPreparedQuery($dbFlarum, array(
        ':id' => userId,
        ':email' => $ban['email'],
        ':suspend_until' => $duration
    ), "UPDATE users SET suspend_until=:suspend_until WHERE id=:id OR email=:email");

    $bansMigrated += $query->rowCount();

}

WriteInLog("DONE. Results : ");
WriteInLog("> " . $bansMigrated . " ban(s) migrated successfully");
WriteInLog("> " . $bansIgnored . " ban(s) ignored (account not found)");
