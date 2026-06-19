#!/usr/bin/env php
<?php

/**
 * Support Panel — Database Seeder
 * ================================
 * Generates:
 *   •  10 operators  (support panel users)
 *   • 3 000 customers
 *   • 5–15 activities per customer
 *   • 1–8 comments on every 3rd activity (linked to a random operator)
 *
 * Usage:
 *   php db/seed.php                   # defaults: root@127.0.0.1/support_app
 *   php db/seed.php --nette           # reads config/local.neon
 *   DB_HOST=x DB_USER=y DB_PASS=z DB_NAME=n php db/seed.php
 */

declare(strict_types=1);

$config = resolveConfig($argv ?? []);
$pdo    = connectPdo($config);
(new Seeder($pdo))->run();

// ── Config ────────────────────────────────────────────────────────────────────

function resolveConfig(array $argv): array
{
    if (in_array('--nette', $argv, true)) {
        $path = dirname(__DIR__) . '/config/local.neon';
        if (file_exists($path)) {
            return parseNeon($path);
        }
        fwrite(STDERR, "Warning: config/local.neon not found, using defaults.\n");
    }

    if (getenv('DB_HOST') !== false) {
        return [
            'host'   => (string) (getenv('DB_HOST') ?: '127.0.0.1'),
            'port'   => (int)    (getenv('DB_PORT') ?: 3306),
            'dbname' => (string) (getenv('DB_NAME') ?: 'support_app'),
            'user'   => (string) (getenv('DB_USER') ?: 'root'),
            'pass'   => (string) (getenv('DB_PASS') ?: ''),
        ];
    }

    return ['host' => '127.0.0.1', 'port' => 3306, 'dbname' => 'support_app', 'user' => 'root', 'pass' => ''];
}

function parseNeon(string $path): array
{
    $content = file_get_contents($path);
    $cfg = ['host' => '127.0.0.1', 'port' => 3306, 'dbname' => 'support_app', 'user' => 'root', 'pass' => ''];

    if (preg_match("/dsn:\s*['\"]mysql:([^'\"]+)['\"]/", $content, $m)) {
        foreach (explode(';', $m[1]) as $part) {
            [$k, $v] = array_map('trim', explode('=', $part, 2) + [1 => '']);
            if ($k === 'host')   $cfg['host']   = $v;
            if ($k === 'port')   $cfg['port']   = (int) $v;
            if ($k === 'dbname') $cfg['dbname'] = $v;
        }
    }
    if (preg_match("/^\s+user:\s*['\"]?([^'\"\n]+)['\"]?/m",    $content, $m)) $cfg['user'] = trim($m[1]);
    if (preg_match("/^\s+password:\s*['\"]?([^'\"\n]*)['\"]?/m", $content, $m)) $cfg['pass'] = trim($m[1]);

    return $cfg;
}

function connectPdo(array $cfg): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['dbname']);
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET NAMES utf8mb4");
        echo "✓ Connected to {$cfg['dbname']} @ {$cfg['host']}\n\n";
        return $pdo;
    } catch (\PDOException $e) {
        fwrite(STDERR, "Connection failed: {$e->getMessage()}\n");
        exit(1);
    }
}

// ─────────────────────────────────────────────────────────────────────────────

class Seeder
{
    private const CUSTOMERS     = 3_000;
    private const ACT_MIN       = 5;
    private const ACT_MAX       = 15;
    private const COMMENT_EVERY = 3;
    private const COMMENT_MIN   = 1;
    private const COMMENT_MAX   = 8;
    private const BATCH_SIZE    = 300;

    private const ACTIVITY_TYPES = [
        'login', 'purchase', 'support_ticket', 'password_reset',
        'profile_update', 'subscription', 'refund', 'note',
    ];

