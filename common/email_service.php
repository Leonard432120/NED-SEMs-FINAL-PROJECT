<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ================= EMAIL CREDENTIALS ================= */
define('EMAIL_USER', 'leonardmlungupro@gmail.com');
define('EMAIL_PASS', 'pzza mjot khbl ojya');

/* ================= CORE EMAIL FUNCTION ================= */
function send_email($to_email, $subject, $message) {

    if (!$to_email) return false;

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USER;
        $mail->Password = EMAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom(EMAIL_USER, 'NED-SEMS System');
        $mail->addAddress($to_email);

        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $message;

        return $mail->send();

    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

/* ================= USER ASSIGNMENT EMAIL ================= */
function send_assignment_email($email, $name, $exam_name, $role) {

    $subject = "NED-SEMS Assignment Notification";

    $message =
"Hello $name,

You have been assigned a new role in the system.

Exam: $exam_name
Role: $role

Please login to your dashboard to view details.";

    return send_email($email, $subject, $message);
}