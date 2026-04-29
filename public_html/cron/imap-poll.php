<?php
/**
 * imap-poll.php — every 2 min, log into the replies inbox over IMAP, find
 * unread messages whose To: address matches the plus-addressed token we set
 * as Reply-To on outbound RFQs, and route them into rfq_replies.
 *
 * cron line:
 *   *2 * * * * /usr/bin/curl -fsS "https://habeshair.com/cron/imap-poll.php?secret=XXX"
 *
 * config.php needs:
 *   'imap' => [
 *     'host'    => '{mail.habeshair.com:993/imap/ssl}INBOX',
 *     'user'    => 'replies@habeshair.com',
 *     'pass'    => '...',
 *   ],
 *   'mail' => [..., 'reply_inbox_local' => 'replies', 'reply_inbox_domain' => 'habeshair.com', ...],
 *
 * Requires PHP IMAP extension. Namecheap shared hosting has it enabled by
 * default; if you see "Call to undefined function imap_open" install /
 * enable it via cPanel → Select PHP Version → Extensions → check `imap`.
 *
 * Robustness:
 *   - cron-secret query param required
 *   - every message is wrapped in try/catch; errors append to logs/imap.log
 *   - exit code is always 0 so cPanel doesn't email failures
 *   - messages without a matching token are LEFT UNREAD so admin can still
 *     see them in their normal client (don't mark generic mail \\Seen)
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secret = (string)cfg('cron.secret', '');
if ($secret === '' || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!function_exists('imap_open')) {
    echo json_encode(['ok' => false, 'error' => 'PHP IMAP extension not enabled']);
    exit;
}

$result = imap_poll_run();
echo json_encode($result);
exit;

// =============================================================================

function imap_poll_run(): array {
    $host = (string)cfg('imap.host', '');
    $user = (string)cfg('imap.user', '');
    $pass = (string)cfg('imap.pass', '');
    if ($host === '' || $user === '' || $pass === '') {
        return ['ok' => false, 'error' => 'cfg(imap.*) not fully set'];
    }

    $localPart  = (string)cfg('mail.reply_inbox_local', 'replies');
    $domainPart = (string)cfg('mail.reply_inbox_domain', parse_url((string)cfg('app.base_url',''), PHP_URL_HOST) ?: 'habeshair.com');

    $mbox = @imap_open($host, $user, $pass, 0, 1);
    if (!$mbox) {
        $err = imap_last_error() ?: 'imap_open failed';
        _log_imap("connect: {$err}");
        return ['ok' => false, 'error' => 'IMAP connect failed: ' . $err];
    }

    $matched   = 0;
    $unmatched = 0;
    $errors    = 0;

    try {
        $uids = @imap_search($mbox, 'UNSEEN', SE_UID) ?: [];
        foreach ($uids as $uid) {
            try {
                $hdr = @imap_rfc822_parse_headers((string)imap_fetchheader($mbox, $uid, FT_UID));
                if (!$hdr) { $errors++; continue; }
                $token = _extract_token($hdr, $localPart, $domainPart);
                if ($token === null) {
                    // Not for us — leave UNSEEN, skip
                    $unmatched++;
                    continue;
                }
                $dispatch = _find_dispatch_by_token($token);
                if (!$dispatch) {
                    _log_imap("uid {$uid}: token {$token} not in rfq_dispatches");
                    @imap_setflag_full($mbox, (string)$uid, "\\Seen", ST_UID);
                    $errors++;
                    continue;
                }

                $structure = imap_fetchstructure($mbox, $uid, FT_UID);
                $bodyText  = _extract_body($mbox, $uid, $structure, 'PLAIN');
                $bodyHtml  = _extract_body($mbox, $uid, $structure, 'HTML');
                $atts      = _extract_attachments($mbox, $uid, $structure);

                $fromEmail = '';
                $fromName  = '';
                if (!empty($hdr->from[0])) {
                    $fromEmail = ($hdr->from[0]->mailbox ?? '') . '@' . ($hdr->from[0]->host ?? '');
                    $fromName  = (string)($hdr->from[0]->personal ?? '');
                }
                $subject = isset($hdr->subject) ? _decode_mime_header($hdr->subject) : null;
                $receivedAt = isset($hdr->date) ? date('Y-m-d H:i:s', strtotime($hdr->date)) : date('Y-m-d H:i:s');

                $bodyTrim = $bodyText !== '' ? $bodyText : strip_tags((string)$bodyHtml);
                $snippet  = mb_substr(trim(preg_replace('/\s+/', ' ', $bodyTrim) ?: ''), 0, 500);
                $price    = _extract_price($bodyTrim);

                // Persist
                db()->beginTransaction();
                $ins = db()->prepare(
                    'INSERT INTO rfq_replies
                     (dispatch_id, from_email, from_name, subject, body_text, body_html,
                      has_attachments, attachments, imap_uid, received_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $dispatch['id'],
                    mb_substr($fromEmail, 0, 190),
                    $fromName !== '' ? mb_substr($fromName, 0, 190) : null,
                    $subject !== null ? mb_substr($subject, 0, 250) : null,
                    $bodyText !== '' ? $bodyText : null,
                    $bodyHtml !== '' ? $bodyHtml : null,
                    $atts ? 1 : 0,
                    $atts ? json_encode($atts) : null,
                    (int)$uid,
                    $receivedAt,
                ]);

                // Update dispatch
                $newStatus = $price !== null ? 'Quoted' : 'Replied';
                $up = db()->prepare(
                    'UPDATE rfq_dispatches
                     SET status = ?, reply_at = COALESCE(reply_at, ?),
                         reply_snippet = ?, quoted_amount = COALESCE(?, quoted_amount),
                         quoted_currency = COALESCE(?, quoted_currency)
                     WHERE id = ?'
                );
                $up->execute([
                    $newStatus,
                    $receivedAt,
                    $snippet ?: null,
                    $price['amount'] ?? null,
                    $price['currency'] ?? null,
                    $dispatch['id'],
                ]);

                // Bump request status to RFQ-Received unless past it
                $progressed = ['Quoted','Waiting','Confirmed','Flown','Cancelled','Closed'];
                $statusRow = db()->prepare('SELECT status FROM charter_requests WHERE id = ?');
                $statusRow->execute([$dispatch['request_id']]);
                $cur = (string)$statusRow->fetchColumn();
                if (!in_array($cur, $progressed, true)) {
                    db()->prepare('UPDATE charter_requests SET status = "RFQ-Received" WHERE id = ?')
                        ->execute([$dispatch['request_id']]);
                }
                db()->commit();

                @imap_setflag_full($mbox, (string)$uid, "\\Seen", ST_UID);

                // Notify admin
                $aname = (string)($dispatch['airline_name'] ?? 'airline');
                $ref   = (string)($dispatch['reference_code'] ?? '?');
                $sub = $price !== null
                     ? sprintf('💰 Quote from %s for %s — %s %s', $aname, $ref, $price['currency'] ?? 'USD', number_format((float)$price['amount'], 2))
                     : sprintf('💬 Reply from %s for %s', $aname, $ref);
                $body = "Snippet:\n{$snippet}\n\nView: " . url('/admin/rfq-view.php?id=' . (int)$dispatch['id']) . "\n";
                @send_admin_notification($sub, $body);

                $matched++;
            } catch (\Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                _log_imap("uid {$uid}: " . $e->getMessage());
                $errors++;
            }
        }
    } finally {
        @imap_expunge($mbox);
        @imap_close($mbox);
    }

    return [
        'ok'        => true,
        'matched'   => $matched,
        'unmatched' => $unmatched,
        'errors'    => $errors,
        'at'        => date('c'),
    ];
}

// =============================================================================
// Helpers
// =============================================================================

function _extract_token(object $hdr, string $local, string $domain): ?string {
    // Check To: + Cc: + Delivered-To header
    $candidates = [];
    foreach (['to', 'cc', 'rcpt_to'] as $field) {
        if (!empty($hdr->{$field}) && is_array($hdr->{$field})) {
            foreach ($hdr->{$field} as $a) {
                $candidates[] = ($a->mailbox ?? '') . '@' . ($a->host ?? '');
            }
        }
    }
    if (!empty($hdr->Delivered_To)) $candidates[] = (string)$hdr->Delivered_To;

    $localQ  = preg_quote($local, '/');
    $domainQ = preg_quote($domain, '/');
    $re = '/' . $localQ . '\+([a-f0-9]{16})@' . $domainQ . '/i';
    foreach ($candidates as $addr) {
        if (preg_match($re, (string)$addr, $m)) return strtolower($m[1]);
    }
    return null;
}

function _find_dispatch_by_token(string $token): ?array {
    $stmt = db()->prepare(
        'SELECT d.id, d.request_id, d.airline_id, d.status,
                a.name AS airline_name, r.reference_code
         FROM rfq_dispatches d
         JOIN airlines a ON a.id = d.airline_id
         JOIN charter_requests r ON r.id = d.request_id
         WHERE d.reply_token = ? LIMIT 1'
    );
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function _extract_body($mbox, int $uid, $structure, string $kind): string {
    $kindCode = $kind === 'HTML' ? TYPETEXT : TYPETEXT;
    $kindSub  = $kind === 'HTML' ? 'HTML' : 'PLAIN';

    if (!isset($structure->parts)) {
        $partType = $structure->type ?? 0;
        $sub      = strtoupper($structure->subtype ?? 'PLAIN');
        if ($partType === 0 && $sub === $kindSub) {
            $raw = imap_fetchbody($mbox, $uid, '1', FT_UID);
            return _decode_part_body($raw, $structure);
        }
        return '';
    }
    return _walk_parts_for_body($mbox, $uid, $structure->parts, $kindSub, '');
}

function _walk_parts_for_body($mbox, int $uid, array $parts, string $kindSub, string $prefix): string {
    foreach ($parts as $i => $p) {
        $section = $prefix === '' ? (string)($i + 1) : ($prefix . '.' . ($i + 1));
        $sub = strtoupper($p->subtype ?? '');
        if (($p->type ?? 0) === 0 && $sub === $kindSub) {
            $raw = imap_fetchbody($mbox, $uid, $section, FT_UID);
            return _decode_part_body($raw, $p);
        }
        if (!empty($p->parts)) {
            $deep = _walk_parts_for_body($mbox, $uid, $p->parts, $kindSub, $section);
            if ($deep !== '') return $deep;
        }
    }
    return '';
}

function _decode_part_body(string $raw, $part): string {
    $enc = $part->encoding ?? 0;
    if ($enc === ENCBASE64)         $raw = base64_decode($raw);
    elseif ($enc === ENCQUOTEDPRINTABLE) $raw = quoted_printable_decode($raw);
    // Charset → UTF-8
    $charset = 'UTF-8';
    if (!empty($part->parameters)) {
        foreach ($part->parameters as $pa) {
            if (strtolower($pa->attribute) === 'charset') { $charset = strtoupper($pa->value); break; }
        }
    }
    if ($charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
        $raw = @mb_convert_encoding($raw, 'UTF-8', $charset) ?: $raw;
    }
    return $raw;
}

function _extract_attachments($mbox, int $uid, $structure): array {
    $out = [];
    if (empty($structure->parts)) return $out;
    _walk_parts_for_atts($mbox, $uid, $structure->parts, '', $out);
    return $out;
}

function _walk_parts_for_atts($mbox, int $uid, array $parts, string $prefix, array &$out): void {
    foreach ($parts as $i => $p) {
        $section = $prefix === '' ? (string)($i + 1) : ($prefix . '.' . ($i + 1));
        $isAttachment = false;
        $filename = '';
        if (!empty($p->disposition) && strtolower($p->disposition) === 'attachment') {
            $isAttachment = true;
        }
        if (!empty($p->dparameters)) {
            foreach ($p->dparameters as $dp) {
                if (strtolower($dp->attribute) === 'filename') $filename = (string)$dp->value;
            }
        }
        if ($filename === '' && !empty($p->parameters)) {
            foreach ($p->parameters as $pa) {
                if (strtolower($pa->attribute) === 'name') $filename = (string)$pa->value;
            }
        }
        if ($isAttachment || $filename !== '') {
            $out[] = [
                'section'  => $section,
                'filename' => $filename ?: 'unnamed',
                'size'     => (int)($p->bytes ?? 0),
                'mime'     => strtolower(($p->subtype ?? '')),
            ];
        }
        if (!empty($p->parts)) {
            _walk_parts_for_atts($mbox, $uid, $p->parts, $section, $out);
        }
    }
}

function _decode_mime_header(string $h): string {
    $els = imap_mime_header_decode($h) ?: [];
    $out = '';
    foreach ($els as $el) {
        $cs = strtoupper($el->charset ?? 'default');
        $txt = $el->text ?? '';
        if ($cs !== 'DEFAULT' && $cs !== 'UTF-8' && function_exists('mb_convert_encoding')) {
            $txt = @mb_convert_encoding($txt, 'UTF-8', $cs) ?: $txt;
        }
        $out .= $txt;
    }
    return $out;
}

/**
 * Best-effort price extraction. Looks for common patterns:
 *   $4,500.00 / USD 4500 / 4500 USD / EUR 3,200 / KES 250000 / £1,200
 * Returns ['amount' => 4500.00, 'currency' => 'USD'] or null.
 */
