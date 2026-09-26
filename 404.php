<?php
/**
 * 404.php - Not Found Error Page
 */

require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found - GlobalSCM</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #F8FAFC;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            text-align: center;
            padding: 20px;
            max-width: 500px;
        }
        
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: #2F80ED;
            opacity: 0.2;
            line-height: 1;
        }
        
        .icon {
            font-size: 60px;
            color: #2F80ED;
            margin: 20px 0;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 8px;
        }
        
        p {
            color: #6B7280;
            font-size: 16px;
            margin-bottom: 25px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 32px;
            background: #2F80ED;
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #2563EB;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(47, 128, 237, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404</div>
        <div class="icon"><i class="fas fa-compass"></i></div>
        <h1>Page Not Found</h1>
        <p>Oops! The page you're looking for doesn't exist or has been moved.</p>
        <a href="<?php echo isLoggedIn() ? 'admin/dashboard.php' : 'login.php'; ?>" class="btn">
            <i class="fas fa-home"></i> Back to Home
        </a>
    </div>
</body>
</html>