    // ── Operators (fixed, with known passwords for dev use) ───────────────────
    private const OPERATORS = [
        ['name' => 'Alice Admin',      'email' => 'alice@support.local',   'role' => 'admin'],
        ['name' => 'Bob Senior',       'email' => 'bob@support.local',     'role' => 'senior'],
        ['name' => 'Carol Agent',      'email' => 'carol@support.local',   'role' => 'agent'],
        ['name' => 'David Agent',      'email' => 'david@support.local',   'role' => 'agent'],
        ['name' => 'Eva Agent',        'email' => 'eva@support.local',     'role' => 'agent'],
        ['name' => 'Frank Senior',     'email' => 'frank@support.local',   'role' => 'senior'],
        ['name' => 'Grace Agent',      'email' => 'grace@support.local',   'role' => 'agent'],
        ['name' => 'Henry Agent',      'email' => 'henry@support.local',   'role' => 'agent'],
        ['name' => 'Iris Agent',       'email' => 'iris@support.local',    'role' => 'agent'],
        ['name' => 'Jack Agent',       'email' => 'jack@support.local',    'role' => 'agent'],
    ];
    // All operators get password: "password"
    // (bcrypt hash of "password" with cost=10)
    private const PASSWORD_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    private const FIRST_NAMES = [
        'James','Mary','John','Patricia','Robert','Jennifer','Michael','Linda',
        'William','Barbara','David','Susan','Richard','Jessica','Joseph','Sarah',
        'Thomas','Karen','Charles','Lisa','Christopher','Nancy','Daniel','Betty',
        'Matthew','Margaret','Anthony','Sandra','Mark','Ashley','Donald','Dorothy',
        'Steven','Kimberly','Paul','Emily','Andrew','Donna','Joshua','Michelle',
        'Kenneth','Carol','Kevin','Amanda','Brian','Melissa','George','Deborah',
        'Timothy','Stephanie','Ronald','Rebecca','Edward','Sharon','Jason','Laura',
        'Jeffrey','Cynthia','Ryan','Kathleen','Jacob','Angela','Gary','Shirley',
        'Nicholas','Emma','Eric','Jean','Jonathan','Victoria','Stephen','Evelyn',
        'Larry','Grace','Justin','Denise','Scott','Alice','Brandon','Teresa',
        'Benjamin','Kathryn','Samuel','Virginia','Raymond','Samantha','Gregory','Rachel',
        'Frank','Hannah','Patrick','Charlotte','Alexander','Amber','Jack','Julie',
        'Ethan','Olivia','Nathan','Sophia','Dylan','Ava','Logan','Isabella',
    ];

    private const LAST_NAMES = [
        'Smith','Johnson','Williams','Brown','Jones','Garcia','Miller','Davis',
        'Rodriguez','Martinez','Hernandez','Lopez','Gonzalez','Wilson','Anderson',
        'Thomas','Taylor','Moore','Jackson','Martin','Lee','Perez','Thompson',
        'White','Harris','Sanchez','Clark','Ramirez','Lewis','Robinson','Walker',
        'Young','Allen','King','Wright','Scott','Torres','Nguyen','Hill','Flores',
        'Green','Adams','Nelson','Baker','Hall','Rivera','Campbell','Mitchell',
        'Carter','Roberts','Turner','Phillips','Evans','Collins','Stewart','Morris',
        'Rogers','Reed','Cook','Bailey','Bell','Cooper','Richardson','Cox',
        'Ford','Hamilton','Graham','Sullivan','Wallace','Woods','Cole','West',
    ];

    private const DOMAINS = [
        'gmail.com','yahoo.com','hotmail.com','outlook.com','icloud.com',
        'protonmail.com','company.com','business.io','enterprise.net','corp.org',
        'fastmail.com','zoho.com','mail.com','live.com','me.com',
    ];

    private const NOTES_POOL = [
        'VIP customer — priority support.',
        'Prefers contact via email only.',
        'Enterprise plan, assigned account manager.',
        'Requested callback on weekdays only.',
        'Startup plan — potential upsell candidate.',
        'Migrated from legacy system.',
        'Referred by partner program.',
        'Annual billing, auto-renew enabled.',
        'Trial user — follow up in 7 days.',
        'High NPS score — potential case study.',
        null, null, null, null, null,
        null, null, null, null, null,
        null, null, null, null, null,
    ];

