<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Start session to use $_SESSION
session_start();

if(isset($_POST['submitContact'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $message = $_POST['message'];
    $subject = $_POST['subject'];
    $Contact = $_POST['Contact'];

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();                           // Send using SMTP
        $mail->SMTPAuth = true;    
        $mail->Host = 'smtp.gmail.com';            // SMTP server
        $mail->Username = 'harikakommineni817@gmail.com';  // SMTP username
        $mail->Password = 'fogo xghx iqbx plme';           // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS
        $mail->Port = 587;                         // TCP port (587 for TLS)

        // Recipients
        $mail->setFrom('harikakommineni817@gmail.com', 'harika');
        $mail->addAddress('medirapid78@gmail.com', 'harika');

        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'New enquiry - contact form';
        $mail->Body = '<h3>Hello, you got a new enquiry</h3>
            <h4>Full Name: ' . $full_name . '</h4>
            <h4>Email: ' . $email . '</h4>    
            <h4>Message: ' . $message . '</h4>
            <h4>Subject: ' . $subject . '</h4>
            <h4>Contact: ' . $Contact . '</h4>';

        if ($mail->send()) {
            $_SESSION['status'] = 'Thank you for contacting us.';
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
        else{
            $_SESSSION['status']="Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            header("Location: " . $_SERVER['HTTP_REFERER']);
            exit();
        }
    } catch (Exception $e) {
       echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
} else {
    header('Location: index.php');
    exit();
}
?>
