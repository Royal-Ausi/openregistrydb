 <!DOCTYPE html>
 <html lang="en">
 <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
        margin: 0;
        font-family: Arial, sans-serif;
        
        }
        .navbar {
        background-color: #E8492A;
        color: #FCF1E3;
        display: flex;
        align-items: center;
        justify-content: center;
        position: fixed;
        top: 0;
        width: 100%;
        z-index: 1000;
        }

        .navbar .menu-toggle {
        position: absolute;
        left: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #FCF1E3;
        }
    </style>
 </head>
 <body>
    <!-- Top Navbar -->
  <nav class="navbar">
    <span class="menu-toggle" onclick="toggleSidebar()">☰</span>
    <span class="navbar-brand mx-auto text-white"> Welcome! This is Open Registry</span>

     <!-- Back Button -->
    <button class="btn btn-outline-light" onclick="history.back()" style="margin-left: auto;">
      ← Back
    </button>
  </nav>
 </body>
 </html>
 
 
