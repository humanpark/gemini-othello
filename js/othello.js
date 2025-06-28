document.addEventListener('DOMContentLoaded', function() {
    console.log("Gemini Othello: DOMContentLoaded event fired.");

    const gameElement = document.getElementById('gemini-othello-game');
    if (!gameElement) {
        console.error("ゲームコンテナ #gemini-othello-game が見つかりません!");
        return;
    }

    // 初期メッセージをクリア
    gameElement.innerHTML = '';

    // ボードの作成と追加
    const boardElement = document.createElement('div');
    boardElement.id = 'othello-board';
    gameElement.appendChild(boardElement);

    // ゲームコントロールの作成と追加
    const gameControlsContainer = document.createElement('div');
    gameControlsContainer.id = 'game-controls-container';
    gameControlsContainer.innerHTML = `
        <div id="game-controls">
            <label for="difficulty-select">難易度:</label>
            <select id="difficulty-select">
                <option value="easy">かんたん</option>
                <option value="normal" selected>ふつう</option>
                <option value="hard">むずかしい</option>
            </select>
        </div>
        <div id="game-messages"></div>
        <button id="restart-game" style="display:none;">もう一度トライしますか？</button>
    `;
    gameElement.appendChild(gameControlsContainer);

    let board = Array(8).fill(null).map(() => Array(8).fill(null));
    let currentPlayer = 'black'; // 黒 (ユーザー) から開始

    function initializeBoard() {
        board[3][3] = 'white';
        board[3][4] = 'black';
        board[4][3] = 'black';
        board[4][4] = 'white';
        console.log("Gemini Othello: Board initialized.", JSON.parse(JSON.stringify(board)));
        renderBoard();
        displayGameMessage('新しいゲームを開始します！');
    }

    function renderBoard() {
        boardElement.innerHTML = '';
        const validMoves = getValidMoves(currentPlayer);
        console.log("Gemini Othello: Rendering board. Current Player:", currentPlayer, "Valid Moves:", validMoves);

        for (let row = 0; row < 8; row++) {
            for (let col = 0; col < 8; col++) {
                const cell = document.createElement('div');
                cell.className = 'cell';
                cell.dataset.row = row;
                cell.dataset.col = col;

                if (board[row][col]) {
                    const disc = document.createElement('div');
                    disc.className = `disc ${board[row][col]}`;
                    cell.appendChild(disc);
                } else if (currentPlayer === 'black' && validMoves.some(move => move.row === row && move.col === col)) {
                    // プレイヤーの有効な手をハイライト
                    cell.classList.add('valid-move');
                }
                boardElement.appendChild(cell);
            }
        }
        console.log("Gemini Othello: Board after render:", JSON.parse(JSON.stringify(board)));
    }

    function getValidMoves(player) {
        const moves = [];
        for (let row = 0; row < 8; row++) {
            for (let col = 0; col < 8; col++) {
                if (isValidMove(row, col, player)) {
                    moves.push({ row, col });
                }
            }
        }
        return moves;
    }

    function isValidMove(row, col, player) {
        if (board[row][col] !== null) {
            return false;
        }
        return getFlips(row, col, player).length > 0;
    }

    function getFlips(row, col, player) {
        const opponent = (player === 'black') ? 'white' : 'black';
        let flips = [];
        const directions = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1], [1, 0], [1, 1]
        ];

        directions.forEach(dir => {
            let potentialFlips = [];
            let r = row + dir[0];
            let c = col + dir[1];

            if (r >= 0 && r < 8 && c >= 0 && c < 8 && board[r][c] === opponent) {
                potentialFlips.push({ row: r, col: c });
                r += dir[0];
                c += dir[1];

                while (r >= 0 && r < 8 && c >= 0 && c < 8) {
                    if (board[r][c] === player) {
                        flips = flips.concat(potentialFlips);
                        break;
                    }
                    if (board[r][c] === null) {
                        break;
                    }
                    potentialFlips.push({ row: r, col: c });
                    r += dir[0];
                    c += dir[1];
                }
            }
        });
        return flips;
    }

    function handleCellClick(event) {
        event.preventDefault(); // タッチイベントで画面が動くのを防ぐ
        const cell = event.target.closest('.cell');
        if (!cell || currentPlayer !== 'black') return;

        const row = parseInt(cell.dataset.row);
        const col = parseInt(cell.dataset.col);

        if (isValidMove(row, col, currentPlayer)) {
            placeDisc(row, col, currentPlayer);
            renderBoard();
            switchPlayer();
        }
    }
    
    function placeDisc(row, col, player) {
        const flips = getFlips(row, col, player);
        console.log(`Gemini Othello: Placing disc at (${row}, ${col}) for ${player}. Flips:`, flips);
        if (flips.length > 0) {
            board[row][col] = player;
            flips.forEach(flip => {
                board[flip.row][flip.col] = player;
            });
        }
        console.log("Gemini Othello: Board after placeDisc:", JSON.parse(JSON.stringify(board)));
    }

    function switchPlayer() {
        currentPlayer = 'white'; // AIのターンに切り替え
        renderBoard(); // 有効な手のハイライトを消す
        displayGameMessage('AIが考え中...');
        setTimeout(() => aiTurn(), 500); // AIの思考時間をシミュレート
    }

    function aiTurn() {
        console.log("Gemini Othello: AI Turn started.");
        const difficulty = document.getElementById('difficulty-select').value;
        fetch(gemini_othello_data.api_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ 
                board: board,
                difficulty: difficulty // 難易度を追加
            }),
        })
        .then(response => response.json())
        .then(data => {
            console.log("Gemini Othello: AI API Response Data:", data);
            displayGameMessage(''); // 「考え中...」のメッセージをクリア

            if (data.move && data.move.row !== null) {
                const { row, col } = data.move;
                console.log(`Gemini Othello: AI proposed move: (${row}, ${col}).`);
                const aiMoveIsValid = isValidMove(row, col, 'white');
                console.log("Gemini Othello: AI proposed move is valid?", aiMoveIsValid);

                if (aiMoveIsValid) {
                    placeDisc(row, col, 'white');
                } else {
                    // AIが不正な手を返した場合のメッセージ
                    displayGameMessage('AIが間違った手を指しました。パスします。');
                }
            } else {
                 // AIがパスした場合のメッセージ
                 displayGameMessage('AIがパスしました。');
            }
            currentPlayer = 'black';
            renderBoard();
            checkGameOver();
        })
        .catch(error => {
            console.error('Error:', error);
            displayGameMessage('システムエラー: AIとの通信に失敗しました。');
            currentPlayer = 'black';
            renderBoard();
            checkGameOver();
        });
    }

    function displayGameMessage(message) {
        const messagesDiv = document.getElementById('game-messages');
        messagesDiv.innerHTML = message;
    }

    function checkGameOver() {
        const blackMoves = getValidMoves('black');
        const whiteMoves = getValidMoves('white');

        if (blackMoves.length === 0 && whiteMoves.length === 0) {
            // 両者とも打つ手がない場合、ゲーム終了
            let blackCount = 0;
            let whiteCount = 0;
            for (let r = 0; r < 8; r++) {
                for (let c = 0; c < 8; c++) {
                    if (board[r][c] === 'black') blackCount++;
                    else if (board[r][c] === 'white') whiteCount++;
                }
            }

            let resultMessage = `ゲーム終了！<br>黒: ${blackCount} 白: ${whiteCount}<br>`;
            if (blackCount > whiteCount) {
                resultMessage += 'あなたの勝ちです！おめでとうございます！';
            } else if (whiteCount > blackCount) {
                resultMessage += 'AIの勝ちです！残念でした！';
            } else {
                resultMessage += '引き分けです！';
            }
            displayGameMessage(resultMessage);

            // 再挑戦ボタンを表示
            const restartButton = document.getElementById('restart-game');
            restartButton.style.display = 'block';
            restartButton.addEventListener('click', restartGame);

            // 盤面クリックを無効化
            boardElement.removeEventListener('click', handleCellClick);
            boardElement.removeEventListener('touchstart', handleCellClick);
        }
    }

    function restartGame() {
        // 盤面とメッセージをリセット
        board = Array(8).fill(null).map(() => Array(8).fill(null));
        currentPlayer = 'black';
        displayGameMessage('');
        const restartButton = document.getElementById('restart-game');
        if (restartButton) {
            restartButton.style.display = 'none';
        }
        initializeBoard();
        boardElement.addEventListener('click', handleCellClick);
        boardElement.addEventListener('touchstart', handleCellClick);
    }

    // イベントリスナーを登録 (クリックとタッチの両方に対応)
    boardElement.addEventListener('click', handleCellClick);
    boardElement.addEventListener('touchstart', handleCellClick);

    initializeBoard();
});