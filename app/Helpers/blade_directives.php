<?php

function bootstrap4_breadcrumbs($items)
{
    $ret = "<?php echo '<ol class=\"breadcrumb\">';\n";
    $ret .= '$items = ' . $items . ';'."\n";
    $ret .= 'foreach ($items as $item) {'."\n";
        $ret .= 'if (isset($item["active"]) && $item["active"] === true) {' . "\n";
            $ret .= "echo '<li class=\"breadcrumb-item active\">';"."\n";
        $ret .= "} else { \n";
            $ret .= "echo '<li class=\"breadcrumb-item\">';"."\n";
        $ret .= "}\n";
        $ret .= 'if (isset($item["active"]) && $item["active"] === true) {' . "\n";
        $ret .= 'echo "{$item[\'name\']}";' ."\n";
        $ret .= "} else { \n";
        $ret .= 'echo "<a href=\"{$item[\'url\']}\">{$item[\'name\']}</a>";' ."\n";
        $ret .= "}\n";
        $ret .= "echo '</li>';"."\n";
    $ret .= "}\n";
    return $ret . 'echo \'</ol>\'; ?>';
}

function bootstrap4_alert_b($opts)
{
    $ret = "<?php echo '<div class=\"alert {$opts["type"]}\" role=\"alert\">'; ?>";
    return $ret;
}

function bootstrap4_alert_e()
{
    return '<?php echo "</div>" ?>';
}

function fontawesome($opts)
{
    if (strpos($opts, '[') !== false) {
        return '<?php $opts = ' . $opts . '; echo "<i class=\"fa fa-{$opts[\'icon\']} " . (isset($opts[\'extra\']) ? $opts[\'extra\'] : \'\' ) . \'\"></i>\'; ?>';
    } else {
        return "<?php echo '<i class=\"fa fa-{$opts}\"></i>'; ?>";
    }
}

function perms_show_if($perm)
{
    return has_perm($perm);
}

function settings($key = null, $default = null) {
    if ($key === null) {
        return app(App\Settings::class);
    }

    return app(App\Settings::class)->get($key, $default);
}
