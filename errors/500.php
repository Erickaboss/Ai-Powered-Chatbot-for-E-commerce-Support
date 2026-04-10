<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Internal Server Error</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #e94560 0%, #0f3460 100%);
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
        .error-details {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 10px;
            margin: 2rem 0;
            font-size: 0.9rem;
            display: none;
        }
        .toggle-details {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            cursor: pointer;
            margin-top: 1rem;
            transition: all 0.3s;
        }
        .toggle-details:hover {
            background: white;
            color: #e94560;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="icon-robot">😕</div>
        <div class="error-code">500</div>
        <div class="error-message">Internal Server Error</div>
        <p class="mb-4">Something went wrong on our end. We're working to fix it!</p>
        
        <button class="toggle-details" onclick="toggleDetails()">
            <i class="bi bi-bug me-2"></i>Show Technical Details
        </button>
        
        <div class="error-details" id="errorDetails">
            <strong>Error ID:</strong> <span id="errorId"></span><br>
            <strong>Time:</strong> <span id="errorTime"></span><br>
            <small>This information helps our support team diagnose the issue.</small>
        </div>
        
        <div class="mt-4">
            <a href="/" class="btn-home">
                <i class="bi bi-house me-2"></i>Go Back Home
            </a>
            <a href="javascript:location.reload()" class="btn-home ms-2" style="background:#0f3460;color:white">
                <i class="bi bi-arrow-clockwise me-2"></i>Try Again
            </a>
        </div>
    </div>
    
    <script>
        // Generate error ID and timestamp
        const errorId = 'ERR-' + Math.random().toString(36).substr(2, 9).toUpperCase();
        const errorTime = new Date().toISOString();
        
        document.getElementById('errorId').textContent = errorId;
        document.getElementById('errorTime').textContent = errorTime;
        
        function toggleDetails() {
            const details = document.getElementById('errorDetails');
            details.style.display = details.style.display === 'block' ? 'none' : 'block';
        }
        
        // Log error to server
        fetch('<?= SITE_URL ?>/includes/logger.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                level: 'error',
                message: '500 Error: ' + errorId,
                url: window.location.href,
                errorId: errorId
            })
        }).catch(() => {});
    </script>
</body>
</html>
