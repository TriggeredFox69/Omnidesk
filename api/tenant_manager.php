<?php
// Multi-Tenant Isolation Engine for OmniDesk SaaS
// Guarantees separate databases, leads, campaigns, and product configurations per company.

function getTenantBaseDir(): string {
    $dir = __DIR__ . '/../data/tenants';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function resolveActiveCompanyId(): string {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $sessionUser = $_SESSION['omnidesk_user'] ?? null;
    $comp = 'COMP-001';

    if (!empty($sessionUser['company_id']) && $sessionUser['company_id'] !== 'GLOBAL') {
        $comp = $sessionUser['company_id'];
    }

    // Allow Super Admin or authorized request to specify company_id
    $reqComp = $_GET['company_id'] ?? $_POST['company_id'] ?? null;
    if (!$reqComp) {
        $raw = @file_get_contents('php://input');
        if ($raw) {
            $j = @json_decode($raw, true);
            if (is_array($j) && !empty($j['company_id'])) {
                $reqComp = $j['company_id'];
            }
        }
    }

    if (!empty($reqComp)) {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim((string)$reqComp));
        if ($clean) {
            $isSuper = (!empty($sessionUser['role']) && $sessionUser['role'] === 'superadmin') ||
                       (!empty($sessionUser['company_id']) && $sessionUser['company_id'] === 'GLOBAL');
            if ($isSuper || empty($sessionUser) || $clean === ($sessionUser['company_id'] ?? '')) {
                $comp = $clean;
            }
        }
    }

    return $comp ?: 'COMP-001';
}

function getTenantDir(string $companyId): string {
    $clean = preg_replace('/[^A-Za-z0-9_-]/', '', trim($companyId)) ?: 'COMP-001';
    $dir = getTenantBaseDir() . '/' . $clean;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function getTenantLeadsFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/leads.json';
    if (!file_exists($file)) {
        // Migration safeguard: if COMP-001, load existing leads from root data/leads.json if it exists
        $legacy = __DIR__ . '/../data/leads.json';
        if ($companyId === 'COMP-001' && file_exists($legacy) && filesize($legacy) > 10) {
            @copy($legacy, $file);
        } else {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    return $file;
}

function getTenantCampaignLogsFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/campaign_logs.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $file;
}

function getTenantConfigFile(string $companyId): string {
    $dir = getTenantDir($companyId);
    $file = $dir . '/config.json';
    if (!file_exists($file)) {
        $isNunno = ($companyId === 'COMP-001');
        $defaultConfig = [
            'company_id'   => $companyId,
            'company_name' => $isNunno ? 'Nunno Official' : 'Company ' . $companyId,
            'products'     => $isNunno ? [
                ['id' => 'pro', 'name' => 'Nunno Pro', 'amount' => 5000, 'commission' => 2000, 'badge' => '💎 Most Popular'],
                ['id' => 'starter', 'name' => 'Nunno Starter', 'amount' => 1700, 'commission' => 700, 'badge' => '⚡ Scalper Entry'],
                ['id' => 'whale', 'name' => 'Whale VIP', 'amount' => 15000, 'commission' => 10000, 'badge' => '🐋 Institutional Alpha'],
                ['id' => 'annual', 'name' => 'Annual Pass', 'amount' => 35000, 'commission' => 15000, 'badge' => '👑 Unlimited Pass']
            ] : [
                ['id' => 'p_standard', 'name' => 'Standard Package', 'amount' => 5000, 'commission' => 1500, 'badge' => '⭐ Recommended'],
                ['id' => 'p_starter', 'name' => 'Starter Deal', 'amount' => 2000, 'commission' => 500, 'badge' => '⚡ Quick Start'],
                ['id' => 'p_vip', 'name' => 'VIP Premium Deal', 'amount' => 12000, 'commission' => 4000, 'badge' => '👑 High Value']
            ],
            'default_pitch' => $isNunno 
                ? "{Salam|AoA} {name}! Market wicks se liquid ho rahe hain? Nunno AI Liquidation Magnet se exact whale levels pehle dekhein."
                : "{Salam|AoA|Hello} {name}! Welcome to our company. Special limited-time offers available today."
        ];
        file_put_contents($file, json_encode($defaultConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $file;
}

function getTenantConfig(string $companyId): array {
    $file = getTenantConfigFile($companyId);
    $data = @json_decode(@file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

// Scans tenant databases to locate which company owns an incoming customer phone number
function findCompanyIdForPhone(string $phone): string {
    $cleanPhone = preg_replace('/\D+/', '', $phone);
    if (!$cleanPhone) return 'COMP-001';

    $baseDir = getTenantBaseDir();
    $tenants = @scandir($baseDir);
    if (is_array($tenants)) {
        foreach ($tenants as $t) {
            if ($t === '.' || $t === '..') continue;
            $tenantLeadsFile = $baseDir . '/' . $t . '/leads.json';
            if (file_exists($tenantLeadsFile)) {
                $leads = @json_decode(@file_get_contents($tenantLeadsFile), true);
                if (is_array($leads)) {
                    foreach ($leads as $l) {
                        $lp = preg_replace('/\D+/', '', $l['phone'] ?? '');
                        if ($lp === $cleanPhone) {
                            return $t;
                        }
                    }
                }
            }
        }
    }
    return 'COMP-001';
}
