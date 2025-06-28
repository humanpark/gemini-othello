<?php

/**
 * Plugin Name: Geminiオセロ
 * Description: Gemini APIと連携し、AIと会話しながらオセロができるプラグインです。
 * Version: 1.2
 * Author: HumanPark
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// --- ショートコード登録 ---
function gemini_othello_game()
{
    return "<div id='gemini-othello-game'>オセロを読み込んでいます...</div>";
}
add_shortcode('gemini-othello', 'gemini_othello_game');

// --- スクリプトとスタイルの登録 ---
function gemini_othello_enqueue_scripts()
{
    if (is_singular() && has_shortcode(get_post()->post_content, 'gemini-othello')) {
        wp_enqueue_style('gemini-othello-css', plugin_dir_url(__FILE__) . 'css/othello.css');
        wp_enqueue_script('gemini-othello-js', plugin_dir_url(__FILE__) . 'js/othello.js', array(), '1.4', true);
        wp_localize_script('gemini-othello-js', 'gemini_othello_data', array(
            'api_url' => esc_url_raw(rest_url('gemini-othello/v1/move'))
        ));
    }
}
add_action('wp_enqueue_scripts', 'gemini_othello_enqueue_scripts');

// --- 管理画面 ---
if (is_admin()) {
    require_once(plugin_dir_path(__FILE__) . 'admin/settings-page.php');
}

// --- REST API エンドポイント登録 ---
function gemini_othello_register_rest_route()
{
    register_rest_route('gemini-othello/v1', '/move', array(
        'methods' => 'POST',
        'callback' => 'gemini_othello_handle_ai_move',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'gemini_othello_register_rest_route');


// =================================================================
// オセロのゲームロジック (PHP側)
// =================================================================

/**
 * 特定のマスに石を置いたときに裏返る相手の石のリストを取得する
 */
function gemini_othello_get_flips($board, $row, $col, $player)
{
    $opponent = ($player === 'white') ? 'black' : 'white';
    $flips = [];
    $directions = [
        [-1, -1],
        [-1, 0],
        [-1, 1],
        [0, -1],
        [0, 1],
        [1, -1],
        [1, 0],
        [1, 1]
    ];

    foreach ($directions as $dir) {
        $potential_flips = [];
        $r = $row + $dir[0];
        $c = $col + $dir[1];

        // 隣が相手の石かチェック
        if ($r >= 0 && $r < 8 && $c >= 0 && $c < 8 && isset($board[$r][$c]) && $board[$r][$c] === $opponent) {
            $potential_flips[] = ['row' => $r, 'col' => $c];
            $r += $dir[0];
            $c += $dir[1];

            // 相手の石が続く限り進む
            while ($r >= 0 && $r < 8 && $c >= 0 && $c < 8) {
                if (!isset($board[$r][$c]) || $board[$r][$c] === null) {
                    break; // 空白なら終了
                }
                if ($board[$r][$c] === $player) {
                    $flips = array_merge($flips, $potential_flips); // 自分の石を見つけたらフリップリストに追加
                    break;
                }
                $potential_flips[] = ['row' => $r, 'col' => $c];
                $r += $dir[0];
                $c += $dir[1];
            }
        }
    }
    return $flips;
}

/**
 * 特定のマスが有効な手かどうかを判定する
 */
function gemini_othello_is_valid_move($board, $row, $col, $player)
{
    // すでに石があれば無効
    if (isset($board[$row][$col]) && $board[$row][$col] !== null) {
        return false;
    }
    // 1つ以上裏返せるか
    return count(gemini_othello_get_flips($board, $row, $col, $player)) > 0;
}

/**
 * 指定されたプレイヤーの有効な手をすべて見つける
 */
function gemini_othello_get_valid_moves($board, $player)
{
    $valid_moves = [];
    for ($row = 0; $row < 8; $row++) {
        for ($col = 0; $col < 8; $col++) {
            if (gemini_othello_is_valid_move($board, $row, $col, $player)) {
                $valid_moves[] = ['row' => $row, 'col' => $col];
            }
        }
    }
    return $valid_moves;
}


// =================================================================
// AIの応答を処理するメイン関数
// =================================================================

function gemini_othello_handle_ai_move($request)
{
    $api_key = get_option('gemini_othello_api_key');
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'Gemini APIキーが設定されていません。', ['status' => 400]);
    }

    $board = $request->get_param('board');
    $difficulty = sanitize_text_field($request->get_param('difficulty') ?? 'normal');
    $ai_player = 'white';

    // デバッグ情報初期化
    $debug_info = [
        'received_board' => $board,
        'difficulty' => $difficulty,
        'php_valid_moves' => [],
        'prompt_sent_to_gemini' => '',
        'gemini_raw_response' => '',
        'gemini_parsed_response' => null,
        'json_parse_error' => null,
        'ai_move_valid_by_php' => false,
        'final_move_source' => ''
    ];

    // 1. AI (白) の有効な手を確認する
    $valid_moves = gemini_othello_get_valid_moves($board, $ai_player);
    $debug_info['php_valid_moves'] = $valid_moves;

    // 2. 有効な手がない場合は、パスする
    if (empty($valid_moves)) {
        $debug_info['final_move_source'] = 'PHP_NO_VALID_MOVES';
        return new WP_REST_Response([
            'move' => null,
            'debug' => $debug_info
        ], 200);
    }

    // 3. 有効な手がある場合は、APIに問い合わせる
    $board_string = "";
    foreach ($board as $r_idx => $row) {
        foreach ($row as $c_idx => $cell) {
            if ($cell === 'black') $board_string .= 'B';
            elseif ($cell === 'white') $board_string .= 'W';
            else $board_string .= '.';
        }
        $board_string .= "\n";
    }

    $valid_moves_string = json_encode($valid_moves);

    // --- AIへの指示 (プロンプト) ---
    $base_prompt = <<<PROMPT
