<?php
/**
 * Contact form handler for the Starline Studio site (IONOS hosting).
 *
 * contact.html POSTs here; this emails the submission to the studio inbox
 * with PHP mail(). Mirrors send-feedback.php.
 *
 * ===================================================================
 * Both addresses below are the IONOS mailbox  support@starlinestudio.co.uk
 * (which you've set to forward to starline.studio@outlook.com).
 *  - $FROM must be a real IONOS mailbox on this domain, or mail() is refused.
 *  - $TO is that same mailbox; IONOS forwards it on to Outlook, so the send
 *    never leaves IONOS and can't be spam-filtered on the way.
 * If you rename the mailbox, update both lines to match exactly.
 * ===================================================================
 */

$TO   = 'support@starlinestudio.co.uk';
$FROM = 'support@starlinestudio.co.uk';
$SITE = 'Starline Studio';
$LOG  = __DIR__ . '/contact-error.log';    // written only when a send fails

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

function respond($ok, $message, $wantsJson)
{
    if ($wantsJson) {
        header('Content-Type: application/json');
        http_response_code($ok ? 200 : 422);
        echo json_encode(['success' => $ok, 'message' => $message]);
    } else {
        header('Location: contact.html?' . ($ok ? 'sent=1' : 'error=1'));
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    respond(false, 'Method not allowed.', $wantsJson);
}

// Honeypot — bots fill hidden fields, people don't. Silently accept and drop.
if (!empty($_POST['_honey'])) {
    respond(true, 'Thanks.', $wantsJson);
}

// Strip CR/LF from single-line fields to prevent header injection.
function clean($v)
{
    return trim(str_replace(["\r", "\n"], ' ', (string) $v));
}

$name    = clean($_POST['name'] ?? '');
$email   = clean($_POST['email'] ?? '');
$topic   = clean($_POST['topic'] ?? 'General question');
$message = trim((string) ($_POST['message'] ?? ''));

if ($message === '') {
    respond(false, 'Please include a message.', $wantsJson);
}

$validEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);

if (!$validEmail) {
    respond(false, 'Please include a valid email address so we can reply.', $wantsJson);
}

$subject = "[Contact] {$topic}";

$body = implode("\r\n", [
    "New contact message from the {$SITE} website",
    str_repeat('-', 44),
    "Topic:  {$topic}",
    "Name:   " . ($name !== '' ? $name : '(not given)'),
    "Email:  {$email}",
    "",
    "Message:",
    $message,
    "",
    str_repeat('-', 44),
    "Sent:   " . date('Y-m-d H:i:s'),
    "IP:     " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
]);

$headers = [
    "From: {$SITE} <{$FROM}>",
    "MIME-Version: 1.0",
    "Content-Type: text/plain; charset=UTF-8",
    "Reply-To: " . ($name !== '' ? "{$name} <{$email}>" : $email),
];
$headerStr = implode("\r\n", $headers);

// Try with the envelope-sender param; if the host blocks it, retry without.
$sent = @mail($TO, $subject, $body, $headerStr, "-f{$FROM}");
if (!$sent) {
    $sent = @mail($TO, $subject, $body, $headerStr);
}

if (!$sent) {
    $err = error_get_last();
    @file_put_contents(
        $LOG,
        date('c') . "  mail() failed  from={$FROM} to={$TO}  " .
        "lasterr=" . ($err['message'] ?? 'none') . "\n",
        FILE_APPEND
    );
}

respond(
    (bool) $sent,
    $sent
        ? "Thanks - that's landed."
        : 'The mail server rejected the message. Please email us directly.',
    $wantsJson
);
