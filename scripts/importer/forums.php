<?php

WriteInLog("#####################################");
WriteInLog("### [3/8] Subcategories migration ###");
WriteInLog("#####################################");

$query = RunQuery($dbFluxbb, "SELECT id, forum_name, forum_desc, disp_position, cat_id FROM " . $dbPrefix . "forums");
$forums = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog("Migrating " . $query->rowCount() . " subcategories...");
$forumsMigrated = 0;

// Contains the list of sub-categories in the form of a table of
// correspondence between the forums IDs and new tags created
$forumsTagsArray = [];

foreach ($forums as $forum) {

    $forumData = array(
        ':name' => $forum['forum_name'],
        ':slug' => Slugify($forum['forum_name']),
        ':description' => $forum['forum_desc'],
        ':color' => GetRandomColor(),
        ':position' => intval($forum['disp_position']),
        ':parent_id' => intval($forum['cat_id'])
    );

    $query = RunPreparedQuery($dbFlarum, $forumData, "INSERT INTO tags(name,slug,description,color,position,parent_id) VALUES(:name,:slug,:description,:color,:position,:parent_id)");
    $forumsMigrated += $query->rowCount();

    // Retrieve the forum/tag id
    $forumId = $forum['id'];
    $tagId = $dbFlarum->lastInsertId();
    $forumsTagsArray[$forumId] = $tagId;

    WriteInLog("+ Subcategory '" . $forum['forum_name'] . "' done");

}

WriteInLog("DONE. Results : ");
WriteInLog("> " . $forumsMigrated . " subcategories migrated successfully");
