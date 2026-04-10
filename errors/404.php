<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f3460 0%, #e94560 100%);
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .error-container {
            text-align: center;
            padding: 3rem;
            max-width: 600px;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 1rem;
            text-shadow: 4px 4px 8px rgba(0,0,0,0.3);
        }
        .error-message {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .btn-home {
            background: white;
            color: #e94560;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
        }
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            color: #0f3460;
        }
        .icon-robot {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon-robot">🤖</div>
        <div class="error-code">404</div>
        <div class="error-message">Oops! The page you're looking for doesn't exist.</div>
        <p class="mb-4">Don't worry, our AI chatbot can help you find what you need!</p>
        <a href="/" class="btn-home">
            <i class="bi bi-house me-2"></i>Go Back Home
        </a>
    </div>
    
    <script>
        // Log 404 error
        fetch('<?= SITE_URL ?>/includes/logger.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                level: 'warning',
                message: '404 Error on ' + window.location.pathname,
                url: window.location.href
            })
        }).catch(() => {});
    </script>
</body>
</html>
