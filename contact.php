<?php
// Fehleranzeige aktivieren (nur zum Testen!)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Prüfe, ob die Dateien im 'src' Ordner liegen (Standard bei PHPMailer)
// Falls dein Ordner anders strukturiert ist, pass das hier an!
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  http_response_code(403);
  exit("Direkter Zugriff verboten.");
}

$name = strip_tags($_POST["name"] ?? "");
$email = filter_var($_POST["email"] ?? "", FILTER_SANITIZE_EMAIL);
$message = strip_tags($_POST["message"] ?? "");

$mail = new PHPMailer(true);

try {
  // Server Einstellungen
  $mail->isSMTP();
  $mail->Host = 'smtp-relay.brevo.com';
  $mail->SMTPAuth = true;
  $mail->Username = 'a1190a001@smtp-brevo.com'; // Meist die Email, mit der du dich bei Brevo einloggst
  $mail->Password = 'xsmtpsib-abdd01eb0a6b73d72f7466779272061d86c98c75bf4fe8884b3e194347bdfc4c-WGywylSBIH3bsLAB';     // Dein Master-Passwort oder API Key
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = 587;
  $mail->CharSet = 'UTF-8';

  // Absender & Empfänger
  $mail->setFrom('contact@arnovoyer.com', 'Portfolio Kontakt');
  $mail->addAddress('contact@arnovoyer.com');
  $mail->addReplyTo($email, $name);

  // Inhalt
  $mail->isHTML(false);
  $mail->Subject = 'Neue Nachricht von ' . $name;
  $mail->Body = "Name: $name\nEmail: $email\n\nNachricht:\n$message";

  $mail->send();
  echo "Success"; // Wichtig für die JS-Antwort
} catch (Exception $e) {
  http_response_code(500);
  echo "Mail-Fehler: {$mail->ErrorInfo}";
}