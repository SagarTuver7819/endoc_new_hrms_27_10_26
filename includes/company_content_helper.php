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
            'company' => 'Fishfa Rubbers Limited',
            'heading' => 'Company History',
            'paragraphs' => [
                'Fishfa Rubbers Limited is a global leader in the Butyl and Depolymer Reclaim Rubber industry, headquartered at Lodhika GIDC, Metoda, Rajkot, Gujarat. The company creates wealth from waste by recycling hazardous rubber waste and transforming it into valuable reclaim rubber, while eliminating environmental pollution and earning carbon credits.',
                'Fishfa\'s journey began in 1971 as a modest tyre retreading firm. As the number of clients grew, the group forayed into the highly competitive reclaim rubber business, was incorporated as Fishfa Rubbers in May 2000, and became a Limited company in 2022.',
                'The company has adopted ultra-modern manufacturing technologies and follows best industry practices to suit global standards. Its environment-friendly infrastructure keeps even the rubber-waste processing premises clean, green and pollution free.',
                'Today Fishfa exports around 90% of its production to more than 22 countries across Europe, the USA, the Far East and South Africa. The company is REACH certified under European Community Regulation and is a winner of the Export Excellence Award.',
            ],
            'products_2021' => [
                'Tyre & Tube Manufacturing',
                'Automotive Rubber Parts',
                'Conveyor Belts & Hoses',
                'Footwear',
                'Mats, Sheets & Moulded Goods',
            ],
            'products_today' => [
                'Butyl Reclaim Rubber',
                'EPDM Reclaim Rubber',
                'Natural Tube Reclaim',
                'Whole Tire Reclaim Rubber',
                'Recovered Carbon Black (rCB)',
            ],
        ];
    }

    function companyContentDefaultVision()
    {
        return [
            'heading' => 'Vision, Mission & Core Values',
            'leadership' => 'The Fishfa Group is led by Founder & Chairman Mr. Kanjibhai Kalavadia, with CEO Mr. S.K. Joshi driving international marketing and global growth.',
            'vision' => 'Having achieved the No.1 position in the global Butyl Reclaim Rubber industry, our vision is to be a leader in the global rubber industry by launching the best quality products for domestic and international customers, and to play a major role in freeing the world from hazardous rubber waste.',
            'mission' => [
                'Recycle hazardous rubber waste into high-quality reclaim rubber and rCB.',
                'Deliver consistent quality that meets global standards and customer specifications.',
                'Protect the environment through clean, sustainable and responsible manufacturing.',
                'Continuously invest in technology, infrastructure and people.',
            ],
            'core_values' => [
                'Sustainability',
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
            'Fishfa Rubbers Limited',
            'Fishfa Rubbers',
            'Founder & Chairman Mr. Kanjibhai Kalavadia',
            'Mr. Kanjibhai Kalavadia',
            'Mr. S.K. Joshi',
            'Butyl Reclaim Rubber',
            'Depolymer',
            'REACH certified',
            'Export Excellence Award',
            '22 countries',
            '90% of its production',
            'No.1 position',
            'carbon credits',
            '1971',
            'May 2000',
            '2022',
            'Rajkot, Gujarat',
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
