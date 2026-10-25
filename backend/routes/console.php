<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contributions:flag-late')->daily();
Schedule::command('cycles:open-due')->daily();
Schedule::command('loans:flag-late')->daily();
Schedule::command('notifications:reminders')->daily();
