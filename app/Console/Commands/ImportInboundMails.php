<?php

namespace App\Console\Commands;

use App\Services\Messages\InboundMailImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:import-inbound-mails')]
#[Description('Holt neue E-Mails aus dem Cockpit-Postfach und legt sie als interne Nachrichten an')]
class ImportInboundMails extends Command
{
    public function handle(InboundMailImporter $importer): int
    {
        if (! $importer->isEnabled()) {
            $this->warn('Kein Cockpit-Postfach eingerichtet (IMAP_HOST fehlt).');

            return self::SUCCESS;
        }

        $imported = $importer->import();

        $this->info("{$imported} E-Mails als Nachricht übernommen.");

        return self::SUCCESS;
    }
}