    private const ACTIVITY_DETAILS = [
        'login'          => ['Logged in from %s.', 'Login via mobile app from %s.', 'New device login detected — %s.', 'SSO login from %s.'],
        'purchase'       => ['Starter Plan — $9.99 USD.', 'Pro Plan — $49.99 USD.', 'Enterprise Plan — $199.99 USD.', 'Add-on: Extra Storage — $4.99 USD.', 'Annual subscription renewal — $499.00 USD.'],
        'support_ticket' => ['Cannot log in — TK-%05d.', 'Payment failed — TK-%05d.', 'Feature request: bulk export — TK-%05d.', 'Bug report: dashboard error — TK-%05d.', 'Billing dispute — TK-%05d.'],
        'password_reset' => ['Password reset email sent.', 'Password changed via reset link.', 'Reset requested from mobile app.', 'Forced reset by security policy.'],
        'profile_update' => ['Email address updated.', 'Phone number updated.', 'Company name changed.', 'Timezone updated to %s.', 'Two-factor authentication enabled.'],
        'subscription'   => ['Upgraded from Starter to Pro.', 'Upgraded from Pro to Enterprise.', 'Switched to annual billing.', 'Added team seat (+1 user).', 'Plan paused for 30 days.'],
        'refund'         => ['Refund issued — $9.99 USD. Reason: duplicate charge.', 'Refund issued — $49.99 USD. Reason: cancellation.', 'Partial refund — $25.00 USD. Reason: service outage.'],
        'note'           => ['Called customer, left voicemail.', 'Sent follow-up email re: renewal.', 'Customer confirmed receipt of invoice.', 'Escalated to tier-2 support.', 'Issue resolved after third contact.', 'Marked for churn-risk review.'],
    ];

    private const IPS = ['192.168.1.1','10.0.0.5','85.214.30.20','8.8.8.8','1.1.1.1','104.21.0.10','51.75.68.10'];
    private const TIMEZONES = ['UTC','America/New_York','America/Chicago','Europe/London','Europe/Berlin','Asia/Tokyo'];

    private const COMMENT_BODIES = [
        'Reviewed the issue and confirmed on our end.',
        'Reached out to the customer via email.',
        'Escalated to the engineering team.',
        'Issue resolved — root cause was a configuration mismatch.',
        'Customer confirmed resolution, closing ticket.',
        'Waiting for customer response.',
        'Applied account credit as compensation.',
        'Scheduled a follow-up call for next week.',
        'Potential upsell opportunity noted.',
        'Customer is satisfied with the outcome.',
        'Transferred to billing department.',
        'Fix deployed in the latest release.',
        'Sent knowledge-base article to customer.',
        'No response after 3 attempts — marking resolved.',
        'Refund processed, visible in 3–5 business days.',
        'Account access restored after identity verification.',
        'Password reset email resent at customer request.',
        'Customer accepted the workaround solution.',
        'Monitoring for 48 hours to confirm stability.',
        'Handoff to account manager for renewal discussion.',
    ];

    // ── Runtime state ─────────────────────────────────────────────────────────
    private array $operatorIds    = [];
    private int   $totalCustomers  = 0;
    private int   $totalActivities = 0;
    private int   $totalComments   = 0;
    private array $usedEmails      = [];

    public function __construct(private readonly PDO $pdo) {}

    // ── Entry point ───────────────────────────────────────────────────────────

    public function run(): void
    {
        $start = microtime(true);

        $this->validateEnum();
        $this->truncate();
        $this->seedOperators();
        $this->seedCustomers();

        $elapsed = round(microtime(true) - $start, 1);

        echo "\n";
        echo "╔══════════════════════════════════╗\n";
        echo "║         Seed complete!           ║\n";
        echo "╠══════════════════════════════════╣\n";
        printf("║  Operators   : %'16s  ║\n", number_format(count($this->operatorIds)));
        printf("║  Customers   : %'16s  ║\n", number_format($this->totalCustomers));
        printf("║  Activities  : %'16s  ║\n", number_format($this->totalActivities));
        printf("║  Comments    : %'16s  ║\n", number_format($this->totalComments));
        printf("║  Time        : %'13s s  ║\n", $elapsed);
        echo "╚══════════════════════════════════╝\n\n";
        echo "Operator logins (password: \"password\" for all):\n";
        foreach (self::OPERATORS as $op) {
            printf("  %-10s  %s\n", "[$op[role]]", $op['email']);
        }
    }

