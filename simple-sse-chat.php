<?php
/*
    Plugin Name: Simple SSE Chat
    Description: Server Snet Eventsを使用した簡易チャット
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグインがアクティブ化されたときに実行される
register_activation_hook(__FILE__, function () {
    global $wpdb;

    $sql = sprintf('CREATE TABLE %ssimple_sse_chat (
        `id` INT NOT NULL AUTO_INCREMENT,
        `user_id` INT NOT NULL,
        `content` TEXT NOT NULL,
        PRIMARY KEY (`id`)
    ) %s;', $wpdb->prefix, $wpdb->get_charset_collate());

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// プラグインが削除されたときに実行される
function simple_sse_chat_uninstall () {
    // TODO マルチサイトの考慮をするとより良いものとなる
    //  see also https://github.com/okumurakengo/simple-sse-chat/pull/7
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}simple_sse_chat");
    delete_option('simple_sse_chat_header');
}
register_uninstall_hook(__FILE__, 'simple_sse_chat_uninstall');

// 管理画面のメニューに追加
add_action('admin_menu', function () {
    add_menu_page(
        'Simple Chat Settings', // <title>タグの内容を設定
        'Simple Chat', // 左メニューに表示される名前を設定
        'manage_options', // 権限
        'simple-sse-chat', // スラッグ
        'admin_menu_simple_sse_chat', // メニューを開いたときに実行される関数名
        'dashicons-admin-comments', // アイコン
        200 // メニューの表示順、200と大きい数字にしたので、メニューの一番下に表示
    );
});
function admin_menu_simple_sse_chat() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_admin_referer('hoge-fuga-piyo'); // CSRF対策
        update_option('simple_sse_chat_header', $_POST['chat_heder']);
        ?>
        <div id="setting-error-settings-update" class="update-settings-error notice is-dismissible"><strong>Settings have been saved.</strong></div>
        <?php
    }
    $chat_heder = get_option('simple_sse_chat_header', 'Simple Chat');
    ?>
        <div class="wrap">
            <h2>Simple Chat Settings</h2>
            <form method="POST" action="">
                <?php wp_nonce_field( 'hoge-fuga-piyo'); ?>
                <label for="chat_heder">チャットタイトル</label>
                <textarea name="chat_heder" class="large-text"><?= esc_textarea($chat_heder) ?></textarea>
                <input type="submit" name="submit_scripts_update" class="button button-primary" value="UPDATE">
            </form>
        </div>
    <?php
}

// ショートコード
add_shortcode('simple_sse_chat', function () {
    $header = get_option('simple_sse_chat_header', 'Simple Chat');

    ob_start();
    ?>
    <form id="js-simple-sse-chat-form">
        <h2><?= esc_html($header) ?></h2>
        <div class="simple-sse-chat-container">
            <table id="js-simple-sse-chat-body">
                <tbody></tbody>
            </table>
        </div>
        <input type="text" name="chat-content" id="js-simple-sse-chat-input">
        <input type="submit" value="送信">
    </form>
    <style>
        .simple-sse-chat-container {
            height: 200px;
            overflow: scroll;
        }
    </style>
    <?php
    return ob_get_clean();
});

// jsを読み込む
add_action('the_content', function ($content) {
    // ショートコードが使われているページのみjsを読み込む
    if (has_shortcode($content, 'simple_sse_chat')) {
        // script.jsにsimple_sse_chat_dataという名前のオブジュエクトを定義し、home_urlというプロパティを定義
        wp_enqueue_script('simple_sse_chat', plugin_dir_url(__FILE__) . 'script.js');
        wp_localize_script('simple_sse_chat', 'simple_sse_chat_data', [
            'home_url' => home_url(),
            'nonce' => wp_create_nonce('hoge-fuga-piyo'), // CSRF対策
        ]);
    }
    return $content;
});

// ajaxで入力値を保存
add_action('wp_ajax_chat_post', function () {
    check_ajax_referer('hoge-fuga-piyo', 'security'); // CSRF対策

    global $wpdb;
    $wpdb->insert($wpdb->prefix.'simple_sse_chat', [
        'user_id' => get_current_user_id(),
        'content' => $_POST['chat-content'],
    ]);
});

// ajaxのhookだが、SSEも問題なかったのでこれを使用しました
add_action('wp_ajax_event_streame', function () {
    if (!wp_verify_nonce($_GET['_wpnonce'], 'hoge-fuga-piyo')) {
        exit; // CSRF対策
    }

    global $wpdb;

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-store');
    while(true) {
        printf("data: %s\n\n", json_encode([
            'chat_data' => $wpdb->get_results(
                "SELECT s.id, s.content, u.user_login
                 FROM {$wpdb->prefix}simple_sse_chat s
                 LEFT JOIN {$wpdb->prefix}users u ON s.user_id = u.id
                 ORDER BY id DESC
                 LIMIT 10"
            ),
        ]));
        ob_end_flush();
        flush();
        sleep(1);
    }
});