あなたはオセロ(リバーシ)のAI対戦相手です。あなたの担当は白(W)です。

# 状況
- **現在の盤面** (B:黒, W:白, .:空):
$board_string
- **あなた(W)が打てる有効な手のリスト**: $valid_moves_string

# あなたのタスク
`有効な手のリスト`の中から、戦略的に最も良いと思う手を **1つだけ** 選んでください。有効な手がある限り、パスはできません。
思考や他のテキストは一切含めず、指定されたJSON形式で回答だけを出力してください。

# 出力形式 (JSON)
{
  "move": {
    "row": number,
    "col": number
  }
}
PROMPT;

    $difficulty_instruction = "";
    switch ($difficulty) {
        case 'easy':
            $difficulty_instruction = "\n# 追加指示\nあなたはオセロの初心者です。有効な手のリストの中から、ランダムに近い手を選んでください。";
            break;
        case 'hard':
            $difficulty_instruction = "\n# 追加指示\nあなたは世界チャンピオンレベルのオセロマスターです。有効な手のリストの中から、盤面の隅(0,0), (0,7), (7,0), (7,7)を取ることを最優先し、相手に隅を取らせないように、戦略的に最善の手を選んでください。";
            break;
        default: // normal
            $difficulty_instruction = "\n# 追加指示\nあなたは賢いオセロプレイヤーです。有効な手のリストの中から、戦略的に良い手を選んでください。";
            break;
    }

    $prompt = $base_prompt . $difficulty_instruction . "\n\nさあ、あなたの番です！";
    $debug_info['prompt_sent_to_gemini'] = $prompt;

    // 4. Gemini APIにリクエストを送信
    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $api_key;
    $api_response = wp_remote_post($api_url, [
        'method'    => 'POST',
        'headers'   => ['Content-Type' => 'application/json'],
        'body'      => json_encode([
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
            ]
        ]),
        'timeout'   => 30,
    ]);

    // 5. APIレスポンスを処理
    if (is_wp_error($api_response)) {
        $debug_info['final_move_source'] = 'API_ERROR_WP';
        $debug_info['api_error_message'] = $api_response->get_error_message();
        // APIエラーの場合、ランダムな手を選択
        $chosen_move = $valid_moves[array_rand($valid_moves)];
        return new WP_REST_Response([
            'move' => $chosen_move,
            'debug' => $debug_info
        ], 200);
    }

    $response_body = wp_remote_retrieve_body($api_response);
    $debug_info['gemini_raw_response'] = $response_body;
    $data = json_decode($response_body, true);

    // API自体がエラーを返した場合 (例: 不正なAPIキー)
    if (isset($data['error'])) {
        $debug_info['final_move_source'] = 'API_ERROR_GEMINI';
        $debug_info['api_error_message'] = $data['error']['message'];
        $chosen_move = $valid_moves[array_rand($valid_moves)];
        return new WP_REST_Response([
            'move' => $chosen_move,
            'debug' => $debug_info
        ], 200);
    }

    // JSONモードなので、レスポンスは直接パースできるはず
    $gemini_text_response = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    $parsed_response = json_decode($gemini_text_response, true);
    $debug_info['gemini_parsed_response'] = $parsed_response;

    if (json_last_error() !== JSON_ERROR_NONE) {
        $debug_info['final_move_source'] = 'JSON_PARSE_ERROR';
        $debug_info['json_parse_error'] = json_last_error_msg();
        // JSONパースに失敗した場合、ランダムな手を選択
        $chosen_move = $valid_moves[array_rand($valid_moves)];
        return new WP_REST_Response([
            'move' => $chosen_move,
            'debug' => $debug_info
        ], 200);
    }

    // 6. AIの選んだ手が有効か最終チェック
    $ai_move = $parsed_response['move'] ?? null;
    $is_ai_move_valid = false;
    if ($ai_move && isset($ai_move['row']) && isset($ai_move['col'])) {
        foreach ($valid_moves as $valid_move) {
            if ($valid_move['row'] === $ai_move['row'] && $valid_move['col'] === $ai_move['col']) {
                $is_ai_move_valid = true;
                break;
            }
        }
    }
    $debug_info['ai_move_valid_by_php'] = $is_ai_move_valid;

    // AIの選択が無効だった場合、ランダムな有効手を選択
    if (!$is_ai_move_valid) {
        $debug_info['final_move_source'] = 'AI_MOVE_INVALID';
        $chosen_move = $valid_moves[array_rand($valid_moves)];
        $parsed_response['move'] = $chosen_move;
    } else {
        $debug_info['final_move_source'] = 'AI_SUGGESTED_VALID_MOVE';
    }

    return new WP_REST_Response($parsed_response + ['debug' => $debug_info], 200);
}