    // ── Validate ──────────────────────────────────────────────────────────────

    private function validateEnum(): void
    {
        $row = $this->pdo->query("SHOW COLUMNS FROM activities LIKE 'activity_type'")->fetch();
        if (!$row) {
            fwrite(STDERR, "ERROR: Table activities not found. Run schema.sql first.\n");
            exit(1);
        }
        preg_match_all("/'([^']+)'/", $row['Type'], $m);
        $missing = array_diff(self::ACTIVITY_TYPES, $m[1]);
        if ($missing) {
            fwrite(STDERR, "ERROR: ENUM mismatch: " . implode(', ', $missing) . "\n");
            exit(1);
        }
        echo "✓ ENUM validated: " . implode(', ', $m[1]) . "\n";
    }

    // ── Truncate ──────────────────────────────────────────────────────────────

    private function truncate(): void
    {
        echo "Truncating tables… ";
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['comments', 'activities', 'customers', 'operators'] as $t) {
            $this->pdo->exec("TRUNCATE TABLE `{$t}`");
        }
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "done.\n\n";
    }

    // ── Operators ─────────────────────────────────────────────────────────────

    private function seedOperators(): void
    {
        echo "Seeding " . count(self::OPERATORS) . " operators…\n";
        $stmt = $this->pdo->prepare(
            "INSERT INTO operators (name, email, password_hash, role) VALUES (?, ?, ?, ?)"
        );
        foreach (self::OPERATORS as $op) {
            $stmt->execute([$op['name'], $op['email'], self::PASSWORD_HASH, $op['role']]);
            $this->operatorIds[] = (int) $this->pdo->lastInsertId();
        }
        echo "  Operators seeded.\n\n";
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    private function seedCustomers(): void
    {
        echo "Seeding " . number_format(self::CUSTOMERS) . " customers (batch: " . self::BATCH_SIZE . ")…\n";
        $buffer = [];

        for ($i = 1; $i <= self::CUSTOMERS; $i++) {
            $buffer[] = $this->buildCustomerRow();

            if (count($buffer) >= self::BATCH_SIZE || $i === self::CUSTOMERS) {
                $ids = $this->insertCustomers($buffer);
                $this->totalCustomers += count($ids);
                $buffer = [];

                foreach ($ids as $cid) {
                    $acts = $this->insertActivities($cid);
                    $this->insertComments($acts);
                }

                $pct = round($this->totalCustomers / self::CUSTOMERS * 100);
                echo sprintf(
                    "  [%3d%%] customers=%-6s  activities=%-7s  comments=%s\n",
                    $pct,
                    number_format($this->totalCustomers),
                    number_format($this->totalActivities),
                    number_format($this->totalComments)
                );
            }
        }
    }

    private function buildCustomerRow(): array
    {
        $name = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)]
              . ' '
              . self::LAST_NAMES[array_rand(self::LAST_NAMES)];

        return [
            'name'       => $name,
            'email'      => $this->uniqueEmail($name),
            'phone'      => mt_rand(0, 2) > 0 ? sprintf('+1-%03d-%04d', mt_rand(200,999), mt_rand(1000,9999)) : null,
            'is_active'  => mt_rand(1, 10) <= 8 ? 1 : 0,
            'notes'      => self::NOTES_POOL[array_rand(self::NOTES_POOL)],
            'created_at' => $this->rndDatetime('2018-01-01', '2024-10-01'),
        ];
    }

    /** @return int[] */
    private function insertCustomers(array $rows): array
    {
        $ph   = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?)'));
        $stmt = $this->pdo->prepare(
            "INSERT INTO customers (name, email, phone, is_active, notes, created_at) VALUES {$ph}"
        );
        $params = [];
        foreach ($rows as $r) {
            array_push($params, $r['name'], $r['email'], $r['phone'], $r['is_active'], $r['notes'], $r['created_at']);
        }
        $stmt->execute($params);
        $firstId = (int) $this->pdo->lastInsertId();
        return range($firstId, $firstId + count($rows) - 1);
    }

    // ── Activities ────────────────────────────────────────────────────────────

    /** @return array<int, array{id:int, created_at:string}> */
    private function insertActivities(int $customerId): array
    {
        $count     = mt_rand(self::ACT_MIN, self::ACT_MAX);
        $createdAt = $this->pdo->query("SELECT created_at FROM customers WHERE id = {$customerId}")->fetchColumn();
        $current   = new \DateTimeImmutable($createdAt);
        $cutoff    = new \DateTimeImmutable('2024-12-31');
        $rows      = [];

        for ($i = 0; $i < $count; $i++) {
            $current = $current->modify('+' . mt_rand(1, 45) . ' days');
            if ($current > $cutoff) break;
            $type    = self::ACTIVITY_TYPES[array_rand(self::ACTIVITY_TYPES)];
            $rows[]  = [
                'customer_id'   => $customerId,
                'activity_type' => $type,
                'details'       => $this->buildDetails($type),
                'created_at'    => $current->format('Y-m-d H:i:s'),
            ];
        }
        if (!$rows) return [];

        $ph   = implode(',', array_fill(0, count($rows), '(?,?,?,?)'));
        $stmt = $this->pdo->prepare(
            "INSERT INTO activities (customer_id, activity_type, details, created_at) VALUES {$ph}"
        );
        $params = [];
        foreach ($rows as $r) {
            array_push($params, $r['customer_id'], $r['activity_type'], $r['details'], $r['created_at']);
        }
        $stmt->execute($params);

        $firstId    = (int) $this->pdo->lastInsertId();
        $activities = [];
        foreach ($rows as $idx => $r) {
            $activities[] = ['id' => $firstId + $idx, 'created_at' => $r['created_at']];
        }

        $this->totalActivities += count($activities);
        return $activities;
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    private function insertComments(array $activities): void
    {
        if (!$activities) return;
        $cmtRows = [];

        foreach ($activities as $idx => $act) {
            if (($idx + 1) % self::COMMENT_EVERY !== 0) continue;

            $count   = mt_rand(self::COMMENT_MIN, self::COMMENT_MAX);
            $current = new \DateTimeImmutable($act['created_at']);

            for ($k = 0; $k < $count; $k++) {
                $current  = $current->modify('+' . mt_rand(5, 600) . ' minutes');
                $cmtRows[] = [
                    'activity_id' => $act['id'],
                    'operator_id' => $this->operatorIds[array_rand($this->operatorIds)],  // random operator
                    'body'        => self::COMMENT_BODIES[array_rand(self::COMMENT_BODIES)],
                    'created_at'  => $current->format('Y-m-d H:i:s'),
                ];
            }
        }

        if (!$cmtRows) return;

        foreach (array_chunk($cmtRows, self::BATCH_SIZE) as $batch) {
            $ph   = implode(',', array_fill(0, count($batch), '(?,?,?,?)'));
            $stmt = $this->pdo->prepare(
                "INSERT INTO comments (activity_id, operator_id, body, created_at) VALUES {$ph}"
            );
            $params = [];
            foreach ($batch as $r) {
                array_push($params, $r['activity_id'], $r['operator_id'], $r['body'], $r['created_at']);
            }
            $stmt->execute($params);
        }

        $this->totalComments += count($cmtRows);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function uniqueEmail(string $name): string
    {
        $base   = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name));
        $domain = self::DOMAINS[array_rand(self::DOMAINS)];
        $email  = "{$base}@{$domain}";
        $n      = 2;
        while (isset($this->usedEmails[$email])) {
            $email = "{$base}{$n}@{$domain}";
            $n++;
        }
        $this->usedEmails[$email] = true;
        return $email;
    }

    private function rndDatetime(string $from, string $to): string
    {
        return date('Y-m-d H:i:s', mt_rand(strtotime($from), strtotime($to)));
    }

    private function buildDetails(string $type): string
    {
        $pool = self::ACTIVITY_DETAILS[$type] ?? ['Activity recorded.'];
        $tpl  = $pool[array_rand($pool)];
        $subs = [self::IPS[array_rand(self::IPS)], mt_rand(10000, 99999), self::TIMEZONES[array_rand(self::TIMEZONES)]];
        preg_match_all('/%[sd]/', $tpl, $m);
        $args = array_slice($subs, 0, count($m[0]));
        return $args ? vsprintf($tpl, $args) : $tpl;
    }
}
