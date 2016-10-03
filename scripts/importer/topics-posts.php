<?php

WriteInLog('########################################');
WriteInLog('### [4/8] Topics and posts migration ###');
WriteInLog('########################################');

$query = RunQuery($dbFluxbb, "SELECT * FROM ${dbFluxbbPrefix}topics");
$topics = $query->fetchAll(PDO::FETCH_ASSOC);
WriteInLog('Migrating ' . $query->rowCount() . ' topics...');

$topicsMigrated = 0;
$postsMigrated  = 0;
$topicsIgnored  = 0;
$postsTruncate  = 0;

foreach ($topics as $topic) {

    $startUserId = GetUserID($topic['poster']);

    if(!IsNullOrEmptyString($topic['moved_to'])) {
        $topicsIgnored++;
        continue;
    }

    // In fluxbb, topics contains user posts only, with Flarum,
    // there may be also event posts (e.g: xxx pin this discussion,
    // xxxxx renamed the title, xxx added the tag xxx ...etc)
    // --------------------------------------------------------------------------------
    // totalPostsInDiscussion :
    // Fluxbb -> Number of answers in the topic
    // Flarum -> corresponding columns : comments_count, number_index, last_post_number
    // +1 = include the first post of the topic
    $totalPostsInDiscussion = (intval($topic['num_replies']) + 1);

    $query = RunPreparedQuery($dbFluxbb, [':topic_id' => $topic['id']], "SELECT * FROM ${dbFluxbbPrefix}posts WHERE topic_id = :topic_id ORDER BY id");
    $posts = $query->fetchAll(PDO::FETCH_ASSOC);

    $currentPostNumber = 0;
    $participantsList  = [];

    // Treat all posts from current topic
    foreach ($posts as $post) {

        $currentPostNumber++;
        $userId = $post['poster_id'];

        if(!in_array($userId, $participantsList))
            $participantsList[] = $userId;

        $content = $post['message'];

        foreach($smileys as $smiley){
            $quotedSmiley = preg_quote($smiley[1], '#');
            $match = '#(?<=\s|^)(' . $quotedSmiley .')(?=\s|$)#'; // a space is required before and after the pattern
            $content = preg_replace($match, '[img]/assets/images/smileys/'.$smiley[0].'[/img]', $content);
        }

        $content = TextFormatter::parse(ReplaceUnsupportedMarks($content));

        if (strlen($content) > 65535) {
            WriteInLog("/!\ The post id:'" . $post['id'] . "' is too long, more than 65535 characters", 'ERROR');
            // To avoid the error 500 (Internal Server Error)
            $content = TextFormatter::parse('This post was erased because the content exceed 65535 characters. Message by fluxbb_to_flarum importer');
            $postsTruncate++;
        }

        $postData = [
            ':id' => $post['id'],
            ':discussion_id' => $post['topic_id'],
            ':number' => $currentPostNumber,
            ':time' => ConvertTimestampToDatetime(intval($post['posted'])),
            ':user_id' => $userId,
            ':type' => 'comment',
            ':content' => $content,
            ':edit_time' => $post['edited'] ? ConvertTimestampToDatetime(intval($post['edited'])) : null,
            ':edit_user_id' => $post['edited_by'] ? GetUserID($post['edited_by']) : null,
            ':is_approved' => 1,
            ':ip_address' => !empty($post['poster_ip']) ? $post['poster_ip'] : null
        ];

        $query = RunPreparedQuery($dbFlarum, $postData, 'INSERT INTO posts(id,discussion_id,number,time,user_id,type,content,edit_time,edit_user_id,ip_address,is_approved) VALUES(:id,:discussion_id,:number,:time,:user_id,:type,:content,:edit_time,:edit_user_id,:ip_address,:is_approved)');
        $postsMigrated += $query->rowCount();
    }

    $topicData = [
        ':id'                 => $topic['id'],                                            // Topic ID
        ':title'              => $topic['subject'],                                       // Topic title
        ':comments_count'     => $totalPostsInDiscussion,                                 // Number of posts in the topic counting the first post
        ':participants_count' => count($participantsList),                                // Number of participants in this topic
        ':number_index'       => $totalPostsInDiscussion,                                 // Number of items in the topic
        ':start_time'         => ConvertTimestampToDatetime(intval($topic['posted'])),    // Topic creation date
        ':start_user_id'      => $startUserId,                                            // ID of the user who created the topic
        ':start_post_id'      => $topic['first_post_id'],                                 // First post ID
        ':last_time'          => ConvertTimestampToDatetime(intval($topic['last_post'])), // Last post date
        ':last_user_id'       => GetUserID($topic['last_poster']),                        // ID of the user who posted last
        ':last_post_id'       => intval($topic['last_post_id']),                          // Last post ID
        ':last_post_number'   => $totalPostsInDiscussion,                                 // Index of the last element of the topic
        ':slug'               => Slugify($topic['subject']),                              // Topic url slug part (human-readable keywords)
        ':is_approved'        => 1,                                                       // Approve all migrated topics
        ':is_locked'          => intval($topic['closed']),                                // Is the topic locked ?
        ':is_sticky'          => intval($topic['sticky']),                                // Is the topic pinned ?
    ];

    $query = RunPreparedQuery($dbFlarum, $topicData, 'INSERT INTO discussions(id,title,comments_count,participants_count,number_index,start_time,start_user_id,start_post_id,last_time,last_user_id,last_post_id,last_post_number,slug,is_approved,is_locked,is_sticky) VALUES(:id,:title,:comments_count,:participants_count,:number_index,:start_time,:start_user_id,:start_post_id,:last_time,:last_user_id,:last_post_id,:last_post_number,:slug,:is_approved,:is_locked,:is_sticky)');
    $topicsMigrated += $query->rowCount();

    //
    // Topic/tags link
    //

    $query = RunPreparedQuery($dbFluxbb, [':id' => $topic['forum_id']], "SELECT cat_id FROM ${dbFluxbbPrefix}forums WHERE id = :id");
    $row = $query->fetch(PDO::FETCH_ASSOC);

    // Link the topic with a primary tag (fluxbb category)
    RunPreparedQuery($dbFlarum, [
        ':discussion_id' => $topic['id'],
        ':tag_id' => $row['cat_id']
    ], 'INSERT INTO discussions_tags(discussion_id, tag_id) VALUES(:discussion_id, :tag_id)');

    // Link the topic with a secondary tag (fluxbb subcategory)
    RunPreparedQuery($dbFlarum, [
        ':discussion_id' => $topic['id'],
        ':tag_id' => $forumsTagsArray[$topic['forum_id']]
    ], 'INSERT INTO discussions_tags(discussion_id, tag_id) VALUES(:discussion_id, :tag_id)');

}

WriteInLog('DONE. Results : ');
WriteInLog("> $topicsMigrated topic(s) migrated successfully", 'SUCCESS');
WriteInLog("> $postsMigrated post(s) migrated successfully", 'SUCCESS');
WriteInLog("> $topicsIgnored topic(s) ignored (moved topics)", 'SUCCESS');
WriteInLog("> $postsTruncate topic(s) truncated (longer than 65535 characters)", 'SUCCESS');
