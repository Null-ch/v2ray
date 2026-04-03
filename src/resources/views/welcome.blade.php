<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Защищенная передача данных в сети</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Обеспечьте безопасность и приватность ваших данных в интернете с помощью современных технологий шифрования">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0a0e1a 0%, #1a1f2e 100%);
            background-attachment: fixed;
            color: #e6e6e6;
            line-height: 1.6;
            min-height: 100vh;
        }

        header {
            background: linear-gradient(135deg, #1a1d24 0%, #0f1115 100%);
            padding: 80px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(100, 150, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 50% 50%, rgba(100, 150, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        header h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #64a0ff 0%, #4d7fff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }

        header p {
            max-width: 720px;
            margin: 0 auto;
            color: #bdbdbd;
            font-size: 1.2rem;
            position: relative;
            z-index: 1;
        }

        .badge {
            display: inline-block;
            background: rgba(100, 150, 255, 0.15);
            border: 1px solid rgba(100, 150, 255, 0.3);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #64a0ff;
            margin-top: 10px;
        }

        main {
            max-width: 1000px;
            margin: 60px auto;
            padding: 0 20px;
        }

        section {
            margin-bottom: 50px;
            background: rgba(26, 29, 36, 0.6);
            border: 1px solid rgba(100, 150, 255, 0.1);
            border-radius: 12px;
            padding: 30px;
            backdrop-filter: blur(10px);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        section:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(100, 150, 255, 0.1);
            border-color: rgba(100, 150, 255, 0.2);
        }

        h2 {
            color: #64a0ff;
            border-left: 4px solid #4d7fff;
            padding-left: 15px;
            margin-top: 0;
            font-size: 1.8rem;
        }

        p {
            margin-top: 15px;
            color: #d0d0d0;
            font-size: 1.05rem;
        }

        ul {
            margin-top: 15px;
            padding-left: 25px;
        }

        li {
            margin-bottom: 12px;
            color: #d0d0d0;
            position: relative;
        }

        li::marker {
            color: #64a0ff;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 25px;
        }

        .feature-card {
            background: rgba(15, 17, 21, 0.8);
            border: 1px solid rgba(100, 150, 255, 0.15);
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            border-color: rgba(100, 150, 255, 0.4);
            background: rgba(15, 17, 21, 0.95);
        }

        .feature-card h3 {
            color: #64a0ff;
            margin-top: 0;
            font-size: 1.3rem;
        }

        .feature-card p {
            margin-top: 10px;
            font-size: 0.95rem;
        }

        .highlight {
            background: linear-gradient(135deg, rgba(100, 150, 255, 0.15) 0%, rgba(77, 127, 255, 0.1) 100%);
            border: 1px solid rgba(100, 150, 255, 0.3);
            padding: 25px;
            border-radius: 10px;
            color: #e0e8ff;
        }

        .highlight h2 {
            color: #7ab3ff;
        }

        .tech-badge {
            display: inline-block;
            background: rgba(100, 150, 255, 0.2);
            color: #64a0ff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin: 5px 5px 5px 0;
            font-family: 'Courier New', monospace;
        }

        footer {
            border-top: 1px solid rgba(100, 150, 255, 0.2);
            padding: 30px 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #888;
            background: rgba(15, 17, 21, 0.8);
            margin-top: 60px;
        }

        a {
            color: #64a0ff;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        a:hover {
            color: #7ab3ff;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            header h1 {
                font-size: 2rem;
            }

            header p {
                font-size: 1rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <h1>🔒 Защищенная передача данных</h1>
    <p>
        Современные технологии шифрования для обеспечения безопасности 
        и приватности ваших данных в интернете
    </p>
    <span class="badge">{{env('APP_ENV')}} версия</span>
</header>

<main>

    <section>
        <h2>Что такое защищенная передача данных</h2>
        <p>
            Защищенная передача данных — это процесс безопасной отправки информации 
            через интернет с использованием современных протоколов шифрования. 
            Это гарантирует, что ваши личные данные, пароли, финансовая информация 
            и другая конфиденциальная информация остаются недоступными для третьих лиц.
        </p>
        <p>
            В современном цифровом мире, где кибератаки и утечки данных становятся 
            все более частыми, защита информации становится критически важной.
        </p>
    </section>

    <section>
        <h2>Основные технологии защиты</h2>
        <div class="features-grid">
            <div class="feature-card">
                <h3>🔐 Шифрование</h3>
                <p>
                    Использование алгоритмов шифрования для преобразования данных 
                    в нечитаемый формат, который может быть расшифрован только 
                    получателем с правильным ключом.
                </p>
            </div>
            <div class="feature-card">
                <h3>🛡️ VPN-туннели</h3>
                <p>
                    Создание защищенных виртуальных туннелей между вашим устройством 
                    и сервером, скрывающих ваш реальный IP-адрес и шифрующих весь трафик.
                </p>
            </div>
            <div class="feature-card">
                <h3>🔑 Криптографические ключи</h3>
                <p>
                    Применение современных протоколов обмена ключами для обеспечения 
                    безопасной передачи данных без возможности перехвата.
                </p>
            </div>
            <div class="feature-card">
                <h3>🌐 Защищенные протоколы</h3>
                <p>
                    Использование протоколов TLS/SSL, WireGuard, V2Ray и других 
                    современных стандартов для обеспечения максимальной безопасности.
                </p>
            </div>
        </div>
    </section>

    <section>
        <h2>Преимущества защищенной передачи данных</h2>
        <ul>
            <li><strong>Приватность:</strong> Ваши действия в интернете остаются конфиденциальными</li>
            <li><strong>Безопасность:</strong> Защита от перехвата данных хакерами и злоумышленниками</li>
            <li><strong>Анонимность:</strong> Скрытие вашего реального местоположения и IP-адреса</li>
            <li><strong>Защита в публичных сетях:</strong> Безопасное использование публичного Wi-Fi</li>
            <li><strong>Защита от слежки:</strong> Предотвращение отслеживания интернет-провайдерами и рекламодателями</li>
        </ul>
    </section>

    <section class="highlight">
        <h2>Как это работает</h2>
        <p>
            При использовании защищенного соединения ваши данные проходят через несколько этапов защиты:
        </p>
        <ol style="padding-left: 25px;">
            <li><strong>Инициализация соединения:</strong> Установление безопасного канала связи с использованием криптографических протоколов</li>
            <li><strong>Шифрование данных:</strong> Все передаваемые данные автоматически шифруются перед отправкой</li>
            <li><strong>Маршрутизация через защищенный туннель:</strong> Данные передаются через зашифрованный VPN-туннель</li>
            <li><strong>Расшифровка на стороне получателя:</strong> Данные безопасно расшифровываются только у получателя</li>
        </ol>
        <p style="margin-top: 20px;">
            <span class="tech-badge">TLS/SSL</span>
            <span class="tech-badge">AES-256</span>
            <span class="tech-badge">RSA-2048</span>
            <span class="tech-badge">V2Ray</span>
            <span class="tech-badge">WireGuard</span>
        </p>
    </section>

    <section>
        <h2>Когда особенно важна защита данных</h2>
        <ul>
            <li>При работе с банковскими операциями и финансовыми транзакциями</li>
            <li>При использовании публичных Wi-Fi сетей (кафе, аэропорты, отели)</li>
            <li>При доступе к конфиденциальной корпоративной информации</li>
            <li>При общении в мессенджерах и отправке личных сообщений</li>
            <li>При работе в странах с ограничениями интернета</li>
            <li>При необходимости скрыть свою активность от интернет-провайдера</li>
        </ul>
    </section>

    <section>
        <h2>Современные стандарты безопасности</h2>
        <p>
            Современные системы защиты данных используют проверенные временем и 
            новейшие криптографические алгоритмы, которые соответствуют международным 
            стандартам безопасности. Это гарантирует, что даже при попытке перехвата 
            данных злоумышленники не смогут их расшифровать.
        </p>
        <p>
            Регулярные обновления протоколов и алгоритмов обеспечивают защиту от 
            новых угроз и уязвимостей, делая ваше соединение максимально безопасным.
        </p>
    </section>

</main>

<footer>
    <p>
        Обеспечьте безопасность ваших данных в интернете
        <br>
        © 2026 | Защищенная передача данных
    </p>
</footer>

</body>
</html>
