<?php

return [
    'title' => 'Mail test mode',
    'badge_active' => 'active',
    'status' => 'Status',
    'state_enabled' => 'Test mode active',
    'state_disabled' => 'Test mode off',
    'recipient' => 'Test recipient',
    'redirect_info' => 'All outgoing mails are redirected to:',
    'no_recipient' => 'no address – mails are discarded',
    'forced_by_env' => 'The test mode is enabled via environment variable (MAIL_TEST_MODE) and cannot be switched off here.',
    'activated_at' => 'Activated at :date',
    'disabled_info' => 'Mails are delivered to their real recipients.',
    'banner' => 'Mail test mode active – all mails go to :recipient',
    'banner_link' => 'Settings',
    'actions' => [
        'enable' => 'Enable test mode',
        'enable_description' => 'While the test mode is active, no mail reaches its real recipient. All mails go to the following address instead.',
        'change_recipient' => 'Change address',
        'change_recipient_description' => 'All mails will be sent to the new address from now on.',
        'disable' => 'Disable test mode',
        'disable_description' => 'Mails will be delivered to their real recipients again.',
        'send_test_mail' => 'Send test mail',
    ],
    'notifications' => [
        'enabled' => 'Test mode active – all mails go to :recipient',
        'recipient_changed' => 'All mails now go to :recipient',
        'disabled' => 'Test mode disabled',
        'test_mail_sent' => 'Test mail sent to :recipient',
    ],
    'test_mail' => [
        'subject' => 'Test mail',
        'body' => 'This mail confirms that the mail test mode is working.',
    ],
];
