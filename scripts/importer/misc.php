<?php

WriteInLog("###########################");
WriteInLog("### [8/8] Miscellaneous ###");
WriteInLog("###########################");

$query = RunQuery($dbFlarum, "SELECT id FROM users");
$users = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog("> Starting update users posts and discussions counters");

foreach ($users as $user) {
    $userId = $user['id'];

    // count the number of posts
    $query = RunQuery($dbFlarum, "SELECT COUNT(id) AS nb_posts FROM posts WHERE user_id = $userId");
    $nb_posts = $query->fetchAll(PDO::FETCH_ASSOC);

    // count the number of discussions
    $query = RunQuery($dbFlarum, "SELECT COUNT(id) AS nb_discussions FROM discussions WHERE start_user_id = $userId");
    $nb_discussions = $query->fetchAll(PDO::FETCH_ASSOC);

    $comments_count = intval($nb_posts[0]['nb_posts']);
    $discussions_count = intval($nb_discussions[0]['nb_discussions']);

    RunQuery($dbFlarum, "UPDATE users SET discussions_count = $discussions_count, comments_count = $comments_count WHERE id = $userId");
}

WriteInLog("> Done");

WriteInLog("> Starting update tag table");

$query = RunQuery($dbFlarum, "SELECT id FROM tags");
$tags = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($tags as $tag) {
    $tagId = $tag['id'];

    // Update last message in the current tag
    // SELECT *
    // FROM discussions_tags
    // INNER JOIN posts
    //   ON discussions_tags.discussion_id = posts.discussion_id
    // WHERE discussions_tags.tag_id = 1
    // ORDER BY posts.time DESC
    // LIMIT 1
    $query = RunQuery($dbFlarum, "SELECT posts.discussion_id, posts.time FROM discussions_tags INNER JOIN posts ON discussions_tags.discussion_id = posts.discussion_id WHERE discussions_tags.tag_id = $tagId ORDER BY posts.time DESC LIMIT 1");
    $last_discussion = $query->fetchAll(PDO::FETCH_ASSOC);
    $last_discussion_id = $last_discussion[0]['discussion_id'];
    $last_time = $last_discussion[0]['time'];


    $tagData = [
        ':tagId' => $tagId,
        ':last_time' => empty($last_time) ? null:$last_time,
        ':last_discussion_id' => $last_discussion_id
    ];

    $query = RunPreparedQuery($dbFlarum, $tagData, "UPDATE tags SET last_discussion_id = :last_discussion_id, last_time = :last_time WHERE id = :tagId");

    // Update tags.discussions_count
    $query = RunQuery($dbFlarum, "SELECT COUNT(*) AS count FROM discussions_tags WHERE tag_id = $tagId");
    $discussions_count = $query->fetchAll(PDO::FETCH_ASSOC);
    $discussions_count = intval($discussions_count[0]['count']);

    RunQuery($dbFlarum, "UPDATE tags SET discussions_count = $discussions_count WHERE id = $tagId");

}

WriteInLog("> Done");

WriteInLog("> Converting fluxbb http(s) links");

$query = RunQuery($dbFlarum, "SELECT id, content FROM posts");
$posts = $query->fetchAll(PDO::FETCH_ASSOC);

foreach ($posts as $post) {
    $content = $post['content'];
    $postId = $post['id'];

    $content = s9e\TextFormatter\Unparser::unparse($content);
    $content = ConvertLinkFluxbb($content);
    $content = TextFormatter::parse($content);

    RunQuery($dbFlarum, "UPDATE posts SET content = '" . addslashes($content) . "' WHERE id = $postId");
}

WriteInLog("> Done");
