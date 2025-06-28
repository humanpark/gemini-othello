<?php

function gemini_othello_register_settings() {
    add_option('gemini_othello_api_key', '');
    register_setting('gemini_othello_options_group', 'gemini_othello_api_key', 'gemini_othello_sanitize_api_key');
}
add_action('admin_init', 'gemini_othello_register_settings');

function gemini_othello_register_options_page() {
    add_options_page('Geminiオセロ設定', 'Geminiオセロ', 'manage_options', 'gemini-othello', 'gemini_othello_options_page_html');
}
add_action('admin_menu', 'gemini_othello_register_options_page');

function gemini_othello_sanitize_api_key($input) {
    return sanitize_text_field($input);
}

function gemini_othello_options_page_html() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <div style="background: #fff; border: 1px solid #c3c4c7; padding: 1px 20px; margin-top: 15px; border-left-width: 4px;">
            <h2><span class="dashicons dashicons-info" style="font-size: 20px; line-height: 1.3; margin-right: 5px;"></span>このプラグインについて</h2>
            <p>
                このプラグインは、GoogleのGemini APIを利用したAIと会話しながら対戦できるオセロゲームを提供します。
            </p>
            <h2><span class="dashicons dashicons-admin-settings" style="font-size: 20px; line-height: 1.3; margin-right: 5px;"></span>使い方</h2>
            <ol>
                <li><strong>APIキーの入力:</strong> まず、下の入力欄にあなたのGoogle Gemini APIキーを入力し、「変更を保存」をクリックしてください。APIキーは<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>から無料で取得できます。</li>
                <li><strong>ショートコードの利用:</strong> 新規投稿または固定ページを作成（または既存のページを編集）し、ゲームを表示したい場所に以下のショートコードを追加してください:</li>
                <li style="background: #f6f7f7; padding: 5px 10px; border-radius: 3px; font-family: monospace;"><code>[gemini-othello]</code></li>
                <li><strong>ゲームをプレイ:</strong> ページを公開または更新すると、そのページにオセロ盤とチャット画面が表示されます。</li>
            </ol>
        </div>

        <form action="options.php" method="post">
            <?php
            settings_fields('gemini_othello_options_group');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Gemini APIキー</th>
                    <td><input type="text" name="gemini_othello_api_key" value="<?php echo esc_attr(get_option('gemini_othello_api_key')); ?>" size="50" placeholder="ここにAPIキーを貼り付けてください"/></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
