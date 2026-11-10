<?php

return [
    'title' => 'E-Mail-Testmodus',
    'badge_active' => 'aktiv',
    'status' => 'Status',
    'state_enabled' => 'Testmodus aktiv',
    'state_disabled' => 'Testmodus aus',
    'recipient' => 'Testempfänger',
    'redirect_info' => 'Alle ausgehenden E-Mails werden umgeleitet an:',
    'no_recipient' => 'keine Adresse – E-Mails werden verworfen',
    'forced_by_env' => 'Der Testmodus ist per Umgebungsvariable (MAIL_TEST_MODE) aktiviert und kann hier nicht ausgeschaltet werden.',
    'activated_at' => 'Aktiviert am :date',
    'disabled_info' => 'E-Mails werden normal an ihre Empfänger verschickt.',
    'banner' => 'E-Mail-Testmodus aktiv – alle E-Mails gehen an :recipient',
    'banner_link' => 'Einstellungen',
    'actions' => [
        'enable' => 'Testmodus aktivieren',
        'enable_description' => 'Solange der Testmodus aktiv ist, geht keine E-Mail an echte Empfänger, sondern alle an die folgende Adresse.',
        'change_recipient' => 'Adresse ändern',
        'change_recipient_description' => 'Alle E-Mails gehen ab sofort an die neue Adresse.',
        'disable' => 'Testmodus beenden',
        'disable_description' => 'E-Mails werden danach wieder an die echten Empfänger verschickt.',
        'send_test_mail' => 'Testmail senden',
    ],
    'notifications' => [
        'enabled' => 'Testmodus aktiv – alle E-Mails gehen an :recipient',
        'recipient_changed' => 'Alle E-Mails gehen jetzt an :recipient',
        'disabled' => 'Testmodus beendet',
        'test_mail_sent' => 'Testmail an :recipient verschickt',
    ],
    'test_mail' => [
        'subject' => 'Testmail',
        'body' => 'Diese E-Mail bestätigt, dass der E-Mail-Testmodus funktioniert.',
    ],
];
