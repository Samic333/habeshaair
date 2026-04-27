<?php
/**
 * schema-org.php — emits a JSON-LD <script> based on $page['schema'].
 * Called from header.php.
 */

function render_schema_org(): void {
    global $page;
    $key  = $page['schema'] ?? 'organization';
    $base = (string)cfg('app.base_url', 'https://habeshair.com');
    $org  = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => (string)cfg('app.company', 'HabeshAir'),
        'url'      => $base,
        'logo'     => $base . '/assets/images/logo.svg',
        'email'    => (string)cfg('app.email', 'info@habeshair.com'),
        'telephone'=> (string)cfg('app.whatsapp_display', '+1 (480) 915-9971'),
        'description' => 'Premium air charter brokerage coordinating VIP, cargo, humanitarian, and emergency flights across Africa, the Middle East, and beyond.',
        'areaServed'  => ['Africa', 'Middle East', 'Europe'],
    ];

    $blocks = [$org];

    if ($key === 'home' || $key === 'organization') {
        // Just the org block
    } elseif ($key === 'service') {
        $blocks[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'Service',
            'serviceType' => 'Air Charter Brokerage',
            'provider' => ['@type' => 'Organization', 'name' => $org['name']],
            'areaServed' => 'Africa, Middle East, and global routes',
            'description' => 'VIP, cargo, humanitarian, emergency medevac, and group charter flight coordination through certified Part 135 operators.',
        ];
    } elseif ($key === 'contactpage') {
        $blocks[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'ContactPage',
            'name'     => 'Contact HabeshAir',
            'url'      => $base . '/contact.php',
        ];
        $blocks[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'ContactPoint',
            'contactType' => 'customer service',
            'email'    => $org['email'],
            'telephone'=> $org['telephone'],
            'availableLanguage' => ['English', 'Amharic'],
            'areaServed' => 'Worldwide',
        ];
    } elseif ($key === 'faqpage' && !empty($page['faq']) && is_array($page['faq'])) {
        $items = [];
        foreach ($page['faq'] as $qa) {
            $items[] = [
                '@type' => 'Question',
                'name'  => $qa['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa['a']],
            ];
        }
        $blocks[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    foreach ($blocks as $b) {
        echo "<script type=\"application/ld+json\">" . json_encode($b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "</script>\n";
    }
}
