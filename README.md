Comments Plugin for Morfy CMS
=============================

Configuration
-------------

Place the `comments` folder with its contents in `plugins` folder. Then update your `config.php` file:

    <?php
        return array(
    
            ...
            ...
            ...
    
            'plugins' => array(
                'markdown',
                'sitemap',
                'admin', // <= Recommended to be installed
                'comments' // <= Activation
            ),
            'comments_config' => array(
    
                // Notify new comment that has been added?
                'email_notify' => false,
                'email_notify_subject' => 'New comment has been added.',
                'email_notify_message' => 'A new comment has been added to this page => {url}',
    
                // Maximum comments allowed per page. If the amount has been exceeded
                // the `max` value, then the comment form will be hidden automatically
                // so that visitors no longer be able to post a comment.
                'max' => 500,
    
                'max_length_name' => 60, // Maximum character length for guest name
                'max_length_url' => 120, // Maximum character length for guest URL
                'max_length_message' => 1000, // Maximum character length for guest message
    
                // List of item's HTML class parts
                'classes' => array(
                    'connector' => '-',
                    'container' => 'container',
                    'comment' => 'comment',
                    'header' => 'header',
                    'list' => 'list',
                    'avatar' => 'avatar',
                    'detail' => 'detail',
                    'footer' => 'footer',
                    'line' => 'line',
                    'name' => 'name',
                    'email' => 'email',
                    'url' => 'url',
                    'math' => 'math',
                    'time' => 'time',
                    'status' => 'status',
                    'is' => 'is',
                    'not' => 'not',
                    'admin' => 'admin',
                    'guest' => 'guest',
                    'pager' => 'pager',
                    'prev' => 'prev',
                    'next' => 'next',
                    'num' => 'num',
                    'form' => 'form',
                    'submit' => 'submit',
                    'message' => 'message',
                    'error' => 'error',
                    'success' => 'success',
                    'reply' => 'reply',
                    'edit' => 'edit',
                    'cancel' => 'cancel',
                    'save' => 'save',
                    'disabled' => 'disabled'
                ),
    
                // List of item's readable text or labels
                'labels' => array(
                    'missing_name' => 'Please enter your name.',
                    'missing_email' => 'Please enter your email.',
                    'missing_message' => 'Please enter your message.',
                    'invalid_url' => 'Invalid URL.',
                    'invalid_math' => 'Wrong math answer.',
                    'invalid_token' => 'Invalid token.',
                    'invalid_email' => 'Invalid email address.',
                    'is_admin_email' => 'This email already in use.',
                    'is_user_banned' => 'You have been banned.',
                    'max_length_name' => 'Maximum character length for guest name is {num}',
                    'max_length_url' => 'Maximum character length for guest URL is {num}',
                    'max_length_message' => 'Maximum character length for guest message is {num}',
                    'comment_guide' => '<p>You may use this basic HTML tags: <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;ins&gt;</code>, <code>&lt;blockquote&gt;</code></p>',
                    'comment_e' => 'No comments yet.',
                    'comment_p' => 'Comments', // Plural
                    'comment_s' => 'Comment', // Singular
                    'comment_add' => 'Add Comment',
                    'comment_prev' => 'Previous',
                    'comment_next' => 'Next',
                    'comment_success' => 'Your comment has been added.',
                    'comment_closed' => 'Comments has been closed.',
                    'comment_show_all' => 'Remove filter',
                    'comment_reply' => 'Reply',
                    'comment_edit' => 'Edit',
                    'comment_save' => 'Update',
                    'comment_cancel' => 'Cancel',
                    'comment_delete' => 'Delete',
                    'default_name' => 'Name',
                    'default_email' => 'email@domain.com',
                    'default_url' => 'http://',
                    'default_message' => 'Write your comment here&hellip;',
                    'not_found' => 'Not found.'
                ),
    
                // Enable experimental replying comment feature?
                'threaded_comments' => false,
    
                // Number of comments to show per page
                'per_page' => 100,
    
                // Allowed HTML tags in comment message
                'allowed_html' => 'b|blockquote|br|code|em|i|ins|mark|pre|q|strong|u',
    
                // Set to `0` to hide the avatar
                'avatar_size' => 40,
    
                // Read more => http://www.php.net/manual/en/function.date.php
                'date_format' => 'Y/m/d H:i:s',
    
                // Example => `../blog/page-slug?commentPage=2`
                'param' => 'commentPage'
            )
        );

Add this snippet to your `blog_post.html` that is placed in your `themes` folder to show the comments area:

    <section class="comments">
        <?php Morfy::factory()->runAction('comments'); ?>
    </section>

Done. Read more &rarr; http://latitudu.com/notes/morfy-cms/plugins/comments-plugin