function _extract_price(string $body): ?array {
    if ($body === '') return null;

    // Currency-symbol prefix: $4,500 | £1,200 | €3.500 | KSh 1,000,000
    if (preg_match('/(\$|€|£|KSh|KES)\s?([0-9]{1,3}(?:[,. ]?[0-9]{3})*(?:\.[0-9]{1,2})?)/iu', $body, $m)) {
        $amount = (float)str_replace([',', ' '], '', $m[2]);
        $currency = match (strtoupper($m[1])) {
            '$'   => 'USD',
            '€'   => 'EUR',
            '£'   => 'GBP',
            'KSH','KES' => 'KES',
            default => 'USD',
        };
        if ($amount > 0) return ['amount' => round($amount, 2), 'currency' => $currency];
    }
    // ISO code prefix or suffix: "USD 4500" / "4500 USD"
    if (preg_match('/\b(USD|EUR|GBP|KES|ETB|UGX|TZS|ZAR|AED|SAR)\s?([0-9]{1,3}(?:[,. ]?[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $body, $m)) {
        $amount = (float)str_replace([',', ' '], '', $m[2]);
        if ($amount > 0) return ['amount' => round($amount, 2), 'currency' => strtoupper($m[1])];
    }
    if (preg_match('/([0-9]{1,3}(?:[,. ]?[0-9]{3})*(?:\.[0-9]{1,2})?)\s?(USD|EUR|GBP|KES|ETB|UGX|TZS|ZAR|AED|SAR)\b/i', $body, $m)) {
        $amount = (float)str_replace([',', ' '], '', $m[1]);
        if ($amount > 0) return ['amount' => round($amount, 2), 'currency' => strtoupper($m[2])];
    }
    return null;
}

function _log_imap(string $msg): void {
    @file_put_contents(__DIR__ . '/../logs/imap.log',
        sprintf("[%s] %s\n", date('c'), $msg), FILE_APPEND);
}
