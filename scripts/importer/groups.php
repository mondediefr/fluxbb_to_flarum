<?php

WriteInLog('###################################');
WriteInLog('### [5/8] User groups migration ###');
WriteInLog('###################################');

$query = RunQuery($dbFluxbb, "SELECT * FROM ${dbFluxbbPrefix}groups");
$groups = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog('Migrating ' . $query->rowCount() . ' user groups...');
$groupsMigrated = 0;

foreach ($groups as $group) {

    $groupId = $group['g_id'];
    $icon = null;

    // Admin 1->1
    if ($groupId == 1) {
        $icon = 'wrench';
    // Mods 2->4
    } else if ($groupId == 2) {
        $groupId = 4;
        $icon = 'bolt';
    // Member 4->3
    } else if ($groupId == 4) {
        $groupId = 3;
    // Guest 3->2
    } else if ($groupId == 3) {
        $groupId = 2;
    }

    $query = RunPreparedQuery($dbFluxbb, [':group_id' => $group['g_id']], "SELECT id FROM ${dbFluxbbPrefix}users WHERE group_id = :group_id");
    $users = $query->fetchAll(PDO::FETCH_ASSOC);

    // Members are not affiliated with any group in flarum
    if ($groupId != 3) {
        foreach ($users as $user) {
            RunPreparedQuery($dbFlarum, [
                ':user_id' => $user['id'],
                ':group_id' => $groupId
            ], 'INSERT INTO users_groups(user_id, group_id) VALUES(:user_id, :group_id)');
        }
    }

    $groupData = [
        ':id' => $groupId,
        ':name_singular' => $group['g_user_title'],
        ':name_plural' => $group['g_title'],
        ':color' => ($group['g_color']) ? $group['g_color'] : GetRandomColor(),
        ':icon' => $icon
    ];

    $query = RunPreparedQuery($dbFlarum, $groupData, 'INSERT INTO groups(id, name_singular, name_plural, color, icon) VALUES(:id, :name_singular, :name_plural, :color, :icon)');
    $groupsMigrated += $query->rowCount();

    WriteInLog("+ Groupe '" . $group['g_title'] . "' done");

    /*
    GROUPS PERMISSIONS
    ###########################
    /!\ Don't do this right now, i think flarum permissions may changed with the stable version.

    $groupPermissions = array(
        ':group_id' => $groupId,
        ':permission' => '...'
    );

    RunPreparedQuery($dbFlarum, $groupPermissions, 'INSERT INTO permissions(group_id, permission) VALUES(:group_id, :permission)');
    */

}

WriteInLog('DONE. Results : ');
WriteInLog("> $groupsMigrated user groups migrated successfully", 'SUCCESS');
