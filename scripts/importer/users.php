<?php

use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

WriteInLog("#############################");
WriteInLog("### [1/8] Users migration ###");
WriteInLog("#############################");

$query = RunQuery($dbFluxbb, "SELECT id, username, email, registered, last_visit, signature FROM ${dbFluxbbPrefix}users");
$users = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog("Migrating " . $query->rowCount() . " users...");

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

        // TODO : Check that the username is only alphanumeric.
        // The username must contain only letters, numbers and hyphens.
        // If the username doesn't match this format, clean it and then send an email
        // to notify account's owner.

        if(preg_match('/\s/', $user['username']) || strpos($user['username'], ' ')) {
            $username = RemoveSpaces($user['username']);
            $usersCleaned++;
            WriteInLog("/!\ User '" . $user['username'] . "' cleaned (incorrect format). New nickname : '" . $username . "'", "WARN");
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

        $query = RunPreparedQuery($dbFlarum, $userData, "INSERT INTO users(id,username,email,is_activated,password,avatar_path,join_time,last_seen_time,comments_count,bio,discussions_count) VALUES(:id,:username,:email,:is_activated,:password,:avatar_path,:join_time,:last_seen_time,:comments_count,:bio,:discussions_count)");
        $usersMigrated += $query->rowCount();

    } else {
        $usersIgnored++;
        WriteInLog("/!\ User '" . $user['username'] . "' ignored (no mail address)", "WARN");
    }
}

WriteInLog("DONE. Results : ");
WriteInLog("> " . $usersMigrated . " user(s) migrated successfully");
WriteInLog("> " . $usersIgnored . " user(s) ignored (guest account + those without mail address)");
WriteInLog("> " . $usersCleaned . " user(s) cleaned (incorrect format)");
WriteInLog("> " . $signatureMigrated . " signature(s) cleaned and migrated successfully");
WriteInLog("> " . $avatarMigrated . " avatar(s) migrated successfully");
