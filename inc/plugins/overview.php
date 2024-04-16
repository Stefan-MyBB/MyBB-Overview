<?php

/**
 * This file is part of Overview plugin for MyBB.
 * Copyright (C) 2005-2009 Michael Schlechtinger <kontakt@mybboard.de>
 * Copyright (C) 2010 Andreas Klauer <Andreas.Klauer@metamorpher.de>
 * Copyright (C) 2021 Stefan T. <stefan@mybb.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

if (!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

if (defined('THIS_SCRIPT'))
{
    global $templatelist;

    if (isset($templatelist))
    {
        $templatelist .= ',';
    }

    if (THIS_SCRIPT == 'index.php')
    {
        $templatelist .= 'overview,overview_2_columns,overview_2_columns_row,overview_3_columns,overview_3_columns_row,overview_message,overview_javascript,postbit_reputation_formatted_link';
    }
}

/* --- Hooks: --- */

// AJAX hook.
$plugins->add_hook("xmlhttp", "overview_ajax");

global $mybb;
if (!$mybb->settings['overview_noindex'])
{
    $plugins->add_hook("index_end", "overview_end");
}

// Dirty cache hooks (add only if cache enabled)
if (intval($mybb->settings['overview_cache']) > 0)
{
    // This is the ugly side of caching.
    // Because of the cache, the Overview won't be up-to-date for some time.
    // So we forcibly kill the cache in the most common cases (new thread etc).
    $plugins->add_hook("datahandler_post_insert_post", "overview_deletecache");
    $plugins->add_hook("datahandler_post_insert_thread", "overview_deletecache");
    $plugins->add_hook("datahandler_event_insert", "overview_deletecache");
    $plugins->add_hook("datahandler_user_insert", "overview_deletecache");

    // ...and edits
    $plugins->add_hook("datahandler_post_update", "overview_deletecache");

    // Cover deleted threads as well.
    $plugins->add_hook("class_moderation_delete_post", "overview_deletecache");
    $plugins->add_hook("class_moderation_delete_thread", "overview_deletecache");
    $plugins->add_hook("admin_config_settings_change_commit", "overview_deletecache");
    $plugins->add_hook("admin_user_users_delete", "overview_deletecache");

    // Could do more, but let's not overdo things.
    // Worst case, the overview will show an old link.
}

// Custom hooks that are safe to call on custom pages.
$plugins->add_hook("overview_end", "overview_end");

/* --- Plugin-API: --- */

function overview_info()
{
    return array(
        "name"          => "Overview",
        "title"         => "Overview",
        "description"   => "Displays a box on the index page that shows latest threads, posts, users, and more.",
        "website"       => "https://www.mybb.de",
        "author"        => "Stefan T.",
        "authorsite"    => "https://www.mybb.de",
        "version"       => "3.11.0",
        "codename"      => "overview",
        "compatibility" => "18*"
    );
}

