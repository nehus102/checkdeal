<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('verif:relances')->dailyAt('10:00');
Schedule::command('verif:purger-preuves')->dailyAt('03:00');
