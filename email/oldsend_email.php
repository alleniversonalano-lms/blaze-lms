<?php
date_default_timezone_set('Asia/Manila'); // Change to your correct timezone
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer-master/src/Exception.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER['DOCUMENT_ROOT'] . '/PHPMailer-master/src/SMTP.php';

function sendEmail($to, $name, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@cloudkyle.com';
        $mail->Password = 'Devops@2025';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        // Email Recipients
        $mail->setFrom('no-reply@cloudkyle.com', 'BLAZE');
        $mail->addAddress($to, $name);

        // Email Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        $mail->AltBody = strip_tags($message);

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
?>
