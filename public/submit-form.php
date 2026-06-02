<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');

require dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function field(string $key, int $maxLength = 3000): string
{
    $value = $_POST[$key] ?? '';
    if (is_array($value)) {
        return '';
    }

    $value = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }

    return substr($value, 0, $maxLength);
}

function clean_single_line(string $value): string
{
    return trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');
}

function fail(string $message, int $status = 400): void
{
    json_response(['success' => false, 'message' => $message], $status);
}

function same_origin_request(): bool
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return true;
    }

    $originHost = parse_url($origin, PHP_URL_HOST);
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = explode(':', $host)[0];

    return is_string($originHost) && hash_equals(strtolower($host), strtolower($originHost));
}

function app_secret(): string
{
    require_once dirname(__DIR__) . '/config/env.php';
    $secret = env_value('APP_SECRET', '');
    if ($secret === '' || $secret === 'replace-with-a-long-random-secret') {
        throw new RuntimeException('APP_SECRET is not configured.');
    }
    return $secret;
}

function is_local_env(): bool
{
    require_once dirname(__DIR__) . '/config/env.php';
    return env_value('APP_ENV', 'production') === 'local';
}

function log_form_event(string $message): void
{
    $dir = dirname(__DIR__) . '/tmp';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents($dir . '/form.log', $line, FILE_APPEND | LOCK_EX);
}

function client_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash_hmac('sha256', $ip, app_secret());
}

function enforce_rate_limit(string $hash): void
{
    $dir = dirname(__DIR__) . '/tmp/rate-limit';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Rate limit directory is not writable.');
    }

    $file = $dir . '/' . $hash . '.json';
    $now = time();
    $window = 600;
    $maxRequests = 5;
    $data = ['start' => $now, 'count' => 0];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $data = array_merge($data, $decoded);
        }
    }

    if (($now - (int) $data['start']) > $window) {
        $data = ['start' => $now, 'count' => 0];
    }

    $data['count'] = (int) $data['count'] + 1;
    file_put_contents($file, json_encode($data), LOCK_EX);

    if ($data['count'] > $maxRequests) {
        fail('Trop de demandes ont été envoyées. Merci de réessayer dans quelques minutes.', 429);
    }
}

function send_smtp_mail(array $mailConfig, string $subject, array $bodyLines, string $replyToEmail, string $replyToName): bool
{
    $missing = [];
    foreach (['host', 'username', 'password', 'from_email', 'to_email'] as $key) {
        if (!isset($mailConfig[$key]) || trim((string) $mailConfig[$key]) === '') {
            $missing[] = $key;
        }
    }

    if ($missing !== []) {
        throw new RuntimeException('SMTP configuration incomplete.');
    }

    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $mailConfig['host'];
    $mail->SMTPAuth = true;
    $mail->Username = $mailConfig['username'];
    $mail->Password = $mailConfig['password'];
    $mail->Port = (int) $mailConfig['port'];
    $mail->Timeout = 12;

    $mail->SMTPSecure = (($mailConfig['secure'] ?? 'tls') === 'ssl')
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->setFrom($mailConfig['from_email'], $mailConfig['from_name']);
    $mail->addAddress($mailConfig['to_email'], $mailConfig['to_name']);
    $mail->addReplyTo($replyToEmail, $replyToName);
    $mail->Subject = $subject;
    $mail->Body = implode("\n", $bodyLines);
    $mail->AltBody = $mail->Body;

    return $mail->send();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Méthode non autorisée.'], 405);
}

if (!same_origin_request()) {
    fail('Requête refusée.', 403);
}

if (field('was_hp_check', 200) !== '') {
    log_form_event('Honeypot blocked form submission.');
    json_response([
        'success' => true,
        'message' => 'Votre message a bien été transmis.',
        'email_sent' => true,
    ]);
}

$startedAt = (int) field('form_started', 20);
if ($startedAt <= 0 || time() - $startedAt < 2) {
    fail('Merci de réessayer dans quelques secondes.');
}