function overview_install()
{
    // Clean up to avoid double overview effect.
    overview_uninstall();

    global $db;

    // Insert templates
    $templatearray = array(
        "title" => "overview",
        "template" => "<table width=\"100%\" border=\"0\" cellspacing=\"{\$theme[\'borderwidth\']}\" cellpadding=\"0\" class=\"tborder\">
        <tr><td colspan=\"{\$num_columns}\" class=\"thead\"><strong>{\$lang->overview_overview}</strong></td></tr>
        {\$trow_message}
        <tr>
        {\$overview_content}
        </tr>
        </table>
        <br />",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_2_columns",
        "template" => "<td valign=\"top\" class=\"{\$trow}\"><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"{\$theme[\'tablespace\']}\">
        <tr class=\"tcat\">
        <td colspan=\"2\" valign=\"top\"><strong>{\$table_heading}</strong></td>
        </tr>
        <tr class=\"{\$trow}\">
        <td valign=\"top\"><strong>{\$column1_heading}</strong></td>
        <td align=\"right\" valign=\"top\"><strong>{\$column2_heading}</strong></td>
        </tr>
        {\$table_content}
        </table></td>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_2_columns_row",
        "template" => "<tr class=\"{\$trow}\">
        <td valign=\"top\"><div class=\"smalltext\">{\$val1}</div></td>
        <td align=\"right\" valign=\"top\"><div class=\"smalltext\">{\$val2}</div></td>
        </tr>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_3_columns",
        "template" => "<td valign=\"top\" class=\"{\$trow}\"><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"{\$theme[\'tablespace\']}\">
        <tr class=\"tcat\">
        <td colspan=\"3\" valign=\"top\"><strong>{\$table_heading}</strong></td>
        </tr>
        <tr class=\"{\$trow}\">
        <td valign=\"top\"><strong>{\$column1_heading}</strong></td>
        <td valign=\"top\"><strong>{\$column2_heading}</strong></td>
        <td align=\"right\" valign=\"top\"><strong>{\$column3_heading}</strong></td>
        </tr>
        {\$table_content}
        </table></td>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_3_columns_row",
        "template" => "<tr class=\"{\$trow}\">
        <td valign=\"top\"><div class=\"smalltext\">{\$val1}</div></td>
        <td valign=\"top\"><div class=\"smalltext\">{\$val2}</div></td>
        <td align=\"right\" valign=\"top\"><div class=\"smalltext\">{\$val3}</div></td>
        </tr>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_message",
        "template" => "<tr><td class=\"trow1\" colspan=\"{\$num_columns}\" style=\"padding:{\$theme[\'tablespace\']}px\">
        <span class=\"smalltext\">{\$overview_message}</span>
        </td></tr>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $templatearray = array(
        "title" => "overview_javascript",
        "template" => "<script type=\"text/javascript\">
        function overview_request(wait) {
        if(wait == 1)
        {
            $(\'#overview_load\').html(\'<div style=\"text-align: center; margin: 5px auto auto 5px; width: 200px; position: absolute;\"><table style=\"margin: auto auto;\" border=\"0\" cellpadding=\"3\" cellspacing=\"0\" class=\"tborder\"><tr class=\"trow1\"><td><span class=\"smalltext\"><img src=\"images/spinner.gif\" alt=\"Loading\" width=\"12\" height=\"12\" /> Loading...</span></td></tr><table></div>\')
        }
        $.ajax(
        {
        url: \'xmlhttp.php?action=overview\',
        async: true,
        dataType: \"html\",
        success: function (data)
        {
        $(\'#overview\').html(data)
        $(\'#overview_load\').html(\'\')
        }
        });
        }
        $( document ).ready(function() {
        overview_request({\$loaddisplay})
        setInterval(\'overview_request({\$loaddisplay})\', {\$intervall})
        })
        </script>",
        "sid" => -1
    );
    $db->insert_query("templates", $templatearray);

    $query = $db->simple_select("settinggroups", "MAX(disporder) as disporder");
    $disporder = $db->fetch_field($query, 'disporder') + 1;

    // Insert setting groups
    $overview_group = array(
        "name" => "Overview",
        "title" => "Overview",
        "description" => "Settings for the \"Overview\"-Plugin.",
        "disporder" => $disporder,
        "isdefault" => 0
    );
    $db->insert_query("settinggroups", $overview_group);
    $gid = intval($db->insert_id());

    $disp = 1;
    $col = 1;

    // Drop down menu with 10 items
    $select10 = implode("\n", array(
        "select", "0=No", "1=Yes (Order 1)",
        "2=Yes (Order 2)", "3=Yes (Order 3)",
        "4=Yes (Order 4)", "5=Yes (Order 5)",
        "6=Yes (Order 6)", "7=Yes (Order 7)",
        "8=Yes (Order 8)", "9=Yes (Order 9)",
        "10=Yes (Order 10)"
    ));

    // Insert settings
    $setting = array(
        "name" => "overview_max",
        "title" => "Number of Items",
        "description" => "Enter the number of items (Users/Threads/Posts) to be shown.",
        "optionscode" => "numeric",
        "value" => 5,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_newest_members",
        "title" => "Show newest members?",
        "description" => "Choose if you want the newest members to be shown.",
        "optionscode" => $select10,
        "value" => $col++,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_top_posters",
        "title" => "Show Top Posters?",
        "description" => "Choose if you want the top posters to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => intval($gid)
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_newest_threads",
        "title" => "Show newest threads?",
        "description" => "Choose if you want the newest threads to be shown.",
        "optionscode" => $select10,
        "value" => $col++,
        "disporder" => $disp++,
        "gid" => intval($gid)
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_most_replies",
        "title" => "Show threads with most replies?",
        "description" => "Choose if you want the threads with the most replies to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_favourite_threads",
        "title" => "Show favourite Threads?",
        "description" => "Choose if you want the favourite threads to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_newest_posts",
        "title" => "Show newest posts?",
        "description" => "Choose if you want the newest posts to be shown.",
        "optionscode" => $select10,
        "value" => $col++,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_edited_posts",
        "title" => "Show recently edited posts?",
        "description" => "Choose if you want the recently edited posts to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_bestrep_members",
        "title" => "Show best reputated members?",
        "description" => "Choose if you want the best reputated members to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_newest_polls",
        "title" => "Show best newest polls?",
        "description" => "Choose if you want the newest polls to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_next_events",
        "title" => "Show best next events?",
        "description" => "Choose if you want the next events to be shown.",
        "optionscode" => $select10,
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_show_re",
        "title" => "Do you want to show the \"RE:\" from the subjects of replies?",
        "description" => "Choose if you want the \"RE:\" to be shown in front of the subjects of replies.",
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_subjects_length",
        "title" => "Number of Characters",
        "description" => "How many characters of subjects should be shown (0 = show all)?",
        "optionscode" => "numeric",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_usernamestyle",
        "title" => "Format usernames?",
        "description" => "Do you want to format the usernames in the style of their usergroups?",
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_showicon",
        "title" => "Show post icons?",
        "description" => "Do you want to display post icons in front of subjects?",
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_showprefix",
        "title" => "Show thread prefix?",
        "description" => "Do you want to display thread prefix in front of subjects?",
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_trow_message_onoff",
        "title" => "Show message?",
        "description" => "Choose if you want to show a message.",
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_trow_message",
        "title" => "Message",
        "description" => "Enter the message. You can use MyCode.",
        "optionscode" => "textarea",
        "value" => "Enter your message here!",
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_ajax",
        "title" => "AJAX",
        "description" => "Time (in seconds) if you want the overview box to reload itself periodically using AJAX. Set to 0 to disable.",
        "optionscode" => "numeric",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_ajax_loading",
        "title" => "Loading",
        "description" => "When using AJAX, do you want to show a \"Loading\"-Window?",
        "optionscode" => "yesno",
        "value" => 1,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_usergroups",
        "title" => "Disable overview for usergroups",
        "description" => "Enter the usergroups that should not see the overview table.",
        "optionscode" => "groupselect",
        "value" => '',
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_noindex",
        "title" => "Hide overview from index page",
        "description" => "If you don\\'t want the overview to display on the index page, say yes. This is only useful if you make a custom overview page.",
        "optionscode" => "yesno",
        "value" => 0,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    $setting = array(
        "name" => "overview_cache",
        "title" => "Cache overview",
        "description" => "Building the Overview requires some database queries. The Overview result can be cached to reduce server load. Specify for how long the cache should be used (in seconds). Setting to 0 disables the cache.",
        "optionscode" => "numeric",
        "value" => 300,
        "disporder" => $disp++,
        "gid" => $gid,
    );
    $db->insert_query("settings", $setting);

    // rebuild settings.php
    rebuild_settings();
}

function overview_uninstall()
{
    global $db;

    // Remove templates
    $templatearray = array(
        "overview",
        "overview_2_columns",
        "overview_2_columns_row",
        "overview_3_columns",
        "overview_3_columns_row",
        "overview_message",
        "overview_javascript",
    );

    $deltemplates = implode("','", $templatearray);

    // Kill old templates too, if present.
    $deltemplates .= "','index_" . implode("','index_", $templatearray);

    $db->delete_query("templates", "title IN ('{$deltemplates}')");

    // Remove setting groups
    $query = $db->simple_select("settinggroups", "gid", "name='Overview'");
    $g = $db->fetch_array($query);
    $db->delete_query("settinggroups", "gid='{$g['gid']}'");

    // Remove settings
    $db->delete_query("settings", "gid='{$g['gid']}'");

    // rebuild settings.php
    rebuild_settings();
}

function overview_is_installed()
{
    global $templates;

    if ($templates->get("overview", 0, 0))
    {
        return true;
    }

    return false;
}

function overview_activate()
{
    // Clean up to avoid double overview effect.
    overview_deactivate();

    // Insert variables into templates
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    find_replace_templatesets("index", '#{\$header}(\r?)\n#', "{\$header}\n{\$overview}\n");
    find_replace_templatesets("index", '#{\$footer}(\r?)\n#', "{\$footer}\n{\$overview_javascript}\n");
}

function overview_deactivate()
{
    // Remove variables from templates
    require_once MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("index", '#{\$overview}(\r?)\n#', "", 0);
    find_replace_templatesets("index", '#{\$overview_javascript}(\r?)\n#', "", 0);

    overview_deletecache();
}

function overview_deletecache()
{
    global $cache, $db;
    global $overview_deleted;

    // Let's not overdo it.
    if (isset($overview_deleted))
        return;

    $overview_deleted = 1;

    // Remove cache
    if (is_object($cache->handler))
    {
        $query = $db->simple_select("datacache", "title", "title LIKE 'overview%'");
        while ($row = $db->fetch_array($query))
        {
            $cache->handler->delete($row['title']);
        }
    }

    $db->delete_query("datacache", "title LIKE 'overview%'");
}

/* --- Functions: --- */

// Build the main overview function
function overview()
{
    global $db, $mybb, $cache, $templates, $theme, $lang, $overview;

    if (!empty($mybb->settings['overview_usergroups']))
    {
        $overviewgroups = explode(",", $mybb->settings['overview_usergroups']);
    }

    if (empty($mybb->settings['overview_usergroups']) || is_member($overviewgroups))
    {
        // Fetch from cache, if present.
        $delta = intval($mybb->settings['overview_cache']);

        if ($delta > 0)
        {
            // Cache must be unique to the permission and language set of the current user.
            $extra = implode("-", array(
                $mybb->user['usergroup'],
                $mybb->user['additionalgroups'],
                $mybb->usergroup['cancp'],
                $mybb->usergroup['canmodcp'],
                $mybb->usergroup['issupermod'],
                $lang->language
            ));
            // Cache name length is limited so let's hash that.
            $extra = md5($extra);

            $overcache = $cache->read("overview{$extra}");

            if ($overcache && $overcache['time'] >= (TIME_NOW - $delta))
            {
                $overview = $overcache['data'];
                return $overview;
            }
        }

        // No luck with the cache, build the overview:

        // Load language files
        $lang->load("overview");

        // Exclude unviewable forums
        $overview_unviewwhere = "";
        $overview_unviewable = get_unviewable_forums();
        if ($overview_unviewable)
        {
            $overview_unviewwhere = "AND fid NOT IN ({$overview_unviewable})";
        }

        // Check group permissions if we can't view threads not started by us
        $group_permissions = forum_permissions();
        foreach ($group_permissions as $fid => $forum_permissions)
        {
            if ($forum_permissions['canonlyviewownthreads'] == 1)
            {
                $onlyusfids[] = $fid;
            }
        }
        if (!empty($onlyusfids))
        {
            if ($delta > 0)
            {
                // Hides threads and posts when cache is enabled
                $overview_unviewwhere .= " AND fid NOT IN(" . implode(',', $onlyusfids) . ")";
            }
            else
            {
                $overview_unviewwhere .= " AND ((fid IN(" . implode(',', $onlyusfids) . ") AND uid='{$mybb->user['uid']}') OR fid NOT IN(" . implode(',', $onlyusfids) . "))";
            }
        }

        // Define variables
        $overview_content = $trow_message = $overview = "";

        // Determine sort order
        $order = array();

        foreach (array('overview_newest_members', 'overview_top_posters', 'overview_newest_threads', 'overview_most_replies', 'overview_favourite_threads', 'overview_newest_posts', 'overview_edited_posts', 'overview_bestrep_members', 'overview_newest_polls', 'overview_next_events') as $key)
        {
            $val = $mybb->settings[$key];

            if ($val)
            {
                $order[$key] = $val;
            }
        }

        asort($order);

        // Determine number of columns
        $num_columns = count($order);

        // Build the content in the determined order.
        foreach ($order as $key => $val)
        {
            $overview_content .= call_user_func($key, $overview_unviewwhere);
        }

        // Show message?
        if ($mybb->settings['overview_trow_message_onoff'] == "1")
        {
            require_once  MYBB_ROOT . "inc/class_parser.php";
            $messageparser = new postParser;
            $parseoptions = array(
                "allow_html" => 0,
                "allow_mycode" => 1,
                "allow_smilies" => 1,
                "allow_imgcode" => 1
            );
            $overview_message = $messageparser->parse_message(htmlspecialchars_uni($mybb->settings['overview_trow_message']), $parseoptions);
            eval("\$trow_message = \"" . $templates->get("overview_message") . "\";");
        }

        // Load template
        eval("\$overview = \"" . $templates->get("overview") . "\";");

        // Populate cache
        if ($delta > 0)
        {
            $cache->update(
                "overview{$extra}",
                array('time' => TIME_NOW, 'data' => $overview)
            );
        }

        return $overview;
    }
}

function overview_ajax()
{
    global $mybb;

    if ($mybb->input['action'] == "overview" && $mybb->settings['overview_ajax'])
    {
        echo overview();
    }
}

function overview_end()
{
    global $mybb, $templates, $overview, $overview_javascript;

    $overview = $overview_javascript = "";

    if ((empty($mybb->settings['overview_usergroups']) || is_member(explode(",", $mybb->settings['overview_usergroups']))))
    {
        if ($mybb->settings['overview_ajax'])
        {
            if ($mybb->settings['overview_ajax_loading'] == 1)
            {
                $loaddisplay = 1;
            }
            else
            {
                $loaddisplay = 0;
            }

            $intervall = $mybb->settings['overview_ajax'] * 1000;
            $overview = "<span id=\"overview_load\"></span>\n<div id=\"overview\"></div>";
            eval("\$overview_javascript = \"" . $templates->get("overview_javascript") . "\";");
        }
        else
        {
            overview();
        }
    }
}

/* --- Columns: --- */

// Newest members
function overview_newest_members()
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_newest_members;
    $column1_heading = $lang->overview_username;
    $column2_heading = $lang->overview_posts;

    // Fetch data for newest user from database
    $query = $db->simple_select("users", "username, postnum, uid, usergroup, displaygroup", "", array(
        "order_by" => 'regdate', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($users = $db->fetch_array($query))
    {
        $val1 = overview_parseuser($users['uid'], $users['username'], $users['usergroup'], $users['displaygroup']);
        $val2 = "<a href=\"search.php?action=finduser&amp;uid={$users['uid']}\">{$users['postnum']}</a>";
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Top posters
function overview_top_posters()
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_top_posters;
    $column1_heading = $lang->overview_username;
    $column2_heading = $lang->overview_posts;

    // Fetch data for top posters from database
    $query = $db->simple_select("users", "username, postnum, uid, usergroup, displaygroup", "", array(
        "order_by" => 'postnum', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($users = $db->fetch_array($query))
    {
        $val1 = overview_parseuser($users['uid'], $users['username'], $users['usergroup'], $users['displaygroup']);
        $val2 = "<a href=\"search.php?action=finduser&amp;uid={$users['uid']}\">{$users['postnum']}</a>";
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Newest threads
function overview_newest_threads($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    // Hintergrund festlegen
    $trow = alt_trow();

    $table_heading = $lang->overview_newest_threads;
    $column1_heading = $lang->overview_topic;
    $column2_heading = $lang->overview_author;
    $column3_heading = $lang->overview_replies;

    // Fetch data
    $query = $db->simple_select("threads", "subject, username, uid, tid, replies, icon, prefix", "visible = 1 {$overview_unviewwhere} AND closed NOT LIKE 'moved|%'", array(
        "order_by" => 'dateline', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($threads = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($threads['subject'], $threads['icon'], $threads['prefix'], $threads['tid']);
        $val2 = overview_parseuser($threads['uid'], $threads['username']);
        $val3 = "<a href=\"javascript:MyBB.whoPosted({$threads['tid']});\">{$threads['replies']}</a>";
        eval("\$table_content .= \"" . $templates->get("overview_3_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_3_columns") . "\";");

    return $output;
}

// Most replies
function overview_most_replies($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_most_replies;
    $column1_heading = $lang->overview_topic;
    $column2_heading = $lang->overview_replies;

    // Fetch data
    $query = $db->simple_select("threads", "subject, tid, replies, icon, prefix", "visible = 1 {$overview_unviewwhere} AND closed NOT LIKE 'moved|%'", array(
        "order_by" => 'replies', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($threads = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($threads['subject'], $threads['icon'], $threads['prefix'], $threads['tid']);
        $val2 = "<a href=\"javascript:MyBB.whoPosted({$threads['tid']});\">{$threads['replies']}</a>";
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Favourite threads
function overview_favourite_threads($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_favourite_threads;
    $column1_heading = $lang->overview_topic;
    $column2_heading = $lang->overview_views;

    // Fetch data
    $query = $db->simple_select("threads", "subject, tid, views, icon, prefix", "visible = 1 {$overview_unviewwhere} AND closed NOT LIKE 'moved|%'", array(
        "order_by" => 'views', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($threads = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($threads['subject'], $threads['icon'], $threads['prefix'], $threads['tid']);
        $val2 = $threads['views'];
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Newest posts
function overview_newest_posts($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_newest_posts;
    $column1_heading = $lang->overview_subject;
    $column2_heading = $lang->overview_author;

    $overview_unviewwhere = str_replace(array('fid', 'uid'), array('t.fid', 't.uid'), $overview_unviewwhere);

    // Fetch data
    $query = $db->query("SELECT p.subject, p.username, p.uid, p.pid, p.tid, p.icon
                         FROM " . TABLE_PREFIX . "posts p
                         LEFT JOIN " . TABLE_PREFIX . "threads t ON (p.tid=t.tid)
                         WHERE p.visible=1 AND t.visible=1 {$overview_unviewwhere}
                         ORDER BY p.dateline DESC
                         LIMIT {$mybb->settings['overview_max']};");

    // Print data
    while ($posts = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($posts['subject'], $posts['icon'], 0, $posts['tid'], $posts['pid'], 0, 1);
        $val2 = overview_parseuser($posts['uid'], $posts['username']);
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Edited posts
function overview_edited_posts($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_edited_posts;
    $column1_heading = $lang->overview_subject;
    $column2_heading = $lang->overview_author;

    $overview_unviewwhere = str_replace(array('fid', 'uid'), array('t.fid', 't.uid'), $overview_unviewwhere);

    // Fetch data
    $query = $db->query("SELECT p.subject, p.username, p.uid, p.pid, p.tid, p.icon
                         FROM " . TABLE_PREFIX . "posts p
                         LEFT JOIN " . TABLE_PREFIX . "threads t ON (p.tid=t.tid)
                         WHERE p.edittime != 0 AND p.visible=1 AND t.visible=1 {$overview_unviewwhere}
                         ORDER BY p.edittime DESC
                         LIMIT {$mybb->settings['overview_max']};");

    // Print data
    while ($posts = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($posts['subject'], $posts['icon'], 0, $posts['tid'], $posts['pid'], 0, 1);
        $val2 = overview_parseuser($posts['uid'], $posts['username']);
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Next events
function overview_next_events()
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_next_events;
    $column1_heading = $lang->overview_event;
    $column2_heading = $lang->overview_author;

    if ($mybb->usergroup['canviewcalendar'] == 1)
    {
        // Permissions
        $query = $db->simple_select("calendarpermissions", "cid", "gid = '" . intval($mybb->user['usergroup']) . "'AND canviewcalendar = '0';");

        $cids = $sep = "";

        if ($db->num_rows($query) != 0)
        {
            while ($groups = $db->fetch_array($query))
            {
                $cids .= $sep . $groups['cid'];
                $sep = ",";
            }

            $cids = "AND e.cid NOT IN ({$cids})";
        }

        // TODO: Instead of substracting 24 hours, align to the users timezone boundary.
        $today = TIME_NOW - 60 * 60 * 24;

        // Decide whether we can include private events or not.
        if (intval($mybb->settings['overview_cache']) > 0)
        {
            $private = "e.private='0'";
        }
        else
        {
            $private = "(e.private='0' OR e.uid='" . intval($mybb->user['uid']) . "')";
        }

        // Fetch data
        $query = $db->query("SELECT e.eid, e.name, e.starttime, e.uid, u.username, u.usergroup, u.displaygroup
                             FROM " . TABLE_PREFIX . "events e
                             LEFT JOIN " . TABLE_PREFIX . "users u ON (e.uid=u.uid)
                             WHERE e.visible = '1' AND {$private} AND (e.starttime > '{$today}' OR e.endtime > '{$today}') {$cids}
                             ORDER BY e.starttime ASC
                             LIMIT {$mybb->settings['overview_max']};");

        // Print data
        while ($events = $db->fetch_array($query))
        {
            $events['name'] = my_date($mybb->settings['dateformat'], $events['starttime']) . ": " . $events['name'];
            $val1 = overview_parsesubject($events['name'], 0, 0, 0, 0, $events['eid'], 0);
            $val2 = overview_parseuser($events['uid'], $events['username'], $events['usergroup'], $events['displaygroup']);
            eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
        }
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Newest polls
function overview_newest_polls($overview_unviewwhere)
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_newest_polls;
    $column1_heading = $lang->overview_question;
    $column2_heading = $lang->overview_author;

    $overview_unviewwhere = str_replace(array('fid', 'uid'), array('t.fid', 't.uid'), $overview_unviewwhere);

    // Fetch data
    $query = $db->query("SELECT p.question, p.tid, t.uid, t.username
                         FROM " . TABLE_PREFIX . "polls p
                         LEFT JOIN " . TABLE_PREFIX . "threads t ON (p.tid=t.tid)
                         WHERE t.visible='1' {$overview_unviewwhere} AND t.closed NOT LIKE 'moved|%'
                         ORDER BY p.pid DESC
                         LIMIT {$mybb->settings['overview_max']};");

    // Print data
    while ($polls = $db->fetch_array($query))
    {
        $val1 = overview_parsesubject($polls['question'], 0, 0, $polls['tid']);
        $val2 = overview_parseuser($polls['uid'], $polls['username']);
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

// Members with the best reputation
function overview_bestrep_members()
{
    global $mybb, $db, $templates, $theme, $lang, $trow;

    $trow = alt_trow();

    $table_heading = $lang->overview_bestrep_members;
    $column1_heading = $lang->overview_username;
    $column2_heading = $lang->overview_reputation;

    // Fetch data
    $query = $db->simple_select("users", "username, reputation, uid, usergroup, displaygroup", "", array(
        "order_by" => 'reputation', "order_dir" => 'DESC', "limit" => $mybb->settings['overview_max']
    ));

    // Print data
    while ($users = $db->fetch_array($query))
    {
        $val1 = overview_parseuser($users['uid'], $users['username'], $users['usergroup'], $users['displaygroup']);
        $val2 = get_reputation($users['reputation'], $users['uid']);
        eval("\$table_content .= \"" . $templates->get("overview_2_columns_row") . "\";");
    }

    eval("\$output = \"" . $templates->get("overview_2_columns") . "\";");

    return $output;
}

/* --- Helpers: --- */

function overview_parsesubject($subject, $icon = 0, $prefix = 0, $tid = 0, $pid = 0, $eid = 0, $removere = 0)
{
    global $mybb, $parser, $cache, $db;

    if ($mybb->settings['overview_show_re'] == 0 && $removere == 1)
    {
        $subject = str_replace("RE: ", "", $subject);
    }

    if (!$parser)
    {
        require_once  MYBB_ROOT . "inc/class_parser.php";
        $parser = new postParser;
    }

    $subjectfull = $subject = $parser->parse_badwords($subject);

    if ($mybb->settings['overview_subjects_length'] != 0)
    {
        if (my_strlen($subject) > $mybb->settings['overview_subjects_length'])
        {
            $subject = my_substr($subject, 0, $mybb->settings['overview_subjects_length']) . "...";
        }
    }

    $subjectfull = htmlspecialchars_uni($subjectfull);
    $subject = htmlspecialchars_uni($subject);

    if ($pid)
    {
        $link = get_post_link($pid, $tid) . "#pid" . $pid;
    }
    else if ($eid)
    {
        $link = get_event_link($eid);
    }
    else
    {
        $link = get_thread_link($tid);
    }

    // Icon
    if ($mybb->settings['overview_showicon'] != 0 && $icon > 0)
    {
        $icon_cache = $cache->read("posticons");
        $icon = $icon_cache[$icon];
    }

    if (is_array($icon))
    {
        $icon = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" style=\"vertical-align: middle;\" />&nbsp;";
    }
    else
    {
        $icon = "";
    }

    // Prefix
    if ($mybb->settings['overview_showprefix'] && $prefix > 0)
    {
        // MyBB does not have a prefix cache - boo hoo.
        global $overview_prefixcache;

        if (!isset($overview_prefixcache[$prefix]))
        {
            $query = $db->simple_select('threadprefixes', 'displaystyle', "pid='$prefix'");
            $row = $db->fetch_array($query);

            if ($row)
            {
                $overview_prefixcache[$prefix] = $row['displaystyle'] . '&nbsp';
            }
            else
            {
                $overview_prefixcache[$prefix] = '';
            }
        }

        $prefix = $overview_prefixcache[$prefix];
    }
    else
    {
        $prefix = '';
    }

    return "{$icon}{$prefix}<a href=\"{$link}\" title=\"{$subjectfull}\">{$subject}</a>";
}

function overview_parseuser($uid, $username, $usergroup = 0, $displaygroup = 0)
{
    global $mybb, $db, $lang;

    $username = htmlspecialchars_uni($username);

    if (!$uid)
    {
        $usergroup = 1;
        if ($username == "Guest")
        {
            $username = $lang->guest;
        }
    }

    if ($mybb->settings['overview_usernamestyle'] == 1)
    {
        if (!$usergroup)
        {
            $query = $db->simple_select("users", "username, usergroup, displaygroup", "uid = '{$uid}'");
            $user = $db->fetch_array($query);
            $username = htmlspecialchars_uni($user['username']);
            $usergroup = $user['usergroup'];
            $displaygroup = $user['displaygroup'];
        }

        $username = format_name($username, $usergroup, $displaygroup);
    }

    if ($uid)
    {
        $link = get_profile_link($uid);
        return "<a href=\"{$link}\">{$username}</a>";
    }
    else
    {
        return $username;
    }
}
