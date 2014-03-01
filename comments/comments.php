<?php

/**
 * Comments Plugin for Morfy CMS
 *
 * @package Morfy
 * @subpackage Plugins
 * @author Taufik Nurrohman <http://latitudu.com>
 * @copyright 2014 Romanenko Sergey / Awilum
 * @version 1.0.1
 *
 */

// Include `shell.css` in header
Morfy::factory()->addAction('theme_header', function() {
    echo '<link href="' . Morfy::$config['site_url'] . '/plugins/comments/lib/css/shell.css" rel="stylesheet">' . "\n";
});

// Usage => Morfy::factory()->runAction('comments');
Morfy::factory()->addAction('comments', function() {

    // Configuration data
    $config = Morfy::$config['comments_config'];
    // HTML classes
    $_ = $config['classes'];
    $c = $config['classes']['connector'];
    // Get URL data
    $home = rtrim(Morfy::$config['site_url'], '/');
    $current_url = trim(Morfy::factory()->getUrl(), '/');
    // Create "database" name based on current page path
    // Replace all `/` character to `__` so we can use it to make a valid file name
    $database = PLUGINS_PATH . '/comments/content/' . str_replace('/', '__', $current_url) . '.txt';
    // Page param
    $param = $config['param'];
    // Check if we have admin plugin installed
    $is_admin_plugin_installed = in_array('admin', Morfy::$config['plugins']);
    $is_admin = isset(Morfy::$config['logged_in']) && Morfy::$config['logged_in'] === true;

    // Function to create and/or update the content of a TXT file (our database)
    function create_or_update_file($file_path, $data) {
        $handle = fopen($file_path, 'w') or die('Cannot open file: ' . $file_path);
        fwrite($handle, $data);
    }

    // Function to parse the comment data.
    // Based on `Morfy::factory()->getPage()`
    function parse_comment_data($str) {
        $fields = array(
            'id' => 'ID',
            'name' => 'Name',
            'email' => 'Email',
            'url' => 'URL',
            'date' => 'Date',
            'message' => 'Message',
            'role' => 'Role',
            'parent' => 'Parent'
        );
        $comment = array(); // Prepare the container.
        foreach($fields as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $str, $match) && $match[1]) {
                $comment[$field] = trim($match[1]);
            } else {
                $comment[$field] = '';
            }            
        }
        return $comment;
    }

    // Restore allowed HTML outputs. 
    // The rest will appear as plain HTML entities to prevent XSS. 
    // => http://en.wikipedia.org/wiki/Cross-site_scripting
    function restore_allowed_html($data) {
        $tags = explode('|', Morfy::$config['comments_config']['allowed_html']);

        foreach($tags as $tag) {
            if(preg_match('/^(br|hr|img)$/i', $tag)) {
                // Self closing HTML tags.
                $data = preg_replace(
                    array(
                        '/&lt;(br|hr) ?\/?&gt;/i',
                        '/&lt;img (.*?) ?\/?&gt;/i'
                    ),
                    array(
                        '<$1>',
                        '<img $1>'
                    ),
                $data);
            } else {
                // Make sure we only restore the HTML tags that is closed properly.
                // Matched with encoded `<b>text</b>` but not `<b>text<b>` or `<b>text</i>` or `<b>text<i>`
                $data = preg_replace(
                    array(
                        '/&lt;' . $tag . '&gt;(.*?)&lt;\/' . $tag . '&gt;/i'
                    ),
                    array(
                        '<' . $tag . '>$1</' . $tag . '>'
                    ),
                $data);
            }
        }

        $data = preg_replace(
            array(
                // Remove extra line break for possible block element
                '/<br><(blo|div|fig|p|pre)/i',
                '/<\/(blo|div|fig|p|pre)><br>/i',
                // Symbols.
                '/&amp;([a-zA-Z]+|\#[0-9]+);/'
            ),
            array(
                '<$1',
                '</$1>',
                '&$1;'
            ),
        $data);

        return $data;
    }

    // Check whether the "database" is not available. If not, create one!
    if( ! file_exists($database)) {
        create_or_update_file($database, "");
    } else {
        $old_data = file_get_contents($database);
    }

    /**
     * Post a comment
     */

    $notify = ""; // Messages.
    $failed = false; // End result?
    if($_SERVER['REQUEST_METHOD'] == 'POST') {
        $id = uniqid();
        $name = "";
        $email = "";
        $url = "";
        $time = date('U');
        $message = "";
        $role = "Guest"; // Default as guest.
        $parent = "";

        // Make sure the guest name is not empty.
        if(isset($_POST['name']) && ! empty($_POST['name'])) {
            $name = Morfy::factory()->cleanString($_POST['name']);
        } else {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['missing_name'] . '</p>';
        }

        // Make sure the guest email is not empty.
        if(isset($_POST['email']) && ! empty($_POST['email'])) {
            if(filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                $email = Morfy::factory()->cleanString($_POST['email']);
            } else {
                $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['invalid_email'] . '</p>';
            }
        } else {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['missing_email'] . '</p>';
        }

        // Make sure the URL format is valid. Set its value as `-` if empty. 
        if(isset($_POST['url']) && ! empty($_POST['url'])) {
            if(filter_var($_POST['url'], FILTER_VALIDATE_URL)) {
                $url = Morfy::factory()->cleanString($_POST['url']);
            } else {
                $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['invalid_url'] . '</p>';
            }
        } else {
            $url = "-";
        }

        // Make sure the guest message is not empty.
        if(isset($_POST['message']) && ! empty($_POST['message'])) {
            $message = preg_replace(
                array(
                    // [1]
                    '/(\.?[\n\r]\.?){4,}/',
                    '/\n/',
                    '/\r/',
                    '/\t/',
                    // Multiple space characters.
                    '/ {2}/',
                    '/ &nbsp;|&nbsp; /',
                    // Matched with links.
                    '/<(a .*?|\/a)>/i'
                ),
                array(
                    '<br><br>',
                    '<br>',
                    '',
                    '&nbsp;&nbsp;&nbsp;&nbsp;',
                    '&nbsp;&nbsp;',
                    '&nbsp;&nbsp;',
                    // Unlink all links in message content!
                    ''
                ),
            $_POST['message']);
            $message = Morfy::factory()->cleanString($message); // [2]
        } else {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['missing_message'] . '</p>';
        }

        // Check for parent comment.
        if(isset($_POST['parent']) && ! empty($_POST['parent'])) {
            $parent = Morfy::factory()->cleanString($_POST['parent']);
        }

        // Prevent visitor from entering your email address in the email field.
        // Including YOU, if you are not logged in (only if you have admin plugin installed).
        if($is_admin_plugin_installed) {
            if( ! $is_admin) {
                if($email === Morfy::$config['email']) {
                    $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['is_admin_email'] . ' <a href="' . $home . '/admin/login" target="_blank">' . Morfy::$config['admin_config']['labels']['login'] . '</a></p>';
                }
            } else {
                $role = 'Admin'; // Yay!
            }
        }

        // Check for math challenge answer to prevent spam robot.
        if( ! isset($_POST['math']) || empty($_POST['math']) || $_POST['math'] != $_SESSION['math']) {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['invalid_math'] . '</p>';
        }

        // Check for character length limit
        if(strlen($name) > $config['max_length_name']) {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . str_replace('{num}', $config['max_length_name'], $config['labels']['max_length_name']) . '</p>';
        }
        if(strlen($url) > $config['max_length_url']) {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . str_replace('{num}', $config['max_length_url'], $config['labels']['max_length_url']) . '</p>';
        }
        if(strlen($message) > $config['max_length_message']) {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . str_replace('{num}', $config['max_length_message'], $config['labels']['max_length_message']) . '</p>';
        }

        // If all data entered by guest is valid, insert new data!
        if($notify === "") {
            $new_data = "ID: " . $id . "\nName: " . $name . "\nEmail: " . $email . "\nURL: " . $url . "\nDate: " . $time . "\nMessage: " . $message . "\nRole: " . $role . "\nParent: " . $parent;
            // if( ! preg_match('/buy twitter fol|buy|cheap|add your own banning text here|another banning text here/im', $new_data) && ! isset($_SESSION['user_comment_banned'])) {
                if( ! empty($old_data)) {
                    create_or_update_file($database, $old_data . "\n" . Morfy::SEPARATOR . "\n" . $new_data); // Append data.
                } else {
                    create_or_update_file($database, $new_data); // Insert data.
                }
                $notify = '<p class="' . $_['message'] . $c . $_['success'] . '">' . $config['labels']['comment_success'] . '</p>';
            // } else {
                // $notify = '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['is_user_banned'] . '</p>';
                // $_SESSION['user_comment_banned'] = true;
            // }

            if($config['email_notify']) {
                $header  = "From: " . $email . " \r\n";
                $header .= "Reply-To: " . $email . " \r\n";
                $header .= "Return-Path: " . $email . " \r\n";
                $header .= "X-Mailer: PHP \r\n";

                $e_message = str_replace('{url}', $home . '/' . $current_url . '#' . $_['comment'] . $c . $_['header'], $config['email_notify_message'] . "\r\n\r\n" . $name . ": " . $message . "\r\n\r\n" . date('Y/m/d H:i:s', $time));

                if( ! $is_admin) {
                    // Sending email notification...
                    mail(Morfy::$config['email'], $config['email_notify_subject'], $e_message, $header);
                }
            }

        } else {
            $failed = true;
        }

    }

    // [3]
    $_SESSION['guest_name'] = isset($_POST['name']) ? $_POST['name'] : "";
    $_SESSION['guest_email'] = isset($_POST['email']) ? $_POST['email'] : "";
    $_SESSION['guest_url'] = isset($_POST['url']) ? $_POST['url'] : $config['labels']['default_url'];
    $_SESSION['guest_message'] = isset($_POST['message']) && $failed ? Morfy::factory()->cleanString($_POST['message']) : "";


    // ----------------------------------------------------------------------------------------
    // [1]. Prevent guest to type too many line break symbols (sometimes with dots).
    // People usually do these thing to make their SPAM messages looks striking (at least in my country).
    // [2]. Convert all HTML tags into HTML entities. This is done thoroughly for safety.
    // We can revert back the escaped HTML into normal HTML tags later via `restore_allowed_html()`
    // [3]. Save the form data into session. So if something goes wrong, the data entered
    // by guest will still be stored in the form after submitting.
    // ----------------------------------------------------------------------------------------


    // Math challenge to prevent spam robot. 
    // Current answer will be stored in `$_SESSION['math']`
    $x = mt_rand(1, 10);
    $y = mt_rand(1, 10);
    if($x - $y > 0) {
        $math = $x . ' - ' . $y;
        $_SESSION['math'] = $x - $y;
    } else {
        $math = $x . ' + ' . $y;
        $_SESSION['math'] = $x + $y;
    }

    // Testing...
    // echo $math . ' = ' . $_SESSION['math'];


    /**
     * Show the existing data.
     */

    $data = file_get_contents($database);
    $current_page = isset($_GET[$param]) ? $_GET[$param] : 1;
    $total_pages = 0;
    $count = 0;
    $count_no_parent = 0;
    $count_has_parent = 0;
    $pager = "";
    $html = "";

    // "D-R-Y"
    // Dat `$config, $_, $c, $item, $param` things are sux!
    function comment_item($config, $_, $c, $item, $param) {
        $html  = '<li class="' . $_['comment'] . ' ' . $_['comment'] . $c . ($item['role'] == 'Admin' ? $_['admin'] : $_['guest']) . '" id="' . $_['comment'] . $c . $item['id'] . '">';
        if($config['avatar_size'] && $config['avatar_size'] > 0) {
            $html .= '<div class="' . $_['comment'] . $c . $_['avatar'] . '">';
            $html .= '<img alt="' . $item['name'] . '" src="http://www.gravatar.com/avatar/' . md5($item['email']) . '?s=' . $config['avatar_size'] . '&amp;d=monsterid" width="' . $config['avatar_size'] . '" height="' . $config['avatar_size'] . '">';
            $html .= '</div>';
        }
        $html .= '<div class="' . $_['comment'] . $c . $_['detail'] . '">';
        $html .= '<span class="' . $_['comment'] . $c . $_['name'] . '">';
        $html .= $item['url'] != "-" ? '<a href="' . $item['url'] . '" rel="nofollow" target="_blank">' : "";
        $html .= $item['name'];
        $html .= $item['url'] != "-" ? '</a>' : "";
        $html .= '</span>';
        $html .= '<span class="' . $_['comment'] . $c . $_['time'] . '">';
        $html .= '<time datetime="' . date('c', $item['date']) . '">' . date($config['date_format'], $item['date']) . '</time>';
        $html .= ' <a href="?id=' . $item['id'] . '#' . $_['comment'] . $c . $item['id'] . '">#</a>';
        $html .= '</span>';
        $html .= '</div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['message'] . '">' . restore_allowed_html($item['message']) . '</div>';
        if($config['threaded_comments'] !== false) {
            $html .= '<div class="' . $_['comment'] . $c . $_['footer'] . '">';
            $html .= '<div class="' . $_['comment'] . $c . $_['footer'] . $c . $_['line'] . $c . '1">';
            $html .= '<a class="' . $_['comment'] . $c . $_['reply'] . '" href="?reply=' . $item['id'] . '#' . $_['comment'] . $c . $_['form'] . '" data-comment-id="' . $item['id'] . '" data-comment-name="' . $item['name'] . '">' . $config['labels']['comment_reply'] . '</a>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</li>';
        return $html;
    }

    function comment_form($config, $_, $c, $item, $home, $current_url, $total_pages, $param, $is_admin_plugin_installed, $is_admin, $notify, $math, $parent) {
        $html = '<form class="' . $_['comment'] . $c . $_['form'] . ($parent != "-" ? ' ' . $_['comment'] . $c . $_['form'] . $c . $_['reply'] : "") . ' cf" id="' . $_['comment'] . $c . $_['form'] . '" method="post" action="' . $home . '/' . $current_url . ($total_pages > 1 ? '?' . $param . '=' . $total_pages : "") . '#' . $_['comment'] . $c . $_['form'] . '">';
        $html .= $config['labels']['comment_guide'];

        $html .= $notify !== "" ? '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['status'] . '">' . $notify . '</div>' : "";

        $html .= '<input type="hidden" name="parent" value="' . $parent . '">';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['name'] . '"><input type="text" name="name" value="' . $_SESSION['guest_name'] . '" placeholder="' . $config['labels']['default_name'] . '"></div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['email'] . '"><input type="email" name="email" value="' . $_SESSION['guest_email'] . '" placeholder="' . $config['labels']['default_email'] . '"></div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['url'] . '"><input type="url" name="url" value="' . $_SESSION['guest_url'] . '" placeholder="' . $config['labels']['default_url'] . '"></div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['math'] . '"><span>' . $math . '</span><input type="text" name="math" value="" autocomplete="off"></div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['message'] . '"><textarea name="message" placeholder="' . $config['labels']['default_message'] . '">' . $_SESSION['guest_message'] . '</textarea></div>';
        $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['submit'] . '">';

        if($is_admin_plugin_installed) {
            $html .= '<strong class="' . $_['comment'] . $c . $_['is'] . $c . $_['admin'] . '">';
            $html .= $is_admin ? Morfy::$config['admin_config']['labels']['message_logged_in'] . ' <a href="' . $home . '/admin/logout?redirect=' . $current_url . '">' : '<a href="' . $home . '/admin/login?redirect=' . $current_url . '">';
            $html .= Morfy::$config['admin_config']['labels'][$is_admin ? 'logout' : 'login'];
            $html .= '</a></strong>';
        }

        $html .= '<button type="submit">' . $config['labels']['comment_add'] . '</button>';
        $html .= '</div>';

        if(isset($_GET['reply'])) {
            $html .= '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['cancel'] . '">';
            $html .= '<a class="' . $_['comment'] . $c . $_['reply'] . $c . $_['cancel'] . '" href="?' . $param . '=1#' . $_['comment'] . $c . $_['header'] . '">' . $config['labels']['comment_cancel'] . ' ' . $config['labels']['comment_reply'] . '</a>';
            $html .= '</div>';
        }

        $html .= '</form>';
        return $html;
    }

    if( ! empty($data)) {
        $data = explode("\n" . Morfy::SEPARATOR . "\n", $data);
        $count = count($data);
        $total_pages = ceil($count / $config['per_page']);

        $html .= '<h3 class="' . $_['comment'] . $c . $_['header'] . '" id="' . $_['comment'] . $c . $_['header'] . '">' . $count . ' ' . ($count > 1 ? $config['labels']['comment_p'] : $config['labels']['comment_s']) . '</h3>';
        $html .= '<ol class="' . $_['comment'] . $c . $_['list'] . '" id="' . $_['comment'] . $c . $_['list'] . '">';

        for($i = 0; $i < $count; $i++) {
            $item = parse_comment_data($data[$i]);
            // print_r($item);
            if(isset($_GET['id'])) {
                // Show single comment from permalink.
                // Example => ../blog/post-slug?id=52fd783b4d0cd#comment-52fd783b4d0cd
                if($_GET['id'] == $item['id']) {
                    $html .= comment_item($config, $_, $c, $item, $param);
                }
            } else {
                // List all available comments.
                if($i <= ($config['per_page'] * $current_page) - 1 && $i > ($config['per_page'] * ($current_page - 1)) - 1) {
                    if($item['parent'] == "-") {
                        $html .= comment_item($config, $_, $c, $item, $param);
                        $count_no_parent++;
                    }
                    $delay_html = "";
                    for($j = 0; $j < $count; $j++) {
                        $children = parse_comment_data($data[$j]);
                        if($children['parent'] != "-" && $children['parent'] == $item['id']) {
                            $delay_html .= comment_item($config, $_, $c, $children, $param);
                            $count_has_parent++;
                        }
                    }
                    if($delay_html !== "") {
                        $html .= '<ol class="' . $_['comment'] . $c . $_['list'] . ' ' . $_['comment'] . $c . $_['list'] . $c . $_['reply'] . '">' . $delay_html . '</ol>';
                        if($config['threaded_comments'] !== false) {
                            $html .= '<div class="' . $_['comment'] . $c . $_['list'] . $c . $_['reply'] . $c . $_['line'] . $c . '1">';
                            $html .= '<div><a class="' . $_['comment'] . $c . $_['reply'] . '" href="?reply=' . $item['id'] . '#' . $_['comment'] . $c . $_['form'] . $c . $_['reply'] . '" data-comment-id="' . $item['id'] . '" data-comment-name="' . $item['name'] . '">' . $config['labels']['comment_reply'] . '</a></div>';
                            $html .= '</div>';
                        }
                    }
                }
            }
        }

        $html .= '</ol>';

        // Limit page number interval.
        // Example error => ../blog/post-slug?commentPage=0, ../blog/post-slug?commentPage=999999xyz
        if($current_page < 1 || $current_page > $total_pages) {
            $notify .= '<p class="' . $_['message'] . $c . $_['error'] . '">' . $config['labels']['not_found'] . '</p>';
        }

        // Create comment navigation if the number of pages is more than 1.
        if($total_pages > 1) {
            $pager .= $current_page > 1 ? '<a class="' . $_['comment'] . $c . $_['pager'] . $c . $_['prev'] . '" href="?' . $param . '=' . ($current_page - 1) . '#' . $_['comment'] . $c . $_['header'] . '">' . $config['labels']['comment_prev'] . '</a>' : '<span class="' . $_['comment'] . $c . $_['pager'] . $c . $_['prev'] . '">' . $config['labels']['comment_prev'] . '</span>';
            for($i = 0; $i < $total_pages; $i++) {
                if($current_page == ($i + 1)) {
                    $pager .= ' <span class="' . $_['comment'] . $c . $_['pager'] . $c . $_['num'] . '">' . ($i + 1) . '</span>'; // Disabled navigation.
                } else {
                    $pager .= ' <a class="' . $_['comment'] . $c . $_['pager'] . $c . $_['num'] . '" href="?' . $param . '=' . ($i + 1) . '#' . $_['comment'] . $c . $_['header'] . '">' . ($i + 1) . '</a>';
                }
            }
            $pager .= $current_page < $total_pages ? ' <a class="' . $_['comment'] . $c . $_['pager'] . $c . $_['next'] . '" href="?' . $param . '=' . ($current_page + 1) . '#' . $_['comment'] . $c . $_['header'] . '">' . $config['labels']['comment_next'] . '</a>' : ' <span class="' . $_['comment'] . $c . $_['pager'] . $c . $_['next'] . '">' . $config['labels']['comment_next'] . '</span>';
        }

    } else {

        $html .= '<h3 class="' . $_['comment'] . $c . $_['header'] . '" id="' . $_['comment'] . $c . $_['header'] . '">0 ' . $config['labels']['comment_s'] . '</h3>';
        $html .= '<ol class="' . $_['comment'] . $c . $_['list'] . ' ' . $_['comment'] . $c . $_['list'] . $c . $_['disabled'] . '" id="' . $_['comment'] . $c . $_['list'] . '">';
        $html .= '<li class="' . $_['comment'] . '">' . $config['labels']['comment_e'] . '</li>';
        $html .= '</ol>';

    }

    if(isset($_GET['id'])) {
        $pager = '<a href="?' . $param . '=1#' . $_['comment'] . $c . $_['header'] . '">' . $config['labels']['comment_show_all'] . '</a>';
    }

    $html .= '<nav class="' . $_['comment'] . $c . $_['pager'] . '">' . trim($pager) . '</nav>';

    if($count < $config['max'] && ! isset($_SESSION['user_comment_banned'])) {
        $html .= comment_form($config, $_, $c, "", $home, $current_url, $total_pages, $param, $is_admin_plugin_installed, $is_admin, $notify, $math, (isset($_GET['reply']) ? $_GET['reply'] : "-"));
    } else {
        $html .= $notify !== "" ? '<div class="' . $_['comment'] . $c . $_['form'] . $c . $_['status'] . '">' . $notify . '</div>' : "";
        $html .= '<p class="' . $_['comment'] . $c . $_['disabled'] . '">' . $config['labels']['comment_closed'] . '</p>';
    }

    // Include and run `sword.js`
    if($config['threaded_comments'] !== false) {
        $html .= '<script src="' . $home . '/plugins/comments/lib/js/sword.js"></script>';
        $html .= '<script>CommentFormReply({';
        $html .= 'comment:"' . $_['comment'] . $c . $_['list'] . '",form:"' . $_['comment'] . $c . $_['form'] . '",reply:"' . $_['comment'] . $c . $_['reply'] . '",cancel:"' . $_['comment'] . $c . $_['form'] . $c . $_['cancel'] . '",cancelText:"' . $config['labels']['comment_cancel'] . ' ' . $config['labels']['comment_reply'] . '"';
        $html .= '});</script>';
    }

    echo $html;

});
