<?php

WriteInLog('##################################');
WriteInLog('### [2/9] Categories migration ###');
WriteInLog('##################################');

$query = RunQuery($dbFluxbb, "SELECT id, cat_name, disp_position FROM ${dbFluxbbPrefix}categories");
$categories = $query->fetchAll(PDO::FETCH_ASSOC);

WriteInLog('Migrating ' . $query->rowCount() . ' categories...');
$categoriesMigrated = 0;

foreach ($categories as $category) {

    $categorieData = [
        ':id' => $category['id'],
        ':name' => $category['cat_name'],
        ':slug' => Slugify($category['cat_name'], [
            'separator' => '-',
            'lowercase' => false
        ]),
        ':color' => GetRandomColor(),
        ':position' => intval($category['disp_position'])
    ];

    $query = RunPreparedQuery($dbFlarum, $categorieData, "INSERT INTO ${dbFlarumPrefix}tags(id,name,slug,color,position) VALUES(:id,:name,:slug,:color,:position)");
    $categoriesMigrated += $query->rowCount();

    WriteInLog("+ Category '" . $category['cat_name'] . "' done");

}

WriteInLog('Done, results :');
WriteInLog("$categoriesMigrated categories migrated successfully", 'SUCCESS');