try {
    enforce_rate_limit(client_hash());

    $config = require dirname(__DIR__) . '/config/database.php';
    $mailConfig = require dirname(__DIR__) . '/config/mail.php';

    $formType = clean_single_line(field('form_type', 20));
    if (!in_array($formType, ['contact', 'candidature'], true)) {
        fail('Type de formulaire invalide.');
    }

    $firstName = clean_single_line(field('prenom', 120));
    $lastName = clean_single_line(field('nom', 120));
    $email = clean_single_line(field('email', 180));
    $phone = clean_single_line(field('telephone', 40));
    $requestType = clean_single_line(field('type', 180));
    $jobPosition = clean_single_line(field('poste', 180));
    $message = trim(field('message', 3000));

    if ($lastName === '' || $firstName === '' || $email === '' || $message === '') {
        fail('Merci de remplir les champs obligatoires.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Merci de saisir une adresse email valide.');
    }

    if ($phone !== '' && !preg_match('/^[0-9+\s().-]{6,40}$/', $phone)) {
        fail('Merci de saisir un numéro de téléphone valide.');
    }

    if ($formType === 'candidature' && $phone === '') {
        fail('Merci de saisir votre téléphone.');
    }

    if (function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') < 10 : strlen($message) < 10) {
        fail('Merci de préciser votre message.');
    }

    $serverDsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO($serverDsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $subjectBase = $formType === 'candidature'
        ? 'Nouvelle candidature - WAS TELECOM'
        : 'Nouvelle demande de contact - WAS TELECOM';
    $subject = $subjectBase . ' - ' . date('Y-m-d H:i:s');

    $bodyLines = [
        $subject,
        '',
        'Nom : ' . $lastName,
        'Prénom : ' . $firstName,
        'Email : ' . $email,
        'Téléphone : ' . ($phone !== '' ? $phone : 'Non renseigné'),
        'Type de demande : ' . ($requestType !== '' ? $requestType : 'Non renseigné'),
        'Poste recherché : ' . ($jobPosition !== '' ? $jobPosition : 'Non renseigné'),
        '',
        'Message :',
        $message,
    ];

    try {
        $emailSent = send_smtp_mail($mailConfig, $subject, $bodyLines, $email, trim($firstName . ' ' . $lastName));
        $mailError = null;
        log_form_event('SMTP OK to=' . ($mailConfig['to_email'] ?? 'missing') . ' form=' . $formType);
    } catch (MailException | RuntimeException $mailException) {
        $emailSent = false;
        $mailError = $mailException->getMessage();
        error_log('WAS TELECOM SMTP error: ' . $mailError);
        log_form_event('SMTP FAIL to=' . ($mailConfig['to_email'] ?? 'missing') . ' form=' . $formType . ' error=' . $mailError);
    }

    $insert = $pdo->prepare(
        'INSERT INTO form_submissions
        (form_type, first_name, last_name, email, phone, request_type, job_position, message, email_sent)
        VALUES
        (:form_type, :first_name, :last_name, :email, :phone, :request_type, :job_position, :message, :email_sent)'
    );

    $insert->execute([
        ':form_type' => $formType,
        ':first_name' => $firstName,
        ':last_name' => $lastName,
        ':email' => $email,
        ':phone' => $phone !== '' ? $phone : null,
        ':request_type' => $requestType !== '' ? $requestType : null,
        ':job_position' => $jobPosition !== '' ? $jobPosition : null,
        ':message' => $message,
        ':email_sent' => $emailSent ? 1 : 0,
    ]);

    $payload = [
        'success' => true,
        'message' => $emailSent
            ? 'Votre message a bien été envoyé.'
            : 'Votre message est enregistré. WAS TELECOM vous recontactera rapidement.',
        'email_sent' => $emailSent,
    ];

    if (!$emailSent && is_local_env()) {
        $payload['mail_error'] = $mailError;
        $payload['mail_to'] = $mailConfig['to_email'] ?? null;
    }

    json_response($payload);
} catch (Throwable $exception) {
    error_log('WAS TELECOM form error: ' . $exception->getMessage());
    json_response([
        'success' => false,
        'message' => 'Erreur serveur : impossible de traiter le formulaire pour le moment.',
    ], 500);
}
