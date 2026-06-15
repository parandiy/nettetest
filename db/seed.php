#!/usr/bin/env php
<?php

/**
 * Support Panel — Database Seeder
 * ================================
 * Generates:
 *   • 3 000 customers
 *   • 5–15 activities per customer  (~30 000 total)
 *   • 1–8 comments on every 3rd activity  (~45 000 total)
 *
 * Requirements: PHP 8.2+, ext-pdo, ext-pdo_mysql
 *
 * Usage:
 *   php seed.php                    # defaults: root@127.0.0.1/support_app
 *   php seed.php --nette            # reads config/common.neon
 *   DB_HOST=x DB_USER=y DB_PASS=z DB_NAME=n php seed.php
 */

declare(strict_types=1);

$config = resolveConfig($argv ?? []);
$pdo    = connectPdo($config);
(new Seeder($pdo))->run();

// ── Config ────────────────────────────────────────────────────────────────────

function resolveConfig(array $argv): array
{
    if (in_array('--nette', $argv, true)) {
        $path = dirname(__DIR__) . '/config/common.neon';
        if (file_exists($path)) {
            return parseNeon($path);
        }
        fwrite(STDERR, "Warning: config/common.neon not found, using defaults.\n");
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
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $cfg['host'], $cfg['port'], $cfg['dbname']
    );
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
    // ── Tuning ────────────────────────────────────────────────────────────────
    private const CUSTOMERS     = 3_000;
    private const ACT_MIN       = 5;
    private const ACT_MAX       = 15;
    private const COMMENT_EVERY = 3;
    private const COMMENT_MIN   = 1;
    private const COMMENT_MAX   = 8;
    private const BATCH_SIZE    = 300;

    // ── ENUM values — must match schema exactly ────────────────────────────────
    private const ACTIVITY_TYPES = [
        'login',
        'purchase',
        'support_ticket',
        'password_reset',
        'profile_update',
        'subscription',
        'refund',
        'note',
    ];

    // ── Data pools ────────────────────────────────────────────────────────────
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
        'Dennis','Helen','Jerry','Anna','Tyler','Megan','Aaron','Brenda',
        'Ethan','Olivia','Nathan','Sophia','Dylan','Ava','Logan','Isabella',
        'Noah','Mia','Lucas','Emma','Mason','Aria','Aiden','Lily',
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
        'Ward','Peterson','Watson','Brooks','Kelly','Sanders','Price','Bennett',
        'Wood','Barnes','Ross','Henderson','Coleman','Jenkins','Perry','Powell',
        'Long','Patterson','Hughes','Washington','Butler','Simmons','Foster',
        'Gonzales','Bryant','Alexander','Russell','Griffin','Diaz','Hayes','Myers',
        'Ford','Hamilton','Graham','Sullivan','Wallace','Woods','Cole','West',
        'Jordan','Owens','Reynolds','Fisher','Ellis','Harrison','Gibson','Mcdonald',
    ];

    private const DOMAINS = [
        'gmail.com','yahoo.com','hotmail.com','outlook.com','icloud.com',
        'protonmail.com','company.com','business.io','enterprise.net','corp.org',
        'webmail.co','fastmail.com','zoho.com','mail.com','inbox.com',
        'live.com','msn.com','aol.com','me.com','yandex.com',
    ];

    private const NOTES_POOL = [
        'VIP customer — priority support.',
        'Prefers contact via email only.',
        'Enterprise plan, assigned account manager.',
        'Requested callback on weekdays only.',
        'Startup plan — potential upsell candidate.',
        'Migrated from legacy system in Q1.',
        'Referred by partner program.',
        'Runs multiple sub-accounts.',
        'Annual billing, auto-renew enabled.',
        'Trial user — follow up in 7 days.',
        'Requested data export before renewal.',
        'High NPS score — potential case study.',
        null, null, null, null, null,
        null, null, null, null, null,
        null, null, null, null, null,
    ];

    private const ACTIVITY_DETAILS = [
        'login' => [
            'Logged in from %s.',
            'Login via mobile app from %s.',
            'New device login detected — %s.',
            'SSO login from %s.',
            'Logged in using remember-me token from %s.',
        ],
        'purchase' => [
            'Starter Plan — $9.99 USD.',
            'Pro Plan — $49.99 USD.',
            'Enterprise Plan — $199.99 USD.',
            'Add-on: Extra Storage (100 GB) — $4.99 USD.',
            'Add-on: API Access — $14.99 USD.',
            'Annual subscription renewal — $499.00 USD.',
            'Team seat added — $19.99 USD/month.',
        ],
        'support_ticket' => [
            'Cannot log in — TK-%05d.',
            'Payment failed — TK-%05d.',
            'Feature request: bulk CSV export — TK-%05d.',
            'Bug report: dashboard rendering error — TK-%05d.',
            'Data export request — TK-%05d.',
            'Account access issue — TK-%05d.',
            'Billing dispute — TK-%05d.',
            'API integration question — TK-%05d.',
            'Performance issue on large datasets — TK-%05d.',
        ],
        'password_reset' => [
            'Password reset email sent.',
            'Password changed successfully via reset link.',
            'Reset requested from mobile app.',
            'Forced reset triggered by security policy.',
            'Password reset after suspicious activity detected.',
        ],
        'profile_update' => [
            'Email address updated.',
            'Phone number updated.',
            'Company name changed.',
            'Timezone updated to %s.',
            'Notification preferences updated.',
            'Two-factor authentication enabled.',
            'Two-factor authentication disabled.',
            'Avatar image updated.',
            'Billing address changed.',
        ],
        'subscription' => [
            'Upgraded from Starter to Pro plan.',
            'Upgraded from Pro to Enterprise plan.',
            'Downgraded from Enterprise to Pro plan.',
            'Switched from monthly to annual billing.',
            'Switched from annual to monthly billing.',
            'Added team seat (+1 user).',
            'Removed team seat (-1 user).',
            'Plan paused for 30 days.',
            'Plan resumed after pause.',
        ],
        'refund' => [
            'Refund issued — $9.99 USD. Reason: duplicate charge.',
            'Refund issued — $49.99 USD. Reason: cancellation within 14 days.',
            'Partial refund — $25.00 USD. Reason: service outage credit.',
            'Refund issued — $199.99 USD. Reason: billing error.',
            'Pro-rated refund — $14.50 USD. Reason: plan downgrade.',
        ],
        'note' => [
            'Called customer, left voicemail.',
            'Sent follow-up email regarding renewal.',
            'Customer confirmed receipt of invoice.',
            'Escalated to tier-2 support.',
            'Issue resolved after third contact attempt.',
            'Marked for churn-risk review.',
            'Scheduled product demo call.',
            'Account flagged for security review.',
            'Positive feedback received via NPS survey (score: %d).',
        ],
    ];

    private const IPS = [
        '192.168.1.1','10.0.0.5','172.16.0.10','85.214.30.20','8.8.8.8',
        '1.1.1.1','208.67.222.222','104.21.0.10','185.220.101.1','51.75.68.10',
        '203.0.113.42','198.51.100.7','176.58.100.2','91.108.4.15','100.64.0.1',
    ];

    private const TIMEZONES = [
        'UTC','America/New_York','America/Chicago','America/Los_Angeles',
        'America/Denver','Europe/London','Europe/Berlin','Europe/Paris',
        'Asia/Tokyo','Asia/Shanghai','Australia/Sydney','Pacific/Auckland',
    ];

    private const COMMENT_AUTHORS = [
        'Support Agent','Senior Agent','Account Manager','Billing Team',
        'Tech Support','Operator','Team Lead','QA Analyst',
        'Customer Success','Tier-2 Support',
    ];

    private const COMMENT_BODIES = [
        'Reviewed the issue and confirmed on our end.',
        'Reached out to the customer via email.',
        'Escalated to the engineering team for investigation.',
        'Issue resolved — root cause was a configuration mismatch.',
        'Customer confirmed resolution, closing ticket.',
        'Waiting for customer response.',
        'Applied account credit as compensation.',
        'Scheduled a follow-up call for next week.',
        'Internal note: potential upsell opportunity here.',
        'Customer is satisfied with the outcome.',
        'Duplicate request — merged with existing ticket.',
        'Transferred to billing department.',
        'Fix deployed in the latest release.',
        'Sent knowledge-base article link to customer.',
        'No response after 3 attempts — marking as resolved.',
        'Customer confirmed they no longer need assistance.',
        'Refund processed, visible in 3–5 business days.',
        'Account access restored after identity verification.',
        'Two-factor authentication issue fixed on our end.',
        'Password reset email resent at customer request.',
        'Engineering confirmed this is expected behaviour.',
        'Customer accepted the workaround solution.',
        'Monitoring for 48 hours to confirm stability.',
        'Handoff to account manager for renewal discussion.',
        'Added internal tag: high-priority.',
    ];

    // ── Runtime state ─────────────────────────────────────────────────────────
    private int   $totalCustomers  = 0;
    private int   $totalActivities = 0;
    private int   $totalComments   = 0;
    private array $usedEmails      = [];

    public function __construct(private readonly PDO $pdo) {}

    // ── Entry point ───────────────────────────────────────────────────────────

    public function run(): void
    {
        $startTime = microtime(true);

        $this->validateEnum();
        $this->truncate();
        $this->seed();

        $elapsed = round(microtime(true) - $startTime, 1);

        echo "\n";
        echo "╔══════════════════════════════════╗\n";
        echo "║         Seed complete!           ║\n";
        echo "╠══════════════════════════════════╣\n";
        printf("║  Customers   : %'16s  ║\n", number_format($this->totalCustomers));
        printf("║  Activities  : %'16s  ║\n", number_format($this->totalActivities));
        printf("║  Comments    : %'16s  ║\n", number_format($this->totalComments));
        printf("║  Time        : %'13s s  ║\n", $elapsed);
        echo "╚══════════════════════════════════╝\n";
    }

    // ── Validation ────────────────────────────────────────────────────────────

    /**
     * Verify that the ENUM values in the DB match the ones this seeder uses.
     * Prevents silent data corruption if schema gets out of sync.
     */
    private function validateEnum(): void
    {
        $row = $this->pdo
            ->query("SHOW COLUMNS FROM activities LIKE 'activity_type'")
            ->fetch();

        if (!$row) {
            fwrite(STDERR, "ERROR: Column activities.activity_type not found. Run schema.sql first.\n");
            exit(1);
        }

        // MySQL returns: enum('login','purchase',...)
        preg_match_all("/'([^']+)'/", $row['Type'], $matches);
        $dbValues = $matches[1];

        $missing = array_diff(self::ACTIVITY_TYPES, $dbValues);
        if ($missing) {
            fwrite(STDERR, "ERROR: ENUM mismatch. Values in seeder but not in DB: " . implode(', ', $missing) . "\n");
            exit(1);
        }

        echo "✓ ENUM values validated: " . implode(', ', $dbValues) . "\n";
    }

    // ── Truncate ──────────────────────────────────────────────────────────────

    private function truncate(): void
    {
        echo "Truncating existing data… ";
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach (['comments', 'activities', 'customers'] as $table) {
            $this->pdo->exec("TRUNCATE TABLE `{$table}`");
        }
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo "done.\n\n";
    }

    // ── Main seed loop ────────────────────────────────────────────────────────

    private function seed(): void
    {
        echo "Seeding " . number_format(self::CUSTOMERS) . " customers"
            . " (batch size: " . self::BATCH_SIZE . ")…\n";

        $custBuffer = [];

        for ($i = 1; $i <= self::CUSTOMERS; $i++) {
            $custBuffer[] = $this->buildCustomerRow();

            if (count($custBuffer) >= self::BATCH_SIZE || $i === self::CUSTOMERS) {
                $customerIds = $this->insertCustomers($custBuffer);
                $this->totalCustomers += count($customerIds);
                $custBuffer = [];

                foreach ($customerIds as $customerId) {
                    $activities = $this->insertActivitiesForCustomer($customerId);
                    $this->insertCommentsForActivities($activities);
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

    // ── Customers ─────────────────────────────────────────────────────────────

    private function buildCustomerRow(): array
    {
        $name = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)]
              . ' '
              . self::LAST_NAMES[array_rand(self::LAST_NAMES)];

        return [
            'name'       => $name,
            'email'      => $this->uniqueEmail($name),
            'phone'      => mt_rand(0, 2) > 0 ? $this->randomPhone() : null,
            'is_active'  => mt_rand(1, 10) <= 8 ? 1 : 0,
            'notes'      => self::NOTES_POOL[array_rand(self::NOTES_POOL)],
            'created_at' => $this->randomDatetime('2018-01-01', '2024-10-01'),
        ];
    }

    /** @return int[] */
    private function insertCustomers(array $rows): array
    {
        $ph   = implode(',', array_fill(0, count($rows), '(?,?,?,?,?,?)'));
        $stmt = $this->pdo->prepare(
            "INSERT INTO customers (name, email, phone, is_active, notes, created_at)
             VALUES {$ph}"
        );

        $params = [];
        foreach ($rows as $r) {
            array_push($params, $r['name'], $r['email'], $r['phone'],
                                $r['is_active'], $r['notes'], $r['created_at']);
        }
        $stmt->execute($params);

        $firstId = (int) $this->pdo->lastInsertId();
        return range($firstId, $firstId + count($rows) - 1);
    }

    // ── Activities ────────────────────────────────────────────────────────────

    /**
     * Build and INSERT activities for one customer.
     *
     * @return array<int, array{id: int, created_at: string}>
     */
    private function insertActivitiesForCustomer(int $customerId): array
    {
        $count = mt_rand(self::ACT_MIN, self::ACT_MAX);

        // Start from customer's registration date, advance forward
        $createdAt = $this->pdo
            ->query("SELECT created_at FROM customers WHERE id = {$customerId}")
            ->fetchColumn();

        $current = new \DateTimeImmutable($createdAt);
        $cutoff  = new \DateTimeImmutable('2024-12-31');

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $current = $current->modify('+' . mt_rand(1, 45) . ' days');
            if ($current > $cutoff) break;

            $type = self::ACTIVITY_TYPES[array_rand(self::ACTIVITY_TYPES)];

            $rows[] = [
                'customer_id'   => $customerId,
                'activity_type' => $type,                       // ENUM string directly
                'details'       => $this->buildDetails($type),
                'created_at'    => $current->format('Y-m-d H:i:s'),
            ];
        }

        if (!$rows) return [];

        $ph   = implode(',', array_fill(0, count($rows), '(?,?,?,?)'));
        $stmt = $this->pdo->prepare(
            "INSERT INTO activities (customer_id, activity_type, details, created_at)
             VALUES {$ph}"
        );

        $params = [];
        foreach ($rows as $r) {
            array_push($params, $r['customer_id'], $r['activity_type'],
                                $r['details'], $r['created_at']);
        }
        $stmt->execute($params);

        $firstId    = (int) $this->pdo->lastInsertId();
        $activities = [];
        foreach ($rows as $idx => $r) {
            $activities[] = [
                'id'         => $firstId + $idx,
                'created_at' => $r['created_at'],
            ];
        }

        $this->totalActivities += count($activities);
        return $activities;
    }

    // ── Comments ──────────────────────────────────────────────────────────────

    private function insertCommentsForActivities(array $activities): void
    {
        if (!$activities) return;

        $cmtRows = [];

        foreach ($activities as $idx => $act) {
            // Every 3rd activity (1-indexed: 3rd, 6th, 9th, …)
            if (($idx + 1) % self::COMMENT_EVERY !== 0) continue;

            $count   = mt_rand(self::COMMENT_MIN, self::COMMENT_MAX);
            $current = new \DateTimeImmutable($act['created_at']);

            for ($k = 0; $k < $count; $k++) {
                $current  = $current->modify('+' . mt_rand(5, 600) . ' minutes');
                $cmtRows[] = [
                    'activity_id' => $act['id'],
                    'author_name' => self::COMMENT_AUTHORS[array_rand(self::COMMENT_AUTHORS)],
                    'body'        => self::COMMENT_BODIES[array_rand(self::COMMENT_BODIES)],
                    'created_at'  => $current->format('Y-m-d H:i:s'),
                ];
            }
        }

        if (!$cmtRows) return;

        foreach (array_chunk($cmtRows, self::BATCH_SIZE) as $batch) {
            $ph   = implode(',', array_fill(0, count($batch), '(?,?,?,?)'));
            $stmt = $this->pdo->prepare(
                "INSERT INTO comments (activity_id, author_name, body, created_at)
                 VALUES {$ph}"
            );
            $params = [];
            foreach ($batch as $r) {
                array_push($params, $r['activity_id'], $r['author_name'],
                                    $r['body'], $r['created_at']);
            }
            $stmt->execute($params);
        }

        $this->totalComments += count($cmtRows);
    }

    // ── Generators ────────────────────────────────────────────────────────────

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

    private function randomPhone(): string
    {
        return sprintf('+1-%03d-%04d', mt_rand(200, 999), mt_rand(1000, 9999));
    }

    private function randomDatetime(string $from, string $to): string
    {
        return date('Y-m-d H:i:s', mt_rand(strtotime($from), strtotime($to)));
    }

    private function buildDetails(string $type): string
    {
        $pool = self::ACTIVITY_DETAILS[$type] ?? ['Activity recorded.'];
        $tpl  = $pool[array_rand($pool)];

        // Substitution pool — order matters: string, int, string, int
        $subs = [
            self::IPS[array_rand(self::IPS)],
            mt_rand(10000, 99999),
            self::TIMEZONES[array_rand(self::TIMEZONES)],
            mt_rand(7, 10),
        ];

        preg_match_all('/%[sd]/', $tpl, $m);
        $args = array_slice($subs, 0, count($m[0]));

        return $args ? vsprintf($tpl, $args) : $tpl;
    }
}
