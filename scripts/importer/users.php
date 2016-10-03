<?php

use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

WriteInLog('#############################');
WriteInLog('### [1/8] Users migration ###');
WriteInLog('#############################');

$query = RunQuery($dbFluxbb, "SELECT id, username, email, registered, last_visit, signature FROM ${dbFluxbbPrefix}users");
$users = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog('Migrating ' . $query->rowCount() . ' users...');

$usersIgnored      = 0;
$usersCleaned      = 0;
$usersMigrated     = 0;
$signatureMigrated = 0;
$avatarMigrated    = 0;

foreach ($users as $user) {

    if($user['username'] == 'Guest') {
        $usersIgnored++;
        continue;
    }

    if(!IsNullOrEmptyString($user['email'])) {

        // The username must contain only letters, numbers and dashes.
        if(!preg_match('/^[a-zA-Z0-9-_]+$/', $user['username'])) {

            $username = Slugify($user['username'], '');
            $query = RunPreparedQuery($dbFluxbb, [':username' => $username], "SELECT id FROM {$dbFluxbbPrefix}users WHERE BINARY username = :username");
            $row = $query->fetch(PDO::FETCH_ASSOC);

            if($row['id']) {
                $usersIgnored++;
                WriteInLog("/!\ Unable to clean username '" . $user['username'] . "', try to fix this account manually. Proposed nickname : '" . $username . "' (already exists in fluxbb database)", 'ERROR');
                continue;
            } else {
                $usersCleaned++;
                WriteInLog("/!\ User '" . $user['username'] . "' cleaned (incorrect format). New nickname : '" . $username . "'", 'WARN');
                //SendNotificationToUser($user['email'], $user['username'], $username);
            }

        } else {
            $username = $user['username'];
        }

        //
        // User avatar migration
        //
        $extension = ['png', 'gif', 'jpg', 'jpeg'];
        foreach ($extension as $ext) {
            if (file_exists('scripts/avatars/'.$user['id'].'.'.$ext) === true) {
                $avatar = ['is_exist'=> true, 'extension' => $ext];
                break;
            } else {
                $avatar = ['is_exist'=> false];
            }
        }

        if ($avatar['is_exist'] === true) {
            $file = 'scripts/avatars/'.$user['id'].'.'.$avatar['extension'];
            $newFileName = Str::lower(Str::quickRandom()).'.jpg';

            $manager = new ImageManager();
            $encodedImage = $manager->make($file)->fit(100, 100)->encode('jpg', 100);
            file_put_contents('/flarum/app/assets/avatars/'.$newFileName, $encodedImage);

            $avatarMigrated++;
        } else {
            $newFileName = null;
        }

        //
        // User signature migration
        //
        $signature = TextFormatter::parse($user['signature']);
        $signature = s9e\TextFormatter\Utils::removeFormatting($signature);

        if (!empty($signature)){
            $signatureMigrated++;
        } else {
            $signature = null;
        }

        $userData = [
            ':id' => $user['id'],
            ':username' => $username,
            ':email' => $user['email'],
            ':is_activated' => 1,
            ':password' => bin2hex(random_bytes(20)),
            ':avatar_path' => $newFileName,
            ':join_time' => ConvertTimestampToDatetime(intval($user['registered'])),
            ':last_seen_time' => ConvertTimestampToDatetime(intval($user['last_visit'])),
            ':bio' => $signature,
            ':comments_count' => 0,
            ':discussions_count' => 0
        ];

        $query = RunPreparedQuery($dbFlarum, $userData, 'INSERT INTO users(id,username,email,is_activated,password,avatar_path,join_time,last_seen_time,comments_count,bio,discussions_count) VALUES(:id,:username,:email,:is_activated,:password,:avatar_path,:join_time,:last_seen_time,:comments_count,:bio,:discussions_count)');
        $usersMigrated += $query->rowCount();

    } else {
        $usersIgnored++;
        WriteInLog("/!\ User '" . $user['username'] . "' ignored (no mail address)", 'WARN');
    }
}

WriteInLog('DONE. Results : ');
WriteInLog("> $usersMigrated user(s) migrated successfully", 'SUCCESS');
WriteInLog("> $usersIgnored user(s) ignored (guest account + those without mail address + accounts not cleaned)", 'SUCCESS');
WriteInLog("> $usersCleaned user(s) cleaned (incorrect format)", 'SUCCESS');
WriteInLog("> $signatureMigrated signature(s) cleaned and migrated successfully", 'SUCCESS');
WriteInLog("> $avatarMigrated avatar(s) migrated successfully", 'SUCCESS');
