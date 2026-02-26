<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

$name = trim(strip_tags($_POST['name'] ?? ''));
$email = trim($_POST['email'] ?? '');
$message = trim(strip_tags($_POST['message'] ?? ''));

if ($name === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Ungültige Eingaben']);
  exit;
}

$toEmail = getenv('CONTACT_TO') ?: 'contact@arnovoyer.com';
$fromEmail = getenv('CONTACT_FROM') ?: 'noreply@arnovoyer.com';
$fromName = getenv('CONTACT_FROM_NAME') ?: 'Portfolio Kontakt';

if (!function_exists('mail')) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Mail-Funktion nicht verfügbar']);
  exit;
}

$safeName = str_replace(["\r", "\n"], '', $name);
$safeEmail = str_replace(["\r", "\n"], '', $email);
$safeFromName = str_replace(["\r", "\n"], '', $fromName);

$subject = 'Neue Nachricht von ' . $safeName;
$body = "Name: {$safeName}\nE-Mail: {$safeEmail}\n\nNachricht:\n{$message}";

$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'From: ' . $safeFromName . ' <' . $fromEmail . '>';
$headers[] = 'Reply-To: ' . $safeName . ' <' . $safeEmail . '>';

$sent = @mail($toEmail, $subject, $body, implode("\r\n", $headers));

if (!$sent) {
  error_log('PHP mail() failed for contact form');
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Mailversand fehlgeschlagen']);
  exit;
}

echo json_encode(['ok' => true]);