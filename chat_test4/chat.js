function generateRandomHex(size) {
    const randomBytes = new Uint8Array(size);
    window.crypto.getRandomValues(randomBytes);
    return Array.from(randomBytes).map(b => b.toString(16).padStart(2, '0')).join('');
}

document.addEventListener('DOMContentLoaded', function() {
    const startSurveyButton = document.getElementById('startSurvey');
    const chatForm = document.getElementById('chatForm');
    const stepButtons = document.querySelectorAll('button[data-next]');
    const memberId = generateRandomHex(5);  // 5バイトのランダムな16進数文字列を生成

    startSurveyButton.addEventListener('click', handleStartSurvey);
    chatForm.addEventListener('submit', handleSubmitForm);
    stepButtons.forEach(button => {
        button.addEventListener('click', () => {
            nextStep(parseInt(button.getAttribute('data-next')));
        });
    });

    window.addEventListener('beforeunload', handleEndSession); // ページを離れる前にセッションを終了

    async function handleStartSurvey() {
        try {
            const user = await bootChannel();
            console.log('ChannelIO Booted with user:', user);  // ユーザー情報のログ
    
            const chatData = await registerUser(user.id);
            console.log('User registered with chat ID:', chatData.userChatId);  // チャットIDのログ
    
            await startFileSession(user.id, chatData.userChatId);
            console.log('File session started for:', user.id, 'with Chat ID:', chatData.userChatId);  // ファイルセッション開始のログ
    
            startSurvey();
        } catch (error) {
            console.error('Error during chat session setup:', error);
            alert('エラーが発生しました: ' + error.message);
        }
    }
    

    async function bootChannel() {
        return new Promise((resolve, reject) => {
            ChannelIO('boot', {
                pluginKey: 'myPluginKey', // 実際のプラグインキーに置き換えてください
                memberId: memberId
            }, function onBoot(error, user) {
                if (error) {
                    reject(new Error('ChannelIO boot failed: ' + error));
                } else {
                    resolve(user);
                }
            });
        });
    }

    async function registerUser(userId) {
        const response = await fetch('registerUser.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({userId})
        });

        if (!response.ok) {
            throw new Error('Failed to register user');
        }

        return response.json();
    }

    async function startFileSession(userId, userChatId) {
        const response = await fetch('file_session_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({memberId, userId, userChatId, action: 'start'})
        });
    
        if (!response.ok) {
            throw new Error('Failed to start file session');
        }
    
        const data = await response.json();
        if (!data.success) {
            throw new Error('File session error: ' + data.message);
        }
    
        sessionStorage.setItem('session_id', data.session_id);
        sessionStorage.setItem('survey_started', 'true');
        sessionStorage.setItem('userId', userId);
        sessionStorage.setItem('userChatId', userChatId);
    }

    function startSurvey() {
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById('step1').classList.add('active');
        startSurveyButton.style.display = 'none';
    }

    function nextStep(step) {
        const currentInput = document.getElementById(`step${step - 1}`).querySelector('input, textarea').value;
        if (!validateInput(currentInput)) return;

        updateProgress(step);
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById(`step${step}`).classList.add('active');
    }

    async function updateProgress(step) {
        const sessionId = sessionStorage.getItem('session_id');
        const response = await fetch('file_session_manager.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update',
                session_id: sessionId,
                step: step
            })
        });

        if (!response.ok || !(await response.json()).success) {
            console.error('Failed to update session');
        }
    }

    function handleEndSession() {
        const sessionId = sessionStorage.getItem('session_id');
        const surveyStarted = sessionStorage.getItem('survey_started');
        const userChatId = sessionStorage.getItem('userChatId');
        if (sessionId && surveyStarted && userChatId) {
            fetch('file_session_manager.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'end',
                    session_id: sessionId,
                    userChatId: userChatId, // userChatIdをリクエストに含める
                }),
                keepalive: true
            }).then(response => {
                console.log('Session ended successfully');
            }).catch(error => {
                console.error('Failed to end session', error);
            });
            sessionStorage.removeItem('survey_started');
            // セッション終了後はuserChatIdも削除
            sessionStorage.removeItem('userChatId');
        }
    }

    function validateInput(input) {
        if (input.trim() === '') {
            alert('このフィールドは必須です。');
            return false;
        }
        return true;
    }

    function handleSubmitForm(event) {
        event.preventDefault();
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const firstMessage = document.getElementById('firstMessage').value;
        // userIdとuserChatIdを適切な方法で取得
        const userId = sessionStorage.getItem('userId'); // 例
        const userChatId = sessionStorage.getItem('userChatId'); // 例
    
        console.log('Attempting to start chat with user info...');
    
        // APIにデータを送信
        fetch('send_message.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                userId,
                userChatId,
                name,
                email,
                firstMessage,
            }),
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Message sent successfully');
                ChannelIO('openChat', userChatId);
            } else {
                console.error('Failed to send message:', data.message);
                // エラー処理をここに書く
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    (function() {
        var w = window;
        if (w.ChannelIO) {
            return (window.console.error || window.console.log || function(){})('ChannelIO script included twice.');
        }
        var ch = function() {
            ch.c(arguments);
        };
        ch.q = [];
        ch.c = function(args) {
            ch.q.push(args);
        };
        w.ChannelIO = ch;
        function l() {
            if (w.ChannelIOInitialized) {
                return;
            }
            w.ChannelIOInitialized = true;
            var s = document.createElement('script');
            s.type = 'text/javascript';
            s.async = true;
            s.src = 'https://cdn.channel.io/plugin/ch-plugin-web.js';
            s.charset = 'UTF-8';
            var x = document.getElementsByTagName('script')[0];
            x.parentNode.insertBefore(s, x);
        }
        if (document.readyState === 'complete') {
            l();
        } else if (window.attachEvent) {
            window.attachEvent('onload', l);
        } else {
            window.addEventListener('DOMContentLoaded', l, false);
            window.addEventListener('load', l, false);
        }
    })();
});
