<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .payment-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="number"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        input[type="number"]:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .error-message {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }

        .error-message.show {
            display: block;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 20px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <h1>Оплата</h1>

        <div id="alert" class="alert"></div>

        <form id="paymentForm">
            <div class="form-group">
                <label for="amount">Сумма платежа (руб.)</label>
                <input 
                    type="number" 
                    id="amount" 
                    name="amount" 
                    step="0.01" 
                    min="0.01" 
                    required
                    placeholder="0.00"
                >
                <div class="error-message" id="amount-error"></div>
            </div>

            <div class="form-group">
                <label for="description">Описание платежа</label>
                <textarea 
                    id="description" 
                    name="description" 
                    required
                    placeholder="Введите описание платежа"
                ></textarea>
                <div class="error-message" id="description-error"></div>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                Оплатить
            </button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 10px; color: #666;">Создание платежа...</p>
        </div>
    </div>

    <script>
        const form = document.getElementById('paymentForm');
        const submitBtn = document.getElementById('submitBtn');
        const loading = document.getElementById('loading');
        const alert = document.getElementById('alert');

        function showAlert(message, type = 'error') {
            alert.textContent = message;
            alert.className = `alert alert-${type} show`;
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }

        function showError(field, message) {
            const errorEl = document.getElementById(`${field}-error`);
            errorEl.textContent = message;
            errorEl.classList.add('show');
        }

        function clearErrors() {
            document.querySelectorAll('.error-message').forEach(el => {
                el.classList.remove('show');
            });
        }

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();

            const formData = new FormData(form);
            const data = {
                amount: parseFloat(formData.get('amount')),
                description: formData.get('description'),
            };

            // Валидация
            if (!data.amount || data.amount < 0.01) {
                showError('amount', 'Минимальная сумма платежа составляет 0.01 руб.');
                return;
            }

            if (!data.description || data.description.trim().length === 0) {
                showError('description', 'Описание платежа обязательно для заполнения.');
                return;
            }

            if (data.description.length > 255) {
                showError('description', 'Описание не должно превышать 255 символов.');
                return;
            }

            submitBtn.disabled = true;
            loading.classList.add('show');

            try {
                const response = await fetch('{{ route("payment.create") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (response.ok && result.confirmation_url) {
                    // Перенаправляем на страницу оплаты YooKassa
                    window.location.href = result.confirmation_url;
                } else {
                    showAlert(result.message || 'Ошибка при создании платежа');
                    submitBtn.disabled = false;
                    loading.classList.remove('show');
                }
            } catch (error) {
                showAlert('Произошла ошибка при отправке запроса');
                submitBtn.disabled = false;
                loading.classList.remove('show');
            }
        });
    </script>
</body>
</html>

