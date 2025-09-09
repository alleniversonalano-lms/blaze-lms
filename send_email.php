<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function sendEmail($toEmail, $toName, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@blaze-lms.com';
        $mail->Password = 'Blazelms@2025';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Recipients
        $mail->setFrom('no-reply@blaze-lms.com', 'BLAZE');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Fallback for non-HTML clients

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
