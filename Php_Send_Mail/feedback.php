<?php session_start();?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>How to send mail using PHP Mailer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <style>
      :root {
        --primary-green: #2e7d32;
        --light-green: #4caf50;
        --lighter-green: #81c784;
        --lightest-green: #e8f5e9;
      }
      
      body {
        background-color: #f5f5f5;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      }
      
      .card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        overflow: hidden;
      }
      
      .card-header {
        background-color: var(--primary-green);
        color: white;
        padding: 1.5rem;
      }
      
      .card-header h2 {
        margin: 0;
        font-weight: 600;
      }
      
      .card-body {
        padding: 2rem;
        background-color: white;
      }
      
      .form-control {
        border: 1px solid #ddd;
        border-radius: 5px;
        padding: 0.75rem 1rem;
        transition: all 0.3s;
      }
      
      .form-control:focus {
        border-color: var(--light-green);
        box-shadow: 0 0 0 0.25rem rgba(76, 175, 80, 0.25);
      }
      
      label {
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #555;
      }
      
      .btn-primary {
        background-color: var(--primary-green);
        border: none;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        letter-spacing: 0.5px;
        transition: all 0.3s;
      }
      
      .btn-primary:hover {
        background-color: var(--light-green);
        transform: translateY(-2px);
      }
      
      .btn-primary:active {
        background-color: var(--primary-green) !important;
        transform: translateY(0);
      }
      
      textarea {
        resize: vertical;
        min-height: 120px;
      }
      
      .form-group {
        margin-bottom: 1.5rem;
      }
    </style>
  </head>
  <body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header">
                <h2>FeedBack</h2>
            </div>
            <div class="card-body">
                <form action="sendmail.php" method="post">
                    <div class="mb-4">
                        <label for="fullname" class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="fullname" class="form-control" placeholder="Enter your full name" required>
                    </div>
                    <div class="mb-4">
                        <label for="email_address" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email_address" name="email" placeholder="Enter your email address" required>
                    </div>
                    <div class="mb-4">
                        <label for="Contact" class="form-label">Contact Number</label>
                        <input type="text" class="form-control" id="number" name="Contact " placeholder="Enter your phonenumber" required>
                    </div>

                    <div class="mb-4">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" placeholder="Type your message here" required></textarea>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="submitContact" class="btn btn-primary btn-lg">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        var messageText="<?= $_SESSION['status'] ?? ''; ?>";
        if(messageText!=''){
            Swal.fire({
                title: 'Thank you!',
                text: messageText,
                icon: 'success',
                confirmButtonColor: '#2e7d32'
            });
            <?php unset($_SESSION['status']); ?>
        }
    </script>
  </body>
</html>
