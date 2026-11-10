<?php

namespace App\Console\Commands;

use App\Models\Evidence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Supprime les fichiers de preuve arrivés à échéance. La ligne reste, avec son
 * empreinte et son type : la trace du cas survit, pas la donnée personnelle.
 */
class PurgerPreuves extends Command
{
    protected $signature = 'verif:purger-preuves';

    protected $description = 'Supprime les preuves dont la durée de conservation est dépassée';

    public function handle(): int
    {
        $n = 0;

        Evidence::whereNotNull('path')->whereDate('retention_until', '<', now()->toDateString())->each(function (Evidence $e) use (&$n) {
            Storage::delete($e->path);
            $e->update(['path' => null]);
            $n++;
        });

        $this->info("{$n} preuve(s) purgée(s).");

        return self::SUCCESS;
    }
}
