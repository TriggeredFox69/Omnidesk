<?php
/**
 * leads_sync.php while chats are stored in Supabase (data/leads_store_supabase.on).
 *
 * Same actions and answers as the file version, but every change touches one lead or one
 * message row instead of rewriting the company's whole leads.json, and the Sales Desk poll
 * only receives the leads that changed since its last revision.
 *
 * Included by leads_sync.php after sign-in; uses $sessionUser, $activeCompanyId, $companyConfig.
 */

require_once __DIR__ . '/lead_store.php';

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if (!is_array($input)) $input = [];
$action = $input['action'] ?? $_GET['action'] ?? 'get_leads';

$dbRole = strtolower((string) ($sessionUser['role'] ?? 'member'));
$dbIsAdmin = in_array($dbRole, ['admin', 'superadmin', 'master admin'], true);
$dbMyId = (string) ($sessionUser['id'] ?? '');
$dbMyUser = strtolower((string) ($sessionUser['username'] ?? ''));
$dbMyNameClean = preg_replace('/\s+/', '', strtolower((string) ($sessionUser['name'] ?? '')));

function dbLeadsOut(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Placeholder names some old imports used are shown as the number instead. */
function dbCleanName(array $l): array {
    $n = strtolower(trim((string) ($l['name'] ?? '')));
    if ($n === '' || $n === 'trader' || $n === 'alishba') {
        $p = preg_replace('/\D+/', '', (string) ($l['phone'] ?? ''));
        $l['name'] = $p !== '' ? ('+' . $p) : ($l['display'] ?? 'Contact');
    }
    return $l;
}

try {
    // ---------------------------------------------------------------- get_leads
    if ($action === 'get_leads') {
        // since = the revision this browser already has. 0 (or an old-style hash) = full load.
        $sinceRaw = (string) ($_GET['since'] ?? '');
        $since = preg_match('/^\d{1,18}$/', $sinceRaw) ? (int) $sinceRaw : 0;
        $page = chatlyStoreLeadsPage($activeCompanyId, $since, $since > 0 ? 2000 : 3000, 12, $dbIsAdmin, $dbMyId, $dbMyUser);
        $leads = [];
        foreach ((array) ($page['leads'] ?? []) as $l) {
            if (!is_array($l)) continue;
            if (!empty($l['_deleted'])) { $leads[] = $l; continue; }
            // Closers and team leads only ever see their own leads (same rule as the file version).
            if (!$dbIsAdmin && ($dbMyId || $dbMyUser || $dbMyNameClean) && !chatlyLeadVisibleTo($l, $dbMyId, $dbMyUser, $dbMyNameClean)) {
                if ($since > 0) $leads[] = ['id' => $l['id'] ?? '', 'phone' => $l['phone'] ?? '', '_deleted' => true];   // no longer theirs
                continue;
            }
            $leads[] = dbCleanName($l);
        }
        dbLeadsOut([
            'success'        => true,
            'leads'          => $leads,
            'count'          => count($leads),
            'delta'          => $since > 0,
            'company_id'     => $activeCompanyId,
            'company_config' => $companyConfig,
            'rev'            => (string) ($page['rev'] ?? $since),
            'store'          => 'supabase',
        ]);
    }

    // ------------------------------------------------------- get_lead_messages
    if ($action === 'get_lead_messages') {
        $wantId = (string) ($input['id'] ?? ($_GET['id'] ?? ''));
        $wantPhone = (string) ($input['phone'] ?? ($_GET['phone'] ?? ''));
        $lead = chatlyStoreFindLead($activeCompanyId, $wantId, $wantPhone);
        if (!$lead) dbLeadsOut(['success' => false, 'error' => 'Conversation not found.'], 404);
        if (!$dbIsAdmin && ($dbMyId || $dbMyUser || $dbMyNameClean) && !chatlyLeadVisibleTo($lead, $dbMyId, $dbMyUser, $dbMyNameClean)) {
            dbLeadsOut(['success' => false, 'error' => 'This conversation is assigned to another agent.'], 403);
        }
        $msgs = chatlyStoreMessages($activeCompanyId, (string) ($lead['phone'] ?? ''));
        dbLeadsOut(['success' => true, 'id' => $lead['id'] ?? '', 'messages' => $msgs, 'messageCount' => count($msgs)]);
    }

    // -------------------------------------------------------------- update_lead
    if ($action === 'update_lead') {
        $leadId = (string) ($input['id'] ?? '');
        $inputPhone = isset($input['phone']) ? chatlyStoreDigits((string) $input['phone']) : '';
        if ($inputPhone === '' && str_starts_with($leadId, 'wa_lead_')) $inputPhone = preg_replace('/\D+/', '', substr($leadId, 8));
        if ($leadId === '' && $inputPhone === '') dbLeadsOut(['success' => false, 'message' => 'Lead ID or phone required']);

        // The same fields, with the same rules, as the file version.
        $patch = [];
        if ($inputPhone !== '' && isset($input['phone'])) { $patch['phone'] = $inputPhone; $patch['display'] = '+' . $inputPhone; }
        foreach (['leadState', 'status', 'takenOverBy', 'takenOverById', 'takenOverAt', 'email', 'paidDetails', 'notes',
                  'verificationStatus', 'verifiedBy', 'verifiedAt', 'rejectionReason', 'lastActive'] as $f) {
            if (isset($input[$f])) $patch[$f] = $input[$f];
        }
        foreach (['ownerId', 'ownerName', 'originalOwnerId', 'originalOwnerName'] as $f) {
            if (array_key_exists($f, $input)) $patch[$f] = $input[$f];
        }
        if (isset($input['isTakeover'])) $patch['isTakeover'] = (bool) $input['isTakeover'];
        if (isset($input['name']) && trim((string) $input['name']) !== '') $patch['name'] = trim((string) $input['name']);
        if (isset($input['unreadCount'])) $patch['unreadCount'] = (int) $input['unreadCount'];
        if (isset($input['lastMessageAt'])) $patch['lastMessageAt'] = (int) $input['lastMessageAt'];

        // Never creates a lead (see the file version): unknown id / number = "Lead not found".
        $lead = chatlyStorePatchLead($activeCompanyId, $leadId, $inputPhone, $patch);
        if (!$lead) dbLeadsOut(['success' => false, 'message' => 'Lead not found.']);
        $phone = (string) ($lead['phone'] ?? $inputPhone);

        if (isset($input['newMessage']) && is_array($input['newMessage'])) {
            chatlyStoreAddMessages($activeCompanyId, [['phone' => $phone, 'msg' => chatlyStoreMessageWithKey($input['newMessage'], $phone), 'touch' => true]]);
        }
        if (isset($input['updateMessage']) && is_array($input['updateMessage']) && !empty($input['updateMessage']['waMessageId'])) {
            $u = $input['updateMessage'];
            if (!empty($u['deliveryStatus'])) {
                chatlyStoreSetStatus($activeCompanyId, (string) $u['waMessageId'], (string) $u['deliveryStatus'],
                    array_key_exists('deliveryError', $u) && $u['deliveryError'] !== null ? (string) $u['deliveryError'] : null);
            }
        }
        dbLeadsOut(['success' => true, 'message' => 'Lead updated successfully']);
    }

    // ----------------------------------------------------------------- add_lead
    if ($action === 'add_lead') {
        $cleanPhone = chatlyStoreDigits((string) ($input['phone'] ?? ''));
        if (strlen($cleanPhone) < 10) dbLeadsOut(['success' => false, 'message' => 'Valid phone number is required (at least 10 digits).']);
        $rawName = trim((string) ($input['name'] ?? ''));
        $lower = strtolower($rawName);
        $name = ($rawName !== '' && $lower !== 'trader' && $lower !== 'alishba' && $lower !== 'new contact') ? $rawName : ('+' . $cleanPhone);

        $existing = chatlyStoreFindLead($activeCompanyId, '', $cleanPhone);
        if ($existing) {
            if ($name !== '' && $name !== $cleanPhone && (($existing['name'] ?? '') === $cleanPhone || empty($existing['name']))) {
                $existing = chatlyStorePatchLead($activeCompanyId, (string) $existing['id'], '', ['name' => $name]) ?: $existing;
            }
            dbLeadsOut(['success' => true, 'lead' => $existing, 'existing' => true]);
        }
        $newLead = [
            'id' => (string) ($input['id'] ?? ('lead_' . bin2hex(random_bytes(6)))),
            'name' => $name, 'phone' => $cleanPhone, 'display' => '+' . $cleanPhone,
            'city' => 'Pakistan', 'network' => 'WhatsApp',
            'ownerId' => 'USR-ADMIN-01', 'ownerName' => 'System Administrator',
            'leadState' => 'ongoing', 'status' => 'replied', 'unreadCount' => 0,
            'interest' => 'Direct Outreach', 'lastActive' => 'Just now', 'lastMessageAt' => time(),
            'notes' => 'Custom contact added for direct WhatsApp communication.',
        ];
        chatlyStoreSaveLeads($activeCompanyId, [$newLead]);
        $newLead['messages'] = [];
        dbLeadsOut(['success' => true, 'lead' => $newLead, 'message' => 'New chat created successfully!']);
    }

    // ------------------------------------------------------------- delete_leads
    if ($action === 'delete_leads' || $action === 'delete_lead') {
        if (!chatlyIsCompanyAdmin($sessionUser) && !chatlyIsSuperAdmin($sessionUser)) {
            dbLeadsOut(['success' => false, 'error' => 'Only an admin can delete chats.'], 403);
        }
        $leadIds = $input['ids'] ?? [];
        if (empty($leadIds) && !empty($input['id'])) $leadIds = [$input['id']];
        if (empty($leadIds) || !is_array($leadIds)) dbLeadsOut(['success' => false, 'error' => 'No lead IDs provided for deletion'], 400);
        $phones = [];
        foreach ($leadIds as $id) {
            $d = preg_replace('/\D+/', '', (string) $id);
            if (strlen($d) >= 9) $phones[] = $d;
        }
        // the numbers of the leads being deleted, so their messages go too
        foreach ($leadIds as $id) {
            $l = chatlyStoreFindLead($activeCompanyId, (string) $id, '');
            if ($l && !empty($l['phone'])) $phones[] = preg_replace('/\D+/', '', (string) $l['phone']);
        }
        $phones = array_values(array_unique(array_filter($phones)));
        $deleted = chatlyStoreDeleteLeads($activeCompanyId, $leadIds, $phones);

        // Also purge them from the bridge's recent-message caches so they never come back.
        $dataRoot = dirname(__DIR__) . '/data';
        foreach ([$dataRoot . '/whatsapp-bridge/inbox.json', $dataRoot . '/whatsapp-bridge/whatsapp-bridge/inbox.json',
                  $dataRoot . '/whatsapp-bridge/local/inbox.json', $dataRoot . '/whatsapp-cloud/inbox.json', $dataRoot . '/inbox.json'] as $inboxFile) {
            if (!file_exists($inboxFile)) continue;
            $inbox = json_decode((string) @file_get_contents($inboxFile), true) ?: [];
            $keep = array_values(array_filter($inbox, function ($m) use ($phones) {
                $p = preg_replace('/\D/', '', (string) ($m['phone'] ?? ($m['from'] ?? ($m['sender'] ?? ''))));
                return !in_array($p, $phones, true);
            }));
            if (count($keep) !== count($inbox)) @file_put_contents($inboxFile, json_encode($keep, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        dbLeadsOut(['success' => true, 'deleted_count' => $deleted, 'message' => "Successfully deleted {$deleted} conversation(s) permanently."]);
    }

    // -------------------------------------------------------- get_pending_deals
    if ($action === 'get_pending_deals') {
        $pending = [];
        foreach (chatlyLeadsAll($activeCompanyId) as $l) {
            $state = strtolower((string) ($l['leadState'] ?? ($l['status'] ?? '')));
            if ($state === 'pending_verification' || strtolower((string) ($l['verificationStatus'] ?? '')) === 'pending') $pending[] = $l;
        }
        dbLeadsOut(['success' => true, 'deals' => $pending, 'count' => count($pending)]);
    }

    // ------------------------------------------------- verify_deal / reject_deal
    if ($action === 'verify_deal' || $action === 'reject_deal') {
        if (!in_array($dbRole, ['admin', 'superadmin'], true)) {
            dbLeadsOut(['success' => false, 'error' => $action === 'verify_deal'
                ? 'Only administrators can verify deals and release commissions.' : 'Only administrators can reject deals.'], 403);
        }
        $leadId = (string) ($input['id'] ?? '');
        if ($leadId === '') dbLeadsOut(['success' => false, 'error' => 'Lead ID is required.'], 400);
        $l = chatlyStoreFindLead($activeCompanyId, $leadId, preg_match('/^\+?\d{9,}$/', $leadId) ? $leadId : '');
        if (!$l) dbLeadsOut(['success' => false, 'error' => 'Lead not found.'], 404);
        $by = $sessionUser['name'] ?? 'System Administrator';
        $phone = (string) ($l['phone'] ?? '');

        if ($action === 'verify_deal') {
            $amount = (int) ($l['paidDetails']['amount'] ?? ($l['dealAmount'] ?? 5000));
            $commission = (int) ($l['paidDetails']['commission'] ?? 1500);
            $plan = $l['paidDetails']['plan'] ?? ($l['interest'] ?? 'Nunno Pro');
            $closerId = $l['originalOwnerId'] ?? ($l['paidDetails']['closedById'] ?? ($l['ownerId'] ?? ''));
            $closerName = $l['originalOwnerName'] ?? ($l['paidDetails']['closedBy'] ?? ($l['ownerName'] ?? 'Closer'));
            if ($closerId) {
                require_once __DIR__ . '/quota.php';
                chatlyUsersUpdate(__DIR__ . '/../data/users.json', function (array &$users) use ($closerId, $commission, $amount) {
                    foreach ($users as &$u) {
                        if (($u['id'] ?? '') === $closerId || strtolower($u['username'] ?? '') === strtolower((string) $closerId)) {
                            $u['commission_balance'] = ((int) ($u['commission_balance'] ?? 0)) + $commission;
                            $u['total_sales_amount'] = ((int) ($u['total_sales_amount'] ?? 0)) + $amount;
                            $u['deals_won_count'] = ((int) ($u['deals_won_count'] ?? 0)) + 1;
                            return true;
                        }
                    }
                    return false;
                });
            }
            $patch = ['leadState' => 'paid', 'status' => 'paid', 'verificationStatus' => 'verified', 'verifiedBy' => $by, 'verifiedAt' => date('Y-m-d H:i:s')];
            $msg = ['id' => 'verif_' . time(), 'sender' => 'agent', 'agentName' => 'Security Audit', 'time' => date('H:i'), 'timestamp' => time(),
                'text' => "🟢 PAYMENT VERIFIED & COMMISSION RELEASED!\n• Deal: {$plan} (Rs. " . number_format($amount) . ")\n• Commission Released: Rs. " . number_format($commission) . " to {$closerName}\n• Approved by: {$by}"];
            $okText = 'Deal verified successfully! Commission released to closer wallet.';
        } else {
            $reason = trim((string) ($input['reason'] ?? 'Fake slip or payment not received on banking portal'));
            $hasOwner = !empty($l['ownerId']);
            $patch = ['leadState' => $hasOwner ? 'ongoing' : 'fresh', 'status' => $hasOwner ? 'replied' : 'unread', 'verificationStatus' => 'rejected',
                'rejectionReason' => $reason, 'rejectedBy' => $by, 'rejectedAt' => date('Y-m-d H:i:s'), 'paidDetails' => null];
            $msg = ['id' => 'rej_' . time(), 'sender' => 'agent', 'agentName' => 'Security Audit', 'time' => date('H:i'), 'timestamp' => time(),
                'text' => "🔴 TRANSACTION REJECTED (AUDIT NOTICE)\n• Reason: {$reason}\n• Audited by: {$by}\n• Status reverted to Ongoing."];
            $okText = 'Transaction rejected and lead reverted to ongoing.';
        }
        $updated = chatlyStorePatchLead($activeCompanyId, (string) $l['id'], '', $patch) ?: array_merge($l, $patch);
        chatlyStoreAddMessages($activeCompanyId, [['phone' => $phone, 'msg' => $msg, 'touch' => false]]);
        dbLeadsOut(['success' => true, 'message' => $okText, 'lead' => $updated]);
    }

    // ------------------------------------------------------------- import_leads
    if ($action === 'import_leads') {
        $newLeads = $input['leads'] ?? [];
        if (!is_array($newLeads) || empty($newLeads)) dbLeadsOut(['success' => false, 'message' => 'No leads provided']);
        $known = chatlyLeadKeyIndex($activeCompanyId);
        $add = [];
        foreach ($newLeads as $nl) {
            if (!is_array($nl)) continue;
            $d = preg_replace('/\D+/', '', (string) ($nl['phone'] ?? ''));
            $k = strlen($d) >= 9 ? substr($d, -9) : $d;
            if ($k === '' || isset($known[$k])) continue;
            $known[$k] = true;
            if (empty($nl['id'])) $nl['id'] = 'lead_' . bin2hex(random_bytes(6));
            $add[] = $nl;
        }
        if ($add) chatlyStoreSaveLeads($activeCompanyId, $add, 'insert');
        dbLeadsOut(['success' => true, 'added' => count($add)]);
    }

    // Demo tools rewrite the whole CRM file; they are not available while chats are in Supabase.
    if (in_array($action, ['clear_demo_data', 'wipe_demo', 'seed_demo_campaign', 'seed_demo'], true)) {
        dbLeadsOut(['success' => false, 'error' => 'This demo tool is not available while chats are stored in Supabase.'], 409);
    }

    dbLeadsOut(['success' => false, 'error' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    dbLeadsOut(['success' => false, 'error' => 'Chats are temporarily unavailable (' . $e->getMessage() . '). Please try again.'], 503);
}
