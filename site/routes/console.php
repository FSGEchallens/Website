<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Tâches automatiques (lancées par la tâche planifiée Plesk « Laravel - planificateur » : schedule:run chaque minute).
 */

// Chaque nuit : supprime les entrées du journal (et leurs fichiers archivés) plus anciennes que JOURNAL_CONSERVATION_JOURS.
Schedule::command('model:prune')->dailyAt('00:00');
