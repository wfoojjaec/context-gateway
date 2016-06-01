<?php
/* 
 * getContextSettings Snippet
 * Version 0.0.1 
 * Author - YJ Tso <yj@modx.com> based on work by John Peca <john@modx.com>
 * 
 * 
*/ 
$contexts = array();

$cacheKey = $modx->getOption('cache_system_settings_key', null, 'system_settings');
$cacheOptions = array(
    xPDO::OPT_CACHE_HANDLER => $modx->getOption("cache_{$cacheKey}_handler", $scriptProperties, $modx->getOption(xPDO::OPT_CACHE_HANDLER)),
    xPDO::OPT_CACHE_EXPIRES => $modx->getOption("cache_{$cacheKey}_expires", $scriptProperties, $modx->getOption(xPDO::OPT_CACHE_EXPIRES)),
);
/** @var xPDOCache $contextCache */
$contextCache = $modx->cacheManager->getCacheProvider($cacheKey, $cacheOptions);

if ($contextCache) {
    $contexts = $contextCache->get('context_map');
}

if (empty($contexts)) {
    $protectedContexts = array('mgr');
    if ($modx->getOption('skip_web_ctx', $scriptProperties, true, true)) $protectedContexts[] = 'web';
    /** @var modContext $contextsGraph */
    $query = $modx->newQuery('modContext');
    $query->where(array('modContext.key:NOT IN' => $protectedContexts));
    $query->sortby($modx->escape('modContext') . '.' . $modx->escape('key'), 'ASC');
    $contextsGraph = $modx->getCollectionGraph('modContext', '{"ContextSettings":{}}', $query);
    foreach ($contextsGraph as $context) {
        $contextSettings = array();
        foreach ($context->ContextSettings as $cSetting) {
            $contextSettings[$cSetting->get('key')] = $cSetting->get('value');
        }
        $contexts[$context->get('key')] = $contextSettings;
    }
    unset($contextsGraph);
    if ($contextCache) {
        $contextCache->set('context_map', $contexts);
    }
}

// Options for settings
$settingTpl = $modx->getOption('settingTpl', $scriptProperties, '');
$settingSeparator = $modx->getOption('settingSeparator', $scriptProperties, PHP_EOL);
$settingLimit = $modx->getOption('settingLimit', $scriptProperties, '0');
$namespace = $modx->getOption('namespace', $scriptProperties, '');

// Options for contexts
$contextTpl = $modx->getOption('contextTpl', $scriptProperties, '');
$contextSeparator = $modx->getOption('contextSeparator', $scriptProperties, PHP_EOL);
$contextLimit = $modx->getOption('contextLimit', $scriptProperties, '0');
$exclude = $modx->getOption('exclude', $scriptProperties, ''); // coma separated list of excluded contexts

// Option for debugging
$debug = $modx->getOption('debug', $scriptProperties, false);

// prepare excluded contexts into array
$exclude = explode(',', $exclude);
foreach ($exclude AS $key => $value) {
    $exclude[$key] = trim($value);
}

$ctxOut = array();
$ctxIdx = 0;
foreach ($contexts as $key => $context) {
    // If excluded context, skip it
    if (in_array($key, $exclude)) continue;
    // Respect limit param (we're using 1-based indexing in the output, btw)
    $ctxIdx++;
    if (($contextLimit) && ($ctxIdx > $contextLimit)) break;
    
    // Get settings
    $stgOut = array();
    $stgIdx = 0;
    foreach ($context as $setting => $value) {
        // If namespace is set, only grab those settings
        if (!empty($namespace) && (strpos($setting, $namespace) !== 0)) continue;
        // Know your limits
        $stgIdx++;
        if (($settingLimit) && ($stgIdx > $settingLimit)) break;
        // If we're debugging then do that otherwise there's nothing left to do
        if (empty($settingTpl)) {
            if ($debug) $stgOut[] = print_r($context[$setting], true);
            // Continue to debug or do nothing
            continue;
        }
        // Format with settingTpl 
        $stgOut[] = $modx->getChunk($settingTpl, array('key' => $setting, 'value' => $value, 'idx' => $idx));
    }
    // Output settings to placeholder in wrapper chunk
    $context['settings'] = ($debug) ? $stgOut : implode($settingSeparator, $stgOut);
    // Set some useful placeholders
    $context['context_key'] = $key;
    $context['idx'] = $ctxIdx;
    
    // If we're debugging...
    if (empty($contextTpl)) {
        if ($debug) $ctxOut[] = print_r($context, true);
        // Continue to debug or do nothing
        continue;
    }
    // Note the $context has every setting, 
    // AS WELL AS a placeholder 'settings' that holds all templated settings
    $ctxOut[] = $modx->getChunk($contextTpl, $context);
}
// Return
if ($debug) return print_r($ctxOut, true);
return implode($contextSeparator, $ctxOut);