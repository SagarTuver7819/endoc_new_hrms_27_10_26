<?php
/**
 * Company History + Vision / Mission / Core Values content
 */

if (!function_exists('ensureCompanyContentTables')) {

    function ensureCompanyContentTables($conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }

        $conn->query(
            "CREATE TABLE IF NOT EXISTS company_content (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_key VARCHAR(40) NOT NULL,
                title VARCHAR(200) NOT NULL DEFAULT '',
                body_json LONGTEXT,
                status TINYINT(1) NOT NULL DEFAULT 1,
                updated_by INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_company_content_key (content_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        seedCompanyContentDefaults($conn);

        if ($close) {
            $conn->close();
        }
    }

    function companyContentDefaultHistory()
    {
        return [
            'company' => 'Endoc Biotech Pvt Ltd',
            'heading' => 'Company History',
            'paragraphs' => [
                'Endoc Biotech Pvt Ltd is a biotechnology company focused on quality, compliance and people-first operations. The HRMS supports plant and office teams with attendance, payroll, recruitment and employee lifecycle processes.',
                'The organisation continues to grow by investing in skilled people, safe workplaces and reliable systems that keep day-to-day HR and operations organised.',
                'This Company History section can be updated anytime from Masters → Company History with your official narrative, milestones and product lines.',
            ],
            'products_2021' => [
                'Biotechnology products & solutions',
                'Quality & compliance support',
                'Plant & operations services',
            ],
            'products_today' => [
                'Biotechnology products & solutions',
                'Quality & compliance support',
                'Plant & operations services',
            ],
        ];
    }

    function companyContentDefaultVision()
    {
        return [
            'heading' => 'Vision, Mission & Core Values',
            'leadership' => 'Endoc Biotech Pvt Ltd is driven by a leadership team committed to quality, compliance and sustainable growth.',
            'vision' => 'To be a trusted biotechnology organisation known for quality products, ethical practices and an engaged workforce.',
            'mission' => [
                'Deliver consistent quality that meets customer and regulatory expectations.',
                'Build a safe, compliant and people-centric workplace.',
                'Continuously improve processes through technology and training.',
                'Grow responsibly with integrity and customer focus.',
            ],
            'core_values' => [
                'Quality Without Compromise',
                'Safety First',
                'Integrity',
                'Customer Focus',
                'Continuous Improvement',
                'Teamwork',
            ],
        ];
    }

    function seedCompanyContentDefaults($conn)
    {
        $defaults = [
            'history' => [
                'title' => 'Company History',
                'body' => companyContentDefaultHistory(),
            ],
            'vision' => [
                'title' => 'Vision, Mission & Core Values',
                'body' => companyContentDefaultVision(),
            ],
        ];

        foreach ($defaults as $key => $meta) {
            $st = $conn->prepare('SELECT id FROM company_content WHERE content_key = ? LIMIT 1');
            $st->bind_param('s', $key);
            $st->execute();
            $exists = $st->get_result()->fetch_assoc();
            $st->close();
            if ($exists) {
                continue;
            }
            $title = $meta['title'];
            $json = json_encode($meta['body'], JSON_UNESCAPED_UNICODE);
            $ins = $conn->prepare(
                'INSERT INTO company_content (content_key, title, body_json, status) VALUES (?, ?, ?, 1)'
            );
            $ins->bind_param('sss', $key, $title, $json);
            $ins->execute();
            $ins->close();
        }
    }

    function getCompanyContent($key, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureCompanyContentTables($conn);
        $key = trim((string) $key);
        $st = $conn->prepare(
            'SELECT * FROM company_content WHERE content_key = ? AND status = 1 LIMIT 1'
        );
        $st->bind_param('s', $key);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($close) {
            $conn->close();
        }
        if (!$row) {
            return null;
        }
        $body = json_decode((string) ($row['body_json'] ?? ''), true);
        if (!is_array($body)) {
            $body = ($key === 'history') ? companyContentDefaultHistory() : companyContentDefaultVision();
        }
        $row['body'] = $body;
        return $row;
    }

    function saveCompanyContent($key, $title, array $body, $userId = 0, $conn = null)
    {
        $close = false;
        if ($conn === null) {
            $conn = getDBConnection();
            $close = true;
        }
        ensureCompanyContentTables($conn);
        $key = trim((string) $key);
        $title = trim((string) $title);
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $userId = (int) $userId;

        $st = $conn->prepare(
            'INSERT INTO company_content (content_key, title, body_json, status, updated_by)
             VALUES (?, ?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), body_json = VALUES(body_json),
               status = 1, updated_by = VALUES(updated_by)'
        );
        $st->bind_param('sssi', $key, $title, $json, $userId);
        $ok = $st->execute();
        $err = $st->error;
        $st->close();
        if ($close) {
            $conn->close();
        }
        return $ok ? ['ok' => true] : ['ok' => false, 'error' => $err];
    }

    function companyContentLinesToArray($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            $line = ltrim($line, "•\t-–—* ");
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    /**
     * Escape text and bold key company facts (no color highlight).
     */
    function companyContentBoldHtml($text)
    {
        $html = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        $phrases = [
            'Endoc Biotech Pvt Ltd',
            'Endoc Biotech',
            'Biotechnology',
            'Quality Without Compromise',
            'Safety First',
            'Continuous Improvement',
            'Customer Focus',
        ];
        usort($phrases, static function ($a, $b) {
            return strlen($b) - strlen($a);
        });
        $tokens = [];
        $i = 0;
        foreach ($phrases as $phrase) {
            $escaped = preg_quote(htmlspecialchars($phrase, ENT_QUOTES, 'UTF-8'), '/');
            $html = preg_replace_callback(
                '/' . $escaped . '/i',
                static function ($m) use (&$tokens, &$i) {
                    $key = "\x00B" . $i++ . "\x00";
                    $tokens[$key] = '<strong class="co-strong">' . $m[0] . '</strong>';
                    return $key;
                },
                $html
            );
        }
        if ($tokens) {
            $html = strtr($html, $tokens);
        }
        return nl2br($html);
    }
}
