<?php

include 'TextFormatter/src/autoloader.php';

/*
 * For more information, read the documentation of s9e/TextFormatter :
 *  -> http://s9etextformatter.readthedocs.io/Plugins/BBCodes/Add_custom_BBCodes/
 *  -> http://s9etextformatter.readthedocs.io/Plugins/BBCodes/Custom_BBCode_syntax/
 * /!\ Don't forget, you can only edit this supported tags :
 *  -> https://github.com/flarum/flarum-ext-bbcode/blob/master/src/Listener/FormatBBCode.php#L32-L46
 * You can't add new custom bbcode tags.
 */

$configurator = new s9e\TextFormatter\Configurator;
$configurator->loadBundle('Forum');
$configurator->tags->onDuplicate('replace');
$configurator->BBCodes->onDuplicate('replace');

# Fix fluxbb bbcode image tag
$configurator->BBCodes->addCustom(
  '[IMG alt={TEXT;optional} src={URL;useContent}]',
  '<img src="{@src}"><xsl:copy-of select="@alt"/></img>'
);

$configurator->rendering->setEngine('PHP', '/scripts/TextCustomBundle');
$configurator->saveBundle('TextFormatter', '/scripts/TextCustomBundle/TextFormatter.php');
