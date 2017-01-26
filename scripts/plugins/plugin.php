<?php

WriteInLog('#####################');
WriteInLog('### [9/9] Plugins ###');
WriteInLog('#####################');

$plugins = glob('scripts/plugins/*.plugin.php');
$nb = count($plugins);

WriteInLog("$nb plugin(s) to execute");

if ($nb >= 1) {
    foreach ($plugins as $plugin) {
        WriteInLog("plugin : $plugin");
        require_once $plugin;
    }
} else {
    WriteInLog('No plugin found');
}
